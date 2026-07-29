<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Schema {
    public static function table( $name ) {
        global $wpdb;
        $allowed = array( 'applications', 'evidence', 'transitions', 'consents', 'access_log', 'appeals', 'outbox', 'rate_limits' );
        if ( ! in_array( $name, $allowed, true ) ) {
            return '';
        }
        return $wpdb->prefix . 'gdo_' . $name;
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        $engine = 'ENGINE=InnoDB ' . $c;
        $apps = self::table( 'applications' );
        $evidence = self::table( 'evidence' );
        $transitions = self::table( 'transitions' );
        $consents = self::table( 'consents' );
        $access = self::table( 'access_log' );
        $appeals = self::table( 'appeals' );
        $outbox = self::table( 'outbox' );
        $rates = self::table( 'rate_limits' );

        dbDelta( "CREATE TABLE {$apps} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_uuid char(36) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            version int(10) unsigned NOT NULL DEFAULT 1,
            state varchar(40) NOT NULL DEFAULT 'draft',
            row_version bigint(20) unsigned NOT NULL DEFAULT 1,
            profile_json longtext NOT NULL,
            profile_fingerprint char(64) NOT NULL,
            approved_snapshot_json longtext NULL,
            approved_fingerprint char(64) NULL,
            assigned_reviewer_id bigint(20) unsigned NULL,
            recommender_id bigint(20) unsigned NULL,
            finalizer_id bigint(20) unsigned NULL,
            recommended_decision varchar(20) NULL,
            recommendation_reason text NULL,
            recommendation_at datetime NULL,
            submitted_at datetime NULL,
            reviewed_at datetime NULL,
            decision_at datetime NULL,
            verified_until datetime NULL,
            revoked_at datetime NULL,
            withdrawn_at datetime NULL,
            retention_until datetime NULL,
            legal_hold tinyint(1) unsigned NOT NULL DEFAULT 0,
            consent_version varchar(40) NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY application_uuid (application_uuid),
            UNIQUE KEY user_version (user_id,version),
            KEY user_state (user_id,state),
            KEY assigned_state (assigned_reviewer_id,state),
            KEY verified_until (verified_until),
            KEY retention_until (retention_until)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$evidence} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            document_type varchar(30) NOT NULL,
            version int(10) unsigned NOT NULL DEFAULT 1,
            status varchar(30) NOT NULL DEFAULT 'quarantine',
            original_name varchar(255) NOT NULL,
            mime_type varchar(100) NOT NULL,
            file_size bigint(20) unsigned NOT NULL,
            storage_name varchar(180) NOT NULL,
            ciphertext_sha256 char(64) NOT NULL,
            content_hmac char(64) NOT NULL,
            key_id varchar(80) NOT NULL,
            envelope_version varchar(10) NOT NULL,
            checklist_json longtext NULL,
            review_note text NULL,
            registry_result varchar(40) NULL,
            reviewer_id bigint(20) unsigned NULL,
            reviewed_at datetime NULL,
            validity_from date NULL,
            validity_until date NULL,
            retention_state varchar(30) NOT NULL DEFAULT 'active',
            deletion_proof char(64) NULL,
            deleted_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY app_type_version (application_id,document_type,version),
            UNIQUE KEY storage_name (storage_name),
            KEY user_status (user_id,status),
            KEY retention_state (retention_state),
            KEY application_active (application_id,retention_state,deleted_at)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$transitions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            actor_id bigint(20) unsigned NULL,
            from_state varchar(40) NOT NULL,
            to_state varchar(40) NOT NULL,
            reason_code varchar(80) NOT NULL,
            reason_text text NOT NULL,
            event_hash char(64) NOT NULL,
            previous_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_hash (event_hash),
            KEY application_id (application_id),
            KEY actor_id (actor_id),
            KEY created_at (created_at)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$consents} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            consent_version varchar(40) NOT NULL,
            wording_hash char(64) NOT NULL,
            purpose text NOT NULL,
            retention_notice text NOT NULL,
            accepted_at datetime NOT NULL,
            withdrawn_at datetime NULL,
            evidence_hash char(64) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY application_consent (application_id,consent_version),
            KEY user_id (user_id)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$access} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            evidence_id bigint(20) unsigned NOT NULL,
            reviewer_id bigint(20) unsigned NOT NULL,
            purpose_code varchar(80) NOT NULL,
            purpose_hash char(64) NOT NULL,
            result varchar(30) NOT NULL,
            actor_digest char(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY evidence_id (evidence_id),
            KEY reviewer_created (reviewer_id,created_at),
            KEY application_id (application_id)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$appeals} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            source_state varchar(40) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'open',
            reason text NOT NULL,
            resolution text NULL,
            decision varchar(40) NULL,
            resolver_id bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            resolved_at datetime NULL,
            PRIMARY KEY  (id),
            KEY application_status (application_id,status),
            KEY user_id (user_id)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$outbox} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_uuid char(36) NOT NULL,
            event_type varchar(80) NOT NULL,
            recipient_user_id bigint(20) unsigned NOT NULL,
            payload_json longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(10) unsigned NOT NULL DEFAULT 0,
            last_error text NULL,
            available_at datetime NOT NULL,
            delivered_at datetime NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_uuid (event_uuid),
            KEY status_available (status,available_at)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$rates} (
            bucket_hash char(64) NOT NULL,
            window_started bigint(20) unsigned NOT NULL,
            hits int(10) unsigned NOT NULL DEFAULT 0,
            expires_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (bucket_hash),
            KEY expires_at (expires_at)
        ) {$engine};" );
    }
}
