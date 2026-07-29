<?php
defined('ABSPATH')||exit;

final class GDO_Migration {
    public static function maybe_run() {
        if ( absint(get_option('gdo_schema_version',0)) >= GDO_SCHEMA_VERSION ) {
            return;
        }
        GDO_Schema::install();
        self::remove_legacy_capability();
        self::quarantine_legacy();
        update_option('gdo_schema_version',GDO_SCHEMA_VERSION,false);
    }

    public static function remove_legacy_capability() {
        foreach ( wp_roles()->roles as $role_name=>$details ) {
            $role=get_role($role_name);
            if($role&&$role->has_cap('manage_global_doctor_verification'))$role->remove_cap('manage_global_doctor_verification');
        }
    }

    private static function legacy_profile( $user_id ) {
        $profile = array();
        foreach ( GDO_Application::fields() as $field ) {
            if ( 'display_name' === $field ) {
                $user = get_userdata( $user_id );
                $value = $user ? $user->display_name : '';
            } else {
                $legacy_field = 'license_number' === $field ? 'licence_number' : $field;
                $value = get_user_meta( $user_id, '_spd_' . $legacy_field, true );
                if ( '' === (string)$value && 'license_number' === $field ) {
                    $value = get_user_meta( $user_id, '_spd_license_number', true );
                }
            }
            $profile[$field] = is_scalar($value) ? sanitize_text_field((string)$value) : '';
        }
        return $profile;
    }

    private static function decrypt_legacy( $envelope ) {
        if ( 0 !== strpos($envelope,'GDO1') || strlen($envelope)<33 ) {
            return new WP_Error('gdo_legacy_envelope',__('Legacy credential envelope is invalid.','global-doctor-onboarding'));
        }
        $key=hash('sha256',wp_salt('auth').'|gdo-credentials',true);
        $plain=openssl_decrypt(substr($envelope,32),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($envelope,4,12),substr($envelope,16,16));
        return false===$plain?new WP_Error('gdo_legacy_decrypt',__('Legacy credential decryption failed. Keep the original WordPress salts and restore from backup.','global-doctor-onboarding')):$plain;
    }

    private static function quarantine_legacy() {
        global $wpdb;
        $legacy=$wpdb->prefix.'gdo_documents';
        $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$legacy));
        if($exists!==$legacy)return;
        $users=$wpdb->get_col("SELECT DISTINCT user_id FROM {$legacy}");
        $uploads=wp_upload_dir();
        foreach($users as $user_id){
            $app=GDO_Application::latest_for_user($user_id);
            if(!$app){
                $profile=self::legacy_profile($user_id);$now=current_time('mysql',true);
                $inserted=$wpdb->insert(GDO_Schema::table('applications'),array('application_uuid'=>wp_generate_uuid4(),'user_id'=>absint($user_id),'version'=>1,'state'=>'legacy_review_required','row_version'=>1,'profile_json'=>wp_json_encode($profile,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'profile_fingerprint'=>GDO_Application::fingerprint($profile),'retention_until'=>gmdate('Y-m-d H:i:s',time()+365*DAY_IN_SECONDS),'created_at'=>$now,'updated_at'=>$now),array('%s','%d','%d','%s','%d','%s','%s','%s','%s','%s'));
                if(1!==$inserted)continue;
                $app=GDO_Application::get($wpdb->insert_id);
                if(!$app)continue;
                GDO_Audit::transition($app->id,0,'legacy','legacy_review_required','legacy_quarantine','Legacy File 09 data requires independent re-review and credential migration.');
            }
            $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$legacy} WHERE user_id=%d ORDER BY id ASC",$user_id));
            foreach($rows as $row){
                $type=sanitize_key($row->document_type);
                if(!isset(GDO_Evidence::types()[$type]))continue;
                $source=trailingslashit($uploads['basedir']).'gdo-secure/'.basename($row->storage_name);
                if(!is_file($source)||is_link($source))continue;
                $envelope=file_get_contents($source);if(false===$envelope)continue;
                $plain=self::decrypt_legacy($envelope);
                if(is_wp_error($plain)){GDO_Membership_Adapter::audit('doctor_legacy_credential_decrypt_failed',array('application_id'=>$app->id,'user_id'=>absint($user_id),'legacy_document_id'=>absint($row->id),'error'=>$plain->get_error_code()));continue;}
                $version=absint($wpdb->get_var($wpdb->prepare('SELECT MAX(version) FROM '.GDO_Schema::table('evidence').' WHERE application_id=%d AND document_type=%s',$app->id,$type)))+1;
                $meta=array('application_uuid'=>$app->application_uuid,'application_version'=>$app->version,'user_id'=>absint($user_id),'document_type'=>$type,'document_version'=>$version);
                $encrypted=GDO_Crypto::encrypt($plain,$meta);if(is_wp_error($encrypted))continue;
                $storage=wp_generate_uuid4().'.gdo2';$stored=GDO_Storage::atomic_write($storage,$encrypted['bytes']);if(is_wp_error($stored))continue;
                $now=current_time('mysql',true);$wpdb->query('START TRANSACTION');
                $inserted=$wpdb->insert(GDO_Schema::table('evidence'),array('application_id'=>$app->id,'user_id'=>absint($user_id),'document_type'=>$type,'version'=>$version,'status'=>'legacy_quarantine','original_name'=>sanitize_file_name($row->original_name),'mime_type'=>sanitize_text_field($row->mime_type),'file_size'=>strlen($plain),'storage_name'=>$storage,'ciphertext_sha256'=>$stored['sha256'],'content_hmac'=>$encrypted['content_hmac'],'key_id'=>$encrypted['key_id'],'envelope_version'=>'GDO2','retention_state'=>'active','created_at'=>$now,'updated_at'=>$now),array('%d','%d','%s','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s'));
                if(1!==$inserted||!hash_equals($stored['sha256'],hash_file('sha256',GDO_Storage::path($storage)))){$wpdb->query('ROLLBACK');GDO_Storage::delete_verified($storage,$stored['sha256']);continue;}
                if(!@unlink($source)||is_file($source)){$wpdb->query('ROLLBACK');GDO_Storage::delete_verified($storage,$stored['sha256']);continue;}
                $wpdb->query('COMMIT');
            }
        }
        do_action('gdo_legacy_role_cleanup_required',$users);
        GDO_Membership_Adapter::audit('doctor_verification_legacy_quarantined',array('users'=>count($users)));
    }
}
