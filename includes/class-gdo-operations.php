<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Operations {
	public static function safe_mode() {
		return (bool) get_option( 'gdo_safe_mode', false );
	}

	public static function mutation_allowed() {
		return ! self::safe_mode()
			&& GDO_Membership_Adapter::available()
			&& GDO_Membership_Adapter::authentication_available()
			&& GDO_Crypto::available()
			&& ! is_wp_error( GDO_Storage::health() );
	}

	public static function health() {
		global $wpdb;
		$checks = array();
		$checks['membership_contract'] = GDO_Membership_Adapter::available() ? 'pass' : 'fail';
		$checks['reauthentication_contract'] = GDO_Membership_Adapter::authentication_available() ? 'pass' : 'fail';
		$checks['crypto_keyring'] = GDO_Crypto::available() ? 'pass' : 'fail';
		$checks['private_storage'] = is_wp_error( GDO_Storage::health() ) ? 'fail' : 'pass';
		$checks['schema_version'] = absint( get_option( 'gdo_schema_version', 0 ) ) === GDO_SCHEMA_VERSION ? 'pass' : 'fail';
		$checks['retention_cron'] = wp_next_scheduled( 'gdo_daily_retention' ) ? 'pass' : 'warn';
		$checks['outbox_cron'] = wp_next_scheduled( 'gdo_notification_outbox' ) ? 'pass' : 'warn';
		$checks['notification_provider'] = ( class_exists( 'SUN_Core' ) || has_action( 'sabri_notify' ) ) ? 'pass' : 'warn';
		$checks['claim_signing_key'] = defined( 'GDO_CLAIM_SIGNING_KEY' ) && strlen( (string) GDO_CLAIM_SIGNING_KEY ) >= 32 ? 'pass' : 'fail';
		$checks['claim_consumer'] = self::claim_consumer_available() ? 'pass' : 'warn';
		$dead = absint( $wpdb->get_var( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'outbox' ) . " WHERE status='dead'" ) );
		$pending = absint( $wpdb->get_var( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'outbox' ) . " WHERE status IN ('pending','failed','processing')" ) );
		$stale_claims = absint( $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . GDO_Schema::table( 'applications' ) . " WHERE claim_status IN ('pending','failed','rejected') AND updated_at<%s",
			gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
		) ) );
		$open_critical_risks = absint( $wpdb->get_var( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'risk_signals' ) . " WHERE severity='critical' AND status IN ('open','reviewing')" ) );
		$checks['dead_letters'] = $dead ? 'warn' : 'pass';
		$checks['stale_claims'] = $stale_claims ? 'warn' : 'pass';
		$checks['critical_risks'] = $open_critical_risks ? 'warn' : 'pass';
		$critical = array_keys( array_filter( $checks, function( $value ) { return 'fail' === $value; } ) );
		return array(
			'status' => $critical ? 'degraded' : ( in_array( 'warn', $checks, true ) ? 'attention' : 'healthy' ),
			'safe_mode'=>self::safe_mode(), 'checks'=>$checks, 'critical'=>$critical,
			'dead_letters'=>$dead, 'pending_outbox'=>$pending, 'stale_claims'=>$stale_claims,
			'open_critical_risks'=>$open_critical_risks, 'checked_at'=>gmdate( 'c' ),
			'version'=>GDO_VERSION, 'schema'=>GDO_SCHEMA_VERSION, 'policy_version'=>GDO_Policy::VERSION,
		);
	}

	private static function claim_consumer_available() {
		return is_callable( array( 'SMC_Professional_Verification_Claims', 'consume' ) )
			|| is_callable( array( 'SMC_Professional_Claims', 'consume' ) )
			|| is_callable( array( 'SMC_Contracts', 'consume_professional_claim' ) )
			|| has_filter( 'gdo_deliver_professional_claim' );
	}

	public static function reconcile( $limit = 100 ) {
		global $wpdb;
		$limit = max( 1, min( 500, absint( $limit ) ) );
		$now = current_time( 'mysql', true );
		$expired = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,row_version FROM " . GDO_Schema::table( 'applications' ) . " WHERE state IN ('verified','reinstated','renewal_due') AND verified_until IS NOT NULL AND verified_until<%s LIMIT %d",
			$now, $limit
		) );
		$count = 0;
		foreach ( $expired as $app ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				continue;
			}
			$result = GDO_State::transition( $app->id, 'expired', 0, 'verification_expired', 'Verification validity period ended.', $app->row_version, false );
			$claim = is_wp_error( $result ) ? $result : GDO_Claims::issue( $app->id, 'expired', array(), false );
			$notice = is_wp_error( $claim ) ? $claim : GDO_Notifications::queue( 'doctor_verification_expired', $app->user_id, array( 'application_id'=>$app->id ), false );
			if ( is_wp_error( $result ) || is_wp_error( $claim ) || is_wp_error( $notice ) || false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				continue;
			}
			GDO_Audit::publish_transition( $result );
			GDO_Claims::publish( $claim );
			++$count;
		}
		GDO_Notifications::process( $limit );
		self::record_metric( 'reconciliation.expired', $count, array( 'limit'=>$limit ) );
		return array( 'expired_reconciled'=>$count, 'processed_at'=>gmdate( 'c' ) );
	}

	public static function repair( $action, $actor_id, $reason ) {
		$action = sanitize_key( $action );
		$reason = sanitize_textarea_field( $reason );
		if ( strlen( $reason ) < 20 ) {
			return new WP_Error( 'gdo_repair_reason', __( 'A repair reason of at least 20 characters is required.', 'global-doctor-onboarding' ) );
		}
		$allowed = array( 'schema','schedules','outbox','reconcile','storage_check' );
		if ( ! in_array( $action, $allowed, true ) ) {
			return new WP_Error( 'gdo_repair_action', __( 'The requested repair action is not allowed.', 'global-doctor-onboarding' ) );
		}
		$result = true;
		if ( 'schema' === $action ) {
			$result = GDO_Migration::maybe_run();
		} elseif ( 'schedules' === $action ) {
			if ( ! wp_next_scheduled( 'gdo_daily_retention' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gdo_daily_retention' );
			}
			if ( ! wp_next_scheduled( 'gdo_notification_outbox' ) ) {
				wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'gdo_notification_outbox' );
			}
		} elseif ( 'outbox' === $action ) {
			GDO_Notifications::process( 100 );
		} elseif ( 'reconcile' === $action ) {
			$result = self::reconcile( 200 );
		} elseif ( 'storage_check' === $action ) {
			$result = GDO_Storage::health();
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		GDO_Membership_Adapter::audit( 'doctor_verification_repair_executed', array( 'action'=>$action, 'actor_id'=>absint( $actor_id ), 'reason'=>$reason ) );
		return $result;
	}

	public static function set_safe_mode( $enabled, $actor_id, $reason ) {
		$reason = sanitize_textarea_field( $reason );
		if ( strlen( $reason ) < 20 ) {
			return new WP_Error( 'gdo_safe_mode_reason', __( 'A reason of at least 20 characters is required.', 'global-doctor-onboarding' ) );
		}
		update_option( 'gdo_safe_mode', (bool) $enabled, false );
		GDO_Membership_Adapter::audit( $enabled ? 'doctor_verification_safe_mode_enabled' : 'doctor_verification_safe_mode_disabled', array( 'actor_id'=>absint( $actor_id ), 'reason'=>$reason ) );
		return true;
	}

	public static function record_metric( $name, $value, array $dimensions = array() ) {
		global $wpdb;
		$name = substr( sanitize_key( str_replace( '.', '_', $name ) ), 0, 80 );
		return 1 === $wpdb->insert(
			GDO_Schema::table( 'metrics' ),
			array( 'metric_name'=>$name, 'metric_value'=>(float) $value, 'dimensions_json'=>wp_json_encode( $dimensions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'observed_at'=>current_time( 'mysql', true ) ),
			array( '%s','%f','%s','%s' )
		);
	}
}
