<?php
defined( 'ABSPATH' ) || exit;

/**
 * Versioned File 09 policy and eligibility rules.
 *
 * This class contains no File 00 storage access. It consumes only the public
 * adapter and returns bounded reason codes suitable for UI and audit trails.
 */
final class GDO_Policy {
	const VERSION       = '2026-08-07.2';
	const TERMS_VERSION = 'doctor-verification-2026-08-07';
	const DRAFT_DAYS    = 30;

	public static function evidence_types( $jurisdiction = '', $application_type = 'homeopathic_doctor' ) {
		$minimum = array(
			'identity'      => __( 'Government identity document', 'global-doctor-onboarding' ),
			'qualification' => __( 'Professional qualification certificate', 'global-doctor-onboarding' ),
			'license'       => __( 'Current professional license or registration', 'global-doctor-onboarding' ),
		);
		$filtered = (array) apply_filters( 'gdo_required_evidence_types', $minimum, self::normalize_jurisdiction( $jurisdiction ), sanitize_key( $application_type ) );
		$types = array();
		foreach ( array_merge( $minimum, $filtered ) as $key => $label ) {
			$key = sanitize_key( $key );
			if ( $key ) {
				$types[ $key ] = wp_strip_all_tags( (string) $label );
			}
		}
		foreach ( $minimum as $key => $label ) {
			if ( ! isset( $types[ $key ] ) ) {
				$types[ $key ] = $label;
			}
		}
		return $types;
	}

	public static function required_fields( $jurisdiction = '', $application_type = 'homeopathic_doctor' ) {
		$fields = array(
			'display_name', 'country', 'city', 'professional_title', 'qualification',
			'license_number', 'licensing_authority', 'license_jurisdiction',
			'experience_years', 'specialty', 'languages', 'consultation_modes',
			'phone', 'bio', 'declaration_accuracy', 'declaration_no_impersonation',
			'declaration_professional_scope',
		);
		$filtered = (array) apply_filters( 'gdo_required_profile_fields', $fields, self::normalize_jurisdiction( $jurisdiction ), sanitize_key( $application_type ) );
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_merge( $fields, $filtered ) ) ) ) );
	}

	public static function normalize_jurisdiction( $value ) {
		$value = strtoupper( trim( (string) $value ) );
		$map = array(
			'PAKISTAN' => 'PK', 'PK' => 'PK',
			'INDIA' => 'IN', 'IN' => 'IN',
			'UNITED KINGDOM' => 'GB', 'UK' => 'GB', 'GB' => 'GB',
			'UNITED STATES' => 'US', 'UNITED STATES OF AMERICA' => 'US', 'USA' => 'US', 'US' => 'US',
		);
		if ( isset( $map[ $value ] ) ) {
			return $map[ $value ];
		}
		return substr( preg_replace( '/[^A-Z0-9-]/', '', $value ), 0, 16 );
	}

	public static function eligibility( $user_id, array $context = array() ) {
		$user_id = absint( $user_id );
		$result = array(
			'eligible'       => false,
			'reason_code'    => 'dependency_unavailable',
			'policy_version' => self::VERSION,
			'checked_at'     => gmdate( 'c' ),
			'trace_id'       => wp_generate_uuid4(),
			'jurisdiction'   => self::normalize_jurisdiction( isset( $context['jurisdiction'] ) ? $context['jurisdiction'] : '' ),
		);
		if ( ! $user_id || ! GDO_Membership_Adapter::available() ) {
			return $result;
		}
		$base = GDO_Membership_Adapter::base_assertion( $user_id );
		$subject = GDO_Membership_Adapter::membership_assertion( $user_id, 'clinical_identity_link', 'doctor_application', $result['jurisdiction'] );
		if ( ! $base || ! GDO_Membership_Adapter::membership_allows( $subject ) ) {
			$result['reason_code'] = $subject ? 'membership_context_denied' : 'dependency_unavailable';
			return $result;
		}
		if ( GDO_Membership_Adapter::sanctioned( $user_id ) ) {
			$result['reason_code'] = 'membership_restricted';
			return $result;
		}
		$approved_types = isset( $base['approved_membership_types'] ) && is_array( $base['approved_membership_types'] ) ? array_map( 'sanitize_key', $base['approved_membership_types'] ) : array();
		if ( empty( $base['application_exists'] ) || 'approved' !== sanitize_key( $base['status'] ) || 'doctor' !== sanitize_key( $base['membership_type'] ) || empty( $base['approved'] ) || ! in_array( 'doctor', $approved_types, true ) ) {
			$result['reason_code'] = 'doctor_membership_not_approved';
			return $result;
		}
		if ( empty( $base['identity_documents_current'] ) || empty( $base['email_verified'] ) || empty( $base['phone_verified'] ) || empty( $base['two_factor_ready'] ) ) {
			$result['reason_code'] = 'identity_assurance_incomplete';
			return $result;
		}
		$age = ! empty( $subject['age_context']['known'] ) ? absint( $subject['age_context']['age_years'] ) : 0;
		$minimum = max( 18, absint( apply_filters( 'gdo_minimum_professional_age', 18, $user_id, $base, $subject ) ) );
		if ( ! $age || $age < $minimum ) {
			$result['reason_code'] = 'professional_age_not_met';
			return $result;
		}
		global $wpdb;
		$wpdb->last_error = '';
		$latest = GDO_Application::latest_for_user( $user_id );
		if ( ! empty( $wpdb->last_error ) ) {
			$result['reason_code'] = 'database_unavailable';
			return $result;
		}
		if ( $latest && in_array( $latest->state, array( 'submitted','under_review','more_information','resubmitted','recommended','appeal_pending' ), true ) ) {
			$result['reason_code'] = 'active_application_exists';
			$result['application_id'] = absint( $latest->id );
			return $result;
		}
		if ( $latest && in_array( $latest->state, array( 'rejected','suspended','revoked' ), true ) ) {
			$result['reason_code'] = 'appeal_or_lifecycle_action_required';
			$result['application_id'] = absint( $latest->id );
			return $result;
		}
		$result['eligible'] = true;
		$result['reason_code'] = 'eligible';
		$result['subject_uuid'] = isset( $subject['subject']['platform_uuid'] ) ? (string) $subject['subject']['platform_uuid'] : '';
		$result['membership_record_version'] = isset( $subject['subject']['record_version'] ) ? absint( $subject['subject']['record_version'] ) : 0;
		$filtered = apply_filters( 'gdo_eligibility_result', $result, $user_id, $context );
		if ( ! is_array( $filtered ) ) {
			return $result;
		}
		if ( empty( $filtered['eligible'] ) ) {
			$result['eligible'] = false;
			$result['reason_code'] = isset( $filtered['reason_code'] ) ? sanitize_key( $filtered['reason_code'] ) : 'eligibility_narrowed';
		}
		return $result;
	}

	public static function normalize_date( $value ) {
		$value = trim( (string) $value );
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return new WP_Error( 'gdo_date_format', __( 'Dates must use a real YYYY-MM-DD calendar date.', 'global-doctor-onboarding' ) );
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if ( ! $date || ( is_array( $errors ) && ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) ) ) || $date->format( 'Y-m-d' ) !== $value ) {
			return new WP_Error( 'gdo_date_invalid', __( 'The supplied calendar date is invalid.', 'global-doctor-onboarding' ) );
		}
		return $date->format( 'Y-m-d' );
	}

	public static function normalize_future_date( $value ) {
		$date = self::normalize_date( $value );
		if ( is_wp_error( $date ) ) {
			return $date;
		}
		$expires = strtotime( $date . ' 23:59:59 UTC' );
		return $expires > time() ? $date : new WP_Error( 'gdo_date_not_future', __( 'The date must be in the future.', 'global-doctor-onboarding' ) );
	}

	public static function draft_expiry() {
		return gmdate( 'Y-m-d H:i:s', time() + max( 7, absint( apply_filters( 'gdo_draft_expiry_days', self::DRAFT_DAYS ) ) ) * DAY_IN_SECONDS );
	}

	public static function valid_reason_code( $code ) {
		$allowed = array(
			'eligibility_failed','application_submitted','review_started','more_information_required',
			'application_resubmitted','verification_recommended','verification_finalized',
			'verification_lifecycle','appeal_filed','appeal_resolved','verification_expired',
			'application_withdrawn','risk_signal_resolved','quality_correction',
		);
		return in_array( sanitize_key( $code ), $allowed, true );
	}
}
