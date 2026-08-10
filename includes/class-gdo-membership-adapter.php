<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only compatibility boundary to Files 00 and 02.
 *
 * File 09 never reads File 00 private metadata or TOTP/recovery storage. It
 * consumes versioned public assertions and exposes narrowly scoped decisions.
 */
final class GDO_Membership_Adapter {
	const FILE00_CONTRACT      = 'smc.cf01.membership-assurance';
	const FILE00_VERSION       = '1.0.0';
	const FILE00_BASE_VERSION  = '1.2.0';
	const FILE02_CONTRACT      = 'sa.professional-reauthentication';
	const FILE02_VERSION       = '1.0.0';

	public static function available() {
		return defined( 'SMC_VERSION' )
			&& version_compare( (string) SMC_VERSION, '1.2.7', '>=' )
			&& defined( 'SMC_CONTRACT_VERSION' )
			&& self::FILE00_BASE_VERSION === (string) SMC_CONTRACT_VERSION
			&& class_exists( 'SMC_Contracts' )
			&& is_callable( array( 'SMC_Contracts', 'assertions' ) )
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

	public static function base_assertion( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! self::available() || ! $user_id ) {
			return array();
		}
		$assertion = SMC_Contracts::assertions( $user_id );
		return self::valid_base_assertion( $assertion, $user_id ) ? $assertion : array();
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
		$base = self::base_assertion( $user_id );
		$subject = self::membership_assertion( $user_id );
		if ( ! $base ) {
			return $profile;
		}
		$profile['account_type']      = sanitize_key( $base['membership_type'] );
		$profile['membership_status'] = sanitize_key( $base['status'] );
		$profile['email_verified']    = ! empty( $base['email_verified'] );
		$profile['mobile_verified']   = ! empty( $base['phone_verified'] );
		$profile['two_factor']        = ! empty( $base['two_factor_ready'] );
		$profile['identity_verified'] = self::identity_assurance_current( $user_id );
		$profile['doctor_verified']   = function_exists( 'gdo_user_is_verified' ) ? (bool) gdo_user_is_verified( $user_id ) : false; // File 09 owns professional verification truth.
		$profile['approval_version']  = $subject && isset( $subject['subject']['record_version'] ) ? absint( $subject['subject']['record_version'] ) : 0;
		$profile['platform_uuid']     = $subject && isset( $subject['subject']['platform_uuid'] ) ? (string) $subject['subject']['platform_uuid'] : '';
		$profile['calculated_age']    = $subject && ! empty( $subject['age_context']['known'] ) ? absint( $subject['age_context']['age_years'] ) : 0;
		return $profile;
	}

	public static function status( $user_id ) {
		$base = self::base_assertion( $user_id );
		return isset( $base['status'] ) ? sanitize_key( $base['status'] ) : 'dependency_missing';
	}

	public static function account_type( $user_id ) {
		$base = self::base_assertion( $user_id );
		return isset( $base['membership_type'] ) ? sanitize_key( $base['membership_type'] ) : '';
	}

	public static function email_verified( $user_id ) {
		$base = self::base_assertion( $user_id );
		return ! empty( $base['email_verified'] );
	}

	public static function sanctioned( $user_id ) {
		$base = self::base_assertion( $user_id );
		if ( ! $base ) {
			return true;
		}
		return ! empty( $base['suspended'] )
			|| in_array( sanitize_key( $base['status'] ), array( 'suspended', 'rejected', 'revoked', 'expired', 'appeal_review', 'erasure_pending', 'invalid_application', 'blocked', 'banned' ), true );
	}

	public static function identity_assurance_current( $user_id ) {
		$base = self::base_assertion( $user_id );
		return $base
			&& ! empty( $base['application_exists'] )
			&& 'approved' === sanitize_key( $base['status'] )
			&& ! empty( $base['approved'] )
			&& ! empty( $base['identity_documents_current'] )
			&& ! empty( $base['email_verified'] )
			&& ! empty( $base['phone_verified'] )
			&& ! empty( $base['two_factor_ready'] );
	}

	public static function membership_allows( $assertion ) {
		return is_array( $assertion ) && isset( $assertion['result'] ) && 'allow' === sanitize_key( $assertion['result'] );
	}

	public static function is_active_doctor_candidate( $user_id, $jurisdiction = '' ) {
		$user_id = absint( $user_id );
		$base = self::base_assertion( $user_id );
		$subject = self::membership_assertion( $user_id, 'clinical_identity_link', 'doctor_application', $jurisdiction );
		if ( ! $base || ! self::membership_allows( $subject ) || self::sanctioned( $user_id ) || ! self::identity_assurance_current( $user_id ) ) {
			return false;
		}
		$age = isset( $subject['age_context'] ) && is_array( $subject['age_context'] ) ? $subject['age_context'] : array();
		$age_years = ! empty( $age['known'] ) ? absint( $age['age_years'] ) : 0;
		$minimum_age = max( 18, absint( apply_filters( 'gdo_minimum_professional_age', 18, $user_id, $base, $subject ) ) );
		$approved_types = isset( $base['approved_membership_types'] ) && is_array( $base['approved_membership_types'] ) ? array_map( 'sanitize_key', $base['approved_membership_types'] ) : array();
		$eligible = 'doctor' === sanitize_key( $base['membership_type'] )
			&& in_array( 'doctor', $approved_types, true )
			&& $age_years >= $minimum_age;
		// Professional verification itself is intentionally not required here: File 09 is the professional verifier.
		$filtered = (bool) apply_filters( 'gdo_file00_doctor_application_eligible', $eligible, $user_id, $base, $subject );
		return $eligible && $filtered;
	}

	private static function capability_map() {
		$map = array(
			'sabri_verify_doctors'               => array( 'smc_review_verification' ),
			'sabri_access_doctor_credentials'    => array( 'smc_view_private_documents' ),
			'sabri_manage_doctor_verification'   => array( 'smc_manage_membership' ),
			'sabri_finalize_doctor_verification' => array( 'smc_manage_membership' ),
		);
		// Baseline File 00 capability mapping is a security invariant; extension filters may only narrow in can().
		return $map;
	}

	public static function can( $capability, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$capability = sanitize_key( $capability );
		$base = self::base_assertion( $user_id );
		if ( ! $base || ! $user_id || empty( $base['approved'] ) || self::sanctioned( $user_id ) ) {
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
		$filtered = (bool) apply_filters( 'gdo_file00_capability', $allowed, $capability, $user_id, $required );
		return $allowed && $filtered;
	}

	public static function reviewer_scope_allows( $reviewer_id, $applicant_id, $application_id ) {
		$reviewer_id = absint( $reviewer_id );
		$applicant_id = absint( $applicant_id );
		$allowed = $reviewer_id && $reviewer_id !== $applicant_id && self::can( 'sabri_verify_doctors', $reviewer_id );
		if ( $allowed && $application_id ) {
			global $wpdb;
			$app = GDO_Application::get( $application_id );
			$profile = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . " WHERE user_id=%d AND status='active'", $reviewer_id ) );
			if ( ! $app || ! $profile || absint( $app->user_id ) !== $applicant_id ) {
				$allowed = false;
			} else {
				$jurisdictions = json_decode( $profile->jurisdictions_json, true );
				$languages = json_decode( $profile->languages_json, true );
				if ( $app->jurisdiction && ! in_array( $app->jurisdiction, (array) $jurisdictions, true ) ) {
					$allowed = false;
				}
				if ( $allowed && $app->preferred_language && ! in_array( $app->preferred_language, (array) $languages, true ) && ! in_array( 'en-US', (array) $languages, true ) ) {
					$allowed = false;
				}
			}
		}
		$filtered = (bool) apply_filters( 'gdo_reviewer_scope_allows', $allowed, $reviewer_id, $applicant_id, absint( $application_id ) );
		return $allowed && $filtered;
	}

	public static function reviewer_case_allows( $reviewer_id, $applicant_id, $application_id ) {
		global $wpdb;
		$reviewer_id = absint( $reviewer_id );
		$applicant_id = absint( $applicant_id );
		$application_id = absint( $application_id );
		if ( ! $application_id || ! self::reviewer_scope_allows( $reviewer_id, $applicant_id, $application_id ) ) {
			return false;
		}
		$app = GDO_Application::get( $application_id );
		if ( ! $app || absint( $app->user_id ) !== $applicant_id ) {
			return false;
		}

		// Broad reviewer eligibility is a routing predicate, not private-evidence
		// authorization. Routine access must be tied to an active case relation.
		if ( absint( $app->assigned_reviewer_id ) === $reviewer_id && in_array( $app->state, array( 'under_review', 'more_information', 'recommended' ), true ) ) {
			return true;
		}

		// A recommended case intentionally has no pre-assigned finalizer. A
		// separately authorized finalizer may inspect it, except the recommender.
		if ( 'recommended' === $app->state
			&& absint( $app->recommender_id ) !== $reviewer_id
			&& self::can( 'sabri_finalize_doctor_verification', $reviewer_id ) ) {
			return true;
		}

		// Appeals are independently assigned and never inherit the original case
		// reviewer/finalizer relationship.
		if ( 'appeal_pending' === $app->state ) {
			$appeal_reviewer = absint( $wpdb->get_var( $wpdb->prepare(
				'SELECT assigned_reviewer_id FROM ' . GDO_Schema::table( 'appeals' ) . " WHERE application_id=%d AND status='open' ORDER BY id DESC LIMIT 1",
				$application_id
			) ) );
			if ( $appeal_reviewer && $appeal_reviewer === $reviewer_id ) {
				return true;
			}
		}

		// Post-decision lifecycle investigation is restricted to the native
		// management capability and states where that investigation is relevant.
		if ( in_array( $app->state, array( 'verified', 'reinstated', 'renewal_due', 'suspended', 'revoked' ), true )
			&& self::can( 'sabri_manage_doctor_verification', $reviewer_id ) ) {
			return true;
		}

		return false;
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
		return self::membership_allows( $assertion )
			&& isset( $assertion['subject']['platform_uuid'] )
			&& self::valid_uuid( $subject_uuid )
			&& hash_equals( (string) $assertion['subject']['platform_uuid'], (string) $subject_uuid );
	}

	private static function valid_base_assertion( $assertion, $user_id ) {
		$required = array( 'contract_version', 'user_id', 'application_exists', 'account_class', 'membership_type', 'approved_membership_types', 'status', 'approved', 'suspended', 'eligible', 'two_factor_ready', 'phone_verified', 'email_verified', 'guardian_verified', 'professional_verified', 'identity_documents_current' );
		if ( ! is_array( $assertion ) || self::FILE00_BASE_VERSION !== ( isset( $assertion['contract_version'] ) ? (string) $assertion['contract_version'] : '' ) ) {
			return false;
		}
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $assertion ) ) {
				return false;
			}
		}
		return absint( $assertion['user_id'] ) === absint( $user_id )
			&& is_array( $assertion['approved_membership_types'] );
	}

	private static function valid_membership_assertion( $assertion ) {
		if ( ! is_array( $assertion )
			|| self::FILE00_CONTRACT !== ( isset( $assertion['contract'] ) ? $assertion['contract'] : '' )
			|| self::FILE00_VERSION !== ( isset( $assertion['contract_version'] ) ? $assertion['contract_version'] : '' )
			|| ! in_array( isset( $assertion['result'] ) ? $assertion['result'] : '', array( 'allow', 'deny', 'unknown' ), true )
			|| ! isset( $assertion['subject']['platform_uuid'], $assertion['subject']['record_version'], $assertion['membership'], $assertion['age_context'], $assertion['jurisdiction_context'], $assertion['issued_at'], $assertion['expires_at'] )
			|| ! is_array( $assertion['membership'] ) || ! is_array( $assertion['age_context'] ) || ! is_array( $assertion['jurisdiction_context'] ) ) {
			return false;
		}
		$issued = strtotime( (string) $assertion['issued_at'] );
		$expires = strtotime( (string) $assertion['expires_at'] );
		return self::valid_uuid( $assertion['subject']['platform_uuid'] )
			&& absint( $assertion['subject']['record_version'] ) > 0
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
