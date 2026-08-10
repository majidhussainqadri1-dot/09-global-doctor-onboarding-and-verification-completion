<?php
defined( 'ABSPATH' ) || exit;

/**
 * RC6 corrective layer for Advanced Professional Trust.
 *
 * This class closes lifecycle, migration, privacy, provider and concurrency
 * findings from the 80-round review without creating a second source of truth.
 * File 09 remains the professional-verification owner; this layer only replaces
 * unsafe derivative callbacks/routes from the RC5 Advanced Trust extension.
 */
final class GDO_Advanced_Trust_Hardening {
    const SCHEMA_VERSION = 2;
    const CONTRACT_VERSION = '1.1.0';

    public static function hooks() {
        // Replace RC5 callbacks whose state scheduling/passport semantics were
        // found incomplete. remove_action is safe because GDO_Advanced_Trust
        // registered its hooks immediately before this class is activated.
        remove_action( 'gdo_trust_continuous_monitor', array( 'GDO_Advanced_Trust', 'continuous_monitor' ) );
        remove_action( 'gdo_application_submitted', array( 'GDO_Advanced_Trust', 'application_submitted' ), 10 );
        remove_action( 'gdo_application_decided', array( 'GDO_Advanced_Trust', 'application_decided' ), 10 );
        remove_action( 'gdo_professional_status_changed', array( 'GDO_Advanced_Trust', 'event_reverification' ), 10 );

        add_action( 'init', array( __CLASS__, 'maybe_upgrade_schema' ), 6 );
        add_action( 'gdo_trust_continuous_monitor', array( __CLASS__, 'continuous_monitor' ) );
        add_action( 'gdo_application_submitted', array( __CLASS__, 'application_submitted' ), 10, 1 );
        add_action( 'gdo_application_decided', array( __CLASS__, 'application_decided' ), 10, 2 );
        add_action( 'gdo_professional_status_changed', array( __CLASS__, 'event_reverification' ), 10, 3 );
        add_action( 'rest_api_init', array( __CLASS__, 'rest_overrides' ), 30 );
        add_filter( 'gdo_primary_source_verification', array( __CLASS__, 'normalize_primary_source_result' ), PHP_INT_MAX, 3 );
        add_filter( 'gdo_file09_advanced_trust_contract', array( __CLASS__, 'contract_filter' ), 30 );
    }

    public static function contract_filter( $contract ) {
        $contract = is_array( $contract ) ? $contract : array();
        $contract['version'] = self::CONTRACT_VERSION;
        $contract['schema_version'] = self::SCHEMA_VERSION;
        $contract['review80_corrective_layer'] = true;
        $contract['public_get_mutates_owner_state'] = false;
        return $contract;
    }

    public static function maybe_upgrade_schema() {
        global $wpdb;
        $base = GDO_Advanced_Trust::maybe_install();
        if ( is_wp_error( $base ) ) { return $base; }
        $indexes = array(
            array( GDO_Advanced_Trust::table( 'verification_passports' ), 'application_status', 'application_id,status' ),
            array( GDO_Advanced_Trust::table( 'upload_sessions' ), 'application_state', 'application_id,state' ),
        );
        foreach ( $indexes as $spec ) {
            list( $table, $name, $columns ) = $spec;
            $wpdb->last_error = '';
            $exists = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name=%s", $name ) );
            if ( ! empty( $wpdb->last_error ) ) {
                return new WP_Error( 'gdo_advanced_schema_index_read', __( 'Advanced Trust schema indexes could not be verified safely.', 'global-doctor-onboarding' ) );
            }
            if ( ! $exists ) {
                $added = $wpdb->query( "ALTER TABLE {$table} ADD KEY {$name} ({$columns})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                if ( false === $added ) {
                    return new WP_Error( 'gdo_advanced_schema_index', __( 'Advanced Trust schema index migration failed.', 'global-doctor-onboarding' ) );
                }
            }
        }
        if ( absint( get_option( 'gdo_advanced_trust_schema', 0 ) ) < self::SCHEMA_VERSION ) {
            if ( ! update_option( 'gdo_advanced_trust_schema', self::SCHEMA_VERSION, false ) && absint( get_option( 'gdo_advanced_trust_schema', 0 ) ) !== self::SCHEMA_VERSION ) {
                return new WP_Error( 'gdo_advanced_schema_version', __( 'Advanced Trust schema version could not be persisted.', 'global-doctor-onboarding' ) );
            }
            GDO_Membership_Adapter::audit( 'doctor_advanced_trust_schema_upgraded', array( 'schema'=>self::SCHEMA_VERSION ) );
        }
        return true;
    }

    private static function safe_array( $value, $depth = 0 ) {
        if ( ! is_array( $value ) || $depth > 4 ) { return array(); }
        $out = array();
        foreach ( array_slice( $value, 0, 80, true ) as $key => $item ) {
            $key = substr( sanitize_key( $key ), 0, 80 );
            if ( ! $key || preg_match( '/(?:secret|password|token|api[_-]?key|private[_-]?key|credential)/i', $key ) ) { continue; }
            if ( is_array( $item ) ) {
                $out[ $key ] = self::safe_array( $item, $depth + 1 );
            } elseif ( is_bool( $item ) || is_int( $item ) || is_float( $item ) || null === $item ) {
                $out[ $key ] = $item;
            } else {
                $out[ $key ] = substr( sanitize_textarea_field( (string) $item ), 0, 4000 );
            }
        }
        return $out;
    }

    public static function normalize_primary_source_result( $result, $request, $issuer ) {
        unset( $request, $issuer );
        if ( ! is_array( $result ) ) { return $result; }
        if ( ! empty( $result['expires_at'] ) ) {
            $timestamp = strtotime( (string) $result['expires_at'] );
            $result['expires_at'] = false !== $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
        }
        if ( isset( $result['facts'] ) ) { $result['facts'] = self::safe_array( $result['facts'] ); }
        if ( isset( $result['explanation'] ) ) { $result['explanation'] = self::safe_array( $result['explanation'] ); }
        return $result;
    }

    public static function save_jurisdiction_rule( $jurisdiction, $version, array $rules, $status = 'draft', $effective_from = '', $effective_until = '' ) {
        if ( ! self::can_manage() ) {
            return new WP_Error( 'gdo_trust_forbidden', __( 'Jurisdiction rules require privileged step-up.', 'global-doctor-onboarding' ) );
        }
        global $wpdb;
        $jurisdiction = GDO_Policy::normalize_jurisdiction( $jurisdiction );
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
        $rules = self::safe_array( $rules );
        if ( ! $rules ) { return new WP_Error( 'gdo_jurisdiction_rule_empty', __( 'Jurisdiction rules cannot be empty.', 'global-doctor-onboarding' ) ); }
        $table = GDO_Advanced_Trust::table( 'jurisdiction_rules' );
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE jurisdiction=%s AND rule_version=%s LIMIT 1", $jurisdiction, $version ) );
        $actor = get_current_user_id();
        $json = wp_json_encode( $rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $now = current_time( 'mysql', true );

        if ( ! $existing ) {
            if ( 'draft' !== $status ) {
                return new WP_Error( 'gdo_jurisdiction_rule_separation', __( 'A jurisdiction rule must first be saved as a draft and then approved by a second authorized reviewer.', 'global-doctor-onboarding' ) );
            }
            $ok = $wpdb->insert( $table, array(
                'jurisdiction'=>$jurisdiction, 'rule_version'=>$version, 'status'=>'draft', 'rules_json'=>$json,
                'effective_from'=>$from ?: null, 'effective_until'=>$until ?: null,
                'created_by'=>$actor, 'approved_by'=>null, 'created_at'=>$now, 'updated_at'=>$now,
            ) );
            if ( 1 !== $ok ) { return new WP_Error( 'gdo_jurisdiction_rule_store', __( 'Jurisdiction rule draft could not be stored.', 'global-doctor-onboarding' ) ); }
        } elseif ( 'approved' === $existing->status ) {
            if ( 'retired' !== $status ) {
                return new WP_Error( 'gdo_jurisdiction_rule_immutable', __( 'An approved jurisdiction-rule version is immutable; create a new version for changes.', 'global-doctor-onboarding' ) );
            }
            $ok = $wpdb->update( $table, array( 'status'=>'retired', 'updated_at'=>$now ), array( 'id'=>absint( $existing->id ), 'status'=>'approved' ) );
            if ( 1 !== $ok ) { return new WP_Error( 'gdo_jurisdiction_rule_store', __( 'Jurisdiction rule could not be retired.', 'global-doctor-onboarding' ) ); }
        } elseif ( 'draft' === $existing->status && 'approved' === $status ) {
            if ( absint( $existing->created_by ) === $actor ) {
                return new WP_Error( 'gdo_jurisdiction_rule_separation', __( 'A second authorized reviewer must approve the jurisdiction-rule draft.', 'global-doctor-onboarding' ) );
            }
            if ( ! hash_equals( hash( 'sha256', (string) $existing->rules_json ), hash( 'sha256', $json ) )
                || (string) $existing->effective_from !== (string) ( $from ?: '' )
                || (string) $existing->effective_until !== (string) ( $until ?: '' ) ) {
                return new WP_Error( 'gdo_jurisdiction_rule_changed', __( 'The approver may not silently modify a jurisdiction-rule draft while approving it.', 'global-doctor-onboarding' ) );
            }
            $ok = $wpdb->update( $table, array( 'status'=>'approved', 'approved_by'=>$actor, 'updated_at'=>$now ), array( 'id'=>absint( $existing->id ), 'status'=>'draft' ) );
            if ( 1 !== $ok ) { return new WP_Error( 'gdo_jurisdiction_rule_store', __( 'Jurisdiction rule approval could not be stored.', 'global-doctor-onboarding' ) ); }
        } elseif ( 'draft' === $existing->status && 'draft' === $status ) {
            if ( absint( $existing->created_by ) !== $actor ) {
                return new WP_Error( 'gdo_jurisdiction_rule_draft_owner', __( 'Only the draft author may edit this rule version before independent approval.', 'global-doctor-onboarding' ) );
            }
            $ok = $wpdb->update( $table, array( 'rules_json'=>$json, 'effective_from'=>$from ?: null, 'effective_until'=>$until ?: null, 'updated_at'=>$now ), array( 'id'=>absint( $existing->id ), 'status'=>'draft' ) );
            if ( false === $ok ) { return new WP_Error( 'gdo_jurisdiction_rule_store', __( 'Jurisdiction rule draft could not be updated.', 'global-doctor-onboarding' ) ); }
        } else {
            return new WP_Error( 'gdo_jurisdiction_rule_state', __( 'Jurisdiction rule lifecycle state is not writable.', 'global-doctor-onboarding' ) );
        }
        GDO_Membership_Adapter::audit( 'doctor_jurisdiction_rule_saved', array( 'jurisdiction'=>$jurisdiction, 'version'=>$version, 'status'=>$status, 'actor_id'=>$actor ) );
        return true;
    }

    private static function can_manage() {
        $uid = get_current_user_id();
        return $uid && GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $uid ) && GDO_Membership_Adapter::recent_step_up( $uid );
    }

    public static function schedule_reverification( $application_id, $reason = 'periodic', $when = 0, $preserve_failures = true ) {
        global $wpdb;
        $application_id = absint( $application_id );
        if ( ! $application_id || ! GDO_Application::get( $application_id ) ) { return false; }
        $table = GDO_Advanced_Trust::table( 'monitor_state' );
        $when = $when ? absint( $when ) : time() + DAY_IN_SECONDS;
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE application_id=%d", $application_id ) );
        $data = array(
            'monitor_status'=>'scheduled', 'trigger_reason'=>substr( sanitize_key( $reason ), 0, 80 ),
            'next_check_at'=>gmdate( 'Y-m-d H:i:s', $when ),
            'failure_count'=>$preserve_failures && $existing ? absint( $existing->failure_count ) : 0,
            'updated_at'=>current_time( 'mysql', true ),
        );
        if ( $existing ) {
            return false !== $wpdb->update( $table, $data, array( 'application_id'=>$application_id ) );
        }
        $data['application_id'] = $application_id;
        return 1 === $wpdb->insert( $table, $data );
    }

    private static function schedule_wakeup( $when ) {
        $when = absint( $when );
        if ( ! $when || $when <= time() ) { return true; }
        $next = wp_next_scheduled( 'gdo_trust_continuous_monitor' );
        if ( ! $next || $next > $when + MINUTE_IN_SECONDS ) {
            $scheduled = wp_schedule_single_event( $when, 'gdo_trust_continuous_monitor', array(), true );
            if ( is_wp_error( $scheduled ) || false === $scheduled ) {
                return new WP_Error( 'gdo_trust_wakeup_schedule', __( 'Professional reverification wake-up could not be scheduled safely.', 'global-doctor-onboarding' ) );
            }
        }
        return true;
    }

    public static function event_reverification( $application_id, $event_type = 'status_change', $context = array() ) {
        unset( $context );
        $when = time() + HOUR_IN_SECONDS;
        $ok = self::schedule_reverification( $application_id, $event_type, $when, true );
        if ( $ok ) {
            $wake = self::schedule_wakeup( $when );
            if ( is_wp_error( $wake ) ) {
                GDO_Membership_Adapter::audit( 'doctor_reverification_wakeup_failed', array( 'application_id'=>absint( $application_id ), 'reason'=>$wake->get_error_code() ) );
                return $wake;
            }
        }
        return $ok;
    }

    public static function application_submitted( $application_id ) {
        global $wpdb;
        $app_id = absint( $application_id );
        if ( ! $app_id ) { return; }
        $exists = absint( $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . GDO_Advanced_Trust::table( 'credential_checks' ) . " WHERE application_id=%d AND check_type='equivalency' ORDER BY id DESC LIMIT 1",
            $app_id
        ) ) );
        if ( ! $exists ) { GDO_Advanced_Trust::equivalency_assessment( $app_id ); }
        GDO_Advanced_Trust::fraud_ring_scan( $app_id );
        self::schedule_reverification( $app_id, 'submission', time() + DAY_IN_SECONDS, true );
    }

    private static function history_once( $app, $event_type, array $payload, $public_safe ) {
        global $wpdb;
        $json = wp_json_encode( self::safe_array( $payload ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $hash = hash( 'sha256', $json );
        $exists = absint( $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . GDO_Advanced_Trust::table( 'professional_history' ) . ' WHERE application_id=%d AND event_type=%s AND source_hash=%s LIMIT 1',
            absint( $app->id ), sanitize_key( $event_type ), $hash
        ) ) );
        if ( ! $exists ) { GDO_Advanced_Trust::add_history( $app->user_id, $app->id, $event_type, $payload, $public_safe ); }
    }

    public static function application_decided( $application_id, $decision ) {
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) { return; }
        $decision = sanitize_key( $decision );
        if ( in_array( $decision, array( 'verified','reinstated' ), true ) ) {
            self::history_once( $app, 'professional_decision', array( 'decision'=>$decision, 'verified_until'=>$app->verified_until ), true );
            $passport = self::ensure_passport( $app->id );
            if ( is_wp_error( $passport ) ) {
                GDO_Membership_Adapter::audit( 'doctor_verification_passport_issue_failed', array( 'application_id'=>$app->id, 'decision'=>$decision, 'error'=>$passport->get_error_code() ) );
            }
            self::schedule_reverification( $app->id, 'verified', time() + 30 * DAY_IN_SECONDS, false );
        } elseif ( 'expired' === $decision ) {
            self::history_once( $app, 'professional_expired', array( 'decision'=>'expired' ), true );
            GDO_Advanced_Trust::revoke_passports_for_application( $app->id, 'expired' );
        } elseif ( in_array( $decision, array( 'suspended','revoked','rejected','withdrawn' ), true ) ) {
            self::history_once( $app, 'professional_status_changed', array( 'decision'=>$decision ), false );
            GDO_Advanced_Trust::revoke_passports_for_application( $app->id, $decision );
            self::event_reverification( $app->id, $decision );
        }
    }

    public static function issue_passport( $application_id ) {
        global $wpdb;
        $application_id = absint( $application_id );
        if ( ! defined( 'GDO_CLAIM_SIGNING_KEY' ) || strlen( (string) GDO_CLAIM_SIGNING_KEY ) < 32 ) {
            return new WP_Error( 'gdo_passport_key', __( 'Professional passport signing is unavailable.', 'global-doctor-onboarding' ) );
        }
        $key = hash( 'sha256', 'passport|' . (string) GDO_CLAIM_SIGNING_KEY, true );
        $apps = GDO_Schema::table( 'applications' );
        $table = GDO_Advanced_Trust::table( 'verification_passports' );
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            return new WP_Error( 'gdo_passport_transaction', __( 'A professional passport transaction could not be started safely.', 'global-doctor-onboarding' ) );
        }
        $app = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$apps} WHERE id=%d FOR UPDATE", $application_id ) );
        $verified_expiry = GDO_Advanced_Trust::current_verification_expiry( $app );
        $approved_snapshot = $app ? GDO_Application::stored_approved_snapshot( $app ) : array();
        if ( ! $app || ! $approved_snapshot || empty( $approved_snapshot['captured_at'] ) || ! GDO_State::public_verified( $app->state ) || ! GDO_Membership_Adapter::identity_assurance_current( $app->user_id ) || ! $verified_expiry ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_passport_not_eligible', __( 'A current verified application, intact approved snapshot, explicit future validity date, and current identity assurance are required for a professional passport.', 'global-doctor-onboarding' ) );
        }
        $raw_version = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(version) FROM {$table} WHERE user_id=%d FOR UPDATE", $app->user_id ) );
        if ( ! empty( $wpdb->last_error ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_passport_version', __( 'Professional passport version state could not be verified safely.', 'global-doctor-onboarding' ) );
        }
        $version = absint( $raw_version ) + 1;
        $uuid = wp_generate_uuid4();
        $issued = time();
        $exp = min( $issued + GDO_Advanced_Trust::PASSPORT_TTL, $verified_expiry );
        $scope = GDO_Advanced_Trust::verification_matrix( $app->user_id );
        $payload = array( 'passport_uuid'=>$uuid, 'user_id'=>absint( $app->user_id ), 'application_id'=>absint( $app->id ), 'version'=>$version, 'scope'=>$scope, 'iat'=>$issued, 'exp'=>$exp );
        $body = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
        $token = $body . '.' . hash_hmac( 'sha256', $body, $key );
        $now = current_time( 'mysql', true );
        $revoked = $wpdb->update( $table, array( 'status'=>'revoked', 'revoked_at'=>$now, 'revoke_reason'=>'superseded' ), array( 'user_id'=>$app->user_id, 'status'=>'active' ) );
        if ( false === $revoked ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_passport_supersede', __( 'Existing professional passports could not be superseded safely.', 'global-doctor-onboarding' ) );
        }
        $inserted = $wpdb->insert( $table, array(
            'passport_uuid'=>$uuid, 'user_id'=>$app->user_id, 'application_id'=>$app->id, 'version'=>$version, 'status'=>'active',
            'scope_json'=>wp_json_encode( $scope ), 'token_hash'=>hash( 'sha256', $token ),
            'issued_at'=>gmdate( 'Y-m-d H:i:s', $issued ), 'expires_at'=>gmdate( 'Y-m-d H:i:s', $exp ),
        ) );
        if ( 1 !== $inserted || false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_passport_store', __( 'The professional verification passport could not be issued.', 'global-doctor-onboarding' ) );
        }
        self::history_once( $app, 'verification_passport_issued', array( 'version'=>$version, 'expires_at'=>gmdate( 'c', $exp ) ), true );
        GDO_Membership_Adapter::audit( 'doctor_verification_passport_issued', array( 'application_id'=>$app->id, 'passport_uuid'=>$uuid, 'version'=>$version ) );
        return array( 'token'=>$token, 'passport_uuid'=>$uuid, 'verification_url'=>rest_url( GDO_Advanced_Trust::REST_NAMESPACE . '/public/passport/' . $uuid ), 'qr_payload'=>rest_url( GDO_Advanced_Trust::REST_NAMESPACE . '/public/passport/' . $uuid ) );
    }

    public static function ensure_passport( $application_id ) {
        $existing = GDO_Advanced_Trust::active_passport_for_application( $application_id );
        if ( $existing && ! is_wp_error( self::verify_passport_uuid( $existing->passport_uuid ) ) ) {
            return array( 'token'=>null, 'passport_uuid'=>$existing->passport_uuid, 'verification_url'=>rest_url( GDO_Advanced_Trust::REST_NAMESPACE . '/public/passport/' . $existing->passport_uuid ), 'qr_payload'=>rest_url( GDO_Advanced_Trust::REST_NAMESPACE . '/public/passport/' . $existing->passport_uuid ), 'reused'=>true );
        }
        return self::issue_passport( $application_id );
    }

    public static function verify_passport_uuid( $uuid ) {
        global $wpdb;
        $table = GDO_Advanced_Trust::table( 'verification_passports' );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT passport_uuid,user_id,application_id,version,status,issued_at,expires_at FROM {$table} WHERE passport_uuid=%s LIMIT 1", sanitize_text_field( $uuid ) ), ARRAY_A );
        if ( ! $row || 'active' !== $row['status'] || strtotime( $row['expires_at'] . ' UTC' ) <= time() ) {
            return new WP_Error( 'gdo_passport_inactive', __( 'This professional verification passport is not active.', 'global-doctor-onboarding' ), array( 'status'=>404 ) );
        }
        $app = GDO_Application::get( $row['application_id'] );
        $verified_expiry = GDO_Advanced_Trust::current_verification_expiry( $app );
        $approved_snapshot = $app ? GDO_Application::stored_approved_snapshot( $app ) : array();
        $passport_issued = ! empty( $row['issued_at'] ) ? strtotime( $row['issued_at'] . ' UTC' ) : 0;
        $snapshot_captured = ! empty( $approved_snapshot['captured_at'] ) ? strtotime( $approved_snapshot['captured_at'] . ' UTC' ) : 0;
        if ( ! $app || ! $approved_snapshot || ! $passport_issued || ! $snapshot_captured || $passport_issued < $snapshot_captured
            || absint( $app->user_id ) !== absint( $row['user_id'] ) || ! GDO_State::public_verified( $app->state )
            || ! GDO_Membership_Adapter::identity_assurance_current( $row['user_id'] ) || ! $verified_expiry ) {
            // Public verification is a read operation. It never mutates owner
            // state; lifecycle/reconciliation callbacks revoke derivatives.
            return new WP_Error( 'gdo_passport_inactive', __( 'This professional verification passport is not active.', 'global-doctor-onboarding' ), array( 'status'=>404 ) );
        }
        return array(
            'passport_uuid'=>$row['passport_uuid'], 'version'=>absint( $row['version'] ),
            'verification'=>GDO_Advanced_Trust::verification_matrix( $row['user_id'] ),
            'issued_at'=>$row['issued_at'], 'expires_at'=>$row['expires_at'],
            'cure_guarantee'=>false, 'clinical_authorization'=>false, 'professional_scope_only'=>true,
        );
    }

    public static function continuous_monitor() {
        global $wpdb;
        self::maybe_upgrade_schema();
        $table = GDO_Advanced_Trust::table( 'monitor_state' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE monitor_status IN ('scheduled','degraded') AND next_check_at<=%s ORDER BY next_check_at ASC LIMIT 50",
            current_time( 'mysql', true )
        ) );
        foreach ( (array) $rows as $row ) {
            $app = GDO_Application::get( $row->application_id );
            if ( ! $app ) { $wpdb->delete( $table, array( 'application_id'=>$row->application_id ) ); continue; }
            $result = 'no_license_evidence'; $provider_failure = false; $adverse = false;
            foreach ( GDO_Evidence::records( $app->id, true ) as $evidence ) {
                if ( ! in_array( $evidence->document_type, array( 'license','registration','professional_registration' ), true ) ) { continue; }
                $check = GDO_Advanced_Trust::primary_source_verify( $app->id, $evidence->id );
                $result = is_wp_error( $check ) ? $check->get_error_code() : $check['status'];
                if ( in_array( $result, array( 'provider_unavailable','provider_error','gdo_primary_source_rate','gdo_trust_check_store_failed' ), true ) ) { $provider_failure = true; }
                if ( in_array( $result, array( 'revoked','expired','not_matched' ), true ) ) {
                    $adverse = true;
                    do_action( 'gdo_continuous_verification_adverse_result', $app->id, $result, $check );
                }
            }
            $failures = $provider_failure ? min( 20, absint( $row->failure_count ) + 1 ) : 0;
            if ( $adverse ) { $delay = HOUR_IN_SECONDS; $status = 'scheduled'; }
            elseif ( $provider_failure ) { $delay = min( 7 * DAY_IN_SECONDS, HOUR_IN_SECONDS * (int) pow( 2, min( 7, $failures ) ) ); $status = 'degraded'; }
            else { $delay = 30 * DAY_IN_SECONDS; $status = 'scheduled'; }
            $next_check = time() + $delay;
            $updated = $wpdb->update( $table, array(
                'monitor_status'=>$status, 'last_checked_at'=>current_time( 'mysql', true ),
                'last_result'=>substr( sanitize_key( $result ), 0, 30 ), 'failure_count'=>$failures,
                'next_check_at'=>gmdate( 'Y-m-d H:i:s', $next_check ), 'updated_at'=>current_time( 'mysql', true ),
            ), array( 'application_id'=>$app->id ) );
            if ( false === $updated ) {
                GDO_Membership_Adapter::audit( 'doctor_continuous_verification_monitor_store_failed', array( 'application_id'=>$app->id ) );
                continue;
            }
            if ( $provider_failure ) {
                GDO_Membership_Adapter::audit( 'doctor_continuous_verification_provider_degraded', array( 'application_id'=>$app->id, 'failure_count'=>$failures, 'last_result'=>$result ) );
                $wake = self::schedule_wakeup( $next_check );
                if ( is_wp_error( $wake ) ) { GDO_Membership_Adapter::audit( 'doctor_reverification_wakeup_failed', array( 'application_id'=>$app->id, 'reason'=>$wake->get_error_code() ) ); }
            } elseif ( $adverse ) {
                $wake = self::schedule_wakeup( $next_check );
                if ( is_wp_error( $wake ) ) { GDO_Membership_Adapter::audit( 'doctor_reverification_wakeup_failed', array( 'application_id'=>$app->id, 'reason'=>$wake->get_error_code() ) ); }
            } elseif ( 'no_license_evidence' === $result ) {
                GDO_Membership_Adapter::audit( 'doctor_continuous_verification_license_missing', array( 'application_id'=>$app->id ) );
            }
        }
        self::cleanup_upload_sessions();
    }

    public static function cleanup_upload_sessions() {
        global $wpdb;
        $table = GDO_Advanced_Trust::table( 'upload_sessions' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT upload_uuid,temp_name FROM {$table} WHERE state IN ('open','failed','finalizing') AND expires_at<%s LIMIT 200",
            current_time( 'mysql', true )
        ) );
        if ( null === $rows || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_upload_cleanup_inventory', __( 'Expired resumable uploads could not be inventoried safely.', 'global-doctor-onboarding' ) );
        }
        foreach ( (array) $rows as $row ) {
            $dir = GDO_Storage::directory();
            $path = $dir ? trailingslashit( $dir ) . '.chunk-' . basename( sanitize_file_name( $row->temp_name ) ) : '';
            $deleted = true;
            if ( $path && is_file( $path ) && ! is_link( $path ) ) { $deleted = @unlink( $path ); }
            if ( ! $deleted ) {
                GDO_Membership_Adapter::audit( 'doctor_resumable_upload_cleanup_failed', array( 'upload_uuid'=>$row->upload_uuid ) );
                return new WP_Error( 'gdo_upload_cleanup_file', __( 'An expired resumable upload could not be deleted safely.', 'global-doctor-onboarding' ) );
            }
            $updated = $wpdb->update( $table, array( 'state'=>'expired', 'updated_at'=>current_time( 'mysql', true ) ), array( 'upload_uuid'=>$row->upload_uuid ) );
            if ( false === $updated ) {
                return new WP_Error( 'gdo_upload_cleanup_store', __( 'Expired resumable upload state could not be persisted safely.', 'global-doctor-onboarding' ) );
            }
        }
        return true;
    }

    public static function privacy_erase_application( $application_id, $user_id ) {
        global $wpdb;
        $application_id = absint( $application_id ); $user_id = absint( $user_id );
        $now = current_time( 'mysql', true );
        $uploads = $wpdb->get_results( $wpdb->prepare( 'SELECT upload_uuid,temp_name FROM ' . GDO_Advanced_Trust::table( 'upload_sessions' ) . ' WHERE application_id=%d', $application_id ) );
        if ( null === $uploads || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_privacy_upload_inventory', __( 'Private resumable upload inventory could not be verified before erasure.', 'global-doctor-onboarding' ) );
        }
        foreach ( (array) $uploads as $row ) {
            $dir = GDO_Storage::directory();
            $path = $dir ? trailingslashit( $dir ) . '.chunk-' . basename( sanitize_file_name( $row->temp_name ) ) : '';
            if ( $path && is_file( $path ) && ! is_link( $path ) && ! @unlink( $path ) ) {
                return new WP_Error( 'gdo_privacy_upload_cleanup', __( 'A private resumable upload could not be deleted.', 'global-doctor-onboarding' ) );
            }
        }
        if ( false === $wpdb->delete( GDO_Advanced_Trust::table( 'upload_sessions' ), array( 'application_id'=>$application_id ) )
            || false === $wpdb->delete( GDO_Advanced_Trust::table( 'monitor_state' ), array( 'application_id'=>$application_id ) )
            || false === $wpdb->delete( GDO_Advanced_Trust::table( 'verification_passports' ), array( 'application_id'=>$application_id ) ) ) {
            return new WP_Error( 'gdo_privacy_operational_cleanup', __( 'Advanced Trust operational records could not be removed.', 'global-doctor-onboarding' ) );
        }
        if ( false === $wpdb->update( GDO_Advanced_Trust::table( 'credential_checks' ), array( 'facts_json'=>'{"redacted":"privacy_erasure"}', 'explanation_json'=>'{"redacted":"privacy_erasure"}', 'external_reference'=>'', 'updated_at'=>$now ), array( 'application_id'=>$application_id ) )
            || false === $wpdb->update( GDO_Advanced_Trust::table( 'professional_history' ), array( 'user_id'=>0, 'public_safe'=>0, 'event_json'=>'{"redacted":"privacy_erasure"}', 'source_hash'=>hash( 'sha256', '{"redacted":"privacy_erasure"}' ) ), array( 'application_id'=>$application_id ) )
            || false === $wpdb->update( GDO_Advanced_Trust::table( 'reviewer_conflicts' ), array( 'applicant_id'=>0 ), array( 'application_id'=>$application_id, 'applicant_id'=>$user_id ) ) ) {
            return new WP_Error( 'gdo_privacy_anonymize', __( 'Advanced Trust accountability records could not be anonymized.', 'global-doctor-onboarding' ) );
        }
        // If the erasure subject also acted as a reviewer, pseudonymize the
        // conflict-role identifiers while preserving the fact that a conflict
        // existed. Governance tables for issuers/rules remain accountability
        // evidence unless their own retention/legal basis authorizes deletion.
        $queries = array(
            'reviewer_id' => "UPDATE " . GDO_Advanced_Trust::table( 'reviewer_conflicts' ) . ' SET reviewer_id=0 WHERE reviewer_id=%d',
            'declared_by' => "UPDATE " . GDO_Advanced_Trust::table( 'reviewer_conflicts' ) . ' SET declared_by=0 WHERE declared_by=%d',
            'resolved_by' => "UPDATE " . GDO_Advanced_Trust::table( 'reviewer_conflicts' ) . ' SET resolved_by=0 WHERE resolved_by=%d',
        );
        foreach ( $queries as $sql ) {
            if ( false === $wpdb->query( $wpdb->prepare( $sql, $user_id ) ) ) {
                return new WP_Error( 'gdo_privacy_reviewer_anonymize', __( 'Reviewer conflict identifiers could not be anonymized.', 'global-doctor-onboarding' ) );
            }
        }
        return true;
    }

    private static function rest_value( $value ) {
        if ( is_wp_error( $value ) ) {
            return array( 'ok'=>false, 'code'=>$value->get_error_code(), 'message'=>$value->get_error_message() );
        }
        return array( 'ok'=>true, 'result'=>$value );
    }

    public static function rest_overrides() {
        register_rest_route( GDO_Advanced_Trust::REST_NAMESPACE, '/trust/jurisdiction', array(
            'methods'=>'POST', 'callback'=>array( __CLASS__, 'rest_jurisdiction' ),
            'permission_callback'=>array( __CLASS__, 'rest_manage_permission' ),
        ), true );
        register_rest_route( GDO_Advanced_Trust::REST_NAMESPACE, '/trust/check/(?P<application_id>\d+)/(?P<evidence_id>\d+)', array(
            'methods'=>'POST', 'callback'=>array( __CLASS__, 'rest_check' ),
            'permission_callback'=>array( __CLASS__, 'rest_reviewer_permission' ),
        ), true );
        register_rest_route( GDO_Advanced_Trust::REST_NAMESPACE, '/public/passport/(?P<uuid>[a-f0-9-]{36})', array(
            'methods'=>'GET', 'callback'=>array( __CLASS__, 'rest_public_passport' ), 'permission_callback'=>'__return_true',
        ), true );
    }

    public static function rest_manage_permission() { return self::can_manage(); }
    public static function rest_reviewer_permission() {
        $uid = get_current_user_id();
        return $uid && GDO_Membership_Adapter::can( 'sabri_verify_doctors', $uid ) && GDO_Membership_Adapter::recent_step_up( $uid );
    }

    public static function rest_jurisdiction( WP_REST_Request $request ) {
        $p = (array) $request->get_json_params();
        $result = self::save_jurisdiction_rule(
            isset( $p['jurisdiction'] ) ? $p['jurisdiction'] : '',
            isset( $p['version'] ) ? $p['version'] : '',
            isset( $p['rules'] ) && is_array( $p['rules'] ) ? $p['rules'] : array(),
            isset( $p['status'] ) ? $p['status'] : 'draft',
            isset( $p['effective_from'] ) ? $p['effective_from'] : '',
            isset( $p['effective_until'] ) ? $p['effective_until'] : ''
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'saved'=>true ) );
    }

    public static function rest_check( WP_REST_Request $request ) {
        $app_id = absint( $request['application_id'] ); $evidence_id = absint( $request['evidence_id'] );
        $app = GDO_Application::get( $app_id ); $reviewer = get_current_user_id();
        if ( ! $app || ! GDO_Membership_Adapter::reviewer_case_allows( $reviewer, $app->user_id, $app->id ) ) {
            return new WP_Error( 'gdo_check_forbidden', __( 'The professional trust check is not authorized.', 'global-doctor-onboarding' ), array( 'status'=>403 ) );
        }
        $primary = GDO_Advanced_Trust::primary_source_verify( $app_id, $evidence_id );
        $auth = GDO_Advanced_Trust::authenticity_assessment( $app_id, $evidence_id );
        $ai = GDO_Advanced_Trust::ai_assistance( $app_id, $evidence_id );
        return rest_ensure_response( array(
            'primary_source'=>self::rest_value( $primary ), 'authenticity'=>self::rest_value( $auth ), 'ai_assist'=>self::rest_value( $ai ),
            'risk'=>GDO_Advanced_Trust::risk_explanation( $app_id ), 'dual_review'=>GDO_Advanced_Trust::requires_dual_review( $app_id ),
        ) );
    }

    public static function rest_public_passport( WP_REST_Request $request ) {
        $result = self::verify_passport_uuid( $request['uuid'] );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }
}
