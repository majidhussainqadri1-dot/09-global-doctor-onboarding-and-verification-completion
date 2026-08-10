<?php
defined( 'ABSPATH' ) || exit;

/**
 * File 09 Advanced Professional Trust layer.
 *
 * These capabilities extend the existing File 09 owner boundary without
 * replacing File 00 identity, File 03 profile, File 07/File 26 discovery,
 * File 08 clinic, File 19 delivery or File 24 assurance ownership.
 * External issuer/AI/equivalency providers are adapters only. No provider
 * response may auto-approve, auto-reject or silently mutate a professional
 * verification decision.
 */
final class GDO_Advanced_Trust {
    const CONTRACT_VERSION = '1.0.0';
    const SCHEMA_VERSION   = 1;
    const REST_NAMESPACE   = 'gdo/v1';
    const PASSPORT_TTL     = 31536000;
    const CHUNK_TTL        = 86400;

    private static $tables = array(
        'trusted_issuers', 'jurisdiction_rules', 'credential_checks', 'professional_history',
        'reviewer_conflicts', 'verification_passports', 'monitor_state', 'upload_sessions',
    );

    public function hooks() {
        add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
        add_action( 'rest_api_init', array( $this, 'rest_routes' ) );
        add_action( 'gdo_trust_continuous_monitor', array( __CLASS__, 'continuous_monitor' ) );
        add_action( 'gdo_application_submitted', array( __CLASS__, 'application_submitted' ), 10, 1 );
        add_action( 'gdo_application_decided', array( __CLASS__, 'application_decided' ), 10, 2 );
        add_action( 'gdo_professional_status_changed', array( __CLASS__, 'event_reverification' ), 10, 3 );
        add_filter( 'gdo_reviewer_scope_allows', array( __CLASS__, 'reviewer_conflict_filter' ), 20, 4 );
        add_shortcode( 'gdo_verification_command_center', array( __CLASS__, 'command_center_shortcode' ) );
        add_shortcode( 'gdo_public_verification_card', array( __CLASS__, 'public_card_shortcode' ) );
        add_filter( 'gdo_public_professional_verification_matrix', array( __CLASS__, 'matrix_filter' ), 10, 2 );
        add_filter( 'gdo_file09_advanced_trust_contract', array( __CLASS__, 'contract_filter' ) );
    }

    public static function table( $name ) {
        global $wpdb;
        $name = sanitize_key( $name );
        if ( ! in_array( $name, self::$tables, true ) ) {
            return '';
        }
        return $wpdb->prefix . 'gdo_' . $name;
    }

    public static function maybe_install() {
        if ( absint( get_option( 'gdo_advanced_trust_schema', 0 ) ) >= self::SCHEMA_VERSION ) {
            return true;
        }
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        $engine = 'ENGINE=InnoDB ' . $c;
        $issuers = self::table( 'trusted_issuers' );
        $rules = self::table( 'jurisdiction_rules' );
        $checks = self::table( 'credential_checks' );
        $history = self::table( 'professional_history' );
        $conflicts = self::table( 'reviewer_conflicts' );
        $passports = self::table( 'verification_passports' );
        $monitor = self::table( 'monitor_state' );
        $uploads = self::table( 'upload_sessions' );

        dbDelta( "CREATE TABLE {$issuers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            issuer_uuid char(36) NOT NULL,
            name varchar(191) NOT NULL,
            issuer_type varchar(40) NOT NULL,
            jurisdiction varchar(16) NOT NULL DEFAULT '',
            canonical_domain varchar(191) NOT NULL DEFAULT '',
            adapter_key varchar(80) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'proposed',
            assurance_level varchar(30) NOT NULL DEFAULT 'unassessed',
            metadata_json longtext NULL,
            created_by bigint(20) unsigned NOT NULL,
            reviewed_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY issuer_uuid (issuer_uuid),
            KEY jurisdiction_status (jurisdiction,status),
            KEY domain_status (canonical_domain,status)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$rules} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            jurisdiction varchar(16) NOT NULL,
            rule_version varchar(40) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            rules_json longtext NOT NULL,
            effective_from date NULL,
            effective_until date NULL,
            created_by bigint(20) unsigned NOT NULL,
            approved_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY jurisdiction_version (jurisdiction,rule_version),
            KEY jurisdiction_status (jurisdiction,status)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$checks} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            check_uuid char(36) NOT NULL,
            application_id bigint(20) unsigned NOT NULL,
            evidence_id bigint(20) unsigned NULL,
            check_type varchar(60) NOT NULL,
            provider_key varchar(80) NOT NULL DEFAULT 'native',
            status varchar(30) NOT NULL DEFAULT 'pending',
            confidence decimal(7,4) NOT NULL DEFAULT 0,
            reviewer_required tinyint(1) unsigned NOT NULL DEFAULT 1,
            facts_json longtext NULL,
            explanation_json longtext NULL,
            external_reference varchar(191) NULL,
            checked_at datetime NULL,
            expires_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY check_uuid (check_uuid),
            KEY app_type (application_id,check_type),
            KEY evidence_type (evidence_id,check_type),
            KEY status_expires (status,expires_at)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$history} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            history_uuid char(36) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            application_id bigint(20) unsigned NULL,
            event_type varchar(60) NOT NULL,
            public_safe tinyint(1) unsigned NOT NULL DEFAULT 0,
            event_json longtext NOT NULL,
            source_hash char(64) NOT NULL,
            occurred_at datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY history_uuid (history_uuid),
            KEY user_occurred (user_id,occurred_at),
            KEY application_id (application_id)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$conflicts} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reviewer_id bigint(20) unsigned NOT NULL,
            applicant_id bigint(20) unsigned NOT NULL,
            application_id bigint(20) unsigned NULL,
            conflict_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            reason_hash char(64) NOT NULL,
            declared_by bigint(20) unsigned NOT NULL,
            declared_at datetime NOT NULL,
            resolved_by bigint(20) unsigned NULL,
            resolved_at datetime NULL,
            PRIMARY KEY (id),
            KEY reviewer_applicant (reviewer_id,applicant_id,status),
            KEY application_status (application_id,status)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$passports} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            passport_uuid char(36) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            application_id bigint(20) unsigned NOT NULL,
            version bigint(20) unsigned NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'active',
            scope_json longtext NOT NULL,
            token_hash char(64) NOT NULL,
            issued_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            revoked_at datetime NULL,
            revoke_reason varchar(100) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY passport_uuid (passport_uuid),
            UNIQUE KEY user_version (user_id,version),
            KEY status_expires (status,expires_at)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$monitor} (
            application_id bigint(20) unsigned NOT NULL,
            monitor_status varchar(20) NOT NULL DEFAULT 'scheduled',
            trigger_reason varchar(80) NOT NULL DEFAULT 'periodic',
            last_checked_at datetime NULL,
            next_check_at datetime NOT NULL,
            failure_count int(10) unsigned NOT NULL DEFAULT 0,
            last_result varchar(30) NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (application_id),
            KEY next_status (next_check_at,monitor_status)
        ) {$engine};" );

        dbDelta( "CREATE TABLE {$uploads} (
            upload_uuid char(36) NOT NULL,
            application_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            document_type varchar(30) NOT NULL,
            original_name varchar(255) NOT NULL,
            expected_bytes bigint(20) unsigned NOT NULL,
            received_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
            expected_chunks int(10) unsigned NOT NULL,
            received_chunks int(10) unsigned NOT NULL DEFAULT 0,
            chunk_bytes int(10) unsigned NOT NULL,
            expected_sha256 char(64) NULL,
            state varchar(20) NOT NULL DEFAULT 'open',
            temp_name varchar(191) NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (upload_uuid),
            KEY user_state (user_id,state),
            KEY expires_at (expires_at)
        ) {$engine};" );

        update_option( 'gdo_advanced_trust_schema', self::SCHEMA_VERSION, false );
        if ( ! wp_next_scheduled( 'gdo_trust_continuous_monitor' ) ) {
            wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'gdo_trust_continuous_monitor' );
        }
        GDO_Membership_Adapter::audit( 'doctor_advanced_trust_schema_ready', array( 'schema'=>self::SCHEMA_VERSION ) );
        return true;
    }

    private static function now() {
        return current_time( 'mysql', true );
    }

    private static function can_manage() {
        $uid = get_current_user_id();
        return $uid && GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $uid ) && GDO_Membership_Adapter::recent_step_up( $uid );
    }

    private static function public_matrix_for_app( $app ) {
        $matrix = array(
            'identity'        => false,
            'qualification'   => false,
            'institution'     => false,
            'registration'    => false,
            'license'         => false,
            'current_status'  => false,
            'last_reverified' => null,
            'next_review'     => null,
            'jurisdiction'    => '',
        );
        if ( ! $app ) {
            return $matrix;
        }
        $matrix['identity'] = GDO_Membership_Adapter::identity_assurance_current( $app->user_id );
        $matrix['current_status'] = in_array( $app->state, array( 'verified', 'approved' ), true ) && ( ! $app->verified_until || strtotime( $app->verified_until ) > time() );
        $matrix['jurisdiction'] = sanitize_text_field( $app->jurisdiction );
        $matrix['next_review'] = $app->verified_until ? gmdate( 'c', strtotime( $app->verified_until ) ) : null;
        foreach ( GDO_Evidence::records( $app->id, true ) as $evidence ) {
            $ok = in_array( $evidence->status, array( 'accepted', 'verified', 'approved' ), true );
            if ( ! $ok ) { continue; }
            if ( in_array( $evidence->document_type, array( 'qualification', 'degree', 'diploma' ), true ) ) { $matrix['qualification'] = true; }
            if ( in_array( $evidence->document_type, array( 'license', 'registration', 'professional_registration' ), true ) ) {
                $matrix['license'] = true;
                $matrix['registration'] = true;
            }
            if ( in_array( $evidence->document_type, array( 'institution', 'affiliation', 'employment' ), true ) ) { $matrix['institution'] = true; }
            if ( $evidence->reviewed_at && ( ! $matrix['last_reverified'] || strtotime( $evidence->reviewed_at ) > strtotime( $matrix['last_reverified'] ) ) ) {
                $matrix['last_reverified'] = gmdate( 'c', strtotime( $evidence->reviewed_at ) );
            }
        }
        return $matrix;
    }

    public static function verification_matrix( $user_id ) {
        return self::public_matrix_for_app( GDO_Application::latest_for_user( absint( $user_id ) ) );
    }

    public static function matrix_filter( $matrix, $user_id ) {
        return array_merge( is_array( $matrix ) ? $matrix : array(), self::verification_matrix( $user_id ) );
    }

    public static function contract_filter( $contract = array() ) {
        $contract = is_array( $contract ) ? $contract : array();
        $contract['version'] = self::CONTRACT_VERSION;
        $contract['capabilities'] = array(
            'primary_source_verification','trusted_issuer_registry','credential_authenticity','jurisdiction_rules',
            'cross_border_equivalency','continuous_license_monitoring','event_driven_reverification','professional_verification_passport',
            'public_verification_qr_payload','verification_scope_badge','institutional_affiliation_verification','professional_history_timeline',
            'credential_translation_workspace','ai_assisted_evidence_review','explainable_risk_intelligence','fraud_ring_detection',
            'reviewer_conflict_of_interest','adaptive_dual_review','smart_reviewer_routing','reviewer_calibration',
            'applicant_command_center','resumable_secure_upload','secure_evidence_viewing_room','trust_transparency_dashboard',
        );
        $contract['human_final_decision_required'] = true;
        $contract['private_evidence_searchable'] = false;
        $contract['donor_rank_advantage'] = false;
        return $contract;
    }

    public static function register_issuer( array $data ) {
        if ( ! self::can_manage() ) {
            return new WP_Error( 'gdo_trust_forbidden', __( 'Professional trust registry changes require a current privileged step-up.', 'global-doctor-onboarding' ) );
        }
        global $wpdb;
        $name = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
        $type = sanitize_key( isset( $data['issuer_type'] ) ? $data['issuer_type'] : '' );
        if ( '' === $name || '' === $type ) {
            return new WP_Error( 'gdo_issuer_invalid', __( 'Issuer name and type are required.', 'global-doctor-onboarding' ) );
        }
        $domain = strtolower( preg_replace( '/^www\./', '', sanitize_text_field( isset( $data['canonical_domain'] ) ? $data['canonical_domain'] : '' ) ) );
        $status = sanitize_key( isset( $data['status'] ) ? $data['status'] : 'proposed' );
        if ( ! in_array( $status, array( 'proposed','verified','suspended','retired' ), true ) ) { $status = 'proposed'; }
        $now = self::now();
        $row = array(
            'issuer_uuid'=>wp_generate_uuid4(), 'name'=>$name, 'issuer_type'=>$type,
            'jurisdiction'=>strtoupper( preg_replace( '/[^A-Z0-9-]/i', '', isset( $data['jurisdiction'] ) ? $data['jurisdiction'] : '' ) ),
            'canonical_domain'=>$domain, 'adapter_key'=>sanitize_key( isset( $data['adapter_key'] ) ? $data['adapter_key'] : '' ),
            'status'=>$status, 'assurance_level'=>sanitize_key( isset( $data['assurance_level'] ) ? $data['assurance_level'] : 'unassessed' ),
            'metadata_json'=>wp_json_encode( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : array() ),
            'created_by'=>get_current_user_id(), 'created_at'=>$now, 'updated_at'=>$now,
        );
        if ( 1 !== $wpdb->insert( self::table( 'trusted_issuers' ), $row ) ) {
            return new WP_Error( 'gdo_issuer_store_failed', __( 'The trusted issuer could not be recorded.', 'global-doctor-onboarding' ) );
        }
        GDO_Membership_Adapter::audit( 'doctor_trusted_issuer_registered', array( 'issuer_uuid'=>$row['issuer_uuid'], 'actor_id'=>get_current_user_id() ) );
        return $row;
    }

    public static function trusted_issuer( $name, $jurisdiction = '' ) {
        global $wpdb;
        $name = sanitize_text_field( $name );
        $jurisdiction = strtoupper( preg_replace( '/[^A-Z0-9-]/i', '', $jurisdiction ) );
        $sql = 'SELECT * FROM ' . self::table( 'trusted_issuers' ) . " WHERE name=%s AND status='verified'";
        $args = array( $name );
        if ( $jurisdiction ) { $sql .= ' AND jurisdiction=%s'; $args[] = $jurisdiction; }
        $sql .= ' ORDER BY updated_at DESC LIMIT 1';
        return $wpdb->get_row( $wpdb->prepare( $sql, $args ) );
    }

    public static function save_jurisdiction_rule( $jurisdiction, $version, array $rules, $status = 'draft' ) {
        if ( ! self::can_manage() ) { return new WP_Error( 'gdo_trust_forbidden', __( 'Jurisdiction rules require privileged step-up.', 'global-doctor-onboarding' ) ); }
        global $wpdb;
        $jurisdiction = strtoupper( preg_replace( '/[^A-Z0-9-]/i', '', $jurisdiction ) );
        $version = sanitize_text_field( $version );
        $status = sanitize_key( $status );
        if ( ! $jurisdiction || ! $version || ! in_array( $status, array( 'draft','approved','retired' ), true ) ) {
            return new WP_Error( 'gdo_jurisdiction_rule_invalid', __( 'Jurisdiction rule data are invalid.', 'global-doctor-onboarding' ) );
        }
        $now = self::now();
        $ok = $wpdb->replace( self::table( 'jurisdiction_rules' ), array(
            'jurisdiction'=>$jurisdiction, 'rule_version'=>$version, 'status'=>$status,
            'rules_json'=>wp_json_encode( $rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'created_by'=>get_current_user_id(), 'approved_by'=>'approved' === $status ? get_current_user_id() : null,
            'created_at'=>$now, 'updated_at'=>$now,
        ) );
        return false === $ok ? new WP_Error( 'gdo_jurisdiction_rule_store', __( 'Jurisdiction rule could not be stored.', 'global-doctor-onboarding' ) ) : true;
    }

    public static function jurisdiction_rule( $jurisdiction ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table( 'jurisdiction_rules' ) . " WHERE jurisdiction=%s AND status='approved' AND (effective_from IS NULL OR effective_from<=UTC_DATE()) AND (effective_until IS NULL OR effective_until>=UTC_DATE()) ORDER BY id DESC LIMIT 1",
            strtoupper( preg_replace( '/[^A-Z0-9-]/i', '', $jurisdiction ) )
        ) );
    }

    private static function record_check( $application_id, $evidence_id, $type, $provider, $status, $confidence, array $facts, array $explanation = array(), $external_reference = '', $expires_at = null ) {
        global $wpdb;
        $row = array(
            'check_uuid'=>wp_generate_uuid4(), 'application_id'=>absint( $application_id ), 'evidence_id'=>$evidence_id ? absint( $evidence_id ) : null,
            'check_type'=>sanitize_key( $type ), 'provider_key'=>sanitize_key( $provider ), 'status'=>sanitize_key( $status ),
            'confidence'=>max( 0, min( 1, (float) $confidence ) ), 'reviewer_required'=>1,
            'facts_json'=>wp_json_encode( $facts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'explanation_json'=>wp_json_encode( $explanation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'external_reference'=>sanitize_text_field( $external_reference ), 'checked_at'=>self::now(), 'expires_at'=>$expires_at,
            'created_at'=>self::now(), 'updated_at'=>self::now(),
        );
        $wpdb->insert( self::table( 'credential_checks' ), $row );
        return $row;
    }

    public static function primary_source_verify( $application_id, $evidence_id ) {
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) { return new WP_Error( 'gdo_app_missing', __( 'Application not found.', 'global-doctor-onboarding' ) ); }
        $record = null;
        foreach ( GDO_Evidence::records( $app->id, true ) as $item ) { if ( absint( $item->id ) === absint( $evidence_id ) ) { $record = $item; break; } }
        if ( ! $record ) { return new WP_Error( 'gdo_evidence_missing', __( 'Evidence not found.', 'global-doctor-onboarding' ) ); }
        $profile = json_decode( $app->profile_json, true );
        $profile = is_array( $profile ) ? $profile : array();
        $issuer_name = isset( $profile['licensing_authority'] ) ? $profile['licensing_authority'] : '';
        $issuer = self::trusted_issuer( $issuer_name, $app->jurisdiction );
        if ( ! $issuer ) {
            return self::record_check( $app->id, $record->id, 'primary_source', 'none', 'issuer_unverified', 0, array( 'issuer'=>$issuer_name ), array( 'reason'=>'trusted_issuer_not_available' ) );
        }
        $request = array(
            'application_id'=>absint( $app->id ), 'evidence_id'=>absint( $record->id ), 'document_type'=>$record->document_type,
            'jurisdiction'=>$app->jurisdiction, 'issuer_uuid'=>$issuer->issuer_uuid,
            'license_number'=>isset( $profile['license_number'] ) ? sanitize_text_field( $profile['license_number'] ) : '',
            'source_sha256'=>$record->source_sha256,
        );
        $result = apply_filters( 'gdo_primary_source_verification', null, $request, $issuer );
        if ( ! is_array( $result ) || empty( $result['status'] ) ) {
            return self::record_check( $app->id, $record->id, 'primary_source', $issuer->adapter_key ? $issuer->adapter_key : 'unconfigured', 'provider_unavailable', 0, $request, array( 'reason'=>'provider_adapter_unavailable' ) );
        }
        $allowed = array( 'matched','not_matched','revoked','expired','pending','provider_error' );
        $status = sanitize_key( $result['status'] );
        if ( ! in_array( $status, $allowed, true ) ) { $status = 'provider_error'; }
        return self::record_check( $app->id, $record->id, 'primary_source', $issuer->adapter_key, $status, isset( $result['confidence'] ) ? $result['confidence'] : 0, isset( $result['facts'] ) && is_array( $result['facts'] ) ? $result['facts'] : array(), isset( $result['explanation'] ) && is_array( $result['explanation'] ) ? $result['explanation'] : array(), isset( $result['reference'] ) ? $result['reference'] : '', isset( $result['expires_at'] ) ? $result['expires_at'] : null );
    }

    public static function authenticity_assessment( $application_id, $evidence_id ) {
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) { return new WP_Error( 'gdo_app_missing', __( 'Application not found.', 'global-doctor-onboarding' ) ); }
        $record = null;
        foreach ( GDO_Evidence::records( $app->id, true ) as $item ) { if ( absint( $item->id ) === absint( $evidence_id ) ) { $record = $item; break; } }
        if ( ! $record ) { return new WP_Error( 'gdo_evidence_missing', __( 'Evidence not found.', 'global-doctor-onboarding' ) ); }
        global $wpdb;
        $shared = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT application_id) FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE source_sha256=%s AND deleted_at IS NULL', $record->source_sha256 ) ) );
        $signals = array(
            'malware_clean'=>'clean' === $record->malware_status,
            'hash_present'=>64 === strlen( (string) $record->source_sha256 ),
            'metadata_removed'=>(bool) $record->metadata_removed,
            'shared_across_applications'=>$shared,
            'validity_until'=>$record->validity_until,
        );
        $confidence = 0.35 + ( $signals['malware_clean'] ? 0.2 : 0 ) + ( $signals['hash_present'] ? 0.1 : 0 ) + ( $signals['metadata_removed'] ? 0.05 : 0 );
        if ( $shared > 1 ) { $confidence = max( 0.05, $confidence - 0.35 ); }
        return self::record_check( $app->id, $record->id, 'authenticity', 'native', 'manual_review_required', $confidence, $signals, array( 'rule'=>'local technical checks never establish professional authenticity by themselves' ) );
    }

    public static function equivalency_assessment( $application_id ) {
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) { return new WP_Error( 'gdo_app_missing', __( 'Application not found.', 'global-doctor-onboarding' ) ); }
        $profile = json_decode( $app->profile_json, true );
        $request = array( 'qualification'=>isset( $profile['qualification'] ) ? $profile['qualification'] : '', 'source_jurisdiction'=>isset( $profile['license_jurisdiction'] ) ? $profile['license_jurisdiction'] : '', 'target_jurisdiction'=>$app->jurisdiction );
        $result = apply_filters( 'gdo_credential_equivalency_assessment', null, $request, $app );
        if ( ! is_array( $result ) ) { $result = array( 'status'=>'manual_review_required', 'facts'=>$request, 'explanation'=>array( 'reason'=>'no_equivalency_adapter' ) ); }
        return self::record_check( $app->id, 0, 'equivalency', isset( $result['provider'] ) ? $result['provider'] : 'unconfigured', isset( $result['status'] ) ? $result['status'] : 'manual_review_required', isset( $result['confidence'] ) ? $result['confidence'] : 0, isset( $result['facts'] ) && is_array( $result['facts'] ) ? $result['facts'] : $request, isset( $result['explanation'] ) && is_array( $result['explanation'] ) ? $result['explanation'] : array( 'legal_license_grant'=>false ) );
    }

    public static function verify_affiliation( $application_id, $institution ) {
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) { return new WP_Error( 'gdo_app_missing', __( 'Application not found.', 'global-doctor-onboarding' ) ); }
        $request = array( 'institution'=>sanitize_text_field( $institution ), 'user_id'=>absint( $app->user_id ), 'jurisdiction'=>$app->jurisdiction );
        $result = apply_filters( 'gdo_institutional_affiliation_verification', null, $request, $app );
        if ( ! is_array( $result ) ) { $result = array( 'status'=>'manual_review_required', 'facts'=>$request, 'explanation'=>array( 'reason'=>'no_affiliation_adapter' ) ); }
        return self::record_check( $app->id, 0, 'affiliation', isset( $result['provider'] ) ? $result['provider'] : 'unconfigured', isset( $result['status'] ) ? $result['status'] : 'manual_review_required', isset( $result['confidence'] ) ? $result['confidence'] : 0, isset( $result['facts'] ) && is_array( $result['facts'] ) ? $result['facts'] : $request, isset( $result['explanation'] ) && is_array( $result['explanation'] ) ? $result['explanation'] : array() );
    }

    public static function translation_assistance( $application_id, $evidence_id, $target_locale ) {
        $payload = array( 'application_id'=>absint( $application_id ), 'evidence_id'=>absint( $evidence_id ), 'target_locale'=>sanitize_locale_name( $target_locale ) );
        $result = apply_filters( 'gdo_credential_translation_assistance', null, $payload );
        if ( ! is_array( $result ) ) { $result = array( 'status'=>'unavailable', 'translation'=>'', 'provider'=>'unconfigured' ); }
        $facts = array( 'target_locale'=>$payload['target_locale'], 'translation'=>sanitize_textarea_field( isset( $result['translation'] ) ? $result['translation'] : '' ), 'original_remains_authoritative'=>true );
        return self::record_check( $application_id, $evidence_id, 'translation', isset( $result['provider'] ) ? $result['provider'] : 'unconfigured', isset( $result['status'] ) ? $result['status'] : 'unavailable', isset( $result['confidence'] ) ? $result['confidence'] : 0, $facts, array( 'decision_authority'=>'human_reviewer' ) );
    }

    public static function ai_assistance( $application_id, $evidence_id ) {
        $payload = array( 'application_id'=>absint( $application_id ), 'evidence_id'=>absint( $evidence_id ), 'allowed_tasks'=>array( 'classification','field_extraction','expiry_detection','mismatch_highlighting','duplicate_clues' ) );
        $result = apply_filters( 'gdo_ai_evidence_assistance', null, $payload );
        if ( ! is_array( $result ) ) { $result = array( 'status'=>'unavailable','provider'=>'unconfigured','hints'=>array() ); }
        unset( $result['decision'], $result['approve'], $result['reject'] );
        $hints = isset( $result['hints'] ) && is_array( $result['hints'] ) ? array_slice( $result['hints'], 0, 50 ) : array();
        return self::record_check( $application_id, $evidence_id, 'ai_assist', isset( $result['provider'] ) ? $result['provider'] : 'unconfigured', isset( $result['status'] ) ? $result['status'] : 'unavailable', isset( $result['confidence'] ) ? $result['confidence'] : 0, array( 'hints'=>$hints ), array( 'automated_decision_forbidden'=>true, 'human_final_decision_required'=>true ) );
    }

    public static function fraud_ring_scan( $application_id ) {
        global $wpdb;
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) { return array(); }
        $evidence = GDO_Evidence::records( $app->id, true );
        $links = array();
        foreach ( $evidence as $record ) {
            if ( ! $record->source_sha256 ) { continue; }
            $ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT application_id FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE source_sha256=%s AND application_id<>%d AND deleted_at IS NULL LIMIT 20', $record->source_sha256, $app->id ) );
            foreach ( $ids as $id ) { $links[] = absint( $id ); }
        }
        $links = array_values( array_unique( array_filter( $links ) ) );
        if ( $links ) {
            foreach ( $links as $related_id ) {
                $wpdb->insert( GDO_Schema::table( 'risk_signals' ), array(
                    'application_id'=>$app->id, 'signal_type'=>'credential_reuse_network', 'severity'=>'high', 'status'=>'open',
                    'related_digest'=>hash( 'sha256', 'application:' . $related_id ), 'created_at'=>self::now(), 'updated_at'=>self::now(),
                ) );
            }
        }
        return $links;
    }

    public static function risk_explanation( $application_id ) {
        global $wpdb;
        $signals = $wpdb->get_results( $wpdb->prepare( 'SELECT signal_type,severity,status,created_at FROM ' . GDO_Schema::table( 'risk_signals' ) . ' WHERE application_id=%d ORDER BY id DESC LIMIT 100', absint( $application_id ) ), ARRAY_A );
        $checks = $wpdb->get_results( $wpdb->prepare( 'SELECT check_type,status,confidence,explanation_json,checked_at FROM ' . self::table( 'credential_checks' ) . ' WHERE application_id=%d ORDER BY id DESC LIMIT 100', absint( $application_id ) ), ARRAY_A );
        $reasons = array();
        foreach ( (array) $signals as $s ) { $reasons[] = array( 'source'=>'risk_signal','type'=>$s['signal_type'],'severity'=>$s['severity'],'status'=>$s['status'] ); }
        foreach ( (array) $checks as $c ) { $reasons[] = array( 'source'=>'credential_check','type'=>$c['check_type'],'status'=>$c['status'],'confidence'=>(float)$c['confidence'] ); }
        return array( 'application_id'=>absint( $application_id ), 'reasons'=>$reasons, 'opaque_rejection_forbidden'=>true, 'human_review_required'=>true );
    }

    public static function declare_conflict( $reviewer_id, $applicant_id, $application_id, $type, $reason ) {
        global $wpdb;
        $reviewer_id = absint( $reviewer_id ); $applicant_id = absint( $applicant_id ); $application_id = absint( $application_id );
        if ( ! $reviewer_id || ! $applicant_id || ! $type ) { return false; }
        $wpdb->insert( self::table( 'reviewer_conflicts' ), array(
            'reviewer_id'=>$reviewer_id, 'applicant_id'=>$applicant_id, 'application_id'=>$application_id ?: null,
            'conflict_type'=>sanitize_key( $type ), 'status'=>'active', 'reason_hash'=>hash( 'sha256', sanitize_textarea_field( $reason ) ),
            'declared_by'=>get_current_user_id() ?: $reviewer_id, 'declared_at'=>self::now(),
        ) );
        return true;
    }

    public static function has_conflict( $reviewer_id, $applicant_id, $application_id = 0 ) {
        global $wpdb;
        if ( absint( $reviewer_id ) === absint( $applicant_id ) ) { return true; }
        $count = absint( $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table( 'reviewer_conflicts' ) . " WHERE reviewer_id=%d AND applicant_id=%d AND status='active' AND (application_id IS NULL OR application_id=0 OR application_id=%d)",
            absint( $reviewer_id ), absint( $applicant_id ), absint( $application_id )
        ) ) );
        $detected = $count > 0;
        return (bool) apply_filters( 'gdo_reviewer_conflict_detected', $detected, absint( $reviewer_id ), absint( $applicant_id ), absint( $application_id ) );
    }

    public static function reviewer_conflict_filter( $allowed, $reviewer_id, $applicant_id, $application_id ) {
        return $allowed && ! self::has_conflict( $reviewer_id, $applicant_id, $application_id );
    }

    public static function requires_dual_review( $application_id ) {
        global $wpdb;
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) { return true; }
        $high = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . GDO_Schema::table( 'risk_signals' ) . " WHERE application_id=%d AND status='open' AND severity IN ('high','critical')", $app->id ) ) );
        $cross_border = false;
        $profile = json_decode( $app->profile_json, true );
        if ( is_array( $profile ) && ! empty( $profile['license_jurisdiction'] ) && $app->jurisdiction ) { $cross_border = strtoupper( $profile['license_jurisdiction'] ) !== strtoupper( $app->jurisdiction ); }
        $required = $high > 0 || $cross_border || in_array( $app->state, array( 'appeal_review','suspended','revoked' ), true );
        return (bool) apply_filters( 'gdo_requires_dual_review', $required, $app );
    }

    public static function smart_reviewer_candidates( $application_id, $limit = 10 ) {
        global $wpdb;
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) { return array(); }
        $profiles = $wpdb->get_results( 'SELECT * FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . " WHERE status='active' ORDER BY updated_at DESC LIMIT 200" );
        $out = array();
        foreach ( (array) $profiles as $profile ) {
            $uid = absint( $profile->user_id );
            if ( ! GDO_Membership_Adapter::reviewer_scope_allows( $uid, $app->user_id, $app->id ) || self::has_conflict( $uid, $app->user_id, $app->id ) ) { continue; }
            $open = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . GDO_Schema::table( 'applications' ) . " WHERE assigned_reviewer_id=%d AND state IN ('submitted','under_review','more_information')", $uid ) ) );
            $langs = json_decode( $profile->languages_json, true ); $jur = json_decode( $profile->jurisdictions_json, true );
            $score = 100 - min( 60, $open * 3 );
            if ( in_array( $app->jurisdiction, (array) $jur, true ) ) { $score += 20; }
            if ( in_array( $app->preferred_language, (array) $langs, true ) ) { $score += 10; }
            $out[] = array( 'reviewer_id'=>$uid, 'score'=>$score, 'open_cases'=>$open );
        }
        usort( $out, function( $a, $b ) { return $a['score'] === $b['score'] ? $a['open_cases'] - $b['open_cases'] : $b['score'] - $a['score']; } );
        return array_slice( $out, 0, max( 1, min( 50, absint( $limit ) ) ) );
    }

    public static function reviewer_calibration( $reviewer_id ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT outcome,status FROM ' . GDO_Schema::table( 'quality_samples' ) . ' WHERE reviewer_id=%d ORDER BY id DESC LIMIT 500', absint( $reviewer_id ) ), ARRAY_A );
        $total = count( $rows ); $complete = 0; $confirmed = 0; $overturned = 0;
        foreach ( $rows as $row ) {
            if ( 'completed' === $row['status'] ) { ++$complete; }
            if ( in_array( $row['outcome'], array( 'confirmed','agree' ), true ) ) { ++$confirmed; }
            if ( in_array( $row['outcome'], array( 'overturned','disagree' ), true ) ) { ++$overturned; }
        }
        return array( 'reviewer_id'=>absint( $reviewer_id ), 'sampled'=>$total, 'completed'=>$complete, 'agreement_rate'=>$complete ? round( $confirmed / $complete, 4 ) : null, 'overturn_rate'=>$complete ? round( $overturned / $complete, 4 ) : null );
    }

    public static function add_history( $user_id, $application_id, $event_type, array $event, $public_safe = false ) {
        global $wpdb;
        $json = wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $wpdb->insert( self::table( 'professional_history' ), array(
            'history_uuid'=>wp_generate_uuid4(), 'user_id'=>absint( $user_id ), 'application_id'=>$application_id ? absint( $application_id ) : null,
            'event_type'=>sanitize_key( $event_type ), 'public_safe'=>$public_safe ? 1 : 0, 'event_json'=>$json,
            'source_hash'=>hash( 'sha256', $json ), 'occurred_at'=>self::now(), 'created_at'=>self::now(),
        ) );
    }

    public static function public_history( $user_id, $limit = 50 ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT event_type,event_json,occurred_at FROM ' . self::table( 'professional_history' ) . ' WHERE user_id=%d AND public_safe=1 ORDER BY occurred_at DESC LIMIT %d', absint( $user_id ), max( 1, min( 100, absint( $limit ) ) ) ), ARRAY_A );
        foreach ( $rows as &$row ) { $row['event'] = json_decode( $row['event_json'], true ); unset( $row['event_json'] ); } unset( $row );
        return $rows;
    }

    private static function passport_key() {
        return defined( 'GDO_CLAIM_SIGNING_KEY' ) ? hash( 'sha256', 'passport|' . (string) GDO_CLAIM_SIGNING_KEY, true ) : '';
    }

    public static function issue_passport( $application_id ) {
        global $wpdb;
        $app = GDO_Application::get( $application_id );
        if ( ! $app || ! in_array( $app->state, array( 'verified','approved' ), true ) || ! self::passport_key() ) {
            return new WP_Error( 'gdo_passport_not_eligible', __( 'A current verified application is required for a professional passport.', 'global-doctor-onboarding' ) );
        }
        $version = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . self::table( 'verification_passports' ) . ' WHERE user_id=%d', $app->user_id ) ) ) + 1;
        $uuid = wp_generate_uuid4(); $issued = time(); $exp = min( $issued + self::PASSPORT_TTL, $app->verified_until ? strtotime( $app->verified_until ) : $issued + self::PASSPORT_TTL );
        $scope = self::verification_matrix( $app->user_id );
        $payload = array( 'passport_uuid'=>$uuid, 'user_id'=>absint( $app->user_id ), 'application_id'=>absint( $app->id ), 'version'=>$version, 'scope'=>$scope, 'iat'=>$issued, 'exp'=>$exp );
        $body = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
        $sig = hash_hmac( 'sha256', $body, self::passport_key() );
        $token = $body . '.' . $sig;
        $wpdb->insert( self::table( 'verification_passports' ), array(
            'passport_uuid'=>$uuid, 'user_id'=>$app->user_id, 'application_id'=>$app->id, 'version'=>$version, 'status'=>'active',
            'scope_json'=>wp_json_encode( $scope ), 'token_hash'=>hash( 'sha256', $token ), 'issued_at'=>gmdate( 'Y-m-d H:i:s', $issued ), 'expires_at'=>gmdate( 'Y-m-d H:i:s', $exp ),
        ) );
        self::add_history( $app->user_id, $app->id, 'verification_passport_issued', array( 'version'=>$version, 'expires_at'=>gmdate( 'c', $exp ) ), true );
        return array( 'token'=>$token, 'passport_uuid'=>$uuid, 'verification_url'=>rest_url( self::REST_NAMESPACE . '/public/passport/' . $uuid ), 'qr_payload'=>rest_url( self::REST_NAMESPACE . '/public/passport/' . $uuid ) );
    }

    public static function verify_passport_uuid( $uuid ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT passport_uuid,user_id,application_id,version,status,scope_json,issued_at,expires_at,revoked_at,revoke_reason FROM ' . self::table( 'verification_passports' ) . ' WHERE passport_uuid=%s LIMIT 1', sanitize_text_field( $uuid ) ), ARRAY_A );
        if ( ! $row || 'active' !== $row['status'] || strtotime( $row['expires_at'] ) <= time() ) { return new WP_Error( 'gdo_passport_inactive', __( 'This professional verification passport is not active.', 'global-doctor-onboarding' ), array( 'status'=>404 ) ); }
        return array( 'passport_uuid'=>$row['passport_uuid'], 'version'=>absint( $row['version'] ), 'verification'=>json_decode( $row['scope_json'], true ), 'issued_at'=>$row['issued_at'], 'expires_at'=>$row['expires_at'], 'cure_guarantee'=>false, 'professional_scope_only'=>true );
    }

    public static function schedule_reverification( $application_id, $reason = 'periodic', $when = 0 ) {
        global $wpdb;
        $when = $when ? absint( $when ) : time() + DAY_IN_SECONDS;
        return false !== $wpdb->replace( self::table( 'monitor_state' ), array( 'application_id'=>absint( $application_id ), 'monitor_status'=>'scheduled', 'trigger_reason'=>sanitize_key( $reason ), 'next_check_at'=>gmdate( 'Y-m-d H:i:s', $when ), 'failure_count'=>0, 'updated_at'=>self::now() ) );
    }

    public static function event_reverification( $application_id, $event_type = 'status_change', $context = array() ) {
        self::schedule_reverification( $application_id, $event_type, time() + HOUR_IN_SECONDS );
    }

    public static function continuous_monitor() {
        global $wpdb;
        self::maybe_install();
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'monitor_state' ) . " WHERE monitor_status='scheduled' AND next_check_at<=%s ORDER BY next_check_at ASC LIMIT 50", self::now() ) );
        foreach ( (array) $rows as $row ) {
            $app = GDO_Application::get( $row->application_id );
            if ( ! $app ) { $wpdb->delete( self::table( 'monitor_state' ), array( 'application_id'=>$row->application_id ) ); continue; }
            $result = 'no_license_evidence';
            foreach ( GDO_Evidence::records( $app->id, true ) as $evidence ) {
                if ( in_array( $evidence->document_type, array( 'license','registration','professional_registration' ), true ) ) {
                    $check = self::primary_source_verify( $app->id, $evidence->id );
                    $result = is_wp_error( $check ) ? $check->get_error_code() : $check['status'];
                    if ( in_array( $result, array( 'revoked','expired','not_matched' ), true ) ) {
                        do_action( 'gdo_continuous_verification_adverse_result', $app->id, $result, $check );
                    }
                }
            }
            $wpdb->update( self::table( 'monitor_state' ), array( 'last_checked_at'=>self::now(), 'last_result'=>sanitize_key( $result ), 'next_check_at'=>gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ), 'updated_at'=>self::now() ), array( 'application_id'=>$app->id ) );
        }
        self::cleanup_upload_sessions();
    }

    public static function application_submitted( $application_id ) {
        self::fraud_ring_scan( $application_id );
        self::equivalency_assessment( $application_id );
        self::schedule_reverification( $application_id, 'submission', time() + DAY_IN_SECONDS );
    }

    public static function application_decided( $application_id, $decision ) {
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) { return; }
        self::add_history( $app->user_id, $app->id, 'professional_decision', array( 'decision'=>sanitize_key( $decision ), 'verified_until'=>$app->verified_until ), true );
        if ( in_array( sanitize_key( $decision ), array( 'approved','verified' ), true ) ) { self::issue_passport( $app->id ); self::schedule_reverification( $app->id, 'verified', time() + 30 * DAY_IN_SECONDS ); }
    }

    public static function command_center( $user_id ) {
        $app = GDO_Application::latest_for_user( absint( $user_id ) );
        if ( ! $app ) { return array( 'application'=>null, 'next_action'=>'start_application' ); }
        $complete = GDO_Application::completeness( $app );
        global $wpdb;
        $checks = $wpdb->get_results( $wpdb->prepare( 'SELECT check_type,status,checked_at,expires_at FROM ' . self::table( 'credential_checks' ) . ' WHERE application_id=%d ORDER BY id DESC LIMIT 50', $app->id ), ARRAY_A );
        $next = 'await_review';
        if ( in_array( $app->state, array( 'draft','more_information' ), true ) ) { $next = count( $complete['missing_fields'] ) || count( $complete['missing_evidence'] ) ? 'complete_missing_items' : 'submit'; }
        if ( in_array( $app->state, array( 'verified','approved' ), true ) ) { $next = 'monitor_credentials'; }
        if ( 'renewal_due' === $app->state || 'expired' === $app->state ) { $next = 'renew'; }
        if ( in_array( $app->state, array( 'rejected','suspended','revoked' ), true ) ) { $next = 'appeal_or_correct'; }
        return array(
            'application'=>array( 'id'=>absint( $app->id ), 'state'=>$app->state, 'version'=>absint( $app->version ), 'verified_until'=>$app->verified_until, 'more_info_due_at'=>$app->more_info_due_at ),
            'completion'=>array( 'missing_fields'=>$complete['missing_fields'], 'missing_evidence'=>$complete['missing_evidence'] ),
            'verification_matrix'=>self::verification_matrix( $user_id ), 'checks'=>$checks, 'next_action'=>$next,
        );
    }

    public static function command_center_shortcode() {
        if ( ! is_user_logged_in() ) { return '<p>' . esc_html__( 'Log in to view your verification command center.', 'global-doctor-onboarding' ) . '</p>'; }
        $data = self::command_center( get_current_user_id() );
        ob_start(); ?>
        <section class="gdo-trust-command-center" aria-labelledby="gdo-trust-title">
            <h2 id="gdo-trust-title"><?php esc_html_e( 'Professional Verification Command Center', 'global-doctor-onboarding' ); ?></h2>
            <?php if ( empty( $data['application'] ) ) : ?><p><?php esc_html_e( 'No professional application exists yet.', 'global-doctor-onboarding' ); ?></p><?php else : ?>
            <p><strong><?php echo esc_html( $data['application']['state'] ); ?></strong> · <?php echo esc_html( $data['next_action'] ); ?></p>
            <p><?php printf( esc_html__( '%1$d missing fields · %2$d missing evidence items', 'global-doctor-onboarding' ), count( $data['completion']['missing_fields'] ), count( $data['completion']['missing_evidence'] ) ); ?></p>
            <?php endif; ?>
        </section><?php return ob_get_clean();
    }

    public static function public_card_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'user_id'=>0 ), $atts, 'gdo_public_verification_card' );
        $uid = absint( $atts['user_id'] ); if ( ! $uid ) { return ''; }
        $matrix = self::verification_matrix( $uid );
        $labels = array( 'identity'=>'Identity','qualification'=>'Qualification','institution'=>'Institution','registration'=>'Registration','license'=>'License','current_status'=>'Current status' );
        ob_start(); ?><section class="gdo-public-verification-card"><h3><?php esc_html_e( 'Professional Verification', 'global-doctor-onboarding' ); ?></h3><ul><?php foreach ( $labels as $key=>$label ) : ?><li><?php echo esc_html( $label ); ?>: <strong><?php echo ! empty( $matrix[$key] ) ? esc_html__( 'Verified', 'global-doctor-onboarding' ) : esc_html__( 'Not verified', 'global-doctor-onboarding' ); ?></strong></li><?php endforeach; ?></ul><?php if ( $matrix['last_reverified'] ) : ?><p><?php echo esc_html( $matrix['last_reverified'] ); ?></p><?php endif; ?><p><?php esc_html_e( 'Professional verification does not guarantee treatment outcomes.', 'global-doctor-onboarding' ); ?></p></section><?php return ob_get_clean();
    }

    public static function issue_viewing_room_grant( $application_id, $evidence_id, $reviewer_id, $purpose = 'credential_review' ) {
        global $wpdb;
        if ( ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer_id, GDO_Application::get( $application_id )->user_id, $application_id ) || ! GDO_Membership_Adapter::recent_step_up( $reviewer_id ) ) {
            return new WP_Error( 'gdo_room_forbidden', __( 'Secure evidence viewing requires current reviewer authorization and step-up.', 'global-doctor-onboarding' ) );
        }
        $grant = wp_generate_uuid4();
        $session = wp_get_session_token();
        $hash = hash( 'sha256', $grant . '|' . $session . '|' . $reviewer_id );
        $expires = gmdate( 'Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS );
        $wpdb->insert( GDO_Schema::table( 'access_grants' ), array(
            'grant_hash'=>$hash, 'application_id'=>absint( $application_id ), 'evidence_id'=>absint( $evidence_id ), 'reviewer_id'=>absint( $reviewer_id ),
            'purpose_code'=>sanitize_key( $purpose ), 'session_digest'=>hash( 'sha256', $session ), 'mode'=>'secure_room', 'expires_at'=>$expires, 'created_at'=>self::now(),
        ) );
        return array( 'grant'=>$grant, 'expires_at'=>$expires, 'watermark'=>sprintf( 'Reviewer %d · %s · %s', absint( $reviewer_id ), gmdate( 'c' ), wp_generate_uuid4() ), 'download_allowed'=>false );
    }

    private static function upload_temp_path( $name ) {
        $dir = GDO_Storage::directory();
        return $dir ? trailingslashit( $dir ) . '.chunk-' . basename( sanitize_file_name( $name ) ) : '';
    }

    public static function create_upload_session( $application_id, $document_type, $name, $bytes, $chunks, $chunk_bytes, $sha256 = '' ) {
        global $wpdb;
        $app = GDO_Application::get( $application_id ); $uid = get_current_user_id();
        if ( ! $app || absint( $app->user_id ) !== $uid || ! in_array( $app->state, array( 'draft','more_information' ), true ) || ! isset( GDO_Evidence::types( $app->jurisdiction, $app->application_type )[ sanitize_key( $document_type ) ] ) ) {
            return new WP_Error( 'gdo_upload_session_forbidden', __( 'A resumable upload cannot be started for this application.', 'global-doctor-onboarding' ) );
        }
        $bytes = absint( $bytes ); $chunks = absint( $chunks ); $chunk_bytes = absint( $chunk_bytes );
        if ( $bytes < 32 || $bytes > GDO_Evidence::MAX_BYTES || $chunks < 1 || $chunks > 200 || $chunk_bytes < 16384 || $chunk_bytes > 1048576 ) {
            return new WP_Error( 'gdo_upload_session_invalid', __( 'Resumable upload dimensions are invalid.', 'global-doctor-onboarding' ) );
        }
        $uuid = wp_generate_uuid4(); $temp = $uuid . '.part'; $path = self::upload_temp_path( $temp );
        if ( ! $path || false === @file_put_contents( $path, '' ) ) { return new WP_Error( 'gdo_upload_session_storage', __( 'Private resumable upload storage is unavailable.', 'global-doctor-onboarding' ) ); }
        @chmod( $path, 0600 );
        $wpdb->insert( self::table( 'upload_sessions' ), array(
            'upload_uuid'=>$uuid,'application_id'=>$app->id,'user_id'=>$uid,'document_type'=>sanitize_key($document_type),'original_name'=>sanitize_file_name($name),
            'expected_bytes'=>$bytes,'received_bytes'=>0,'expected_chunks'=>$chunks,'received_chunks'=>0,'chunk_bytes'=>$chunk_bytes,'expected_sha256'=>preg_match('/^[a-f0-9]{64}$/i',$sha256)?strtolower($sha256):null,
            'state'=>'open','temp_name'=>$temp,'expires_at'=>gmdate('Y-m-d H:i:s',time()+self::CHUNK_TTL),'created_at'=>self::now(),'updated_at'=>self::now(),
        ) );
        return array( 'upload_uuid'=>$uuid, 'expires_at'=>gmdate( 'c', time()+self::CHUNK_TTL ) );
    }

    public static function append_upload_chunk( $uuid, $index, $bytes ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'upload_sessions' ) . " WHERE upload_uuid=%s AND user_id=%d AND state='open' LIMIT 1", sanitize_text_field( $uuid ), get_current_user_id() ) );
        if ( ! $row || strtotime( $row->expires_at ) <= time() ) { return new WP_Error( 'gdo_upload_session_missing', __( 'Resumable upload session is unavailable or expired.', 'global-doctor-onboarding' ) ); }
        $index = absint( $index );
        if ( $index !== absint( $row->received_chunks ) || strlen( $bytes ) < 1 || strlen( $bytes ) > absint( $row->chunk_bytes ) || absint( $row->received_bytes ) + strlen( $bytes ) > absint( $row->expected_bytes ) ) {
            return new WP_Error( 'gdo_upload_chunk_order', __( 'Upload chunks must arrive exactly once and in order.', 'global-doctor-onboarding' ) );
        }
        $path = self::upload_temp_path( $row->temp_name ); $fh = @fopen( $path, 'ab' );
        if ( ! $fh ) { return new WP_Error( 'gdo_upload_chunk_storage', __( 'The private upload chunk could not be stored.', 'global-doctor-onboarding' ) ); }
        if ( ! flock( $fh, LOCK_EX ) ) { fclose( $fh ); return new WP_Error( 'gdo_upload_chunk_lock', __( 'The resumable upload is busy.', 'global-doctor-onboarding' ) ); }
        $written = fwrite( $fh, $bytes ); if ( function_exists( 'fsync' ) ) { @fsync( $fh ); } flock( $fh, LOCK_UN ); fclose( $fh );
        if ( $written !== strlen( $bytes ) ) { return new WP_Error( 'gdo_upload_chunk_write', __( 'The upload chunk was incomplete.', 'global-doctor-onboarding' ) ); }
        $wpdb->update( self::table( 'upload_sessions' ), array( 'received_bytes'=>absint($row->received_bytes)+$written, 'received_chunks'=>absint($row->received_chunks)+1, 'updated_at'=>self::now() ), array( 'upload_uuid'=>$row->upload_uuid, 'received_chunks'=>absint($row->received_chunks) ) );
        return array( 'received_chunks'=>absint($row->received_chunks)+1, 'received_bytes'=>absint($row->received_bytes)+$written );
    }

    public static function finalize_upload_session( $uuid ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'upload_sessions' ) . " WHERE upload_uuid=%s AND user_id=%d AND state='open' LIMIT 1", sanitize_text_field( $uuid ), get_current_user_id() ) );
        if ( ! $row || absint( $row->received_chunks ) !== absint( $row->expected_chunks ) || absint( $row->received_bytes ) !== absint( $row->expected_bytes ) ) { return new WP_Error( 'gdo_upload_incomplete', __( 'The resumable upload is incomplete.', 'global-doctor-onboarding' ) ); }
        $path = self::upload_temp_path( $row->temp_name );
        if ( ! is_file( $path ) || is_link( $path ) ) { return new WP_Error( 'gdo_upload_file_missing', __( 'The private resumable upload is unavailable.', 'global-doctor-onboarding' ) ); }
        $actual = hash_file( 'sha256', $path ); if ( $row->expected_sha256 && ! hash_equals( $row->expected_sha256, $actual ) ) { return new WP_Error( 'gdo_upload_hash_mismatch', __( 'The completed upload hash does not match.', 'global-doctor-onboarding' ) ); }
        $file = array( 'tmp_name'=>$path, 'error'=>UPLOAD_ERR_OK, 'size'=>filesize($path), 'name'=>$row->original_name );
        $allow = function( $is, $tmp ) use ( $path ) { return wp_normalize_path( $tmp ) === wp_normalize_path( $path ) ? true : $is; };
        add_filter( 'gdo_is_uploaded_file', $allow, 99, 2 );
        $result = GDO_Evidence::stage_upload( GDO_Application::get( $row->application_id ), $row->document_type, $file );
        remove_filter( 'gdo_is_uploaded_file', $allow, 99 );
        @unlink( $path );
        if ( is_wp_error( $result ) ) { return $result; }
        $wpdb->update( self::table( 'upload_sessions' ), array( 'state'=>'committed','updated_at'=>self::now() ), array( 'upload_uuid'=>$row->upload_uuid,'state'=>'open' ) );
        self::authenticity_assessment( $row->application_id, $result['id'] );
        self::ai_assistance( $row->application_id, $result['id'] );
        return $result;
    }

    public static function cleanup_upload_sessions() {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT upload_uuid,temp_name FROM ' . self::table( 'upload_sessions' ) . " WHERE state='open' AND expires_at<%s LIMIT 200", self::now() ) );
        foreach ( (array) $rows as $row ) { $path = self::upload_temp_path( $row->temp_name ); if ( $path && is_file( $path ) && ! is_link( $path ) ) { @unlink( $path ); } $wpdb->update( self::table('upload_sessions'), array('state'=>'expired','updated_at'=>self::now()), array('upload_uuid'=>$row->upload_uuid)); }
    }

    public static function transparency_snapshot( $days = 90 ) {
        global $wpdb;
        $since = gmdate( 'Y-m-d H:i:s', time() - max( 1, min( 365, absint( $days ) ) ) * DAY_IN_SECONDS );
        $apps = GDO_Schema::table( 'applications' );
        $appeals = GDO_Schema::table( 'appeals' );
        $quality = GDO_Schema::table( 'quality_samples' );
        $risk = GDO_Schema::table( 'risk_signals' );
        return array(
            'period_days'=>absint($days),
            'decisions'=>absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$apps} WHERE decision_at>=%s",$since))),
            'verified'=>absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$apps} WHERE decision_at>=%s AND state IN ('verified','approved')",$since))),
            'appeals_resolved'=>absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$appeals} WHERE resolved_at>=%s",$since))),
            'appeals_overturned'=>absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$appeals} WHERE resolved_at>=%s AND decision IN ('overturned','restored','approved')",$since))),
            'quality_samples'=>absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$quality} WHERE created_at>=%s",$since))),
            'fraud_signals'=>absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$risk} WHERE created_at>=%s AND signal_type IN ('credential_reuse_network','duplicate_identity','duplicate_document')",$since))),
            'privacy'=>'aggregate_only',
        );
    }

    public function rest_routes() {
        register_rest_route( self::REST_NAMESPACE, '/trust/command-center', array( 'methods'=>'GET', 'callback'=>array($this,'rest_command_center'), 'permission_callback'=>function(){ return is_user_logged_in(); } ) );
        register_rest_route( self::REST_NAMESPACE, '/trust/issuer', array( 'methods'=>'POST', 'callback'=>array($this,'rest_issuer'), 'permission_callback'=>array($this,'rest_manage_permission') ) );
        register_rest_route( self::REST_NAMESPACE, '/trust/jurisdiction', array( 'methods'=>'POST', 'callback'=>array($this,'rest_jurisdiction'), 'permission_callback'=>array($this,'rest_manage_permission') ) );
        register_rest_route( self::REST_NAMESPACE, '/trust/check/(?P<application_id>\d+)/(?P<evidence_id>\d+)', array( 'methods'=>'POST', 'callback'=>array($this,'rest_check'), 'permission_callback'=>array($this,'rest_reviewer_permission') ) );
        register_rest_route( self::REST_NAMESPACE, '/trust/upload/start', array( 'methods'=>'POST', 'callback'=>array($this,'rest_upload_start'), 'permission_callback'=>function(){ return is_user_logged_in(); } ) );
        register_rest_route( self::REST_NAMESPACE, '/trust/upload/(?P<uuid>[a-f0-9-]{36})/chunk/(?P<index>\d+)', array( 'methods'=>'POST', 'callback'=>array($this,'rest_upload_chunk'), 'permission_callback'=>function(){ return is_user_logged_in(); } ) );
        register_rest_route( self::REST_NAMESPACE, '/trust/upload/(?P<uuid>[a-f0-9-]{36})/finalize', array( 'methods'=>'POST', 'callback'=>array($this,'rest_upload_finalize'), 'permission_callback'=>function(){ return is_user_logged_in(); } ) );
        register_rest_route( self::REST_NAMESPACE, '/trust/viewing-room', array( 'methods'=>'POST', 'callback'=>array($this,'rest_viewing_room'), 'permission_callback'=>array($this,'rest_reviewer_permission') ) );
        register_rest_route( self::REST_NAMESPACE, '/public/passport/(?P<uuid>[a-f0-9-]{36})', array( 'methods'=>'GET', 'callback'=>array($this,'rest_public_passport'), 'permission_callback'=>'__return_true' ) );
        register_rest_route( self::REST_NAMESPACE, '/public/transparency', array( 'methods'=>'GET', 'callback'=>array($this,'rest_transparency'), 'permission_callback'=>'__return_true' ) );
    }

    public function rest_manage_permission() { return self::can_manage(); }
    public function rest_reviewer_permission() { $uid=get_current_user_id(); return $uid && GDO_Membership_Adapter::can('sabri_verify_doctors',$uid) && GDO_Membership_Adapter::recent_step_up($uid); }
    public function rest_command_center() { return rest_ensure_response( self::command_center( get_current_user_id() ) ); }
    public function rest_issuer( WP_REST_Request $r ) { $v=self::register_issuer($r->get_json_params()); return is_wp_error($v)?$v:rest_ensure_response($v); }
    public function rest_jurisdiction( WP_REST_Request $r ) { $p=(array)$r->get_json_params(); $v=self::save_jurisdiction_rule(isset($p['jurisdiction'])?$p['jurisdiction']:'',isset($p['version'])?$p['version']:'',isset($p['rules'])&&is_array($p['rules'])?$p['rules']:array(),isset($p['status'])?$p['status']:'draft'); return is_wp_error($v)?$v:rest_ensure_response(array('saved'=>(bool)$v)); }
    public function rest_check( WP_REST_Request $r ) { $app=absint($r['application_id']);$ev=absint($r['evidence_id']);$primary=self::primary_source_verify($app,$ev);$auth=self::authenticity_assessment($app,$ev);$ai=self::ai_assistance($app,$ev);return rest_ensure_response(array('primary_source'=>$primary,'authenticity'=>$auth,'ai_assist'=>$ai,'risk'=>self::risk_explanation($app),'dual_review'=>self::requires_dual_review($app))); }
    public function rest_upload_start( WP_REST_Request $r ) { $p=(array)$r->get_json_params();$v=self::create_upload_session(isset($p['application_id'])?$p['application_id']:0,isset($p['document_type'])?$p['document_type']:'',isset($p['name'])?$p['name']:'credential',isset($p['bytes'])?$p['bytes']:0,isset($p['chunks'])?$p['chunks']:0,isset($p['chunk_bytes'])?$p['chunk_bytes']:0,isset($p['sha256'])?$p['sha256']:'');return is_wp_error($v)?$v:rest_ensure_response($v); }
    public function rest_upload_chunk( WP_REST_Request $r ) { $bytes=$r->get_body();$v=self::append_upload_chunk($r['uuid'],$r['index'],$bytes);return is_wp_error($v)?$v:rest_ensure_response($v); }
    public function rest_upload_finalize( WP_REST_Request $r ) { $v=self::finalize_upload_session($r['uuid']);return is_wp_error($v)?$v:rest_ensure_response($v); }
    public function rest_viewing_room( WP_REST_Request $r ) { $p=(array)$r->get_json_params();$v=self::issue_viewing_room_grant(isset($p['application_id'])?$p['application_id']:0,isset($p['evidence_id'])?$p['evidence_id']:0,get_current_user_id(),isset($p['purpose'])?$p['purpose']:'credential_review');return is_wp_error($v)?$v:rest_ensure_response($v); }
    public function rest_public_passport( WP_REST_Request $r ) { $v=self::verify_passport_uuid($r['uuid']);return is_wp_error($v)?$v:rest_ensure_response($v); }
    public function rest_transparency( WP_REST_Request $r ) { return rest_ensure_response( self::transparency_snapshot( $r->get_param('days') ? absint($r->get_param('days')) : 90 ) ); }
}
