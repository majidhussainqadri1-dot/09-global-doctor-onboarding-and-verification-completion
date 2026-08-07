<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Audit {
	public static function transition( $application_id, $actor_id, $from, $to, $reason_code, $reason_text, $trace_id = '' ) {
		global $wpdb;
		$table = GDO_Schema::table( 'transitions' );
		$previous = (string) $wpdb->get_var( $wpdb->prepare( "SELECT event_hash FROM {$table} WHERE application_id=%d ORDER BY id DESC LIMIT 1 FOR UPDATE", absint( $application_id ) ) );
		$created = current_time( 'mysql', true );
		$reason_code = sanitize_key( $reason_code );
		$reason_text = sanitize_textarea_field( $reason_text );
		$trace_id = self::valid_uuid( $trace_id ) ? $trace_id : wp_generate_uuid4();
		if ( ! $reason_code || strlen( $reason_text ) < 8 ) {
			return new WP_Error( 'gdo_audit_reason_invalid', __( 'A structured reason code and meaningful reason are required.', 'global-doctor-onboarding' ) );
		}
		$data = array(
			'application_id' => absint( $application_id ),
			'actor_id'       => absint( $actor_id ) ?: null,
			'from_state'     => sanitize_key( $from ),
			'to_state'       => sanitize_key( $to ),
			'reason_code'    => $reason_code,
			'reason_text'    => $reason_text,
			'trace_id'       => $trace_id,
			'previous_hash'  => $previous ?: str_repeat( '0', 64 ),
			'created_at'     => $created,
		);
		$data['event_hash'] = hash( 'sha256', GDO_Claims::canonical_json( $data ) );
		$inserted = $wpdb->insert( $table, $data, array( '%d','%d','%s','%s','%s','%s','%s','%s','%s','%s' ) );
		if ( 1 !== $inserted ) {
			return new WP_Error( 'gdo_audit_write_failed', __( 'The verification audit record could not be committed.', 'global-doctor-onboarding' ) );
		}
		GDO_Membership_Adapter::audit( 'doctor_verification_transition', $data );
		return true;
	}

	public static function access( $application_id, $evidence_id, $reviewer_id, $purpose, $result, $trace_id = '' ) {
		global $wpdb;
		$purpose = sanitize_textarea_field( $purpose );
		$trace_id = self::valid_uuid( $trace_id ) ? $trace_id : wp_generate_uuid4();
		$purpose_code = substr( sanitize_key( wp_trim_words( $purpose, 8, '' ) ), 0, 80 );
		$purpose_code = $purpose_code ? $purpose_code : 'credential_review_recorded_purpose';
		$data = array(
			'application_id' => absint( $application_id ),
			'evidence_id'    => absint( $evidence_id ),
			'reviewer_id'    => absint( $reviewer_id ),
			'purpose_code'   => $purpose_code,
			'purpose_hash'   => hash( 'sha256', $purpose ),
			'result'         => sanitize_key( $result ),
			'actor_digest'   => hash( 'sha256', absint( $reviewer_id ) . '|' . wp_salt( 'nonce' ) ),
			'trace_id'       => $trace_id,
			'created_at'     => current_time( 'mysql', true ),
		);
		$inserted = $wpdb->insert( GDO_Schema::table( 'access_log' ), $data, array( '%d','%d','%d','%s','%s','%s','%s','%s','%s' ) );
		if ( 1 !== $inserted ) {
			return new WP_Error( 'gdo_access_audit_failed', __( 'Credential access was stopped because its audit record could not be stored.', 'global-doctor-onboarding' ) );
		}
		GDO_Membership_Adapter::audit( 'doctor_credential_access', $data );
		return true;
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
