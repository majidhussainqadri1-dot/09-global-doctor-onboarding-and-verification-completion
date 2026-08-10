<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Claims {
	const CONTRACT = 'gdo.file00.professional-decision';
	const VERSION  = '1.0.0';

	public static function issue( $application_id, $state, array $snapshot = array(), $manage_transaction = true ) {
		global $wpdb;
		$application_id = absint( $application_id );
		$state = sanitize_key( $state );
		$allowed_states = array( 'verified','rejected','suspended','revoked','expired','renewal_due','reinstated','under_review' );
		if ( ! in_array( $state, $allowed_states, true ) ) {
			return new WP_Error( 'gdo_claim_state_invalid', __( 'The professional-decision claim state is invalid.', 'global-doctor-onboarding' ) );
		}
		$secret = defined( 'GDO_CLAIM_SIGNING_KEY' ) ? (string) GDO_CLAIM_SIGNING_KEY : '';
		if ( strlen( $secret ) < 32 ) {
			return new WP_Error( 'gdo_claim_key_missing', __( 'The File 09 claim signing key is unavailable.', 'global-doctor-onboarding' ) );
		}
		if ( $manage_transaction && false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'gdo_claim_transaction', __( 'The professional claim transaction could not be started safely.', 'global-doctor-onboarding' ) );
		}
		$app = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', $application_id ) );
		if ( ! $app ) {
			if ( $manage_transaction ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return new WP_Error( 'gdo_claim_application_missing', __( 'The application is unavailable.', 'global-doctor-onboarding' ) );
		}
		if ( sanitize_key( $app->state ) !== $state ) {
			if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'gdo_claim_state_mismatch', __( 'The professional claim must match the current locked application state.', 'global-doctor-onboarding' ) );
		}
		if ( GDO_State::public_verified( $state ) && ! GDO_Membership_Adapter::is_active_doctor_candidate( $app->user_id, $app->jurisdiction ) ) {
			if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'gdo_claim_membership_not_current', __( 'A verified professional claim requires current File 00 identity and doctor-membership assurance.', 'global-doctor-onboarding' ) );
		}
		$approved_snapshot = GDO_State::public_verified( $state ) ? GDO_Application::stored_approved_snapshot( $app ) : array();
		if ( GDO_State::public_verified( $state ) && ! $approved_snapshot ) {
			if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'gdo_claim_snapshot_missing', __( 'A verified professional claim requires a valid immutable approved snapshot.', 'global-doctor-onboarding' ) );
		}
		$claim_version = absint( $app->claim_version ) + 1;
		$subject = GDO_Membership_Adapter::membership_assertion( $app->user_id, 'clinical_identity_link', 'professional_verification_claim', $app->jurisdiction );
		$subject_uuid = isset( $subject['subject']['platform_uuid'] ) ? (string) $subject['subject']['platform_uuid'] : '';
		$record_version = isset( $subject['subject']['record_version'] ) ? absint( $subject['subject']['record_version'] ) : 0;
		if ( ( GDO_State::public_verified( $state ) && ! GDO_Membership_Adapter::membership_allows( $subject ) ) || ! $subject_uuid || ! $record_version ) {
			if ( $manage_transaction ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return new WP_Error( 'gdo_claim_membership_context', __( 'Current File 00 subject and record version are required for claim issuance.', 'global-doctor-onboarding' ) );
		}
		$payload = array(
			'contract'                 => self::CONTRACT,
			'contract_version'         => self::VERSION,
			'event_id'                 => wp_generate_uuid4(),
			'application_uuid'         => (string) $app->application_uuid,
			'application_version'      => absint( $app->version ),
			'claim_version'            => $claim_version,
			'subject_uuid'             => $subject_uuid,
			'membership_record_version'=> $record_version,
			'state'                    => $state,
			'effective_at'             => gmdate( 'c' ),
			'expires_at'               => ! empty( $app->verified_until ) ? gmdate( 'c', strtotime( $app->verified_until . ' UTC' ) ) : null,
			'snapshot_fingerprint'     => ! empty( $app->approved_fingerprint ) ? (string) $app->approved_fingerprint : '',
			'policy_version'           => GDO_Policy::VERSION,
			'producer_version'         => GDO_VERSION,
			'producer_schema'          => GDO_SCHEMA_VERSION,
		);
		$snapshot_for_claim = $snapshot ? $snapshot : $approved_snapshot;
		if ( $snapshot_for_claim ) {
			$payload['snapshot_schema'] = isset( $snapshot_for_claim['schema'] ) ? absint( $snapshot_for_claim['schema'] ) : GDO_SCHEMA_VERSION;
		}
		$payload['signature'] = hash_hmac( 'sha256', self::canonical_json( $payload ), $secret );
		$payload['signature_algorithm'] = 'HMAC-SHA256';
		$updated = $wpdb->update(
			GDO_Schema::table( 'applications' ),
			array( 'claim_version'=>$claim_version, 'claim_status'=>'pending', 'claim_ack_at'=>null, 'claim_last_error'=>null, 'updated_at'=>current_time( 'mysql', true ) ),
			array( 'id'=>$application_id, 'claim_version'=>absint( $app->claim_version ) ),
			array( '%d','%s','%s','%s','%s' ),
			array( '%d','%d' )
		);
		if ( 1 !== $updated ) {
			if ( $manage_transaction ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return new WP_Error( 'gdo_claim_concurrent_change', __( 'The professional claim version changed before issuance.', 'global-doctor-onboarding' ) );
		}
		$queued = GDO_Notifications::queue(
			'doctor_professional_claim',
			absint( $app->user_id ),
			array( 'application_id'=>$application_id, 'claim'=>$payload, 'event_uuid'=>$payload['event_id'] ),
			false
		);
		if ( is_wp_error( $queued ) ) {
			if ( $manage_transaction ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return $queued;
		}
		if ( $manage_transaction && false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gdo_claim_commit_failed', __( 'The professional claim could not be committed.', 'global-doctor-onboarding' ) );
		}
		if ( $manage_transaction ) {
			self::publish( $payload );
			GDO_Notifications::process( 1, $payload['event_id'] );
		}
		return $payload;
	}

	public static function publish( $payload ) {
		if ( ! is_array( $payload ) || self::CONTRACT !== ( isset( $payload['contract'] ) ? $payload['contract'] : '' ) || empty( $payload['event_id'] ) || empty( $payload['application_uuid'] ) || empty( $payload['claim_version'] ) ) {
			return false;
		}
		global $wpdb;
		$application_id = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE application_uuid=%s LIMIT 1', (string) $payload['application_uuid'] ) ) );
		GDO_Membership_Adapter::audit( 'doctor_professional_claim_issued', array(
			'application_id'=>$application_id,
			'application_uuid'=>(string) $payload['application_uuid'],
			'claim_version'=>absint( $payload['claim_version'] ),
			'state'=>sanitize_key( isset( $payload['state'] ) ? $payload['state'] : '' ),
			'event_id'=>(string) $payload['event_id'],
			'subject_digest'=>! empty( $payload['subject_uuid'] ) ? hash( 'sha256', (string) $payload['subject_uuid'] ) : '',
		) );
		do_action( 'gdo_professional_claim_issued', $payload );
		return true;
	}

	public static function acknowledge( $application_id, $claim_version, $status, $reason = '' ) {
		global $wpdb;
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'accepted','rejected' ), true ) ) {
			return false;
		}
		$updated = $wpdb->update(
			GDO_Schema::table( 'applications' ),
			array(
				'claim_status'=>$status, 'claim_ack_at'=>current_time( 'mysql', true ),
				'claim_last_error'=>sanitize_textarea_field( $reason ), 'updated_at'=>current_time( 'mysql', true ),
			),
			array( 'id'=>absint( $application_id ), 'claim_version'=>absint( $claim_version ) ),
			array( '%s','%s','%s','%s' ),
			array( '%d','%d' )
		);
		if ( 1 === $updated ) {
			GDO_Membership_Adapter::audit( 'doctor_professional_claim_acknowledged', array( 'application_id'=>absint( $application_id ), 'claim_version'=>absint( $claim_version ), 'status'=>$status ) );
			return true;
		}
		return false;
	}

	public static function canonical_json( array $value ) {
		$value = self::sort_recursive( $value );
		return wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function sort_recursive( array $value ) {
		if ( self::is_list( $value ) ) {
			foreach ( $value as $key => $item ) {
				if ( is_array( $item ) ) {
					$value[ $key ] = self::sort_recursive( $item );
				}
			}
			return $value;
		}
		ksort( $value );
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::sort_recursive( $item );
			}
		}
		return $value;
	}

	private static function is_list( array $value ) {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
