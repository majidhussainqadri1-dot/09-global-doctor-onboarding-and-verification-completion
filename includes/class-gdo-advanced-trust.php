<?php
defined( 'ABSPATH' ) || exit;

/**
 * File 09 Advanced Professional Trust layer.
 *
 * External issuer/AI/equivalency/translation/affiliation providers are facts or
 * reviewer-assistance adapters only. They never own a professional decision.
 */
final class GDO_Advanced_Trust {
    const CONTRACT_VERSION = '1.1.0';
    const SCHEMA_VERSION   = 1;
    const REST_NAMESPACE   = 'gdo/v1';
    const PASSPORT_TTL     = 31536000;
    const CHUNK_TTL        = 86400;
    const PUBLIC_WINDOW_DAYS = 90;
    const PROVIDER_PAYLOAD_MAX_BYTES = 32768;

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
            return self::verify_installation();
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
            KEY status_expires (status,expires_at),
            KEY application_status (application_id,status)
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
            KEY application_state (application_id,state),
            KEY expires_at (expires_at)
        ) {$engine};" );

        $verified = self::verify_installation();
        if ( is_wp_error( $verified ) ) { return $verified; }
        if ( ! update_option( 'gdo_advanced_trust_schema', self::SCHEMA_VERSION, false ) && absint( get_option( 'gdo_advanced_trust_schema', 0 ) ) < self::SCHEMA_VERSION ) {
            return new WP_Error( 'gdo_advanced_schema_version', __( 'Advanced Trust schema version could not be persisted.', 'global-doctor-onboarding' ) );
        }
        if ( ! wp_next_scheduled( 'gdo_trust_continuous_monitor' ) ) {
            $scheduled = wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'gdo_trust_continuous_monitor', array(), true );
            if ( is_wp_error( $scheduled ) || false === $scheduled || ! wp_next_scheduled( 'gdo_trust_continuous_monitor' ) ) {
                return new WP_Error( 'gdo_advanced_monitor_schedule', __( 'Advanced Trust continuous monitoring could not be scheduled safely.', 'global-doctor-onboarding' ) );
            }
        }
        GDO_Membership_Adapter::audit( 'doctor_advanced_trust_schema_ready', array( 'schema'=>self::SCHEMA_VERSION ) );
        return true;
    }

    public static function verify_installation() {
        global $wpdb;
        $required = array(
            'trusted_issuers'=>array( 'id','issuer_uuid','status' ),
            'jurisdiction_rules'=>array( 'id','jurisdiction','rule_version','status' ),
            'credential_checks'=>array( 'id','check_uuid','application_id','status' ),
            'professional_history'=>array( 'id','history_uuid','user_id','application_id' ),
            'reviewer_conflicts'=>array( 'id','reviewer_id','applicant_id','status' ),
            'verification_passports'=>array( 'id','passport_uuid','application_id','status' ),
            'monitor_state'=>array( 'application_id','monitor_status','next_check_at' ),
            'upload_sessions'=>array( 'upload_uuid','application_id','state','expires_at' ),
        );
        foreach ( $required as $name=>$columns ) {
            $table = self::table( $name );
            $wpdb->last_error = '';
            $actual = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( ! is_array( $actual ) || ! empty( $wpdb->last_error ) ) {
                return new WP_Error( 'gdo_advanced_schema_table', __( 'A required Advanced Trust table is unavailable after schema installation.', 'global-doctor-onboarding' ) );
            }
            foreach ( $columns as $column ) {
                if ( ! in_array( $column, $actual, true ) ) {
                    return new WP_Error( 'gdo_advanced_schema_column', __( 'A required Advanced Trust column is unavailable after schema installation.', 'global-doctor-onboarding' ) );
                }
            }
            $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ) );
            if ( ! $status || ! empty( $wpdb->last_error ) || 'innodb' !== strtolower( (string) $status->Engine ) ) {
                return new WP_Error( 'gdo_advanced_schema_engine', __( 'Advanced Trust transactional tables must use InnoDB.', 'global-doctor-onboarding' ) );
            }
        }
        return true;
    }

    private static function now() {
        return current_time( 'mysql', true );
    }

    private static function can_manage() {
        $uid = get_current_user_id();
        return $uid && GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $uid ) && GDO_Membership_Adapter::recent_step_up( $uid );
    }

    private static function normalize_jurisdiction( $value ) {
        return GDO_Policy::normalize_jurisdiction( $value );
    }

    private static function normalize_domain( $value ) {
        $value = trim( strtolower( (string) $value ) );
        if ( '' === $value ) {
            return '';
        }
        if ( false === strpos( $value, '://' ) ) {
            $value = 'https://' . $value;
        }
        $host = wp_parse_url( $value, PHP_URL_HOST );
        if ( ! is_string( $host ) || '' === $host ) {
            return '';
        }
        $host = preg_replace( '/^www\./', '', strtolower( $host ) );
        if ( function_exists( 'idn_to_ascii' ) ) {
            $ascii = idn_to_ascii( $host, 0, defined( 'INTL_IDNA_VARIANT_UTS46' ) ? INTL_IDNA_VARIANT_UTS46 : 0 );
            if ( is_string( $ascii ) && '' !== $ascii ) {
                $host = strtolower( $ascii );
            }
        }
        return strlen( $host ) <= 191 && preg_match( '/^[a-z0-9.-]+$/', $host ) ? $host : '';
    }

    private static function sanitize_provider_array( $value, $depth = 0 ) {
        if ( $depth > 4 ) {
            return array();
        }
        if ( ! is_array( $value ) ) {
            return array();
        }
        $out = array();
        foreach ( array_slice( $value, 0, 60, true ) as $key => $item ) {
            $key = substr( sanitize_key( $key ), 0, 80 );
            if ( '' === $key || preg_match( '/(?:secret|password|token|api[_-]?key|private[_-]?key|credential)/i', $key ) ) {
                continue;
            }
            if ( is_array( $item ) ) {
                $out[ $key ] = self::sanitize_provider_array( $item, $depth + 1 );
            } elseif ( is_bool( $item ) || is_int( $item ) || is_float( $item ) || null === $item ) {
                $out[ $key ] = $item;
            } else {
                $out[ $key ] = substr( sanitize_textarea_field( (string) $item ), 0, 2000 );
            }
        }
        $json = wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( is_string( $json ) && strlen( $json ) > self::PROVIDER_PAYLOAD_MAX_BYTES ) {
            return array( 'truncated'=>true, 'reason'=>'provider_payload_exceeded_safe_limit' );
        }
        return $out;
    }

    public static function current_verification_expiry( $app ) {
        if ( ! $app || empty( $app->verified_until ) ) {
            return 0;
        }
        $expires = strtotime( $app->verified_until . ' UTC' );
        return $expires && $expires > time() ? $expires : 0;
    }

    private static function public_matrix_for_app( $app ) {
        $matrix = array(
            'identity'=>false, 'qualification'=>false, 'institution'=>false,
            'registration'=>false, 'license'=>false, 'current_status'=>false,
            'last_reverified'=>null, 'next_review'=>null, 'jurisdiction'=>'', 'data_available'=>true,
            'scope_status'=>array(
                'identity'=>'not_verified', 'qualification'=>'not_verified', 'institution'=>'not_applicable',
                'registration'=>'not_verified', 'license'=>'not_verified', 'current_status'=>'not_verified',
            ),
        );
        if ( ! $app ) {
            return $matrix;
        }
        $verified_expiry = self::current_verification_expiry( $app );
        $current = GDO_State::public_verified( $app->state ) && (bool) $verified_expiry && 'accepted' === sanitize_key( $app->claim_status );
        $identity = GDO_Membership_Adapter::identity_assurance_current( $app->user_id );
        $matrix['identity'] = (bool) $identity;
        $matrix['current_status'] = (bool) ( $identity && $current );
        $matrix['jurisdiction'] = sanitize_text_field( $app->jurisdiction );
        $next_review_ts = $app->verified_until ? strtotime( $app->verified_until . ' UTC' ) : false;
        $matrix['next_review'] = $next_review_ts ? gmdate( 'c', $next_review_ts ) : null;
        $matrix['scope_status']['identity'] = $identity ? 'verified' : 'not_verified';
        $matrix['scope_status']['current_status'] = $matrix['current_status'] ? 'verified' : 'not_verified';

        $required = array_keys( GDO_Policy::evidence_types( $app->jurisdiction, $app->application_type ) );
        if ( in_array( 'institution', $required, true ) || in_array( 'affiliation', $required, true ) || in_array( 'employment', $required, true ) ) {
            $matrix['scope_status']['institution'] = 'not_verified';
        }
        $evidence_rows = GDO_Evidence::records_checked( $app->id, true );
        if ( is_wp_error( $evidence_rows ) ) {
            // Public trust projection must understate rather than preserve an
            // unverifiable professional-current state during DB uncertainty.
            $matrix['current_status'] = false;
            $matrix['scope_status']['current_status'] = 'not_verified';
            $matrix['data_available'] = false;
            return $matrix;
        }
        foreach ( $evidence_rows as $evidence ) {
            $status = sanitize_key( $evidence->status );
            $accepted = 'accepted' === $status && ! empty( $evidence->reviewer_id ) && ! empty( $evidence->reviewed_at );
            if ( $accepted && ! empty( $evidence->expires_at ) ) {
                $expires_at = strtotime( $evidence->expires_at . ' UTC' );
                $accepted = $expires_at && $expires_at > time();
            }
            if ( $accepted && ! empty( $evidence->validity_until ) ) {
                $validity_until = strtotime( $evidence->validity_until . ' 23:59:59 UTC' );
                $accepted = $validity_until && $validity_until > time();
            }
            $pending = in_array( $status, array( 'pending_review','more_information' ), true );
            if ( in_array( $evidence->document_type, array( 'qualification','degree','diploma' ), true ) ) {
                $matrix['qualification'] = $matrix['qualification'] || $accepted;
                $matrix['scope_status']['qualification'] = $accepted ? 'verified' : ( $pending ? 'pending' : $matrix['scope_status']['qualification'] );
            }
            $document_type = sanitize_key( $evidence->document_type );
            if ( 'license' === $document_type ) {
                $matrix['license'] = $matrix['license'] || $accepted;
                $matrix['scope_status']['license'] = $accepted ? 'verified' : ( $pending ? 'pending' : $matrix['scope_status']['license'] );
            }
            if ( in_array( $document_type, array( 'registration','professional_registration' ), true ) ) {
                $matrix['registration'] = $matrix['registration'] || $accepted;
                $matrix['scope_status']['registration'] = $accepted ? 'verified' : ( $pending ? 'pending' : $matrix['scope_status']['registration'] );
            }
            if ( in_array( $evidence->document_type, array( 'institution','affiliation','employment' ), true ) ) {
                $matrix['institution'] = $matrix['institution'] || $accepted;
                $matrix['scope_status']['institution'] = $accepted ? 'verified' : ( $pending ? 'pending' : $matrix['scope_status']['institution'] );
            }
            if ( $evidence->reviewed_at && ( ! $matrix['last_reverified'] || strtotime( $evidence->reviewed_at . ' UTC' ) > strtotime( $matrix['last_reverified'] ) ) ) {
                $matrix['last_reverified'] = gmdate( 'c', strtotime( $evidence->reviewed_at . ' UTC' ) );
            }
        }
        return $matrix;
    }

    public static function verification_matrix( $user_id ) {
        global $wpdb;
        $wpdb->last_error = '';
        $app = GDO_Application::verification_record_for_user( absint( $user_id ) );
        if ( ! empty( $wpdb->last_error ) ) {
            $matrix = self::public_matrix_for_app( null );
            $matrix['data_available'] = false;
            return $matrix;
        }
        return self::public_matrix_for_app( $app );
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
        $contract['public_scope_semantics'] = array( 'verified','pending','not_verified','not_applicable' );
        return $contract;
    }
    public static function register_issuer( array $data ) {
        if ( ! GDO_Operations::mutation_allowed() ) {
            return new WP_Error( 'gdo_issuer_runtime_not_ready', __( 'Professional trust registry changes are temporarily unavailable.', 'global-doctor-onboarding' ) );
        }
        if ( ! self::can_manage() ) {
            return new WP_Error( 'gdo_trust_forbidden', __( 'Professional trust registry changes require a current privileged step-up.', 'global-doctor-onboarding' ) );
        }
        global $wpdb;
        $name = substr( sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' ), 0, 191 );
        $type = substr( sanitize_key( isset( $data['issuer_type'] ) ? $data['issuer_type'] : '' ), 0, 40 );
        $jurisdiction = self::normalize_jurisdiction( isset( $data['jurisdiction'] ) ? $data['jurisdiction'] : '' );
        $domain = self::normalize_domain( isset( $data['canonical_domain'] ) ? $data['canonical_domain'] : '' );
        if ( '' === $name || '' === $type || ( ! empty( $data['canonical_domain'] ) && '' === $domain ) ) {
            return new WP_Error( 'gdo_issuer_invalid', __( 'Issuer name, type, jurisdiction, or domain is invalid.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $duplicate_raw = $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . self::table( 'trusted_issuers' ) . " WHERE name=%s AND jurisdiction=%s AND status<>'retired' LIMIT 1",
            $name, $jurisdiction
        ) );
        if ( ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_issuer_duplicate_query', __( 'Issuer uniqueness could not be verified safely.', 'global-doctor-onboarding' ) );
        }
        if ( absint( $duplicate_raw ) ) {
            return new WP_Error( 'gdo_issuer_duplicate', __( 'An active/proposed issuer with this name and jurisdiction already exists.', 'global-doctor-onboarding' ) );
        }
        $now = self::now();
        $row = array(
            'issuer_uuid'=>wp_generate_uuid4(), 'name'=>$name, 'issuer_type'=>$type, 'jurisdiction'=>$jurisdiction,
            'canonical_domain'=>$domain, 'adapter_key'=>substr( sanitize_key( isset( $data['adapter_key'] ) ? $data['adapter_key'] : '' ), 0, 80 ),
            'status'=>'proposed', 'assurance_level'=>'unassessed',
            'metadata_json'=>wp_json_encode( self::sanitize_provider_array( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : array() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'created_by'=>get_current_user_id(), 'reviewed_by'=>null, 'created_at'=>$now, 'updated_at'=>$now,
        );
        if ( 1 !== $wpdb->insert( self::table( 'trusted_issuers' ), $row ) ) {
            return new WP_Error( 'gdo_issuer_store_failed', __( 'The trusted issuer could not be recorded.', 'global-doctor-onboarding' ) );
        }
        GDO_Membership_Adapter::audit( 'doctor_trusted_issuer_proposed', array( 'issuer_uuid'=>$row['issuer_uuid'], 'actor_id'=>get_current_user_id() ) );
        return $row;
    }
    public static function review_issuer( $issuer_uuid, $status, $assurance_level = 'verified_source' ) {
        if ( ! GDO_Operations::mutation_allowed() ) {
            return new WP_Error( 'gdo_issuer_review_runtime_not_ready', __( 'Professional issuer review is temporarily unavailable.', 'global-doctor-onboarding' ) );
        }
        if ( ! self::can_manage() ) {
            return new WP_Error( 'gdo_trust_forbidden', __( 'Issuer review requires current privileged authorization and step-up.', 'global-doctor-onboarding' ) );
        }
        global $wpdb;
        $issuer_uuid = sanitize_text_field( $issuer_uuid );
        $status = sanitize_key( $status );
        if ( ! in_array( $status, array( 'verified','suspended','retired' ), true ) ) {
            return new WP_Error( 'gdo_issuer_review_status', __( 'Issuer review status is invalid.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $issuer = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'trusted_issuers' ) . ' WHERE issuer_uuid=%s LIMIT 1', $issuer_uuid ) );
        if ( null === $issuer && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_issuer_review_query', __( 'Issuer state could not be read safely for review.', 'global-doctor-onboarding' ) );
        }
        $actor = get_current_user_id();
        if ( ! $issuer ) {
            return new WP_Error( 'gdo_issuer_review_not_found', __( 'The trusted issuer could not be found for review.', 'global-doctor-onboarding' ) );
        }
        if ( 'verified' === $status && absint( $issuer->created_by ) === $actor ) {
            return new WP_Error( 'gdo_issuer_review_separation', __( 'A second authorized reviewer must verify a newly proposed issuer.', 'global-doctor-onboarding' ) );
        }
        if ( sanitize_key( $issuer->status ) === $status ) {
            if ( absint( $issuer->reviewed_by ) === $actor ) { return true; }
            return new WP_Error( 'gdo_issuer_review_no_transition', __( 'Issuer review requires a real lifecycle transition; an existing review cannot be silently reassigned.', 'global-doctor-onboarding' ) );
        }
        $updated = $wpdb->update(
            self::table( 'trusted_issuers' ),
            array( 'status'=>$status, 'assurance_level'=>substr( sanitize_key( $assurance_level ), 0, 30 ), 'reviewed_by'=>$actor, 'updated_at'=>self::now() ),
            array( 'id'=>absint( $issuer->id ), 'status'=>sanitize_key( $issuer->status ) ),
            array( '%s','%s','%d','%s' ), array( '%d','%s' )
        );
        if ( false === $updated ) {
            return new WP_Error( 'gdo_issuer_review_store', __( 'Issuer review could not be stored.', 'global-doctor-onboarding' ) );
        }
        if ( 1 !== $updated ) {
            return new WP_Error( 'gdo_issuer_review_conflict', __( 'Issuer state changed during review. Reload the current state before retrying.', 'global-doctor-onboarding' ) );
        }
        GDO_Membership_Adapter::audit( 'doctor_trusted_issuer_reviewed', array( 'issuer_uuid'=>$issuer_uuid, 'status'=>$status, 'actor_id'=>$actor ) );
        return true;
    }
    public static function trusted_issuer( $name, $jurisdiction = '' ) {
        global $wpdb;
        $name = substr( sanitize_text_field( $name ), 0, 191 );
        $jurisdiction = self::normalize_jurisdiction( $jurisdiction );
        $sql = 'SELECT * FROM ' . self::table( 'trusted_issuers' ) . " WHERE name=%s AND status='verified'";
        $args = array( $name );
        if ( $jurisdiction ) { $sql .= ' AND jurisdiction=%s'; $args[] = $jurisdiction; }
        $sql .= ' ORDER BY updated_at DESC LIMIT 1';
        $wpdb->last_error = '';
        $row = $wpdb->get_row( $wpdb->prepare( $sql, $args ) );
        if ( null === $row && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_trusted_issuer_query', __( 'Trusted issuer state could not be verified safely.', 'global-doctor-onboarding' ) );
        }
        return $row;
    }

    public static function save_jurisdiction_rule( $jurisdiction, $version, array $rules, $status = 'draft', $effective_from = '', $effective_until = '' ) {
        if ( ! GDO_Operations::mutation_allowed() ) {
            return new WP_Error( 'gdo_jurisdiction_runtime_not_ready', __( 'Jurisdiction-rule changes are temporarily unavailable.', 'global-doctor-onboarding' ) );
        }
        if ( ! self::can_manage() ) {
            return new WP_Error( 'gdo_trust_forbidden', __( 'Jurisdiction rules require privileged step-up.', 'global-doctor-onboarding' ) );
        }
        global $wpdb;
        $jurisdiction = self::normalize_jurisdiction( $jurisdiction );
        $version = substr( sanitize_text_field( $version ), 0, 40 );
        $status = sanitize_key( $status );
        if ( ! $jurisdiction || ! $version || ! in_array( $status, array( 'draft','approved','retired' ), true ) ) {
            return new WP_Error( 'gdo_jurisdiction_rule_invalid', __( 'Jurisdiction rule data are invalid.', 'global-doctor-onboarding' ) );
        }
        $from = '' !== trim( (string) $effective_from ) ? GDO_Policy::normalize_date( $effective_from ) : '';
        $until = '' !== trim( (string) $effective_until ) ? GDO_Policy::normalize_date( $effective_until ) : '';
        if ( is_wp_error( $from ) || is_wp_error( $until ) || ( $from && $until && $from > $until ) ) {
            return new WP_Error( 'gdo_jurisdiction_rule_dates', __( 'Jurisdiction rule effective dates are invalid.', 'global-doctor-onboarding' ) );
        }
        $rules = self::sanitize_provider_array( $rules );
        if ( ! $rules ) {
            return new WP_Error( 'gdo_jurisdiction_rule_empty', __( 'Jurisdiction rules cannot be empty.', 'global-doctor-onboarding' ) );
        }
        $now = self::now();
        $wpdb->last_error = '';
        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'jurisdiction_rules' ) . ' WHERE jurisdiction=%s AND rule_version=%s LIMIT 1', $jurisdiction, $version ) );
        if ( null === $existing && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_jurisdiction_rule_query', __( 'Jurisdiction-rule state could not be read safely.', 'global-doctor-onboarding' ) ); }
        $data = array(
            'status'=>$status,
            'rules_json'=>wp_json_encode( $rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'effective_from'=>$from ?: null, 'effective_until'=>$until ?: null,
            'approved_by'=>'approved' === $status ? get_current_user_id() : null,
            'updated_at'=>$now,
        );
        if ( $existing ) {
            $ok = $wpdb->update( self::table( 'jurisdiction_rules' ), $data, array( 'id'=>absint( $existing->id ) ) );
        } else {
            $data['jurisdiction'] = $jurisdiction;
            $data['rule_version'] = $version;
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = $now;
            $ok = $wpdb->insert( self::table( 'jurisdiction_rules' ), $data );
        }
        if ( false === $ok || ( ! $existing && 1 !== $ok ) ) {
            return new WP_Error( 'gdo_jurisdiction_rule_store', __( 'Jurisdiction rule could not be stored.', 'global-doctor-onboarding' ) );
        }
        GDO_Membership_Adapter::audit( 'doctor_jurisdiction_rule_saved', array( 'jurisdiction'=>$jurisdiction, 'version'=>$version, 'status'=>$status, 'actor_id'=>get_current_user_id() ) );
        return true;
    }

    public static function jurisdiction_rule( $jurisdiction ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table( 'jurisdiction_rules' ) . " WHERE jurisdiction=%s AND status='approved' AND (effective_from IS NULL OR effective_from<=UTC_DATE()) AND (effective_until IS NULL OR effective_until>=UTC_DATE()) ORDER BY id DESC LIMIT 1",
            self::normalize_jurisdiction( $jurisdiction )
        ) );
    }
    private static function has_check( $application_id, $evidence_id, $type ) {
        global $wpdb;
        $sql = 'SELECT id FROM ' . self::table( 'credential_checks' ) . ' WHERE application_id=%d AND check_type=%s';
        $args = array( absint( $application_id ), sanitize_key( $type ) );
        if ( $evidence_id ) { $sql .= ' AND evidence_id=%d'; $args[] = absint( $evidence_id ); }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $wpdb->last_error = '';
        $raw = $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
        if ( null === $raw && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_trust_check_query', __( 'Professional trust-check state could not be read safely.', 'global-doctor-onboarding' ) );
        }
        return absint( $raw ) > 0;
    }

    private static function safe_external_reference( $reference ) {
        $reference = trim( sanitize_text_field( (string) $reference ) );
        if ( '' === $reference ) {
            return '';
        }
        // Provider references are correlation hints, never secret containers.
        // URLs and structurally unusual values are stored only as digests so
        // tokens/query strings cannot become durable credential-check metadata.
        if ( filter_var( $reference, FILTER_VALIDATE_URL ) || ! preg_match( '/^[A-Za-z0-9._:-]{1,191}$/', $reference ) ) {
            return 'refsha256:' . hash( 'sha256', $reference );
        }
        return $reference;
    }

    private static function record_check( $application_id, $evidence_id, $type, $provider, $status, $confidence, array $facts, array $explanation = array(), $external_reference = '', $expires_at = null ) {
        global $wpdb;
        $facts = self::sanitize_provider_array( $facts );
        $explanation = self::sanitize_provider_array( $explanation );
        $expires_at = $expires_at ? sanitize_text_field( $expires_at ) : null;
        $row = array(
            'check_uuid'=>wp_generate_uuid4(), 'application_id'=>absint( $application_id ), 'evidence_id'=>$evidence_id ? absint( $evidence_id ) : null,
            'check_type'=>substr( sanitize_key( $type ), 0, 60 ), 'provider_key'=>substr( sanitize_key( $provider ), 0, 80 ), 'status'=>substr( sanitize_key( $status ), 0, 30 ),
            'confidence'=>max( 0, min( 1, (float) $confidence ) ), 'reviewer_required'=>1,
            'facts_json'=>wp_json_encode( $facts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'explanation_json'=>wp_json_encode( $explanation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'external_reference'=>self::safe_external_reference( $external_reference ), 'checked_at'=>self::now(), 'expires_at'=>$expires_at,
            'created_at'=>self::now(), 'updated_at'=>self::now(),
        );
        if ( 1 !== $wpdb->insert( self::table( 'credential_checks' ), $row ) ) {
            return new WP_Error( 'gdo_trust_check_store_failed', __( 'The professional trust check could not be recorded.', 'global-doctor-onboarding' ) );
        }
        GDO_Membership_Adapter::audit( 'doctor_credential_check_recorded', array( 'application_id'=>absint($application_id), 'evidence_id'=>absint($evidence_id), 'check_type'=>$row['check_type'], 'status'=>$row['status'], 'check_uuid'=>$row['check_uuid'] ) );
        return $row;
    }

    private static function application_record( $application_id ) {
        global $wpdb;
        $wpdb->last_error = '';
        $app = GDO_Application::get( absint( $application_id ) );
        if ( ! $app && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_trust_application_query', __( 'Professional application state could not be read safely.', 'global-doctor-onboarding' ), array( 'status'=>503 ) );
        }
        return $app;
    }

    private static function evidence_record( $application_id, $evidence_id ) {
        $records = GDO_Evidence::records_checked( absint( $application_id ), true );
        if ( is_wp_error( $records ) ) {
            return $records;
        }
        foreach ( $records as $item ) {
            if ( absint( $item->id ) === absint( $evidence_id ) ) {
                return $item;
            }
        }
        return null;
    }

    public static function primary_source_verify( $application_id, $evidence_id ) {
        $app = self::application_record( $application_id );
        if ( is_wp_error( $app ) ) { return $app; }
        $record = $app ? self::evidence_record( $app->id, $evidence_id ) : null;
        if ( is_wp_error( $record ) ) { return $record; }
        if ( ! $app || ! $record ) {
            return new WP_Error( 'gdo_evidence_missing', __( 'Application or evidence was not found.', 'global-doctor-onboarding' ) );
        }
        if ( ! GDO_Rate_Limiter::hit( 'primary-source-check:' . absint( $app->id ), 20, HOUR_IN_SECONDS ) ) {
            return new WP_Error( 'gdo_primary_source_rate', __( 'Too many primary-source checks are in progress. Try again later.', 'global-doctor-onboarding' ) );
        }
        $profile = json_decode( $app->profile_json, true );
        $profile = is_array( $profile ) ? $profile : array();
        $issuer_name = isset( $profile['licensing_authority'] ) ? $profile['licensing_authority'] : '';
        $issuer = self::trusted_issuer( $issuer_name, $app->jurisdiction );
        if ( is_wp_error( $issuer ) ) { return $issuer; }
        if ( ! $issuer ) {
            return self::record_check( $app->id, $record->id, 'primary_source', 'none', 'manual_review_required', 0, array( 'issuer_name'=>$issuer_name ), array( 'reason'=>'trusted_issuer_not_available' ) );
        }
        $request = array(
            'application_id'=>absint( $app->id ), 'evidence_id'=>absint( $record->id ), 'document_type'=>$record->document_type,
            'jurisdiction'=>$app->jurisdiction, 'issuer_uuid'=>$issuer->issuer_uuid,
            'license_number'=>isset( $profile['license_number'] ) ? substr( sanitize_text_field( $profile['license_number'] ), 0, 120 ) : '',
        );
        try {
            $request = (array) apply_filters( 'gdo_primary_source_request_minimized', $request, absint( $app->id ), absint( $record->id ), sanitize_text_field( $issuer->issuer_uuid ) );
        } catch ( Throwable $e ) {
            unset( $e );
            return self::record_check( $app->id, $record->id, 'primary_source', $issuer->adapter_key ? $issuer->adapter_key : 'configured', 'provider_unavailable', 0, array( 'issuer_uuid'=>$issuer->issuer_uuid ), array( 'reason'=>'request_filter_exception' ) );
        }
        // Extension filters may narrow the request, but may never widen it with
        // profile/evidence/private storage fields. Re-allowlist after filtering.
        $request = array_intersect_key( self::sanitize_provider_array( $request ), array_flip( array( 'application_id','evidence_id','document_type','jurisdiction','issuer_uuid','license_number' ) ) );
        $issuer_context = array(
            'issuer_uuid'=>sanitize_text_field( $issuer->issuer_uuid ),
            'adapter_key'=>substr( sanitize_key( $issuer->adapter_key ), 0, 80 ),
            'jurisdiction'=>self::normalize_jurisdiction( $issuer->jurisdiction ),
            'canonical_domain'=>self::normalize_domain( $issuer->canonical_domain ),
        );
        try {
            $result = apply_filters( 'gdo_primary_source_verification', null, $request, $issuer_context );
        } catch ( Throwable $e ) {
            unset( $e );
            return self::record_check( $app->id, $record->id, 'primary_source', $issuer->adapter_key ? $issuer->adapter_key : 'configured', 'provider_unavailable', 0, array( 'issuer_uuid'=>$issuer->issuer_uuid ), array( 'reason'=>'provider_exception' ) );
        }
        if ( ! is_array( $result ) || empty( $result['status'] ) ) {
            return self::record_check( $app->id, $record->id, 'primary_source', $issuer->adapter_key ? $issuer->adapter_key : 'unconfigured', 'provider_unavailable', 0, array( 'issuer_uuid'=>$issuer->issuer_uuid ), array( 'reason'=>'provider_adapter_unavailable' ) );
        }
        $allowed = array( 'matched','not_matched','revoked','expired','pending','provider_error','provider_unavailable','timeout','malformed_response','manual_review_required' );
        $status = sanitize_key( $result['status'] );
        if ( ! in_array( $status, $allowed, true ) ) { $status = 'provider_error'; }
        return self::record_check(
            $app->id, $record->id, 'primary_source', $issuer->adapter_key ? $issuer->adapter_key : 'configured', $status,
            isset( $result['confidence'] ) ? $result['confidence'] : 0,
            isset( $result['facts'] ) && is_array( $result['facts'] ) ? $result['facts'] : array(),
            isset( $result['explanation'] ) && is_array( $result['explanation'] ) ? $result['explanation'] : array(),
            isset( $result['reference'] ) ? $result['reference'] : '', isset( $result['expires_at'] ) ? $result['expires_at'] : null
        );
    }
    public static function authenticity_assessment( $application_id, $evidence_id ) {
        $app = self::application_record( $application_id );
        if ( is_wp_error( $app ) ) { return $app; }
        $record = $app ? self::evidence_record( $app->id, $evidence_id ) : null;
        if ( is_wp_error( $record ) ) { return $record; }
        if ( ! $app || ! $record ) { return new WP_Error( 'gdo_evidence_missing', __( 'Application or evidence was not found.', 'global-doctor-onboarding' ) ); }
        global $wpdb;
        $wpdb->last_error = '';
        $shared_raw = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT application_id) FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE source_sha256=%s AND deleted_at IS NULL', $record->source_sha256 ) );
        if ( null === $shared_raw || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_authenticity_reuse_query', __( 'Credential reuse could not be checked safely.', 'global-doctor-onboarding' ) );
        }
        $shared = absint( $shared_raw );
        $signals = array( 'malware_clean'=>'clean' === $record->malware_status, 'hash_present'=>64 === strlen((string)$record->source_sha256), 'metadata_removed'=>(bool)$record->metadata_removed, 'shared_across_applications'=>$shared, 'validity_until'=>$record->validity_until );
        $confidence = 0.35 + ( $signals['malware_clean'] ? 0.2 : 0 ) + ( $signals['hash_present'] ? 0.1 : 0 ) + ( $signals['metadata_removed'] ? 0.05 : 0 );
        if ( $shared > 1 ) { $confidence = max( 0.05, $confidence - 0.35 ); }
        return self::record_check( $app->id, $record->id, 'authenticity', 'native', 'manual_review_required', $confidence, $signals, array( 'rule'=>'local technical checks never establish professional authenticity by themselves' ) );
    }

    public static function equivalency_assessment( $application_id ) {
        $app = self::application_record( $application_id );
        if ( is_wp_error( $app ) ) { return $app; }
        if ( ! $app ) { return new WP_Error( 'gdo_app_missing', __( 'Application not found.', 'global-doctor-onboarding' ) ); }
        $profile = json_decode( $app->profile_json, true ); $profile = is_array($profile) ? $profile : array();
        $request = array( 'qualification'=>isset($profile['qualification'])?substr(sanitize_text_field($profile['qualification']),0,240):'', 'source_jurisdiction'=>isset($profile['license_jurisdiction'])?self::normalize_jurisdiction($profile['license_jurisdiction']):'', 'target_jurisdiction'=>self::normalize_jurisdiction($app->jurisdiction) );
        try {
            $result = apply_filters( 'gdo_credential_equivalency_assessment', null, $request );
        } catch ( Throwable $e ) {
            unset( $e );
            $result = array( 'status'=>'manual_review_required', 'facts'=>$request, 'explanation'=>array( 'reason'=>'provider_exception' ) );
        }
        if ( ! is_array($result) ) { $result=array('status'=>'manual_review_required','facts'=>$request,'explanation'=>array('reason'=>'no_equivalency_adapter')); }
        return self::record_check( $app->id, 0, 'equivalency', isset($result['provider'])?$result['provider']:'unconfigured', isset($result['status'])?$result['status']:'manual_review_required', isset($result['confidence'])?$result['confidence']:0, isset($result['facts'])&&is_array($result['facts'])?$result['facts']:$request, isset($result['explanation'])&&is_array($result['explanation'])?$result['explanation']:array('legal_license_grant'=>false) );
    }

    public static function verify_affiliation( $application_id, $institution ) {
        $app=self::application_record($application_id);
        if(is_wp_error($app)){return $app;}
        if(!$app){return new WP_Error('gdo_app_missing',__('Application not found.','global-doctor-onboarding'));}
        $request=array('institution'=>substr(sanitize_text_field($institution),0,240),'application_id'=>absint($app->id),'jurisdiction'=>$app->jurisdiction);
        try {
            $result=apply_filters('gdo_institutional_affiliation_verification',null,$request);
        } catch ( Throwable $e ) {
            unset( $e );
            $result=array('status'=>'manual_review_required','facts'=>$request,'explanation'=>array('reason'=>'provider_exception'));
        }
        if(!is_array($result)){$result=array('status'=>'manual_review_required','facts'=>$request,'explanation'=>array('reason'=>'no_affiliation_adapter'));}
        return self::record_check($app->id,0,'affiliation',isset($result['provider'])?$result['provider']:'unconfigured',isset($result['status'])?$result['status']:'manual_review_required',isset($result['confidence'])?$result['confidence']:0,isset($result['facts'])&&is_array($result['facts'])?$result['facts']:$request,isset($result['explanation'])&&is_array($result['explanation'])?$result['explanation']:array());
    }

    public static function translation_assistance( $application_id, $evidence_id, $target_locale ) {
        $app=self::application_record($application_id); if(is_wp_error($app)){return $app;} $record=$app?self::evidence_record($app->id,$evidence_id):null;
        if(is_wp_error($record)){return $record;}
        if(!$app||!$record){return new WP_Error('gdo_evidence_missing',__('Application or evidence was not found.','global-doctor-onboarding'));}
        $locale=function_exists('sanitize_locale_name')?sanitize_locale_name($target_locale):preg_replace('/[^A-Za-z0-9_-]/','',(string)$target_locale);
        $payload=array('application_id'=>absint($application_id),'evidence_id'=>absint($evidence_id),'target_locale'=>substr((string)$locale,0,20));
        try {
            $result=apply_filters('gdo_credential_translation_assistance',null,$payload);
        } catch ( Throwable $e ) {
            unset( $e );
            $result=array('status'=>'unavailable','translation'=>'','provider'=>'exception');
        }
        if(!is_array($result)){$result=array('status'=>'unavailable','translation'=>'','provider'=>'unconfigured');}
        $facts=array('target_locale'=>$payload['target_locale'],'translation'=>substr(sanitize_textarea_field(isset($result['translation'])?$result['translation']:''),0,12000),'original_remains_authoritative'=>true);
        return self::record_check($application_id,$evidence_id,'translation',isset($result['provider'])?$result['provider']:'unconfigured',isset($result['status'])?$result['status']:'unavailable',isset($result['confidence'])?$result['confidence']:0,$facts,array('decision_authority'=>'human_reviewer'));
    }

    public static function ai_assistance( $application_id, $evidence_id ) {
        $app=self::application_record($application_id); if(is_wp_error($app)){return $app;} $record=$app?self::evidence_record($app->id,$evidence_id):null;
        if(is_wp_error($record)){return $record;}
        if(!$app||!$record){return new WP_Error('gdo_evidence_missing',__('Application or evidence was not found.','global-doctor-onboarding'));}
        if(!GDO_Rate_Limiter::hit('ai-evidence-assist:'.absint($application_id),20,HOUR_IN_SECONDS)){return new WP_Error('gdo_ai_rate',__('Too many AI evidence-assistance requests.','global-doctor-onboarding'));}
        $payload=array('application_id'=>absint($application_id),'evidence_id'=>absint($evidence_id),'document_type'=>$record->document_type,'allowed_tasks'=>array('classification','field_extraction','expiry_detection','mismatch_highlighting','duplicate_clues'));
        try {
            $result=apply_filters('gdo_ai_evidence_assistance',null,$payload);
        } catch ( Throwable $e ) {
            unset( $e );
            $result=array('status'=>'unavailable','provider'=>'exception','hints'=>array());
        }
        if(!is_array($result)){$result=array('status'=>'unavailable','provider'=>'unconfigured','hints'=>array());}
        unset($result['decision'],$result['approve'],$result['reject'],$result['professional_status'],$result['clinical_authorization']);
        $hints=isset($result['hints'])&&is_array($result['hints'])?array_slice(self::sanitize_provider_array($result['hints']),0,50):array();
        return self::record_check($application_id,$evidence_id,'ai_assist',isset($result['provider'])?$result['provider']:'unconfigured',isset($result['status'])?$result['status']:'unavailable',isset($result['confidence'])?$result['confidence']:0,array('hints'=>$hints),array('automated_decision_forbidden'=>true,'human_final_decision_required'=>true));
    }
    public static function fraud_ring_scan( $application_id ) {
        global $wpdb;
        $app = self::application_record( $application_id );
        if ( is_wp_error( $app ) ) { return $app; }
        if ( ! $app ) { return new WP_Error( 'gdo_app_missing', __( 'Application not found.', 'global-doctor-onboarding' ) ); }
        $wpdb->last_error = '';
        $records = GDO_Evidence::records( $app->id, true );
        if ( null === $records || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_fraud_ring_query', __( 'Credential reuse evidence could not be read safely.', 'global-doctor-onboarding' ) );
        }
        $links = array();
        foreach ( $records as $record ) {
            if ( ! $record->source_sha256 ) { continue; }
            $wpdb->last_error = '';
            $ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT application_id FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE source_sha256=%s AND application_id<>%d AND deleted_at IS NULL LIMIT 20', $record->source_sha256, $app->id ) );
            if ( null === $ids || ! empty( $wpdb->last_error ) ) {
                return new WP_Error( 'gdo_fraud_ring_query', __( 'Credential reuse relationships could not be checked safely.', 'global-doctor-onboarding' ) );
            }
            foreach ( $ids as $id ) { $links[] = absint( $id ); }
        }
        $links = array_values( array_unique( array_filter( $links ) ) );
        foreach ( $links as $related_id ) {
            $digest = hash( 'sha256', 'application:' . $related_id );
            $wpdb->last_error = '';
            $exists_raw = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'risk_signals' ) . " WHERE application_id=%d AND signal_type='credential_reuse_network' AND related_digest=%s AND status='open' LIMIT 1", $app->id, $digest ) );
            if ( null === $exists_raw && ! empty( $wpdb->last_error ) ) {
                return new WP_Error( 'gdo_fraud_ring_signal_query', __( 'Existing fraud-ring signal state could not be checked safely.', 'global-doctor-onboarding' ) );
            }
            if ( absint( $exists_raw ) ) { continue; }
            $ok = $wpdb->insert( GDO_Schema::table( 'risk_signals' ), array( 'application_id'=>$app->id, 'signal_type'=>'credential_reuse_network', 'severity'=>'high', 'status'=>'open', 'related_digest'=>$digest, 'created_at'=>self::now(), 'updated_at'=>self::now() ) );
            if ( 1 !== $ok ) {
                return new WP_Error( 'gdo_fraud_ring_signal_store', __( 'A credential-reuse risk signal could not be stored safely.', 'global-doctor-onboarding' ) );
            }
        }
        return $links;
    }
    public static function risk_explanation( $application_id ) {
        global $wpdb;
        $wpdb->last_error = '';
        $signals = $wpdb->get_results( $wpdb->prepare( 'SELECT signal_type,severity,status,created_at FROM ' . GDO_Schema::table( 'risk_signals' ) . ' WHERE application_id=%d ORDER BY id DESC LIMIT 100', absint( $application_id ) ), ARRAY_A );
        if ( null === $signals || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_risk_explanation_query', __( 'Risk signals could not be read safely.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $checks = $wpdb->get_results( $wpdb->prepare( 'SELECT check_type,status,confidence,checked_at FROM ' . self::table( 'credential_checks' ) . ' WHERE application_id=%d ORDER BY id DESC LIMIT 100', absint( $application_id ) ), ARRAY_A );
        if ( null === $checks || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_risk_explanation_query', __( 'Credential checks could not be read safely for risk explanation.', 'global-doctor-onboarding' ) );
        }
        $reasons = array();
        foreach ( $signals as $signal ) { $reasons[] = array( 'source'=>'risk_signal', 'type'=>$signal['signal_type'], 'severity'=>$signal['severity'], 'status'=>$signal['status'] ); }
        foreach ( $checks as $check ) { $reasons[] = array( 'source'=>'credential_check', 'type'=>$check['check_type'], 'status'=>$check['status'], 'confidence'=>(float)$check['confidence'] ); }
        return array( 'application_id'=>absint($application_id), 'reasons'=>$reasons, 'opaque_rejection_forbidden'=>true, 'human_review_required'=>true );
    }

    public static function declare_conflict( $reviewer_id, $applicant_id, $application_id, $type, $reason ) {
        if ( ! GDO_Operations::mutation_allowed() ) { return new WP_Error( 'gdo_conflict_runtime_not_ready', __( 'Reviewer-conflict changes are temporarily unavailable.', 'global-doctor-onboarding' ) ); }
        global $wpdb;
        $reviewer_id=absint($reviewer_id); $applicant_id=absint($applicant_id); $application_id=absint($application_id); $actor=get_current_user_id();
        $type=substr(sanitize_key($type),0,50); $reason=sanitize_textarea_field($reason);
        if(!$reviewer_id||!$applicant_id||!$type||strlen($reason)<8){return new WP_Error('gdo_conflict_invalid',__('A reviewer, applicant, conflict type, and meaningful reason are required.','global-doctor-onboarding'));}
        if($actor!==$reviewer_id && !self::can_manage()){return new WP_Error('gdo_conflict_forbidden',__('Only the reviewer or a privileged verification manager may declare this conflict.','global-doctor-onboarding'));}
        if($application_id){$app=self::application_record($application_id);if(is_wp_error($app)){return $app;}if(!$app||absint($app->user_id)!==$applicant_id){return new WP_Error('gdo_conflict_application',__('The conflict application does not match the applicant.','global-doctor-onboarding'));}}
        $wpdb->last_error='';$exists_raw=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('reviewer_conflicts')." WHERE reviewer_id=%d AND applicant_id=%d AND conflict_type=%s AND status='active' AND (application_id IS NULL OR application_id=0 OR application_id=%d) LIMIT 1",$reviewer_id,$applicant_id,$type,$application_id));
        if(null===$exists_raw&&!empty($wpdb->last_error)){return new WP_Error('gdo_conflict_query',__('Reviewer conflict state could not be verified safely.','global-doctor-onboarding'));}
        if(absint($exists_raw)){return true;}
        $ok=$wpdb->insert(self::table('reviewer_conflicts'),array('reviewer_id'=>$reviewer_id,'applicant_id'=>$applicant_id,'application_id'=>$application_id?:null,'conflict_type'=>$type,'status'=>'active','reason_hash'=>hash('sha256',$reason),'declared_by'=>$actor?:$reviewer_id,'declared_at'=>self::now()));
        if(1!==$ok){return new WP_Error('gdo_conflict_store',__('The reviewer conflict could not be stored.','global-doctor-onboarding'));}
        GDO_Membership_Adapter::audit('doctor_reviewer_conflict_declared',array('application_id'=>$application_id,'reviewer_id'=>$reviewer_id,'applicant_id'=>$applicant_id,'conflict_type'=>$type,'actor_id'=>$actor?:$reviewer_id));
        return true;
    }

    public static function resolve_conflict( $conflict_id ) {
        if ( ! GDO_Operations::mutation_allowed() ) { return new WP_Error( 'gdo_conflict_runtime_not_ready', __( 'Reviewer-conflict changes are temporarily unavailable.', 'global-doctor-onboarding' ) ); }
        if(!self::can_manage()){return new WP_Error('gdo_conflict_forbidden',__('Conflict resolution requires privileged authorization and step-up.','global-doctor-onboarding'));}
        global $wpdb; $id=absint($conflict_id); $actor=get_current_user_id();
        $updated=$wpdb->update(self::table('reviewer_conflicts'),array('status'=>'resolved','resolved_by'=>$actor,'resolved_at'=>self::now()),array('id'=>$id,'status'=>'active'),array('%s','%d','%s'),array('%d','%s'));
        if(false===$updated){return new WP_Error('gdo_conflict_resolve_store',__('The reviewer conflict resolution could not be stored safely.','global-doctor-onboarding'));}
        if(1!==$updated){return new WP_Error('gdo_conflict_resolve',__('The active reviewer conflict could not be resolved because its state changed.','global-doctor-onboarding'));}
        GDO_Membership_Adapter::audit('doctor_reviewer_conflict_resolved',array('conflict_id'=>$id,'actor_id'=>$actor)); return true;
    }

    public static function has_conflict( $reviewer_id, $applicant_id, $application_id = 0 ) {
        global $wpdb; if(absint($reviewer_id)===absint($applicant_id)){return true;}
        $wpdb->last_error='';
        $raw_count=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::table('reviewer_conflicts')." WHERE reviewer_id=%d AND applicant_id=%d AND status='active' AND (application_id IS NULL OR application_id=0 OR application_id=%d)",absint($reviewer_id),absint($applicant_id),absint($application_id)));
        // Conflict uncertainty must narrow authorization, never widen it. COUNT(*)
        // always returns a row on success, so NULL/DB error is a fail-closed conflict.
        if(null===$raw_count||!empty($wpdb->last_error)){return true;}
        $detected=absint($raw_count)>0; $filtered=(bool)apply_filters('gdo_reviewer_conflict_detected',$detected,absint($reviewer_id),absint($applicant_id),absint($application_id)); return $detected || $filtered;
    }

    public static function reviewer_conflict_filter( $allowed, $reviewer_id, $applicant_id, $application_id ) {
        return $allowed && ! self::has_conflict( $reviewer_id, $applicant_id, $application_id );
    }

    public static function requires_dual_review( $application_id ) {
        global $wpdb; $app=GDO_Application::get($application_id); if(!$app){return true;}
        $wpdb->last_error='';
        $raw_high=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.GDO_Schema::table('risk_signals')." WHERE application_id=%d AND status='open' AND severity IN ('high','critical')",$app->id));
        if(null===$raw_high||!empty($wpdb->last_error)){return true;}
        $high=absint($raw_high);
        $profile=json_decode($app->profile_json,true); $cross=false;
        if(is_array($profile)&&!empty($profile['license_jurisdiction'])&&$app->jurisdiction){$cross=self::normalize_jurisdiction($profile['license_jurisdiction'])!==self::normalize_jurisdiction($app->jurisdiction);}
        $required=$high>0||$cross||in_array($app->state,array('appeal_pending','suspended','revoked'),true);
        $filtered=(bool)apply_filters('gdo_requires_dual_review',$required,$app);
        return $required || $filtered;
    }

    public static function smart_reviewer_candidates( $application_id, $limit = 10 ) {
        global $wpdb; $app=GDO_Application::get($application_id); if(!$app){return array();}
        $wpdb->last_error='';
        $profiles=$wpdb->get_results('SELECT * FROM '.GDO_Schema::table('reviewer_profiles')." WHERE status='active' ORDER BY updated_at DESC LIMIT 200"); $out=array();
        if(null===$profiles||!empty($wpdb->last_error)){GDO_Membership_Adapter::audit('gdo_reviewer_candidates_query_failed',array('application_id'=>absint($application_id)));return array();}
        foreach((array)$profiles as $profile){$uid=absint($profile->user_id); if(!GDO_Membership_Adapter::reviewer_scope_allows($uid,$app->user_id,$app->id)||self::has_conflict($uid,$app->user_id,$app->id)){continue;}
            $wpdb->last_error='';
            $raw_open=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.GDO_Schema::table('applications')." WHERE assigned_reviewer_id=%d AND state IN ('submitted','under_review','more_information')",$uid));
            if(null===$raw_open||!empty($wpdb->last_error)){GDO_Membership_Adapter::audit('gdo_reviewer_workload_query_failed',array('application_id'=>absint($application_id),'reviewer_id'=>$uid));continue;}
            $open=absint($raw_open);
            $max=max(1,absint($profile->max_open_cases)); if($open>=$max){continue;}
            $langs=json_decode($profile->languages_json,true);$jur=json_decode($profile->jurisdictions_json,true);$score=100-min(60,$open*3);if(in_array($app->jurisdiction,(array)$jur,true)){$score+=20;}if(in_array($app->preferred_language,(array)$langs,true)){$score+=10;}$out[]=array('reviewer_id'=>$uid,'score'=>$score,'open_cases'=>$open,'max_open_cases'=>$max);
        }
        usort($out,function($a,$b){return $a['score']===$b['score']?$a['open_cases']-$b['open_cases']:$b['score']-$a['score'];}); return array_slice($out,0,max(1,min(50,absint($limit))));
    }
    public static function reviewer_calibration( $reviewer_id ) {
        global $wpdb;
        $wpdb->last_error = '';
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT outcome,status FROM ' . GDO_Schema::table( 'quality_samples' ) . ' WHERE reviewer_id=%d ORDER BY id DESC LIMIT 500', absint( $reviewer_id ) ), ARRAY_A );
        if ( null === $rows || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_reviewer_calibration_query', __( 'Reviewer quality samples could not be read safely.', 'global-doctor-onboarding' ) );
        }
        $total = count( $rows ); $complete = 0; $confirmed = 0; $overturned = 0;
        foreach ( $rows as $row ) {
            if ( 'completed' !== sanitize_key( $row['status'] ) ) { continue; }
            ++$complete;
            if ( in_array( $row['outcome'], array( 'confirmed','agree' ), true ) ) { ++$confirmed; }
            if ( in_array( $row['outcome'], array( 'overturned','disagree' ), true ) ) { ++$overturned; }
        }
        return array( 'reviewer_id'=>absint($reviewer_id), 'sampled'=>$total, 'completed'=>$complete, 'agreement_rate'=>$complete?round($confirmed/$complete,4):null, 'overturn_rate'=>$complete?round($overturned/$complete,4):null );
    }

    public static function add_history( $user_id, $application_id, $event_type, array $event, $public_safe = false ) {
        global $wpdb; $allowed_public=array('verification_passport_issued','verification_passport_revoked','professional_decision','professional_reinstated','professional_expired');
        $event_type=substr(sanitize_key($event_type),0,60); $public_safe=$public_safe && in_array($event_type,$allowed_public,true);
        $event=self::sanitize_provider_array($event); $json=wp_json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $ok=$wpdb->insert(self::table('professional_history'),array('history_uuid'=>wp_generate_uuid4(),'user_id'=>absint($user_id),'application_id'=>$application_id?absint($application_id):null,'event_type'=>$event_type,'public_safe'=>$public_safe?1:0,'event_json'=>$json,'source_hash'=>hash('sha256',$json),'occurred_at'=>self::now(),'created_at'=>self::now()));
        return 1===$ok ? true : new WP_Error('gdo_history_store',__('Professional history could not be recorded.','global-doctor-onboarding'));
    }
    public static function public_history( $user_id, $limit = 50 ) {
        global $wpdb;
        $wpdb->last_error = '';
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT event_type,event_json,occurred_at FROM ' . self::table( 'professional_history' ) . ' WHERE user_id=%d AND public_safe=1 ORDER BY occurred_at DESC LIMIT %d', absint( $user_id ), max(1,min(100,absint($limit))) ), ARRAY_A );
        if ( null === $rows || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_public_history_query', __( 'Public professional history could not be read safely.', 'global-doctor-onboarding' ) );
        }
        $out = array();
        foreach ( $rows as $row ) {
            $event = json_decode( $row['event_json'], true );
            $out[] = array( 'event_type'=>sanitize_key($row['event_type']), 'event'=>self::sanitize_provider_array(is_array($event)?$event:array()), 'occurred_at'=>$row['occurred_at'] );
        }
        return $out;
    }

    private static function passport_key() {
        return defined('GDO_CLAIM_SIGNING_KEY') ? hash('sha256','passport|'.(string)GDO_CLAIM_SIGNING_KEY,true) : '';
    }

    private static function base64url_decode( $value ) {
        $value=strtr((string)$value,'-_','+/'); $pad=strlen($value)%4; if($pad){$value.=str_repeat('=',4-$pad);} return base64_decode($value,true);
    }
    public static function active_passport_for_application( $application_id ) {
        global $wpdb;
        $wpdb->last_error = '';
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'verification_passports' ) . " WHERE application_id=%d AND status='active' AND expires_at>%s ORDER BY version DESC LIMIT 1", absint( $application_id ), self::now() ) );
        if ( null === $row && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_active_passport_query', __( 'Active professional-passport state could not be read safely.', 'global-doctor-onboarding' ) );
        }
        return $row;
    }

    public static function issue_passport( $application_id ) {
        global $wpdb; $application_id=absint($application_id); if(!self::passport_key()){return new WP_Error('gdo_passport_key',__('Professional passport signing is unavailable.','global-doctor-onboarding'));}
        if(false===$wpdb->query('START TRANSACTION')){return new WP_Error('gdo_passport_transaction',__('A professional passport transaction could not be started safely.','global-doctor-onboarding'));}
        $wpdb->last_error='';
        $app=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GDO_Schema::table('applications').' WHERE id=%d FOR UPDATE',$application_id));
        if(null===$app&&!empty($wpdb->last_error)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_passport_application_query',__('Professional application state could not be locked safely for passport issuance.','global-doctor-onboarding'));}
        $verified_expiry=self::current_verification_expiry($app);$approved_snapshot=$app?GDO_Application::stored_approved_snapshot($app):array();if(!$app||!$approved_snapshot||empty($approved_snapshot['captured_at'])||!GDO_State::public_verified($app->state)||!GDO_Membership_Adapter::identity_assurance_current($app->user_id)||!$verified_expiry){$wpdb->query('ROLLBACK');return new WP_Error('gdo_passport_not_eligible',__('A current verified application, intact approved snapshot, explicit future validity date, and current identity assurance are required for a professional passport.','global-doctor-onboarding'));}
        $wpdb->last_error='';
        $raw_version=$wpdb->get_var($wpdb->prepare('SELECT MAX(version) FROM '.self::table('verification_passports').' WHERE user_id=%d FOR UPDATE',$app->user_id));
        if(!empty($wpdb->last_error)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_passport_version',__('Professional passport version state could not be verified safely.','global-doctor-onboarding'));}
        $version=absint($raw_version)+1;
        $uuid=wp_generate_uuid4();$issued=time();$exp=min($issued+self::PASSPORT_TTL,$verified_expiry);$scope=self::public_matrix_for_app($app);
        $payload=array('passport_uuid'=>$uuid,'version'=>$version,'scope'=>$scope,'iat'=>$issued,'exp'=>$exp);
        $body=rtrim(strtr(base64_encode(wp_json_encode($payload)),'+/','-_'),'=');$sig=hash_hmac('sha256',$body,self::passport_key());$token=$body.'.'.$sig;$now=self::now();
        $revoked=$wpdb->update(self::table('verification_passports'),array('status'=>'revoked','revoked_at'=>$now,'revoke_reason'=>'superseded'),array('user_id'=>$app->user_id,'status'=>'active'),array('%s','%s','%s'),array('%d','%s'));
        if(false===$revoked){$wpdb->query('ROLLBACK');return new WP_Error('gdo_passport_supersede',__('Existing professional passports could not be superseded safely.','global-doctor-onboarding'));}
        $inserted=$wpdb->insert(self::table('verification_passports'),array('passport_uuid'=>$uuid,'user_id'=>$app->user_id,'application_id'=>$app->id,'version'=>$version,'status'=>'active','scope_json'=>wp_json_encode($scope),'token_hash'=>hash('sha256',$token),'issued_at'=>gmdate('Y-m-d H:i:s',$issued),'expires_at'=>gmdate('Y-m-d H:i:s',$exp)));
        if(1!==$inserted){$wpdb->query('ROLLBACK');return new WP_Error('gdo_passport_store',__('The professional verification passport could not be issued.','global-doctor-onboarding'));}
        $history=self::add_history($app->user_id,$app->id,'verification_passport_issued',array('version'=>$version,'expires_at'=>gmdate('c',$exp)),true);
        if(is_wp_error($history)){$wpdb->query('ROLLBACK');return $history;}
        if(false===$wpdb->query('COMMIT')){$wpdb->query('ROLLBACK');return new WP_Error('gdo_passport_store',__('The professional verification passport could not be committed.','global-doctor-onboarding'));}
        GDO_Membership_Adapter::audit('doctor_verification_passport_issued',array('application_id'=>$app->id,'passport_uuid'=>$uuid,'version'=>$version));
        return array('token'=>$token,'passport_uuid'=>$uuid,'verification_url'=>rest_url(self::REST_NAMESPACE.'/public/passport/'.$uuid),'qr_payload'=>rest_url(self::REST_NAMESPACE.'/public/passport/'.$uuid));
    }
    public static function ensure_passport( $application_id ) {
        $existing = self::active_passport_for_application( $application_id );
        if ( is_wp_error( $existing ) ) { return $existing; }
        if ( $existing ) {
            $verified = self::verify_passport_uuid( $existing->passport_uuid );
            if ( is_wp_error( $verified ) ) { return $verified; }
            return array( 'token'=>null, 'passport_uuid'=>$existing->passport_uuid, 'verification_url'=>rest_url(self::REST_NAMESPACE.'/public/passport/'.$existing->passport_uuid), 'qr_payload'=>rest_url(self::REST_NAMESPACE.'/public/passport/'.$existing->passport_uuid), 'reused'=>true );
        }
        return self::issue_passport( $application_id );
    }

    public static function revoke_passports_for_application( $application_id, $reason = 'professional_status_changed' ) {
        global $wpdb; $app=GDO_Application::get($application_id); if(!$app){return false;} $now=self::now();
        $updated=$wpdb->query($wpdb->prepare('UPDATE '.self::table('verification_passports')." SET status='revoked',revoked_at=%s,revoke_reason=%s WHERE application_id=%d AND status='active'",$now,substr(sanitize_key($reason),0,100),absint($application_id)));
        if(false===$updated){return false;} if($updated>0){self::add_history($app->user_id,$app->id,'verification_passport_revoked',array('reason'=>substr(sanitize_key($reason),0,100)),true);GDO_Membership_Adapter::audit('doctor_verification_passport_revoked',array('application_id'=>$app->id,'reason'=>substr(sanitize_key($reason),0,100)));} return true;
    }

    public static function verify_passport_uuid( $uuid ) {
        global $wpdb; $wpdb->last_error=''; $row=$wpdb->get_row($wpdb->prepare('SELECT passport_uuid,user_id,application_id,version,status,scope_json,issued_at,expires_at FROM '.self::table('verification_passports').' WHERE passport_uuid=%s LIMIT 1',sanitize_text_field($uuid)),ARRAY_A);
        if(null===$row&&!empty($wpdb->last_error)){return new WP_Error('gdo_passport_lookup_query',__('Professional passport state is temporarily unavailable.','global-doctor-onboarding'),array('status'=>503));}
        if(!$row||'active'!==$row['status']||strtotime($row['expires_at'].' UTC')<=time()){return new WP_Error('gdo_passport_inactive',__('This professional verification passport is not active.','global-doctor-onboarding'),array('status'=>404));}
        $wpdb->last_error='';$app=GDO_Application::get($row['application_id']);
        if(!$app&&!empty($wpdb->last_error)){return new WP_Error('gdo_passport_lookup_query',__('Professional verification state is temporarily unavailable.','global-doctor-onboarding'),array('status'=>503));}
        $verified_expiry=self::current_verification_expiry($app);$approved_snapshot=$app?GDO_Application::stored_approved_snapshot($app):array();$passport_issued=!empty($row['issued_at'])?strtotime($row['issued_at'].' UTC'):0;$snapshot_captured=!empty($approved_snapshot['captured_at'])?strtotime($approved_snapshot['captured_at'].' UTC'):0;if(!$app||!$approved_snapshot||!$passport_issued||!$snapshot_captured||$passport_issued<$snapshot_captured||absint($app->user_id)!==absint($row['user_id'])||!GDO_State::public_verified($app->state)||!GDO_Membership_Adapter::identity_assurance_current($row['user_id'])||!$verified_expiry){
            // Verification is read-only. Lifecycle/reconciliation callbacks own
            // derivative revocation; a token/public read must never mutate state.
            return new WP_Error('gdo_passport_inactive',__('This professional verification passport is not active.','global-doctor-onboarding'),array('status'=>404));
        }
        return array('passport_uuid'=>$row['passport_uuid'],'version'=>absint($row['version']),'verification'=>self::verification_matrix($row['user_id']),'issued_at'=>$row['issued_at'],'expires_at'=>$row['expires_at'],'cure_guarantee'=>false,'clinical_authorization'=>false,'professional_scope_only'=>true);
    }
    public static function verify_passport_token( $token ) {
        global $wpdb;
        $parts = explode( '.', (string) $token, 2 );
        if ( 2 !== count( $parts ) || ! self::passport_key() ) { return new WP_Error( 'gdo_passport_token_invalid', __( 'Professional passport token is invalid.', 'global-doctor-onboarding' ) ); }
        $expected = hash_hmac( 'sha256', $parts[0], self::passport_key() );
        if ( ! hash_equals( $expected, $parts[1] ) ) { return new WP_Error( 'gdo_passport_token_invalid', __( 'Professional passport token is invalid.', 'global-doctor-onboarding' ) ); }
        $decoded = self::base64url_decode( $parts[0] );
        $payload = is_string( $decoded ) ? json_decode( $decoded, true ) : null;
        if ( ! is_array( $payload ) || empty( $payload['passport_uuid'] ) || empty( $payload['exp'] ) || absint( $payload['exp'] ) <= time() ) { return new WP_Error( 'gdo_passport_token_invalid', __( 'Professional passport token is invalid or expired.', 'global-doctor-onboarding' ) ); }
        $wpdb->last_error = '';
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT token_hash,version FROM ' . self::table( 'verification_passports' ) . ' WHERE passport_uuid=%s LIMIT 1', sanitize_text_field( $payload['passport_uuid'] ) ), ARRAY_A );
        if ( null === $row && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_passport_token_query', __( 'Professional passport token state could not be read safely.', 'global-doctor-onboarding' ) );
        }
        if ( ! $row || ! hash_equals( (string)$row['token_hash'], hash('sha256',(string)$token) ) || absint($row['version']) !== absint($payload['version']) ) { return new WP_Error( 'gdo_passport_token_invalid', __( 'Professional passport token is invalid.', 'global-doctor-onboarding' ) ); }
        return self::verify_passport_uuid( $payload['passport_uuid'] );
    }
    public static function schedule_reverification( $application_id, $reason = 'periodic', $when = 0, $preserve_failures = true ) {
        global $wpdb;
        $application_id = absint( $application_id );
        if ( ! $application_id || ! GDO_Application::get( $application_id ) ) { return false; }
        $when = $when ? absint( $when ) : time() + DAY_IN_SECONDS;
        $wpdb->last_error = '';
        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT failure_count FROM ' . self::table( 'monitor_state' ) . ' WHERE application_id=%d', $application_id ) );
        if ( null === $existing && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_reverification_query', __( 'Professional reverification state could not be read safely.', 'global-doctor-onboarding' ) );
        }
        $data = array( 'application_id'=>$application_id, 'monitor_status'=>'scheduled', 'trigger_reason'=>substr(sanitize_key($reason),0,80), 'next_check_at'=>gmdate('Y-m-d H:i:s',$when), 'failure_count'=>$preserve_failures&&$existing?absint($existing->failure_count):0, 'updated_at'=>self::now() );
        return false !== $wpdb->replace( self::table( 'monitor_state' ), $data );
    }

    public static function event_reverification( $application_id, $event_type = 'status_change', $context = array() ) {
        return self::schedule_reverification($application_id,$event_type,time()+HOUR_IN_SECONDS,true);
    }

    public static function continuous_monitor() {
        global $wpdb; self::maybe_install(); $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('monitor_state')." WHERE monitor_status='scheduled' AND next_check_at<=%s ORDER BY next_check_at ASC LIMIT 50",self::now()));
        foreach((array)$rows as $row){$app=GDO_Application::get($row->application_id);if(!$app){$wpdb->delete(self::table('monitor_state'),array('application_id'=>$row->application_id));continue;}
            $result='no_license_evidence';$provider_failure=false;$adverse=false;
            foreach(GDO_Evidence::records($app->id,true) as $evidence){if(!in_array($evidence->document_type,array('license','registration','professional_registration'),true)){continue;}$check=self::primary_source_verify($app->id,$evidence->id);$result=is_wp_error($check)?$check->get_error_code():$check['status'];if(in_array($result,array('provider_unavailable','provider_error','gdo_primary_source_rate','gdo_trust_check_store_failed'),true)){$provider_failure=true;}if(in_array($result,array('revoked','expired','not_matched'),true)){$adverse=true;do_action('gdo_continuous_verification_adverse_result',$app->id,$result,$check);}}
            $failures=$provider_failure?min(20,absint($row->failure_count)+1):0;$delay=$provider_failure?min(7*DAY_IN_SECONDS,HOUR_IN_SECONDS*(int)pow(2,min(7,$failures))):30*DAY_IN_SECONDS;$status=$provider_failure?'degraded':'scheduled';
            $wpdb->update(self::table('monitor_state'),array('monitor_status'=>$status,'last_checked_at'=>self::now(),'last_result'=>substr(sanitize_key($result),0,30),'failure_count'=>$failures,'next_check_at'=>gmdate('Y-m-d H:i:s',time()+$delay),'updated_at'=>self::now()),array('application_id'=>$app->id));
            if($provider_failure){GDO_Membership_Adapter::audit('doctor_continuous_verification_provider_degraded',array('application_id'=>$app->id,'failure_count'=>$failures,'last_result'=>$result));}
            if(!$adverse&&'no_license_evidence'===$result){GDO_Membership_Adapter::audit('doctor_continuous_verification_license_missing',array('application_id'=>$app->id));}
        }
        self::cleanup_upload_sessions();
    }
    public static function application_submitted( $application_id ) {
        $has = self::has_check( $application_id, 0, 'equivalency' );
        if ( is_wp_error( $has ) ) { GDO_Membership_Adapter::audit( 'doctor_submission_equivalency_failed', array( 'application_id'=>absint($application_id), 'error'=>$has->get_error_code() ) ); return $has; }
        if ( ! $has ) {
            $assessment = self::equivalency_assessment( $application_id );
            if ( is_wp_error( $assessment ) ) { GDO_Membership_Adapter::audit( 'doctor_submission_equivalency_failed', array( 'application_id'=>absint($application_id), 'error'=>$assessment->get_error_code() ) ); return $assessment; }
        }
        $fraud = self::fraud_ring_scan( $application_id );
        if ( is_wp_error( $fraud ) ) { GDO_Membership_Adapter::audit( 'doctor_submission_fraud_scan_failed', array( 'application_id'=>absint($application_id), 'error'=>$fraud->get_error_code() ) ); return $fraud; }
        $scheduled = self::schedule_reverification( $application_id, 'submission', time()+DAY_IN_SECONDS, true );
        if ( is_wp_error( $scheduled ) || ! $scheduled ) { GDO_Membership_Adapter::audit( 'doctor_submission_reverification_schedule_failed', array( 'application_id'=>absint($application_id), 'error'=>is_wp_error($scheduled)?$scheduled->get_error_code():'store_failed' ) ); }
        return $scheduled;
    }

    public static function application_decided( $application_id, $decision ) {
        $app=GDO_Application::get($application_id); if(!$app){return;}$decision=sanitize_key($decision);
        $public_event=in_array($decision,array('verified','reinstated'),true)?'professional_decision':(in_array($decision,array('expired'),true)?'professional_expired':'professional_decision');
        self::add_history($app->user_id,$app->id,$public_event,array('decision'=>$decision,'verified_until'=>$app->verified_until),true);
        if(in_array($decision,array('verified','reinstated'),true)){self::ensure_passport($app->id);self::schedule_reverification($app->id,'verified',time()+30*DAY_IN_SECONDS,false);}elseif(in_array($decision,array('suspended','revoked','expired','rejected','withdrawn'),true)){self::revoke_passports_for_application($app->id,$decision);self::schedule_reverification($app->id,$decision,time()+HOUR_IN_SECONDS,true);}
    }
    public static function command_center( $user_id ) {
        global $wpdb;
        $wpdb->last_error = '';
        $app = GDO_Application::latest_for_user( absint( $user_id ) );
        if ( ! $app && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_command_center_application_query', __( 'Professional application state could not be read safely.', 'global-doctor-onboarding' ) );
        }
        if ( ! $app ) { return array( 'application'=>null, 'next_action'=>'start_application' ); }
        $complete = GDO_Application::completeness( $app );
        if ( ! empty( $complete['query_error'] ) ) {
            return new WP_Error( 'gdo_command_center_completeness_query', __( 'Professional application completeness could not be verified safely.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $checks = $wpdb->get_results( $wpdb->prepare( 'SELECT check_type,status,checked_at,expires_at FROM ' . self::table( 'credential_checks' ) . ' WHERE application_id=%d ORDER BY id DESC LIMIT 50', $app->id ), ARRAY_A );
        if ( null === $checks || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_command_center_query', __( 'Professional verification checks could not be read safely.', 'global-doctor-onboarding' ) );
        }
        $pending_checks = 0; $degraded_checks = 0;
        foreach ( $checks as $check ) {
            $check_status = sanitize_key( isset( $check['status'] ) ? $check['status'] : '' );
            if ( in_array( $check_status, array( 'pending','manual_review_required','issuer_unverified' ), true ) ) { ++$pending_checks; }
            if ( in_array( $check_status, array( 'provider_unavailable','provider_error','timeout','malformed_response' ), true ) ) { ++$degraded_checks; }
        }
        $next = 'await_review';
        if ( in_array( $app->state, array( 'draft','more_information' ), true ) ) { $next = count($complete['missing_fields']) || count($complete['missing_evidence']) ? 'complete_missing_items' : 'submit'; }
        elseif ( in_array( $app->state, array( 'verified','reinstated' ), true ) ) { $next='monitor_credentials'; }
        elseif ( 'renewal_due' === $app->state || 'expired' === $app->state ) { $next='renew'; }
        elseif ( in_array( $app->state, array( 'rejected','suspended','revoked' ), true ) ) { $next='appeal_or_correct'; }
        return array( 'application'=>array('id'=>absint($app->id),'state'=>$app->state,'version'=>absint($app->version),'verified_until'=>$app->verified_until,'more_info_due_at'=>$app->more_info_due_at), 'completion'=>array('missing_fields'=>$complete['missing_fields'],'missing_evidence'=>$complete['missing_evidence']), 'verification_matrix'=>self::verification_matrix($user_id), 'check_summary'=>array('pending'=>$pending_checks,'degraded'=>$degraded_checks), 'checks'=>$checks, 'next_action'=>$next );
    }
    public static function command_center_shortcode() {
        if ( ! is_user_logged_in() ) { return '<p>' . esc_html__( 'Log in to view your verification command center.', 'global-doctor-onboarding' ) . '</p>'; }
        $data = self::command_center( get_current_user_id() );
        if ( is_wp_error( $data ) ) { return '<p>' . esc_html__( 'Professional verification status is temporarily unavailable.', 'global-doctor-onboarding' ) . '</p>'; }
        $scope_labels=array('identity'=>__('Identity','global-doctor-onboarding'),'qualification'=>__('Qualification','global-doctor-onboarding'),'institution'=>__('Institution','global-doctor-onboarding'),'registration'=>__('Registration','global-doctor-onboarding'),'license'=>__('License','global-doctor-onboarding'),'current_status'=>__('Current status','global-doctor-onboarding'));
        ob_start(); ?><section class="gdo-trust-command-center" aria-labelledby="gdo-trust-title"><h2 id="gdo-trust-title"><?php esc_html_e('Professional Verification Command Center','global-doctor-onboarding');?></h2><?php if(empty($data['application'])):?><p><?php esc_html_e('No professional application exists yet.','global-doctor-onboarding');?></p><?php else:?><p><strong><?php echo esc_html($data['application']['state']);?></strong> · <?php echo esc_html($data['next_action']);?></p><p><?php printf(esc_html__('%1$d missing fields · %2$d missing evidence items','global-doctor-onboarding'),count($data['completion']['missing_fields']),count($data['completion']['missing_evidence']));?></p><?php if(!empty($data['application']['more_info_due_at'])):?><p><?php printf(esc_html__('More-information deadline: %s UTC','global-doctor-onboarding'),esc_html($data['application']['more_info_due_at']));?></p><?php endif;?><?php if(!empty($data['application']['verified_until'])):?><p><?php printf(esc_html__('Verified until: %s UTC','global-doctor-onboarding'),esc_html($data['application']['verified_until']));?></p><?php endif;?><p><?php printf(esc_html__('%1$d pending professional checks · %2$d provider checks degraded','global-doctor-onboarding'),absint($data['check_summary']['pending']),absint($data['check_summary']['degraded']));?></p><?php if(absint($data['check_summary']['degraded'])):?><p role="status"><?php esc_html_e('One or more verification providers are temporarily degraded. Human review remains authoritative.','global-doctor-onboarding');?></p><?php endif;?><h3><?php esc_html_e('Verification scope','global-doctor-onboarding');?></h3><ul><?php foreach($scope_labels as $scope_key=>$scope_label):$scope_state=isset($data['verification_matrix']['scope_status'][$scope_key])?$data['verification_matrix']['scope_status'][$scope_key]:'not_verified';?><li><?php echo esc_html($scope_label);?>: <strong><?php echo esc_html($scope_state);?></strong></li><?php endforeach;?></ul><?php endif;?></section><?php return ob_get_clean();
    }

    public static function public_card_shortcode( $atts ) {
        $atts=shortcode_atts(array('user_id'=>0),$atts,'gdo_public_verification_card');$uid=absint($atts['user_id']);if(!$uid){return '';}$matrix=self::verification_matrix($uid);if(empty($matrix['data_available'])){return '<section class="gdo-public-verification-card"><h3>'.esc_html__('Professional Verification','global-doctor-onboarding').'</h3><p role="status">'.esc_html__('Professional verification status is temporarily unavailable.','global-doctor-onboarding').'</p></section>';}$labels=array('identity'=>__('Identity','global-doctor-onboarding'),'qualification'=>__('Qualification','global-doctor-onboarding'),'institution'=>__('Institution','global-doctor-onboarding'),'registration'=>__('Registration','global-doctor-onboarding'),'license'=>__('License','global-doctor-onboarding'),'current_status'=>__('Current status','global-doctor-onboarding'));$status_labels=array('verified'=>__('Verified','global-doctor-onboarding'),'pending'=>__('Pending','global-doctor-onboarding'),'not_verified'=>__('Not verified','global-doctor-onboarding'),'not_applicable'=>__('Not applicable','global-doctor-onboarding'));ob_start();?><section class="gdo-public-verification-card"><h3><?php esc_html_e('Professional Verification','global-doctor-onboarding');?></h3><ul><?php foreach($labels as $key=>$label):$state=isset($matrix['scope_status'][$key])?$matrix['scope_status'][$key]:'not_verified';?><li><?php echo esc_html($label);?>: <strong><?php echo esc_html(isset($status_labels[$state])?$status_labels[$state]:$status_labels['not_verified']);?></strong></li><?php endforeach;?></ul><?php if($matrix['last_reverified']):?><p><?php echo esc_html($matrix['last_reverified']);?></p><?php endif;?><p><?php esc_html_e('Professional verification does not guarantee treatment outcomes or grant clinical authorization.','global-doctor-onboarding');?></p></section><?php return ob_get_clean();
    }
    public static function issue_viewing_room_grant( $application_id, $evidence_id, $reviewer_id, $purpose = 'credential_review' ) {
        if ( ! GDO_Operations::mutation_allowed() ) {
            return new WP_Error( 'gdo_viewing_room_runtime_not_ready', __( 'Private credential viewing-room grants are temporarily unavailable.', 'global-doctor-onboarding' ) );
        }
        $app = self::application_record( $application_id );
        if ( is_wp_error( $app ) ) { return $app; }
        $record = $app ? self::evidence_record( $app->id, $evidence_id ) : null;
        if ( is_wp_error( $record ) ) { return $record; }
        if ( ! $app || ! $record ) { return new WP_Error( 'gdo_room_not_found', __( 'The requested credential review room is unavailable.', 'global-doctor-onboarding' ) ); }
        $grant = GDO_Evidence::issue_view_grant( $record->id, absint( $reviewer_id ), $purpose, 'view' );
        if ( is_wp_error( $grant ) ) { return $grant; }
        return array( 'grant'=>$grant['token'], 'token'=>$grant['token'], 'expires_at'=>$grant['expires_at'], 'mode'=>'view', 'watermark'=>sprintf('PRIVATE REVIEW • %d • %s UTC',absint($reviewer_id),gmdate('Y-m-d H:i:s')), 'download_allowed'=>false );
    }

    private static function upload_temp_path( $name ) {
        $dir=GDO_Storage::directory();$name=basename(sanitize_file_name($name));return $dir&&preg_match('/^[a-f0-9-]{36}\.part$/i',$name)?trailingslashit($dir).'.chunk-'.$name:'';
    }
    public static function create_upload_session( $application_id, $document_type, $name, $bytes, $chunks, $chunk_bytes, $sha256 = '' ) {
        global $wpdb;
        if ( ! GDO_Operations::mutation_allowed() ) {
            return new WP_Error( 'gdo_upload_runtime_not_ready', __( 'Credential uploads are temporarily unavailable.', 'global-doctor-onboarding' ) );
        }
        $health = GDO_Storage::health();
        if ( is_wp_error( $health ) ) { return $health; }
        $application_id=absint($application_id);$uid=get_current_user_id();$type=sanitize_key($document_type);
        $bytes=absint($bytes);$chunks=absint($chunks);$chunk_bytes=absint($chunk_bytes);$expected_chunks=$chunk_bytes?(int)ceil($bytes/$chunk_bytes):0;
        if(!$uid||$bytes<32||$bytes>GDO_Evidence::MAX_BYTES||$chunks<1||$chunks>200||$chunk_bytes<16384||$chunk_bytes>1048576||$chunks!==$expected_chunks){
            return new WP_Error('gdo_upload_session_invalid',__('Resumable upload dimensions are invalid.','global-doctor-onboarding'));
        }
        if(!GDO_Rate_Limiter::hit('resumable-upload-session:'.$uid,12,HOUR_IN_SECONDS)){
            return new WP_Error('gdo_upload_session_rate',__('Too many resumable upload sessions.','global-doctor-onboarding'));
        }
        $apps=GDO_Schema::table('applications');$sessions=self::table('upload_sessions');$now=self::now();
        if(false===$wpdb->query('START TRANSACTION')){return new WP_Error('gdo_upload_transaction',__('The resumable upload transaction could not be started safely.','global-doctor-onboarding'));}
        $wpdb->last_error='';
        $locked=$wpdb->get_results($wpdb->prepare("SELECT id FROM {$apps} WHERE user_id=%d ORDER BY id ASC FOR UPDATE",$uid));
        if(null===$locked||!empty($wpdb->last_error)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_application_lock',__('Application upload reservations could not be locked safely.','global-doctor-onboarding'));}
        $wpdb->last_error='';
        $app=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$apps} WHERE id=%d AND user_id=%d FOR UPDATE",$application_id,$uid));
        if(null===$app&&!empty($wpdb->last_error)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_application_query',__('The upload application could not be read safely.','global-doctor-onboarding'));}
        $allowed_types=$app?GDO_Evidence::types($app->jurisdiction,$app->application_type):array();
        if(!GDO_Operations::mutation_allowed()||!$app||!in_array($app->state,array('draft','more_information'),true)||!GDO_Membership_Adapter::is_active_doctor_candidate($uid,$app->jurisdiction)||!isset($allowed_types[$type])){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_session_forbidden',__('A resumable upload cannot be started for this application.','global-doctor-onboarding'));}
        $wpdb->last_error='';
        $raw_reserved=$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(expected_bytes),0) FROM {$sessions} WHERE user_id=%d AND state IN ('open','finalizing') AND expires_at>%s",$uid,$now));
        if(null===$raw_reserved||!empty($wpdb->last_error)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_temp_quota_unknown',__('Temporary upload storage usage could not be verified safely.','global-doctor-onboarding'));}
        $reserved=absint($raw_reserved);$temp_limit=max(GDO_Evidence::MAX_BYTES,absint(apply_filters('gdo_resumable_temp_quota_bytes',GDO_Evidence::MAX_USER_BYTES,$uid)));
        if($reserved+$bytes>$temp_limit){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_temp_quota',__('The private resumable-upload temporary storage quota has been reached.','global-doctor-onboarding'));}
        $uuid=wp_generate_uuid4();$temp=$uuid.'.part';$path=self::upload_temp_path($temp);$fh=$path?@fopen($path,'x+b'):false;
        if(!$fh){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_session_storage',__('Private resumable upload storage is unavailable.','global-doctor-onboarding'));}
        if(!fclose($fh)||!@chmod($path,0600)){@unlink($path);$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_temp_permissions',__('Private resumable upload file permissions could not be secured.','global-doctor-onboarding'));}
        $inserted=$wpdb->insert($sessions,array('upload_uuid'=>$uuid,'application_id'=>$app->id,'user_id'=>$uid,'document_type'=>$type,'original_name'=>sanitize_file_name($name),'expected_bytes'=>$bytes,'received_bytes'=>0,'expected_chunks'=>$chunks,'received_chunks'=>0,'chunk_bytes'=>$chunk_bytes,'expected_sha256'=>preg_match('/^[a-f0-9]{64}$/i',$sha256)?strtolower($sha256):null,'state'=>'open','temp_name'=>$temp,'expires_at'=>gmdate('Y-m-d H:i:s',time()+self::CHUNK_TTL),'created_at'=>$now,'updated_at'=>$now));
        if(1!==$inserted||false===$wpdb->query('COMMIT')){$wpdb->query('ROLLBACK');@unlink($path);return new WP_Error('gdo_upload_session_store',__('The resumable upload session could not be recorded.','global-doctor-onboarding'));}
        return array('upload_uuid'=>$uuid,'expires_at'=>gmdate('c',time()+self::CHUNK_TTL));
    }
    public static function append_upload_chunk( $uuid, $index, $bytes ) {
        global $wpdb;
        if ( ! GDO_Operations::mutation_allowed() ) { return new WP_Error( 'gdo_upload_chunk_runtime_not_ready', __( 'Credential upload changes are temporarily unavailable.', 'global-doctor-onboarding' ) ); }
        $uuid=sanitize_text_field($uuid);$uid=get_current_user_id();$index=absint($index);$table=self::table('upload_sessions');
        if(false===$wpdb->query('START TRANSACTION')){return new WP_Error('gdo_upload_chunk_transaction',__('The upload chunk transaction could not be started safely.','global-doctor-onboarding'));}
        $wpdb->last_error='';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE upload_uuid=%s AND user_id=%d AND state='open' FOR UPDATE",$uuid,$uid));
        if(null===$row&&!empty($wpdb->last_error)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_chunk_query',__('The resumable upload session could not be read safely.','global-doctor-onboarding'));}
        if(!$row||strtotime($row->expires_at.' UTC')<=time()){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_session_missing',__('Resumable upload session is unavailable or expired.','global-doctor-onboarding'));}
        $length=strlen($bytes);if($index!==absint($row->received_chunks)||$length<1||$length>absint($row->chunk_bytes)||absint($row->received_bytes)+$length>absint($row->expected_bytes)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_chunk_order',__('Upload chunks must arrive exactly once and in order.','global-doctor-onboarding'));}
        $remaining=absint($row->expected_bytes)-absint($row->received_bytes);$is_last=$index+1===absint($row->expected_chunks);if((!$is_last&&$length!==absint($row->chunk_bytes))||($is_last&&$length!==$remaining)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_chunk_size',__('The resumable upload chunk size does not match the declared session.','global-doctor-onboarding'));}
        $health=GDO_Storage::health();if(is_wp_error($health)){$wpdb->query('ROLLBACK');return $health;}
        $path=self::upload_temp_path($row->temp_name);if(!$path||!is_file($path)||is_link($path)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_chunk_storage',__('The private upload session file is unavailable.','global-doctor-onboarding'));}
        $fh=@fopen($path,'r+b');if(!$fh||!flock($fh,LOCK_EX)){$wpdb->query('ROLLBACK');if($fh){fclose($fh);}return new WP_Error('gdo_upload_chunk_lock',__('The resumable upload is busy.','global-doctor-onboarding'));}
        $before=absint($row->received_bytes);$actual_size=filesize($path);if(false===$actual_size||absint($actual_size)!==$before){flock($fh,LOCK_UN);fclose($fh);$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_chunk_integrity',__('The resumable upload temporary file is inconsistent.','global-doctor-onboarding'));}
        if(0!==fseek($fh,0,SEEK_END)){flock($fh,LOCK_UN);fclose($fh);$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_chunk_seek',__('The upload file position could not be verified safely.','global-doctor-onboarding'));}
        $written=fwrite($fh,$bytes);
        $sync_ok=true;if(function_exists('fsync')){$sync_ok=fsync($fh);}
        if($written!==$length||!$sync_ok){$truncate_ok=ftruncate($fh,$before);if($truncate_ok&&function_exists('fsync')){$truncate_ok=fsync($fh);}flock($fh,LOCK_UN);fclose($fh);$wpdb->query('ROLLBACK');if(!$truncate_ok){GDO_Membership_Adapter::audit('doctor_resumable_upload_rollback_file_failed',array('upload_uuid'=>$row->upload_uuid,'expected_bytes'=>$before));return new WP_Error('gdo_upload_chunk_rollback_file',__('The incomplete chunk could not be rolled back safely; operator repair is required.','global-doctor-onboarding'));}return new WP_Error(!$sync_ok?'gdo_upload_chunk_sync':'gdo_upload_chunk_write',!$sync_ok?__('The upload chunk could not be synchronized safely.','global-doctor-onboarding'):__('The upload chunk was incomplete.','global-doctor-onboarding'));}
        $updated=$wpdb->update($table,array('received_bytes'=>$before+$written,'received_chunks'=>absint($row->received_chunks)+1,'updated_at'=>self::now()),array('upload_uuid'=>$row->upload_uuid,'state'=>'open','received_chunks'=>absint($row->received_chunks)));
        if(1!==$updated||false===$wpdb->query('COMMIT')){$truncate_ok=ftruncate($fh,$before);if($truncate_ok&&function_exists('fsync')){$truncate_ok=fsync($fh);}flock($fh,LOCK_UN);fclose($fh);$wpdb->query('ROLLBACK');if(!$truncate_ok){GDO_Membership_Adapter::audit('doctor_resumable_upload_rollback_file_failed',array('upload_uuid'=>$row->upload_uuid,'expected_bytes'=>$before));return new WP_Error('gdo_upload_chunk_rollback_file',__('The uncommitted chunk could not be rolled back safely; operator repair is required.','global-doctor-onboarding'));}return new WP_Error('gdo_upload_chunk_conflict',__('The upload chunk could not be committed safely.','global-doctor-onboarding'));}
        flock($fh,LOCK_UN);fclose($fh);return array('received_chunks'=>absint($row->received_chunks)+1,'received_bytes'=>$before+$written);
    }

    private static function set_upload_session_state( $uuid, $from, $to, $expires_at = null ) {
        global $wpdb;
        $data = array( 'state'=>sanitize_key( $to ), 'updated_at'=>self::now() );
        if ( $expires_at ) { $data['expires_at'] = $expires_at; }
        $updated = $wpdb->update( self::table( 'upload_sessions' ), $data, array( 'upload_uuid'=>sanitize_text_field( $uuid ), 'state'=>sanitize_key( $from ) ) );
        if ( 1 !== $updated ) {
            return new WP_Error( 'gdo_upload_finalize_state_store', __( 'The resumable upload recovery state could not be persisted safely.', 'global-doctor-onboarding' ) );
        }
        return true;
    }
    public static function finalize_upload_session( $uuid ) {
        global $wpdb;
        if ( ! GDO_Operations::mutation_allowed() ) { return new WP_Error( 'gdo_upload_finalize_runtime_not_ready', __( 'Credential upload finalization is temporarily unavailable.', 'global-doctor-onboarding' ) ); }
        $uuid=sanitize_text_field($uuid);$uid=get_current_user_id();$table=self::table('upload_sessions');
        if(false===$wpdb->query('START TRANSACTION')){return new WP_Error('gdo_upload_finalize_transaction',__('The resumable upload finalization transaction could not start safely.','global-doctor-onboarding'));}
        $wpdb->last_error='';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE upload_uuid=%s AND user_id=%d FOR UPDATE",$uuid,$uid));
        if(null===$row&&!empty($wpdb->last_error)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_finalize_query',__('The resumable upload could not be read safely for finalization.','global-doctor-onboarding'));}
        if(!$row||'open'!==$row->state||strtotime($row->expires_at.' UTC')<=time()||absint($row->received_chunks)!==absint($row->expected_chunks)||absint($row->received_bytes)!==absint($row->expected_bytes)){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_incomplete',__('The resumable upload is incomplete, expired, or already finalizing.','global-doctor-onboarding'));}
        $claimed=$wpdb->update($table,array('state'=>'finalizing','updated_at'=>self::now()),array('upload_uuid'=>$row->upload_uuid,'state'=>'open'),array('%s','%s'),array('%s','%s'));
        if(1!==$claimed||false===$wpdb->query('COMMIT')){$wpdb->query('ROLLBACK');return new WP_Error('gdo_upload_finalize_conflict',__('The resumable upload is already being finalized.','global-doctor-onboarding'));}
        $health=GDO_Storage::health();if(is_wp_error($health)){$state=self::set_upload_session_state($row->upload_uuid,'finalizing','open');return is_wp_error($state)?$state:$health;}
        $path=self::upload_temp_path($row->temp_name);
        if(!$path||!is_file($path)||is_link($path)){$state=self::set_upload_session_state($row->upload_uuid,'finalizing','failed');return is_wp_error($state)?$state:new WP_Error('gdo_upload_file_missing',__('The private resumable upload is unavailable.','global-doctor-onboarding'));}
        $actual=hash_file('sha256',$path);if(!is_string($actual)||64!==strlen($actual)){$state=self::set_upload_session_state($row->upload_uuid,'finalizing','failed');return is_wp_error($state)?$state:new WP_Error('gdo_upload_hash_read',__('The completed upload hash could not be read safely.','global-doctor-onboarding'));}
        if($row->expected_sha256&&!hash_equals($row->expected_sha256,$actual)){$state=self::set_upload_session_state($row->upload_uuid,'finalizing','failed');return is_wp_error($state)?$state:new WP_Error('gdo_upload_hash_mismatch',__('The completed upload hash does not match.','global-doctor-onboarding'));}
        $size=filesize($path);if(false===$size||absint($size)!==absint($row->expected_bytes)){$state=self::set_upload_session_state($row->upload_uuid,'finalizing','failed');return is_wp_error($state)?$state:new WP_Error('gdo_upload_size_read',__('The completed upload size could not be verified safely.','global-doctor-onboarding'));}
        $app=self::application_record($row->application_id);if(is_wp_error($app)){$state=self::set_upload_session_state($row->upload_uuid,'finalizing','open');return is_wp_error($state)?$state:$app;}if(!$app||absint($app->user_id)!==$uid||!in_array($app->state,array('draft','more_information'),true)){$state=self::set_upload_session_state($row->upload_uuid,'finalizing','failed');return is_wp_error($state)?$state:new WP_Error('gdo_upload_application_changed',__('The application changed before the upload could be finalized.','global-doctor-onboarding'));}
        $file=array('tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'size'=>$size,'name'=>$row->original_name);$result=GDO_Evidence::stage_upload($app,$row->document_type,$file,true,$path);
        if(is_wp_error($result)){$state=self::set_upload_session_state($row->upload_uuid,'finalizing','open');return is_wp_error($state)?$state:$result;}
        $cleanup_deadline=self::now();
        $committed=$wpdb->update($table,array('state'=>'committed','expires_at'=>$cleanup_deadline,'updated_at'=>self::now()),array('upload_uuid'=>$row->upload_uuid,'state'=>'finalizing'));
        if(1!==$committed){GDO_Membership_Adapter::audit('doctor_resumable_upload_commit_marker_failed',array('application_id'=>$row->application_id,'upload_uuid'=>$row->upload_uuid,'evidence_id'=>$result['id']));return new WP_Error('gdo_upload_commit_marker',__('Credential evidence was stored but the upload-session marker requires repair.','global-doctor-onboarding'));}
        $cleanup_pending=false;if(!@unlink($path)||file_exists($path)){$cleanup_pending=true;GDO_Membership_Adapter::audit('doctor_resumable_upload_temp_delete_failed',array('application_id'=>$row->application_id,'upload_uuid'=>$row->upload_uuid,'evidence_id'=>$result['id']));}
        $auth=self::authenticity_assessment($row->application_id,$result['id']);if(is_wp_error($auth)){GDO_Membership_Adapter::audit('doctor_upload_authenticity_postcheck_failed',array('application_id'=>$row->application_id,'evidence_id'=>$result['id'],'error'=>$auth->get_error_code()));}
        $ai=self::ai_assistance($row->application_id,$result['id']);if(is_wp_error($ai)){GDO_Membership_Adapter::audit('doctor_upload_ai_postcheck_failed',array('application_id'=>$row->application_id,'evidence_id'=>$result['id'],'error'=>$ai->get_error_code()));}
        if($cleanup_pending){$result['cleanup_pending']=true;$result['warning_code']='gdo_upload_temp_delete';}
        return $result;
    }
    public static function cleanup_upload_sessions() {
        global $wpdb;
        $health = GDO_Storage::health();
        if ( is_wp_error( $health ) ) { return new WP_Error( 'gdo_upload_cleanup_storage', $health->get_error_message() ); }
        $wpdb->last_error = '';
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT upload_uuid,temp_name FROM ' . self::table( 'upload_sessions' ) . " WHERE state IN ('open','failed','finalizing','committed') AND expires_at<%s LIMIT 200", self::now() ) );
        if ( null === $rows || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_upload_cleanup_query', __( 'Expired resumable uploads could not be read safely.', 'global-doctor-onboarding' ) ); }
        foreach ( $rows as $row ) {
            $path = self::upload_temp_path( $row->temp_name );
            if ( $path && ( is_link($path) || (file_exists($path) && !is_file($path)) ) ) { return new WP_Error( 'gdo_upload_cleanup_unsafe_path', __( 'An expired private upload path is unsafe; cleanup is paused.', 'global-doctor-onboarding' ) ); }
            if ( $path && is_file($path) && ( !@unlink($path) || file_exists($path) ) ) { return new WP_Error( 'gdo_upload_cleanup_file', __( 'An expired private upload could not be deleted safely.', 'global-doctor-onboarding' ) ); }
            $updated = $wpdb->update( self::table('upload_sessions'), array('state'=>'expired','updated_at'=>self::now()), array('upload_uuid'=>$row->upload_uuid) );
            if ( false === $updated ) { return new WP_Error( 'gdo_upload_cleanup_store', __( 'Expired upload-session state could not be persisted safely.', 'global-doctor-onboarding' ) ); }
        }
        return true;
    }
    public static function privacy_export_rows( $application_id ) {
        global $wpdb;
        $id = absint( $application_id ); $rows = array();
        $queries = array(
            'Professional credential check'=>'SELECT check_type,provider_key,status,confidence,checked_at,expires_at FROM '.self::table('credential_checks').' WHERE application_id=%d',
            'Professional history'=>'SELECT event_type,public_safe,event_json,occurred_at FROM '.self::table('professional_history').' WHERE application_id=%d',
            'Verification passport'=>'SELECT passport_uuid,version,status,issued_at,expires_at,revoked_at,revoke_reason FROM '.self::table('verification_passports').' WHERE application_id=%d',
            'Reviewer conflict'=>'SELECT conflict_type,status,declared_at,resolved_at FROM '.self::table('reviewer_conflicts').' WHERE application_id=%d',
        );
        foreach ( $queries as $label=>$sql ) {
            $wpdb->last_error = '';
            $items = $wpdb->get_results( $wpdb->prepare( $sql, $id ), ARRAY_A );
            if ( null === $items || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_privacy_export_query', __( 'Advanced professional-trust data could not be exported completely.', 'global-doctor-onboarding' ) ); }
            foreach ( $items as $item ) { $rows[] = array( 'name'=>$label, 'value'=>wp_json_encode($item) ); }
        }
        return $rows;
    }

    public static function privacy_erase_application( $application_id, $user_id ) {
        global $wpdb;$application_id=absint($application_id);$user_id=absint($user_id);$now=self::now();
        $uploads=$wpdb->get_results($wpdb->prepare('SELECT upload_uuid,temp_name FROM '.self::table('upload_sessions').' WHERE application_id=%d OR user_id=%d',$application_id,$user_id));foreach((array)$uploads as $row){$path=self::upload_temp_path($row->temp_name);if($path&&is_file($path)&&!is_link($path)&&!@unlink($path)){return new WP_Error('gdo_privacy_upload_cleanup',__('A private resumable upload could not be deleted.','global-doctor-onboarding'));}}
        $wpdb->delete(self::table('upload_sessions'),array('application_id'=>$application_id));$wpdb->delete(self::table('monitor_state'),array('application_id'=>$application_id));
        $wpdb->update(self::table('credential_checks'),array('facts_json'=>'{"redacted":"privacy_erasure"}','explanation_json'=>'{"redacted":"privacy_erasure"}','external_reference'=>'','updated_at'=>$now),array('application_id'=>$application_id));
        $wpdb->update(self::table('professional_history'),array('user_id'=>0,'public_safe'=>0,'event_json'=>'{"redacted":"privacy_erasure"}','source_hash'=>hash('sha256','{"redacted":"privacy_erasure"}')),array('application_id'=>$application_id));
        $wpdb->update(self::table('verification_passports'),array('user_id'=>0,'status'=>'revoked','scope_json'=>'{"redacted":"privacy_erasure"}','token_hash'=>hash('sha256','erased|'.$application_id),'revoked_at'=>$now,'revoke_reason'=>'privacy_erasure'),array('application_id'=>$application_id));
        $wpdb->update(self::table('reviewer_conflicts'),array('applicant_id'=>0),array('application_id'=>$application_id,'applicant_id'=>$user_id));
        $wpdb->query($wpdb->prepare('UPDATE '.self::table('reviewer_conflicts').' SET reviewer_id=0 WHERE reviewer_id=%d',$user_id));
        $wpdb->query($wpdb->prepare('UPDATE '.self::table('reviewer_conflicts').' SET declared_by=0 WHERE declared_by=%d',$user_id));
        $wpdb->query($wpdb->prepare('UPDATE '.self::table('reviewer_conflicts').' SET resolved_by=0 WHERE resolved_by=%d',$user_id));
        return true;
    }

    public static function retention_anonymize_application( $application_id, $user_id = 0 ) {
        return self::privacy_erase_application($application_id,$user_id);
    }
    public static function transparency_snapshot( $days = self::PUBLIC_WINDOW_DAYS ) {
        global $wpdb;
        $days=max(30,min(365,absint($days)));$since=gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS);$apps=GDO_Schema::table('applications');$appeals=GDO_Schema::table('appeals');$quality=GDO_Schema::table('quality_samples');$risk=GDO_Schema::table('risk_signals');
        $queries=array(
            'decisions'=>array("SELECT COUNT(*) FROM {$apps} WHERE decision_at>=%s",array($since)),
            'verified'=>array("SELECT COUNT(*) FROM {$apps} WHERE decision_at>=%s AND state IN ('verified','reinstated','renewal_due')",array($since)),
            'appeals_resolved'=>array("SELECT COUNT(*) FROM {$appeals} WHERE resolved_at>=%s",array($since)),
            'appeals_overturned'=>array("SELECT COUNT(*) FROM {$appeals} WHERE resolved_at>=%s AND decision IN ('reinstated','under_review')",array($since)),
            'quality_samples'=>array("SELECT COUNT(*) FROM {$quality} WHERE created_at>=%s",array($since)),
            'fraud_signals'=>array("SELECT COUNT(*) FROM {$risk} WHERE created_at>=%s AND signal_type IN ('credential_reuse_network','duplicate_identity','duplicate_document')",array($since)),
        );
        $snapshot=array('period_days'=>$days,'privacy'=>'aggregate_only');
        foreach($queries as $key=>$spec){$wpdb->last_error='';$raw=$wpdb->get_var($wpdb->prepare($spec[0],$spec[1]));if(null===$raw||!empty($wpdb->last_error)){return new WP_Error('gdo_transparency_query',__('Public verification transparency metrics are temporarily unavailable.','global-doctor-onboarding'));}$snapshot[$key]=absint($raw);}
        return $snapshot;
    }
    public static function public_transparency_snapshot() {
        $days=self::PUBLIC_WINDOW_DAYS;
        $snapshot=self::transparency_snapshot($days);
        if(is_wp_error($snapshot)){return $snapshot;}
        $minimum=max(20,min(100,absint(apply_filters('gdo_public_transparency_minimum_cohort',20))));
        if(absint($snapshot['decisions'])<$minimum){return array('period_days'=>$days,'privacy'=>'aggregate_only','suppressed'=>true,'minimum_cohort'=>$minimum);}
        $minimum_cell=max(5,min(20,absint(apply_filters('gdo_public_transparency_minimum_cell',5))));
        $suppressed_fields=array();
        foreach(array('verified','appeals_resolved','appeals_overturned','quality_samples','fraud_signals') as $field){
            $value=absint($snapshot[$field]);
            if($value>0&&$value<$minimum_cell){$snapshot[$field]=null;$suppressed_fields[]=$field;}
        }
        $snapshot['suppressed']=false;
        $snapshot['minimum_cohort']=$minimum;
        $snapshot['minimum_cell']=$minimum_cell;
        $snapshot['suppressed_fields']=$suppressed_fields;
        return $snapshot;
    }

    public function rest_routes() {
        register_rest_route(self::REST_NAMESPACE,'/trust/command-center',array('methods'=>'GET','callback'=>array($this,'rest_command_center'),'permission_callback'=>function(){return is_user_logged_in();}));
        register_rest_route(self::REST_NAMESPACE,'/trust/issuer',array('methods'=>'POST','callback'=>array($this,'rest_issuer'),'permission_callback'=>array($this,'rest_manage_permission')));
        register_rest_route(self::REST_NAMESPACE,'/trust/issuer/(?P<uuid>[a-f0-9-]{36})/review',array('methods'=>'POST','callback'=>array($this,'rest_issuer_review'),'permission_callback'=>array($this,'rest_manage_permission')));
        register_rest_route(self::REST_NAMESPACE,'/trust/jurisdiction',array('methods'=>'POST','callback'=>array($this,'rest_jurisdiction'),'permission_callback'=>array($this,'rest_manage_permission')));
        register_rest_route(self::REST_NAMESPACE,'/trust/check/(?P<application_id>\d+)/(?P<evidence_id>\d+)',array('methods'=>'POST','callback'=>array($this,'rest_check'),'permission_callback'=>array($this,'rest_reviewer_permission')));
        register_rest_route(self::REST_NAMESPACE,'/trust/upload/start',array('methods'=>'POST','callback'=>array($this,'rest_upload_start'),'permission_callback'=>function(){return is_user_logged_in();}));
        register_rest_route(self::REST_NAMESPACE,'/trust/upload/(?P<uuid>[a-f0-9-]{36})/chunk/(?P<index>\d+)',array('methods'=>'POST','callback'=>array($this,'rest_upload_chunk'),'permission_callback'=>function(){return is_user_logged_in();}));
        register_rest_route(self::REST_NAMESPACE,'/trust/upload/(?P<uuid>[a-f0-9-]{36})/finalize',array('methods'=>'POST','callback'=>array($this,'rest_upload_finalize'),'permission_callback'=>function(){return is_user_logged_in();}));
        register_rest_route(self::REST_NAMESPACE,'/trust/viewing-room',array('methods'=>'POST','callback'=>array($this,'rest_viewing_room'),'permission_callback'=>array($this,'rest_reviewer_permission')));
        register_rest_route(self::REST_NAMESPACE,'/public/passport/(?P<uuid>[a-f0-9-]{36})',array('methods'=>'GET','callback'=>array($this,'rest_public_passport'),'permission_callback'=>'__return_true'));
        register_rest_route(self::REST_NAMESPACE,'/public/transparency',array('methods'=>'GET','callback'=>array($this,'rest_transparency'),'permission_callback'=>'__return_true'));
    }

    public function rest_manage_permission(){return self::can_manage();}
    public function rest_reviewer_permission(){$uid=get_current_user_id();return $uid&&GDO_Membership_Adapter::can('sabri_verify_doctors',$uid)&&GDO_Membership_Adapter::recent_step_up($uid);}
    public function rest_command_center(){$value=self::command_center(get_current_user_id());return is_wp_error($value)?$value:rest_ensure_response($value);}
    public function rest_issuer(WP_REST_Request $r){$v=self::register_issuer((array)$r->get_json_params());return is_wp_error($v)?$v:rest_ensure_response($v);}
    public function rest_issuer_review(WP_REST_Request $r){$p=(array)$r->get_json_params();$v=self::review_issuer($r['uuid'],isset($p['status'])?$p['status']:'verified',isset($p['assurance_level'])?$p['assurance_level']:'verified_source');return is_wp_error($v)?$v:rest_ensure_response(array('saved'=>(bool)$v));}
    public function rest_jurisdiction(WP_REST_Request $r){$p=(array)$r->get_json_params();$v=self::save_jurisdiction_rule(isset($p['jurisdiction'])?$p['jurisdiction']:'',isset($p['version'])?$p['version']:'',isset($p['rules'])&&is_array($p['rules'])?$p['rules']:array(),isset($p['status'])?$p['status']:'draft',isset($p['effective_from'])?$p['effective_from']:'',isset($p['effective_until'])?$p['effective_until']:'');return is_wp_error($v)?$v:rest_ensure_response(array('saved'=>(bool)$v));}
    public function rest_check(WP_REST_Request $r){$app=absint($r['application_id']);$ev=absint($r['evidence_id']);$target=GDO_Application::get($app);if(!$target||!GDO_Membership_Adapter::reviewer_case_allows(get_current_user_id(),$target->user_id,$target->id)){return new WP_Error('gdo_check_forbidden',__('The professional trust check is not authorized.','global-doctor-onboarding'),array('status'=>403));}$primary=self::primary_source_verify($app,$ev);$auth=self::authenticity_assessment($app,$ev);$ai=self::ai_assistance($app,$ev);return rest_ensure_response(array('primary_source'=>$primary,'authenticity'=>$auth,'ai_assist'=>$ai,'risk'=>self::risk_explanation($app),'dual_review'=>self::requires_dual_review($app)));}
    public function rest_upload_start(WP_REST_Request $r){$p=(array)$r->get_json_params();$v=self::create_upload_session(isset($p['application_id'])?$p['application_id']:0,isset($p['document_type'])?$p['document_type']:'',isset($p['name'])?$p['name']:'credential',isset($p['bytes'])?$p['bytes']:0,isset($p['chunks'])?$p['chunks']:0,isset($p['chunk_bytes'])?$p['chunk_bytes']:0,isset($p['sha256'])?$p['sha256']:'');return is_wp_error($v)?$v:rest_ensure_response($v);}
    public function rest_upload_chunk(WP_REST_Request $r){$bytes=$r->get_body();$v=self::append_upload_chunk($r['uuid'],$r['index'],$bytes);return is_wp_error($v)?$v:rest_ensure_response($v);}
    public function rest_upload_finalize(WP_REST_Request $r){$v=self::finalize_upload_session($r['uuid']);return is_wp_error($v)?$v:rest_ensure_response($v);}
    public function rest_viewing_room(WP_REST_Request $r){$p=(array)$r->get_json_params();$v=self::issue_viewing_room_grant(isset($p['application_id'])?$p['application_id']:0,isset($p['evidence_id'])?$p['evidence_id']:0,get_current_user_id(),isset($p['purpose'])?$p['purpose']:'credential_review');return is_wp_error($v)?$v:rest_ensure_response($v);}
    public function rest_public_passport(WP_REST_Request $r){$v=self::verify_passport_uuid($r['uuid']);return is_wp_error($v)?$v:rest_ensure_response($v);}
    public function rest_transparency(){$value=self::public_transparency_snapshot();return is_wp_error($value)?$value:rest_ensure_response($value);}
}
