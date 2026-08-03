<?php
defined( 'ABSPATH' ) || exit;

/**
 * Privacy-minimal professional eligibility provider for CF-01.
 *
 * An allow result means only that File 09's current professional verification
 * evidence is eligible for the requested professional purpose. It never grants
 * clinical object, field, treating-relationship, consent or authentication
 * authority.
 */
final class GDO_CF01_Practitioner_Contract {
	const CONTRACT_NAME    = 'gdo.cf01.practitioner-eligibility';
	const CONTRACT_VERSION = '1.0.0';
	const ASSERTION_TTL     = 60;

	private static $actions = array(
		'clinical_identity_link',
		'clinical_read',
		'clinical_write',
		'prescription_sign',
		'clinical_export',
		'clinical_transfer',
		'break_glass',
	);

	private static $scope_sensitive_actions = array(
		'prescription_sign',
		'break_glass',
	);

	/**
	 * Return action-time professional eligibility evidence.
	 *
	 * @param int   $user_id Practitioner WordPress user ID.
	 * @param array $context action, purpose, jurisdiction and trace_id.
	 * @return array<string,mixed>
	 */
	public static function assertion( $user_id, $context = array() ) {
		$user_id = absint( $user_id );
		$context = is_array( $context ) ? $context : array();
		$action  = sanitize_key( isset( $context['action'] ) ? $context['action'] : '' );
		$purpose = sanitize_key( isset( $context['purpose'] ) ? $context['purpose'] : '' );
		$trace   = self::trace_id( isset( $context['trace_id'] ) ? $context['trace_id'] : '' );
		$now     = time();
		$result  = array(
			'contract'         => self::CONTRACT_NAME,
			'contract_version' => self::CONTRACT_VERSION,
			'producer_version' => defined( 'GDO_VERSION' ) ? GDO_VERSION : '',
			'issued_at'        => gmdate( 'c', $now ),
			'expires_at'       => gmdate( 'c', $now + self::ASSERTION_TTL ),
			'trace_id'         => $trace,
			'action'           => $action,
			'purpose'          => $purpose,
			'result'           => 'unknown',
			'reason_code'      => 'unresolved',
			'grants_clinical_authorization' => false,
		);

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			$result['reason_code'] = 'subject_unavailable';
			return $result;
		}
		if ( ! in_array( $action, self::$actions, true ) || '' === $purpose ) {
			$result['reason_code'] = 'unsupported_action_or_purpose';
			return $result;
		}
		if ( ! GDO_Membership_Adapter::available() ) {
			$result['reason_code'] = 'file00_contract_unavailable';
			return $result;
		}

		$membership = GDO_Membership_Adapter::membership_assertion(
			$user_id,
			'clinical_identity_link',
			'cf01_practitioner_' . $purpose,
			isset( $context['jurisdiction'] ) ? $context['jurisdiction'] : ''
		);
		if ( ! $membership ) {
			$result['reason_code'] = 'file00_contract_invalid';
			return $result;
		}
		$result['subject'] = array(
			'platform_uuid' => (string) $membership['subject']['platform_uuid'],
			'source_owner'  => 'File 00',
			'membership_record_version' => absint( isset( $membership['subject']['record_version'] ) ? $membership['subject']['record_version'] : 0 ),
		);
		$result['membership'] = array(
			'status'    => sanitize_key( isset( $membership['membership']['status'] ) ? $membership['membership']['status'] : 'unknown' ),
			'active'    => ! empty( $membership['membership']['active'] ),
			'suspended' => ! empty( $membership['membership']['suspended'] ),
			'identity_assurance' => sanitize_key( isset( $membership['membership']['identity_assurance'] ) ? $membership['membership']['identity_assurance'] : 'none' ),
		);
		if ( 'allow' !== $membership['result'] || ! empty( $result['membership']['suspended'] ) ) {
			$result['result'] = 'deny';
			$result['reason_code'] = 'membership_' . sanitize_key( isset( $membership['reason_code'] ) ? $membership['reason_code'] : 'denied' );
			return $result;
		}

		$decision = GDO_API::latest_decision( $user_id );
		if ( ! self::valid_decision( $decision ) ) {
			$result['reason_code'] = 'professional_decision_invalid';
			return $result;
		}
		$result['professional_record'] = array(
			'application_uuid'    => (string) $decision['application_uuid'],
			'application_version' => absint( $decision['version'] ),
			'row_version'         => absint( $decision['row_version'] ),
			'state'               => sanitize_key( $decision['state'] ),
			'verified_until'      => (string) $decision['verified_until'],
			'fingerprint'         => (string) $decision['fingerprint'],
			'checked_at'          => (string) $decision['checked_at'],
		);
		if ( empty( $decision['verified'] ) || ! in_array( $decision['state'], array( 'verified', 'reinstated' ), true ) ) {
			$result['result'] = 'deny';
			$result['reason_code'] = 'professional_status_' . sanitize_key( $decision['state'] );
			return $result;
		}

		$snapshot = GDO_Application::approved_snapshot( $decision['application_id'] );
		if ( ! self::valid_snapshot( $snapshot, $decision ) ) {
			$result['result'] = 'deny';
			$result['reason_code'] = 'approved_snapshot_invalid';
			return $result;
		}
		$evidence = self::evidence_projection( $snapshot );
		$result['evidence'] = $evidence;
		if ( ! self::evidence_current( $evidence ) ) {
			$result['result'] = 'deny';
			$result['reason_code'] = 'professional_evidence_not_current';
			return $result;
		}

		$scope = self::scope_projection( $snapshot, $user_id, $decision, $context );
		$result['professional_scope'] = $scope;
		$result['authorization_limits'] = array(
			'requires_current_file00_membership' => true,
			'requires_file02_authentication_assurance' => true,
			'requires_cf01_treating_relationship' => true,
			'requires_cf01_consent_guardian_and_purpose' => true,
			'requires_cf01_object_field_and_record_version' => true,
			'appointment_does_not_create_relationship' => true,
			'public_badge_does_not_grant_access' => true,
		);

		$jurisdiction = self::jurisdiction_decision( $scope, isset( $context['jurisdiction'] ) ? $context['jurisdiction'] : '' );
		$result['jurisdiction'] = $jurisdiction;
		if ( 'deny' === $jurisdiction['result'] ) {
			$result['result'] = 'deny';
			$result['reason_code'] = $jurisdiction['reason_code'];
			return $result;
		}
		if ( 'unknown' === $jurisdiction['result'] && '' !== trim( (string) ( isset( $context['jurisdiction'] ) ? $context['jurisdiction'] : '' ) ) ) {
			$result['reason_code'] = $jurisdiction['reason_code'];
			return $result;
		}
		if ( in_array( $action, self::$scope_sensitive_actions, true ) && 'verified' !== $scope['restriction_status'] ) {
			$result['reason_code'] = 'professional_scope_restrictions_not_structured';
			return $result;
		}

		$result['result'] = 'allow';
		$result['reason_code'] = 'professional_eligibility_current';
		return self::apply_monotonic_filter( $result, $user_id, $context );
	}

	public static function contract() {
		return array(
			'contract' => self::CONTRACT_NAME,
			'contract_version' => self::CONTRACT_VERSION,
			'owner' => 'File 09',
			'asserts' => array( 'current_professional_verification', 'approved_snapshot_integrity', 'credential_evidence_currency', 'professional_scope_projection' ),
			'excludes' => array( 'authentication_grant', 'clinical_object_access', 'treating_relationship', 'patient_consent', 'guardian_authority', 'prescription_content', 'clinical_record_write', 'break_glass_grant' ),
			'writes_data' => false,
		);
	}

	private static function valid_decision( $decision ) {
		if ( ! is_array( $decision ) ) {
			return false;
		}
		$required = array( 'application_id', 'application_uuid', 'version', 'row_version', 'state', 'verified', 'verified_until', 'fingerprint', 'checked_at' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $decision ) ) {
				return false;
			}
		}
		$checked = strtotime( (string) $decision['checked_at'] );
		return absint( $decision['application_id'] ) > 0
			&& self::valid_uuid( $decision['application_uuid'] )
			&& absint( $decision['version'] ) > 0
			&& absint( $decision['row_version'] ) > 0
			&& false !== $checked
			&& $checked >= time() - 120
			&& $checked <= time() + 60;
	}

	private static function valid_snapshot( $snapshot, $decision ) {
		return is_array( $snapshot )
			&& 3 === absint( isset( $snapshot['schema'] ) ? $snapshot['schema'] : 0 )
			&& isset( $snapshot['application_uuid'], $snapshot['application_version'], $snapshot['profile'], $snapshot['evidence'], $snapshot['verified_until'] )
			&& hash_equals( (string) $decision['application_uuid'], (string) $snapshot['application_uuid'] )
			&& absint( $decision['version'] ) === absint( $snapshot['application_version'] )
			&& is_array( $snapshot['profile'] )
			&& is_array( $snapshot['evidence'] )
			&& hash_equals( (string) $decision['verified_until'], gmdate( 'Y-m-d H:i:s', strtotime( (string) $snapshot['verified_until'] . ' 23:59:59 UTC' ) ) );
	}

	private static function evidence_projection( $snapshot ) {
		$out = array();
		foreach ( array( 'identity', 'qualification', 'license' ) as $type ) {
			$record = isset( $snapshot['evidence'][ $type ] ) && is_array( $snapshot['evidence'][ $type ] ) ? $snapshot['evidence'][ $type ] : array();
			$out[ $type ] = array(
				'version'        => absint( isset( $record['version'] ) ? $record['version'] : 0 ),
				'status'         => sanitize_key( isset( $record['status'] ) ? $record['status'] : 'missing' ),
				'validity_until' => (string) ( isset( $record['validity_until'] ) ? $record['validity_until'] : '' ),
			);
		}
		return $out;
	}

	private static function evidence_current( $evidence ) {
		foreach ( array( 'identity', 'qualification', 'license' ) as $type ) {
			if ( empty( $evidence[ $type ]['version'] ) || 'accepted' !== $evidence[ $type ]['status'] ) {
				return false;
			}
			$until = trim( (string) $evidence[ $type ]['validity_until'] );
			if ( '' !== $until ) {
				$expires = strtotime( $until . ' 23:59:59 UTC' );
				if ( false === $expires || $expires <= time() ) {
					return false;
				}
			}
		}
		return true;
	}

	private static function scope_projection( $snapshot, $user_id, $decision, $context ) {
		$profile = (array) $snapshot['profile'];
		$scope = array(
			'profession'           => 'homeopathic_doctor',
			'country'              => self::plain( isset( $profile['country'] ) ? $profile['country'] : '', 100 ),
			'city'                 => self::plain( isset( $profile['city'] ) ? $profile['city'] : '', 120 ),
			'qualification'        => self::plain( isset( $profile['qualification'] ) ? $profile['qualification'] : '', 240 ),
			'licensing_authority'  => self::plain( isset( $profile['licensing_authority'] ) ? $profile['licensing_authority'] : '', 240 ),
			'specialty'            => self::plain( isset( $profile['specialty'] ) ? $profile['specialty'] : '', 180 ),
			'consultation_modes'   => self::plain( isset( $profile['consultation_modes'] ) ? $profile['consultation_modes'] : '', 240 ),
			'restriction_status'   => 'unmodeled',
			'restrictions'         => array(),
			'source'               => 'approved_snapshot_v1',
		);
		$filtered = apply_filters( 'gdo_cf01_practitioner_scope', $scope, absint( $user_id ), $decision, $snapshot, $context );
		if ( ! is_array( $filtered ) ) {
			return $scope;
		}
		if ( isset( $filtered['restriction_status'] ) && in_array( $filtered['restriction_status'], array( 'verified', 'restricted', 'unmodeled' ), true ) ) {
			$scope['restriction_status'] = $filtered['restriction_status'];
		}
		if ( isset( $filtered['restrictions'] ) && is_array( $filtered['restrictions'] ) ) {
			$scope['restrictions'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $filtered['restrictions'] ) ) ) );
		}
		return $scope;
	}

	private static function jurisdiction_decision( $scope, $requested ) {
		$requested = self::normalize_country( $requested );
		$canonical = self::normalize_country( isset( $scope['country'] ) ? $scope['country'] : '' );
		if ( '' === $requested ) {
			return array( 'result' => 'not_requested', 'reason_code' => 'jurisdiction_not_requested', 'canonical' => $canonical, 'requested' => '' );
		}
		if ( '' === $canonical ) {
			return array( 'result' => 'unknown', 'reason_code' => 'professional_jurisdiction_unavailable', 'canonical' => '', 'requested' => $requested );
		}
		if ( ! hash_equals( $canonical, $requested ) ) {
			return array( 'result' => 'deny', 'reason_code' => 'professional_jurisdiction_mismatch', 'canonical' => $canonical, 'requested' => $requested );
		}
		return array( 'result' => 'allow', 'reason_code' => 'professional_jurisdiction_matches', 'canonical' => $canonical, 'requested' => $requested );
	}

	private static function apply_monotonic_filter( $result, $user_id, $context ) {
		$filtered = apply_filters( 'gdo_cf01_practitioner_result', $result, absint( $user_id ), $context );
		if ( ! is_array( $filtered ) || 'allow' !== $result['result'] ) {
			return $result;
		}
		$next = isset( $filtered['result'] ) ? sanitize_key( $filtered['result'] ) : 'allow';
		if ( in_array( $next, array( 'deny', 'unknown' ), true ) ) {
			$result['result'] = $next;
			$result['reason_code'] = sanitize_key( isset( $filtered['reason_code'] ) ? $filtered['reason_code'] : 'provider_narrowed' );
		}
		return $result;
	}

	private static function plain( $value, $maximum ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, absint( $maximum ) );
		}
		return substr( $value, 0, absint( $maximum ) );
	}

	private static function normalize_country( $value ) {
		$value = strtoupper( trim( wp_strip_all_tags( (string) $value ) ) );
		return preg_replace( '/[^A-Z0-9]/', '', $value );
	}

	private static function trace_id( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return self::valid_uuid( $value ) ? $value : strtolower( wp_generate_uuid4() );
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}

function gdo_cf01_practitioner_assertion( $user_id, $context = array() ) {
	return GDO_CF01_Practitioner_Contract::assertion( $user_id, $context );
}

function gdo_cf01_practitioner_contract() {
	return GDO_CF01_Practitioner_Contract::contract();
}
