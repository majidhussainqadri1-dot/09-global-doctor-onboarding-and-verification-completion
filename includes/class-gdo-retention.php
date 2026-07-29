<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Retention {
    public function hooks() {
        add_action( 'gdo_daily_retention', array( $this, 'run' ) );
        add_action( 'gdo_notification_outbox', array( $this, 'process_outbox' ) );
    }

    public function process_outbox() {
        GDO_Notifications::process( 50 );
    }

    public function run() {
        global $wpdb;
        GDO_Rate_Limiter::cleanup();
        GDO_Notifications::process( 50 );
        $now = current_time( 'mysql', true );
        $apps_table = GDO_Schema::table( 'applications' );
        $evidence_table = GDO_Schema::table( 'evidence' );

        $expiring = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$apps_table} WHERE state IN ('verified','reinstated') AND verified_until IS NOT NULL AND verified_until<%s LIMIT 100",
            $now
        ) );
        foreach ( $expiring as $app ) {
            $result = GDO_State::transition( $app->id, 'expired', 0, 'verification_expired', 'Verification validity period ended.', $app->row_version );
            if ( ! is_wp_error($result) ) {
                GDO_Notifications::queue( 'doctor_verification_expired', $app->user_id, array('application_id'=>$app->id) );
                do_action( 'gdo_verification_decision_changed', $app->user_id, 'expired', $app->id, array() );
            }
        }

        $ring = GDO_Crypto::keyring();
        if ( ! is_wp_error( $ring ) ) {
            $rotate = $wpdb->get_col( $wpdb->prepare(
                "SELECT id FROM {$evidence_table} WHERE envelope_version='GDO2' AND key_id<>%s AND deleted_at IS NULL LIMIT 25",
                $ring['active']
            ) );
            foreach ( $rotate as $evidence_id ) {
                GDO_Evidence::rotate_key( $evidence_id );
            }
        }

        $superseded_before = gmdate( 'Y-m-d H:i:s', time() - absint(apply_filters('gdo_superseded_evidence_grace_days',30))*DAY_IN_SECONDS );
        $superseded = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$evidence_table} WHERE retention_state='superseded' AND deleted_at IS NULL AND updated_at<%s LIMIT 100",
            $superseded_before
        ) );
        foreach ( $superseded as $record ) {
            self::delete_record( $record, 'superseded_deleted', $now );
        }

        $apps = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$apps_table} WHERE legal_hold=0 AND retention_until IS NOT NULL AND retention_until<%s LIMIT 100",
            $now
        ) );
        foreach ( $apps as $app ) {
            if ( in_array( $app->state, array('verified','reinstated','under_review','recommended','appeal_pending','submitted','resubmitted'), true ) ) {
                continue;
            }
            foreach ( GDO_Evidence::records( $app->id, true ) as $record ) {
                self::delete_record( $record, 'retention_deleted', $now );
            }
        }

        $wpdb->query( $wpdb->prepare(
            'UPDATE ' . GDO_Schema::table('access_log') . " SET reviewer_id=0,purpose_code='anonymized' WHERE created_at<%s",
            gmdate( 'Y-m-d H:i:s', time() - absint(apply_filters('gdo_access_log_identifiable_days',365))*DAY_IN_SECONDS )
        ) );
        self::cleanup_orphans();
        do_action( 'gdo_retention_completed', $now );
    }

    private static function delete_record( $record, $state, $now ) {
        global $wpdb;
        $proof = GDO_Storage::delete_verified( $record->storage_name, $record->ciphertext_sha256 );
        if ( is_wp_error( $proof ) ) {
            GDO_Membership_Adapter::audit( 'doctor_credential_retention_delete_failed', array('application_id'=>absint($record->application_id),'evidence_id'=>absint($record->id),'error'=>$proof->get_error_code()) );
            return false;
        }
        $updated = $wpdb->update(
            GDO_Schema::table('evidence'),
            array('retention_state'=>$state,'deletion_proof'=>$proof,'deleted_at'=>$now,'storage_name'=>'deleted-'.$record->id,'original_name'=>'erased','updated_at'=>$now),
            array('id'=>$record->id),
            array('%s','%s','%s','%s','%s','%s'),
            array('%d')
        );
        return false !== $updated;
    }

    private static function cleanup_orphans() {
        global $wpdb;
        $health = GDO_Storage::health();
        if ( is_wp_error($health) ) {
            return;
        }
        $dir = GDO_Storage::directory();
        $known = $wpdb->get_col( "SELECT storage_name FROM " . GDO_Schema::table('evidence') . " WHERE deleted_at IS NULL" );
        $known = array_fill_keys( array_map('strval',$known), true );
        $cutoff = time() - absint(apply_filters('gdo_orphan_grace_hours',24))*HOUR_IN_SECONDS;
        foreach ( new DirectoryIterator($dir) as $file ) {
            if ( $file->isDot() || ! $file->isFile() || $file->isLink() ) {
                continue;
            }
            $name = $file->getFilename();
            if ( isset($known[$name]) || $file->getMTime() >= $cutoff ) {
                continue;
            }
            if ( 0 === strpos($name,'.tmp-') || preg_match('/\.(?:gdo1|gdo2)$/i',$name) ) {
                @unlink( $file->getPathname() );
                GDO_Membership_Adapter::audit( 'doctor_credential_orphan_removed', array('storage_digest'=>hash('sha256',$name)) );
            }
        }
    }
}
