<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only compatibility boundary to Files 00 and 02.
 *
 * File 09 never reads File 00 private metadata or TOTP/recovery storage. It
 * consumes versioned public assertions and exposes narrowly scoped decisions.
 */
final class GDO_Membership_Adapter {
	const FILE00_CONTRACT = 'smc.cf01.membership-assurance';
	const FILE00_VERSION  = '1.0.0';
	const FILE02_CONTRACT = 'sa.professional-reauthentication';
	const FILE02_VERSION  = '1.0.0';

	public static function available() {
		return defined( 'SMC_VERSION' )
			&& version_compare( (string) SMC_VERSION, '1.2.7', '>=' )
			&& defined( 'SMC_CF01_CONTRACT_VERSION' )
			&& self::FILE00_VERSION === (string) SMC_CF01_CONTRACT_VERSION
			&& class_exists( 'SMC_CF01_Contract' )
			&& is_callable( array( 'SMC_CF01_Contract', 'membership_assertion' ) );
	}

	public static function authentication_available() {
		return defined( 'SA_PROFESSIONAL_REAUTH_VERSION' )
			&& self::FILE02_VERSION === (string) SA_PROFESSIONAL_REAUTH_VERSION
			&& class_exists( 'SA_Professional_Reauthentication' )
			&& is_callable( array( 'SA_Professional_Reauthentication', 'verify_and_record' ) )
			&& is_callable( array( 'SA_Professional_Reauthentication', 'assertion' ) );
	}

	public static function membership_assertion( $user_id, $action = 'clinical_identity_link', $purpose = 'professional_verification', $jurisdiction = '' ) {
		$user_id = absint( $user_id );
		if ( ! self::available() || ! $user_id ) {
			return array();
		}
		$context = array(
			'action'       => sanitize_key( $action ),
			'purpose'      => sanitize_key( $purpose ),
			'trace_id'     => wp_generate_uuid4(),
			'jurisdiction' => strtoupper( preg_replace( '/[^A-Z]/i', '', (string) $jurisdiction ) ),
		);
		$assertion = SMC_CF01_Contract::membership_assertion( $user_id, $context );
		return self::valid_membership_assertion( $assertion ) ? $assertion : array();
	}

	public static function profile( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$profile = function_exists( 'smc_get_profile' ) ? (array) smc_get_profile( $user_id ) : array();
		$assertion = self::membership_assertion( $user_id );
		if ( ! $assertion ) {
			return $profile;
		}
		$membership = isset( $assertion['membership'] ) && is_array( $assertion['membership'] ) ? $assertion['membership'] : array();
		$age = isset( $assertion['age_context'] ) && is_array( $assertion['age_context'] ) ? $assertion['age_context'] : array();
		$subject = isset( $assertion['subject'] ) && is_array( $assertion['subject'] ) ? $assertion['subject'] : array();
		$profile['account_type']      = isset( $membership['membership_type'] ) ? sanitize_key( $membership['membership_type'] ) : '';
		$profile['membership_status'] = isset( $membership['status'] ) ? sanitize_key( $membership['status'] ) : 'unknown';
		$profile['email_verified']    = in_array( isset( $membership['identity_assurance'] ) ? $membership['identity_assurance'] : 'none', array( 'basic', 'verified' ), true );
		$profile['mobile_verified']   = 'verified' === ( isset( $membership['identity_assurance'] ) ? $membership['identity_assurance'] : 'none' );
		$profile['two_factor']        = ! empty( $membership['two_factor_ready'] );
		$profile['identity_verified'] = in_array( isset( $membership['identity_assurance'] ) ? $membership['identity_assurance'] : 'none', array( 'basic', 'verified' ), true );
		$profile['doctor_verified']   = 'verified' === ( isset( $membership['identity_assurance'] ) ? $membership['identity_assurance'] : 'none' );
		$profile['approval_version']  = absint( isset( $subject['record_version'] ) ? $subject['record_version'] : 0 );
		$profile['platform_uuid']     = isset( $subject['platform_uuid'] ) ? (string) $subject['platform_uuid'] : '';
		$profile['calculated_age']    = ! empty( $age['known'] ) ? absint( $age['age_years'] ) : 0;
		return $profile;
	}

	public static function status( $user_id ) {
		$assertion = self::membership_assertion( $user_id );
		return isset( $assertion['membership']['status'] ) ? sanitize_key( $assertion['membership']['status'] ) : 'dependency_missing';
	}

	public static function account_type( $user_id ) {
		$assertion = self::membership_assertion( $user_id );
		if ( isset( $assertion['membership']['membership_type'] ) && '' !== (string) $assertion['membership']['membership_type'] ) {
			return sanitize_key( $assertion['membership']['membership_type'] );
		}
		return isset( $assertion['membership']['account_class'] ) ? sanitize_key( $assertion['membership']['account_class'] ) : '';
	}

	public static function email_verified( $user_id ) {
		$assertion = self::membership_assertion( $user_id );
		$assurance = isset( $assertion['membership']['identity_assurance'] ) ? $assertion['membership']['identity_assurance'] : 'none';
		return in_array( $assurance, array( 'basic', 'verified' ), true );
	}

	public static function sanctioned( $user_id ) {
		$assertion = self::membership_assertion( $user_id );
		if ( ! $assertion ) {
			return true;
		}
		return ! empty( $assertion['membership']['suspended'] )
			|| in_array( isset( $assertion['membership']['status'] ) ? $assertion['membership']['status'] : 'unknown', array( 'suspended', 'rejected', 'revoked', 'blocked', 'banned' ), true );
	}

	public static function is_active_doctor_candidate( $user_id ) {
		$user_id = absint( $user_id );
		$assertion = self::membership_assertion( $user_id, 'clinical_identity_link', 'doctor_application' );
		if ( ! $assertion || 'allow' !== $assertion['result'] || self::sanctioned( $user_id ) ) {
			return false;
		}
		$type = self::account_type( $user_id );
		$age = isset( $assertion['age_context'] ) && is_array( $assertion['age_context'] ) ? $assertion['age_context'] : array();
		$assurance = isset( $assertion['membership']['identity_assurance'] ) ? $assertion['membership']['identity_assurance'] : 'none';
		$eligible = in_array( $type, array( 'doctor', 'sabri_doctor' ), true )
			&& ! empty( $age['known'] )
			&& absint( $age['age_years'] ) >= max( 18, absint( apply_filters( 'gdo_minimum_professional_age', 18, $user_id, $assertion ) ) )
			&& in_array( $assurance, array( 'basic', 'verified' ), true );
		return (bool) apply_filters( 'gdo_file00_doctor_application_eligible', $eligible, $user_id, $assertion );
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
		return (bool) apply_filters( 'gdo_file00_capability', $allowed, $capability, $user_id, $required );
	}

	public static function reviewer_scope_allows( $reviewer_id, $applicant_id, $application_id ) {
		$reviewer_id = absint( $reviewer_id );
		$applicant_id = absint( $applicant_id );
		$allowed = $reviewer_id && $reviewer_id !== $applicant_id && self::can( 'sabri_verify_doctors', $reviewer_id );
		return (bool) apply_filters( 'gdo_reviewer_scope_allows', $allowed, $reviewer_id, $applicant_id, absint( $application_id ) );
	}

	public static function recent_step_up( $user_id, $seconds = 900 ) {
		$user_id = absint( $user_id );
		if ( ! self::authentication_available() || ! $user_id || $user_id !== get_current_user_id() ) {
			return false;
		}
		$receipt = SA_Professional_Reauthentication::assertion( $user_id, self::review_scope( $user_id ) );
		if ( ! self::valid_reauthentication( $receipt, true ) ) {
			return false;
		}
		$verified_at = strtotime( (string) $receipt['verified_at'] );
		return false !== $verified_at && $verified_at >= time() - max( 60, absint( $seconds ) ) && self::subject_matches( $user_id, $receipt['subject_uuid'] );
	}

	public static function verify_step_up( $user_id, $password, $otp ) {
		$user_id = absint( $user_id );
		if ( ! self::authentication_available() || ! $user_id || $user_id !== get_current_user_id() ) {
			return new WP_Error( 'gdo_step_up_unavailable', __( 'File 02 professional reauthentication is unavailable.', 'global-doctor-onboarding' ) );
		}
		$receipt = SA_Professional_Reauthentication::verify_and_record(
			$user_id,
			(string) $password,
			(string) $otp,
			array(
				'scope'    => self::review_scope( $user_id ),
				'trace_id' => wp_generate_uuid4(),
			)
		);
		if ( ! self::valid_reauthentication( $receipt, true ) || ! self::subject_matches( $user_id, $receipt['subject_uuid'] ) ) {
			$reason = is_array( $receipt ) && isset( $receipt['reason_code'] ) ? sanitize_key( $receipt['reason_code'] ) : 'contract_invalid';
			return new WP_Error( 'gdo_step_up_' . $reason, __( 'Password and Authenticator verification failed or expired.', 'global-doctor-onboarding' ) );
		}
		self::audit( 'doctor_verification_step_up', array( 'actor_id' => $user_id, 'trace_id' => $receipt['trace_id'] ) );
		return true;
	}

	public static function clear_step_up( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( $user_id && self::authentication_available() && is_callable( array( 'SA_Professional_Reauthentication', 'clear_current_session' ) ) ) {
			SA_Professional_Reauthentication::clear_current_session();
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

	private static function review_scope( $user_id ) {
		return 'file09:professional-review-session:' . absint( $user_id );
	}

	private static function subject_matches( $user_id, $subject_uuid ) {
		$assertion = self::membership_assertion( $user_id, 'clinical_identity_link', 'professional_verification_review' );
		return $assertion
			&& isset( $assertion['subject']['platform_uuid'] )
			&& self::valid_uuid( $subject_uuid )
			&& hash_equals( (string) $assertion['subject']['platform_uuid'], (string) $subject_uuid );
	}

	private static function valid_membership_assertion( $assertion ) {
		if ( ! is_array( $assertion )
			|| self::FILE00_CONTRACT !== ( isset( $assertion['contract'] ) ? $assertion['contract'] : '' )
			|| self::FILE00_VERSION !== ( isset( $assertion['contract_version'] ) ? $assertion['contract_version'] : '' )
			|| ! in_array( isset( $assertion['result'] ) ? $assertion['result'] : '', array( 'allow', 'deny', 'unknown' ), true )
			|| ! isset( $assertion['subject']['platform_uuid'], $assertion['issued_at'], $assertion['expires_at'] ) ) {
			return false;
		}
		$issued = strtotime( (string) $assertion['issued_at'] );
		$expires = strtotime( (string) $assertion['expires_at'] );
		return self::valid_uuid( $assertion['subject']['platform_uuid'] )
			&& false !== $issued
			&& false !== $expires
			&& $issued <= time() + 60
			&& $expires > time()
			&& $expires <= time() + 120;
	}

	private static function valid_reauthentication( $receipt, $require_password ) {
		if ( ! is_array( $receipt )
			|| self::FILE02_CONTRACT !== ( isset( $receipt['contract'] ) ? $receipt['contract'] : '' )
			|| self::FILE02_VERSION !== ( isset( $receipt['contract_version'] ) ? $receipt['contract_version'] : '' )
			|| 'professional_verification_review' !== ( isset( $receipt['purpose'] ) ? $receipt['purpose'] : '' )
			|| 'valid' !== ( isset( $receipt['result'] ) ? $receipt['result'] : '' )
			|| 'aal2' !== ( isset( $receipt['assurance_level'] ) ? $receipt['assurance_level'] : '' )
			|| ( $require_password && empty( $receipt['password_verified'] ) ) ) {
			return false;
		}
		$verified = strtotime( isset( $receipt['verified_at'] ) ? $receipt['verified_at'] : '' );
		$expires = strtotime( isset( $receipt['expires_at'] ) ? $receipt['expires_at'] : '' );
		return self::valid_uuid( isset( $receipt['subject_uuid'] ) ? $receipt['subject_uuid'] : '' )
			&& false !== $verified
			&& false !== $expires
			&& $verified <= time() + 60
			&& $expires > time();
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
