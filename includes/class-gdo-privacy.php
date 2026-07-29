<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Privacy {
    public function hooks(){add_filter('wp_privacy_personal_data_exporters',array($this,'exporters'));add_filter('wp_privacy_personal_data_erasers',array($this,'erasers'));add_action('admin_init',array($this,'policy'));}
    public function exporters($e){$e['global-doctor-onboarding']=array('exporter_friendly_name'=>'Global Doctor Verification','callback'=>array($this,'export'));return $e;}

    public function export($email,$page=1){
        $user=get_user_by('email',$email);if(!$user)return array('data'=>array(),'done'=>true);
        global $wpdb;$per=20;$offset=(max(1,absint($page))-1)*$per;
        $apps=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.GDO_Schema::table('applications').' WHERE user_id=%d ORDER BY version ASC LIMIT %d OFFSET %d',$user->ID,$per,$offset));$data=array();
        foreach($apps as $app){
            $rows=array(
                array('name'=>'Application UUID','value'=>$app->application_uuid),array('name'=>'Version','value'=>$app->version),array('name'=>'State','value'=>$app->state),array('name'=>'Submitted at','value'=>$app->submitted_at),array('name'=>'Decision at','value'=>$app->decision_at),array('name'=>'Verified until','value'=>$app->verified_until),array('name'=>'Professional application','value'=>$app->profile_json),array('name'=>'Recommendation','value'=>wp_json_encode(array('decision'=>$app->recommended_decision,'reason'=>$app->recommendation_reason,'at'=>$app->recommendation_at)))
            );
            foreach(GDO_Evidence::records($app->id) as $record)$rows[]=array('name'=>'Credential evidence','value'=>wp_json_encode(array('type'=>$record->document_type,'version'=>$record->version,'status'=>$record->status,'review_note'=>$record->review_note,'registry_result'=>$record->registry_result,'validity_until'=>$record->validity_until,'created_at'=>$record->created_at)));
            $consents=$wpdb->get_results($wpdb->prepare('SELECT consent_version,purpose,retention_notice,accepted_at,withdrawn_at FROM '.GDO_Schema::table('consents').' WHERE application_id=%d',$app->id),ARRAY_A);foreach($consents as $consent)$rows[]=array('name'=>'Consent','value'=>wp_json_encode($consent));
            $access=$wpdb->get_results($wpdb->prepare('SELECT evidence_id,purpose_code,result,created_at FROM '.GDO_Schema::table('access_log').' WHERE application_id=%d',$app->id),ARRAY_A);foreach($access as $event)$rows[]=array('name'=>'Credential access event','value'=>wp_json_encode($event));
            $appeals=$wpdb->get_results($wpdb->prepare('SELECT source_state,status,reason,resolution,decision,created_at,resolved_at FROM '.GDO_Schema::table('appeals').' WHERE application_id=%d',$app->id),ARRAY_A);foreach($appeals as $appeal)$rows[]=array('name'=>'Appeal','value'=>wp_json_encode($appeal));
            $data[]=array('group_id'=>'global-doctor-verification','group_label'=>'Global Doctor Verification','item_id'=>'application-'.$app->id,'data'=>$rows);
        }
        return array('data'=>$data,'done'=>count($apps)<$per);
    }

    public function erasers($e){$e['global-doctor-onboarding']=array('eraser_friendly_name'=>'Global Doctor Verification','callback'=>array($this,'erase'));return $e;}

    public function erase($email,$page=1){
        $user=get_user_by('email',$email);if(!$user)return array('items_removed'=>false,'items_retained'=>false,'messages'=>array(),'done'=>true);
        global $wpdb;$apps=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.GDO_Schema::table('applications').' WHERE user_id=%d ORDER BY id ASC LIMIT 10 OFFSET %d',$user->ID,(max(1,absint($page))-1)*10));
        $removed=false;$retained=false;$messages=array();
        foreach($apps as $app){
            if($app->legal_hold){$retained=true;$messages[]='Application '.$app->application_uuid.' is retained under legal hold.';continue;}
            $deletion_failed=false;
            foreach(GDO_Evidence::records($app->id,true) as $record){
                $proof=GDO_Storage::delete_verified($record->storage_name,$record->ciphertext_sha256);
                if(is_wp_error($proof)){$deletion_failed=true;$retained=true;$messages[]=$proof->get_error_message();continue;}
                $updated=$wpdb->update(GDO_Schema::table('evidence'),array('retention_state'=>'deleted','deletion_proof'=>$proof,'deleted_at'=>current_time('mysql',true),'original_name'=>'erased','storage_name'=>'deleted-'.$record->id,'updated_at'=>current_time('mysql',true)),array('id'=>$record->id),array('%s','%s','%s','%s','%s','%s'),array('%d'));
                if(false===$updated){$deletion_failed=true;$retained=true;$messages[]='A physical credential deletion succeeded but its proof record requires administrator repair.';}else{$removed=true;}
            }
            if($deletion_failed){continue;}
            $current=GDO_Application::get($app->id);
            if($current&&!in_array($current->state,array('withdrawn','revoked'),true)){
                $target=GDO_State::can_transition($current->state,'withdrawn')?'withdrawn':(GDO_State::can_transition($current->state,'revoked')?'revoked':'');
                if($target){$transition=GDO_State::transition($current->id,$target,0,'privacy_erasure','Public verification was disabled before personal-data erasure.',$current->row_version);if(is_wp_error($transition)){$retained=true;$messages[]=$transition->get_error_message();continue;}}
            }
            $anonymous=hash('sha256','erased|'.$app->application_uuid.'|'.wp_salt('nonce'));
            $updated=$wpdb->update(GDO_Schema::table('applications'),array('user_id'=>0,'profile_json'=>'{}','profile_fingerprint'=>$anonymous,'approved_snapshot_json'=>null,'approved_fingerprint'=>null,'assigned_reviewer_id'=>null,'recommender_id'=>null,'finalizer_id'=>null,'recommendation_reason'=>'anonymized','updated_at'=>current_time('mysql',true)),array('id'=>$app->id),array('%d','%s','%s','%s','%s','%s','%s','%s','%s'),array('%d'));
            if(false===$updated){$retained=true;$messages[]='Application anonymization requires administrator repair.';continue;}
            $wpdb->update(GDO_Schema::table('consents'),array('user_id'=>0,'purpose'=>'retained-accountability-record','retention_notice'=>'anonymized','withdrawn_at'=>current_time('mysql',true)),array('application_id'=>$app->id),array('%d','%s','%s','%s'),array('%d'));
            $wpdb->update(GDO_Schema::table('access_log'),array('reviewer_id'=>0,'purpose_code'=>'anonymized'),array('application_id'=>$app->id),array('%d','%s'),array('%d'));
            $wpdb->update(GDO_Schema::table('appeals'),array('user_id'=>0,'reason'=>'anonymized','resolution'=>'anonymized'),array('application_id'=>$app->id),array('%d','%s','%s'),array('%d'));
            do_action('gdo_identity_projection_erased',$user->ID,$app->id);
            $removed=true;$retained=true;$messages[]='Personal credential data was erased; minimal anonymized decision and audit evidence was retained for accountability.';
        }
        return array('items_removed'=>$removed,'items_retained'=>$retained,'messages'=>array_values(array_unique($messages)),'done'=>count($apps)<10);
    }

    public function policy(){if(function_exists('wp_add_privacy_policy_content'))wp_add_privacy_policy_content('Global Doctor Verification','<p class="privacy-policy-tutorial">Doctor applicants provide professional information and private credential evidence. File 09 stores versioned consent, uses encrypted private storage, audits credential access, and applies configured retention, legal-hold, appeal, export, anonymization, and verified-erasure controls.</p>');}
}
