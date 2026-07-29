<?php
defined( 'ABSPATH' ) || exit;

/** Read-only boundary to File 00. File 09 never creates roles or account types. */
final class GDO_Membership_Adapter {
    public static function available() {
        return defined( 'SMC_VERSION' )
            && function_exists( 'smc_get_profile' )
            && function_exists( 'smc_user_status' );
    }

    public static function profile( $user_id ) {
        return self::available() ? (array) smc_get_profile( absint( $user_id ) ) : array();
    }

    public static function status( $user_id ) {
        return self::available() ? sanitize_key( (string) smc_user_status( absint( $user_id ) ) ) : 'dependency_missing';
    }

    public static function account_type( $user_id ) {
        $profile = self::profile( $user_id );
        return isset( $profile['account_type'] ) ? sanitize_key( (string) $profile['account_type'] ) : '';
    }

    public static function email_verified( $user_id ) {
        $profile = self::profile( $user_id );
        $value = isset( $profile['email_verified'] ) ? (bool) $profile['email_verified'] : false;
        return (bool) apply_filters( 'gdo_file00_email_verified', $value, absint( $user_id ), $profile );
    }

    public static function sanctioned( $user_id ) {
        $status = self::status( $user_id );
        $sanctioned = in_array( $status, array( 'suspended', 'rejected', 'revoked', 'blocked', 'banned' ), true );
        return (bool) apply_filters( 'gdo_file00_user_sanctioned', $sanctioned, absint( $user_id ), $status );
    }

    public static function is_active_doctor_candidate( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! self::available() || ! $user_id || self::sanctioned( $user_id ) || ! self::email_verified( $user_id ) ) {
            return false;
        }
        $approved = in_array( self::status( $user_id ), array( 'approved', 'verified', 'active' ), true );
        $doctor = in_array( self::account_type( $user_id ), array( 'doctor', 'sabri_doctor' ), true );
        $profile = self::profile( $user_id );
        $unique = ! empty( $profile['identity_unique'] );
        $adult = ! empty( $profile['age_verified'] );
        $unique = (bool) apply_filters( 'gdo_file00_identity_unique', $unique, $user_id, $profile );
        $adult = (bool) apply_filters( 'gdo_file00_professional_age_verified', $adult, $user_id, $profile );
        $eligible = $approved && $doctor && $unique && $adult;
        return (bool) apply_filters( 'gdo_file00_doctor_application_eligible', $eligible, $user_id, $profile );
    }

    public static function can( $capability, $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! self::available() || ! $user_id || self::sanctioned( $user_id ) ) {
            return false;
        }
        $allowed = user_can( $user_id, sanitize_key( $capability ) );
        return (bool) apply_filters( 'gdo_file00_capability', $allowed, sanitize_key( $capability ), $user_id );
    }

    public static function reviewer_scope_allows( $reviewer_id, $applicant_id, $application_id ) {
        $allowed = self::can( 'sabri_verify_doctors', $reviewer_id );
        return (bool) apply_filters( 'gdo_reviewer_scope_allows', $allowed, absint( $reviewer_id ), absint( $applicant_id ), absint( $application_id ) );
    }

    public static function recent_step_up( $user_id, $seconds = 900 ) {
        $user_id = absint( $user_id );
        $at = absint( get_user_meta( $user_id, '_smc_recent_step_up_at', true ) );
        $ok = $at > 0 && ( time() - $at ) <= absint( $seconds );
        return (bool) apply_filters( 'gdo_recent_step_up', $ok, $user_id, absint( $seconds ) );
    }

    public static function audit( $event, array $context = array() ) {
        $event = sanitize_key( $event );
        if ( function_exists( 'smc_audit_event' ) ) {
            smc_audit_event( $event, $context );
        }
        do_action( 'gdo_canonical_audit_event', $event, $context );
    }
}
