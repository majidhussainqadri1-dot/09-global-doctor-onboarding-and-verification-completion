<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Risk {
	public static function evaluate( $application, array $profile, array $evidence = array() ) {
		global $wpdb;
		if ( ! $application ) {
			return array();
		}
		$signals = array();
		$identity = self::identity_fingerprint( $profile );
		if ( $identity ) {
			$duplicate = $wpdb->get_var( $wpdb->prepare(
				'SELECT id FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id<>%d AND identity_fingerprint=%s AND state NOT IN (\'withdrawn\') LIMIT 1',
				absint( $application->id ), $identity
			) );
			if ( ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'gdo_risk_query_failed', __( 'Duplicate-identity risk checks could not be completed safely.', 'global-doctor-onboarding' ) );
			}
			if ( $duplicate ) {
				$signal = self::record( $application->id, 'identity_duplicate', 'high', hash( 'sha256', 'application:' . absint( $duplicate ) ) );
				if ( is_wp_error( $signal ) ) { return $signal; }
				$signals[] = $signal;
			}
		}
		foreach ( $evidence as $record ) {
			if ( empty( $record->source_sha256 ) ) {
				continue;
			}
			$duplicate = $wpdb->get_var( $wpdb->prepare(
				'SELECT id FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE id<>%d AND source_sha256=%s AND deleted_at IS NULL LIMIT 1',
				absint( $record->id ), (string) $record->source_sha256
			) );
			if ( ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'gdo_risk_query_failed', __( 'Duplicate-credential risk checks could not be completed safely.', 'global-doctor-onboarding' ) );
			}
			if ( $duplicate ) {
				$signal = self::record( $application->id, 'document_hash_duplicate', 'high', hash( 'sha256', 'evidence:' . absint( $duplicate ) ) );
				if ( is_wp_error( $signal ) ) { return $signal; }
				$signals[] = $signal;
			}
		}
		return array_values( array_filter( $signals ) );
	}

	public static function identity_fingerprint( array $profile ) {
		$parts = array(
			isset( $profile['display_name'] ) ? strtolower( trim( $profile['display_name'] ) ) : '',
			isset( $profile['license_number'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $profile['license_number'] ) ) : '',
			isset( $profile['licensing_authority'] ) ? strtolower( trim( $profile['licensing_authority'] ) ) : '',
			isset( $profile['license_jurisdiction'] ) ? GDO_Policy::normalize_jurisdiction( $profile['license_jurisdiction'] ) : '',
		);
		return implode( '', $parts ) ? hash( 'sha256', implode( '|', $parts ) ) : '';
	}

	public static function record( $application_id, $type, $severity, $related_digest = '' ) {
		global $wpdb;
		$type = sanitize_key( $type );
		$severity = sanitize_key( $severity );
		$allowed = array( 'identity_duplicate','document_hash_duplicate','velocity','registry_mismatch','manual_concern' );
		if ( ! in_array( $type, $allowed, true ) || ! in_array( $severity, array( 'low','medium','high','critical' ), true ) ) {
			return 0;
		}
		$existing = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . GDO_Schema::table( 'risk_signals' ) . " WHERE application_id=%d AND signal_type=%s AND status IN ('open','reviewing') LIMIT 1",
			absint( $application_id ), $type
		) );
		if ( $existing ) {
			return absint( $existing );
		}
		$ok = $wpdb->insert( GDO_Schema::table( 'risk_signals' ), array(
			'application_id' => absint( $application_id ),
			'signal_type' => $type,
			'severity' => $severity,
			'status' => 'open',
			'related_digest' => substr( sanitize_text_field( $related_digest ), 0, 64 ),
			'created_at' => current_time( 'mysql', true ),
			'updated_at' => current_time( 'mysql', true ),
		), array( '%d','%s','%s','%s','%s','%s','%s' ) );
		return 1 === $ok ? absint( $wpdb->insert_id ) : new WP_Error( 'gdo_risk_store_failed', __( 'A detected professional-verification risk signal could not be stored safely.', 'global-doctor-onboarding' ) );
	}

	public static function unresolved( $application_id, $minimum = 'high' ) {
		global $wpdb;
		$levels = array( 'low'=>1, 'medium'=>2, 'high'=>3, 'critical'=>4 );
		$min = isset( $levels[ $minimum ] ) ? $levels[ $minimum ] : 3;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT severity FROM ' . GDO_Schema::table( 'risk_signals' ) . " WHERE application_id=%d AND status IN ('open','reviewing')",
			absint( $application_id )
		) );
		// Risk-state uncertainty must narrow professional verification. A failed
		// risk query is treated as unresolved rather than silently allowing a final decision.
		if ( null === $rows || ! empty( $wpdb->last_error ) ) {
			return true;
		}
		foreach ( $rows as $row ) {
			if ( isset( $levels[ $row->severity ] ) && $levels[ $row->severity ] >= $min ) {
				return true;
			}
		}
		return false;
	}

	public static function resolve( $signal_id, $actor_id, $decision, $reason ) {
		global $wpdb;
		$decision = sanitize_key( $decision );
		$reason = sanitize_textarea_field( $reason );
		if ( ! in_array( $decision, array( 'confirmed','false_positive','mitigated' ), true ) || strlen( $reason ) < 20 ) {
			return new WP_Error( 'gdo_risk_resolution_invalid', __( 'A complete risk resolution is required.', 'global-doctor-onboarding' ) );
		}
		$updated = $wpdb->update( GDO_Schema::table( 'risk_signals' ), array(
			'status' => 'resolved', 'resolution' => $decision, 'resolution_reason' => $reason,
			'resolver_id' => absint( $actor_id ), 'resolved_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ),
		), array( 'id'=>absint( $signal_id ), 'status'=>'open' ), array( '%s','%s','%s','%d','%s','%s' ), array( '%d','%s' ) );
		return 1 === $updated ? true : new WP_Error( 'gdo_risk_resolution_conflict', __( 'The risk signal changed before it could be resolved.', 'global-doctor-onboarding' ) );
	}
}
