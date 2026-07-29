<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only compatibility boundary to File 00.
 *
 * File 09 never creates roles, changes account types, or changes File 00
 * membership status. It consumes File 00's published functions, capabilities,
 * profile table and security service, then exposes narrowly-scoped decisions.
 */
final class GDO_Membership_Adapter {
    public static function available() {
        return defined( 'SMC_VERSION' )
            && function_exists( 'smc_get_profile' )
            && function_exists( 'smc_user_status' )
            && function_exists( 'smc_profile_value' );
    }

    public static function profile( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! self::available() || ! $user_id ) {
            return array();
        }
        $profile = (array) smc_get_profile( $user_id );
        $profile['email_verified']    = (bool) get_user_meta( $user_id, '_smc_email_verified', true );
        $profile['mobile_verified']   = (bool) get_user_meta( $user_id, '_smc_mobile_verified', true );
        $profile['two_factor']        = (bool) get_user_meta( $user_id, '_smc_2fa_enabled', true );
        $profile['identity_verified'] = (bool) get_user_meta( $user_id, '_smc_identity_verified', true );
        $profile['doctor_verified']   = (bool) get_user_meta( $user_id, '_smc_doctor_verified', true );
        $profile['approval_version']  = absint( get_user_meta( $user_id, '_smc_approval_version', true ) );
        return $profile;
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
        $value = ! empty( $profile['email_verified'] );
        return (bool) apply_filters( 'gdo_file00_email_verified', $value, absint( $user_id ), $profile );
    }

    public static function sanctioned( $user_id ) {
        $status = self::status( $user_id );
        $sanctioned = in_array( $status, array( 'suspended', 'rejected', 'revoked', 'blocked', 'banned' ), true );
        return (bool) apply_filters( 'gdo_file00_user_sanctioned', $sanctioned, absint( $user_id ), $status );
    }

    public static function is_active_doctor_candidate( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! self::available() || ! $user_id || self::sanctioned( $user_id ) ) {
            return false;
        }
        $profile = self::profile( $user_id );
        $minimum_age = max( 18, absint( apply_filters( 'gdo_minimum_professional_age', 18, $user_id, $profile ) ) );
        $approved = in_array( self::status( $user_id ), array( 'approved', 'verified', 'active' ), true );
        $doctor = in_array( self::account_type( $user_id ), array( 'doctor', 'sabri_doctor' ), true );
        $identity_verified = ! empty( $profile['identity_verified'] ) && ! empty( $profile['approval_version'] );
        $identity_unique = (bool) apply_filters( 'gdo_file00_identity_unique', $identity_verified, $user_id, $profile );
        $age_verified = isset( $profile['calculated_age'] ) && absint( $profile['calculated_age'] ) >= $minimum_age;
        $age_verified = (bool) apply_filters( 'gdo_file00_professional_age_verified', $age_verified, $user_id, $profile );
        $eligible = $approved && $doctor && self::email_verified( $user_id ) && $identity_unique && $age_verified;
        return (bool) apply_filters( 'gdo_file00_doctor_application_eligible', $eligible, $user_id, $profile );
    }

    private static function capability_map() {
        $map = array(
            'sabri_verify_doctors'               => array( 'smc_review_verification' ),
            'sabri_access_doctor_credentials'    => array( 'smc_view_private_documents' ),
            'sabri_manage_doctor_verification'   => array( 'smc_manage_membership' ),
            'sabri_finalize_doctor_verification' => array( 'smc_manage_membership' ),
        );
        return (array) apply_filters( 'gdo_file00_capability_map', $map );
    }

    public static function can( $capability, $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        $capability = sanitize_key( $capability );
        if ( ! self::available() || ! $user_id || self::sanctioned( $user_id ) ) {
            return false;
        }
        $map = self::capability_map();
        $required = isset( $map[ $capability ] ) ? (array) $map[ $capability ] : array( $capability );
        $allowed = false;
        foreach ( $required as $file00_capability ) {
            if ( user_can( $user_id, sanitize_key( $file00_capability ) ) ) {
                $allowed = true;
                break;
            }
        }
        return (bool) apply_filters( 'gdo_file00_capability', $allowed, $capability, $user_id, $required );
    }

    public static function reviewer_scope_allows( $reviewer_id, $applicant_id, $application_id ) {
        $reviewer_id = absint( $reviewer_id );
        $applicant_id = absint( $applicant_id );
        $allowed = $reviewer_id && $reviewer_id !== $applicant_id && self::can( 'sabri_verify_doctors', $reviewer_id );
        return (bool) apply_filters( 'gdo_reviewer_scope_allows', $allowed, $reviewer_id, $applicant_id, absint( $application_id ) );
    }

    private static function step_up_key( $user_id ) {
        $token = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
        return 'gdo_step_up_' . hash( 'sha256', absint( $user_id ) . '|' . $token . '|' . wp_salt( 'auth' ) );
    }

    public static function recent_step_up( $user_id, $seconds = 900 ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || $user_id !== get_current_user_id() ) {
            return false;
        }
        $at = absint( get_transient( self::step_up_key( $user_id ) ) );
        $ok = $at > 0 && ( time() - $at ) <= max( 60, absint( $seconds ) );
        return (bool) apply_filters( 'gdo_recent_step_up', $ok, $user_id, absint( $seconds ) );
    }

    public static function verify_step_up( $user_id, $password, $otp ) {
        $user_id = absint( $user_id );
        $user = get_userdata( $user_id );
        if ( ! $user || ! wp_check_password( (string) $password, $user->user_pass, $user_id ) ) {
            return new WP_Error( 'gdo_step_up_password', __( 'The current password is invalid.', 'global-doctor-onboarding' ) );
        }
        if ( ! class_exists( 'SMC_Security' ) || ! method_exists( 'SMC_Security', 'verify_totp' ) ) {
            return new WP_Error( 'gdo_step_up_unavailable', __( 'File 00 two-factor verification is unavailable.', 'global-doctor-onboarding' ) );
        }
        $secret = (string) get_user_meta( $user_id, '_smc_totp_secret', true );
        $valid = $secret && SMC_Security::verify_totp( $secret, sanitize_text_field( (string) $otp ) );
        if ( ! $valid ) {
            return new WP_Error( 'gdo_step_up_otp', __( 'The Authenticator code is invalid.', 'global-doctor-onboarding' ) );
        }
        set_transient( self::step_up_key( $user_id ), time(), 15 * MINUTE_IN_SECONDS );
        self::audit( 'doctor_verification_step_up', array( 'actor_id'=>$user_id ) );
        return true;
    }

    public static function clear_step_up( $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( $user_id ) {
            delete_transient( self::step_up_key( $user_id ) );
        }
    }

    public static function audit( $event, array $context = array() ) {
        $event = sanitize_key( $event );
        if ( class_exists( 'SMC_Security' ) && method_exists( 'SMC_Security', 'audit' ) ) {
            $subject = isset( $context['user_id'] ) ? absint( $context['user_id'] ) : ( isset( $context['applicant_id'] ) ? absint( $context['applicant_id'] ) : 0 );
            $object_id = isset( $context['application_id'] ) ? absint( $context['application_id'] ) : 0;
            SMC_Security::audit( $event, $subject, 'doctor_verification', $object_id, $context );
        }
        do_action( 'gdo_canonical_audit_event', $event, $context );
    }
}
