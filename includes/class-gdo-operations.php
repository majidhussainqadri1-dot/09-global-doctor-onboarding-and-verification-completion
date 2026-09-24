<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Operations {
	public static function safe_mode() {
		return (bool) get_option( 'gdo_safe_mode', false );
	}

	public static function mutation_allowed() {
		$core_schema_ready = absint( get_option( 'gdo_schema_version', 0 ) ) === GDO_SCHEMA_VERSION;
		$advanced_schema_ready = ! class_exists( 'GDO_Advanced_Trust_Hardening' )
			|| absint( get_option( 'gdo_advanced_trust_schema', 0 ) ) === GDO_Advanced_Trust_Hardening::SCHEMA_VERSION;
		$schedules_ready = self::required_schedules_ready();
		return ! self::safe_mode()
			&& $core_schema_ready
			&& $advanced_schema_ready
			&& $schedules_ready
			&& defined( 'GDO_CLAIM_SIGNING_KEY' )
			&& strlen( (string) GDO_CLAIM_SIGNING_KEY ) >= 32
			&& GDO_Membership_Adapter::available()
			&& GDO_Membership_Adapter::authentication_available()
			&& GDO_Crypto::available()
			&& ! is_wp_error( GDO_Storage::health() );
	}

	private static function recurring_schedule_ready( $hook, $recurrence ) {
		if ( ! function_exists( 'wp_get_scheduled_event' ) ) { return false; }
		$event = wp_get_scheduled_event( $hook, array() );
		return is_object( $event ) && isset( $event->schedule ) && $recurrence === $event->schedule;
	}

	private static function required_schedules_ready() {
		return self::recurring_schedule_ready( 'gdo_daily_retention', 'daily' )
			&& self::recurring_schedule_ready( 'gdo_notification_outbox', 'hourly' )
			&& self::recurring_schedule_ready( 'gdo_trust_continuous_monitor', 'daily' );
	}

	private static function count_query( $sql, $error_code ) {
		global $wpdb;
		$wpdb->last_error = '';
		$raw = $wpdb->get_var( $sql );
		if ( null === $raw || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( sanitize_key( $error_code ), __( 'A File 09 health query could not be completed safely.', 'global-doctor-onboarding' ) );
		}
		return absint( $raw );
	}

	public static function health() {
		global $wpdb;
		$checks = array();
		$checks['membership_contract'] = GDO_Membership_Adapter::available() ? 'pass' : 'fail';
		$checks['reauthentication_contract'] = GDO_Membership_Adapter::authentication_available() ? 'pass' : 'fail';
		$checks['crypto_keyring'] = GDO_Crypto::available() ? 'pass' : 'fail';
		$checks['private_storage'] = is_wp_error( GDO_Storage::health() ) ? 'fail' : 'pass';
		$checks['schema_version'] = absint( get_option( 'gdo_schema_version', 0 ) ) === GDO_SCHEMA_VERSION ? 'pass' : 'fail';
		$checks['advanced_trust_schema'] = ! class_exists( 'GDO_Advanced_Trust_Hardening' ) || absint( get_option( 'gdo_advanced_trust_schema', 0 ) ) === GDO_Advanced_Trust_Hardening::SCHEMA_VERSION ? 'pass' : 'fail';
		$checks['retention_cron'] = self::recurring_schedule_ready( 'gdo_daily_retention', 'daily' ) ? 'pass' : 'fail';
		$checks['outbox_cron'] = self::recurring_schedule_ready( 'gdo_notification_outbox', 'hourly' ) ? 'pass' : 'fail';
		$checks['trust_monitor_cron'] = self::recurring_schedule_ready( 'gdo_trust_continuous_monitor', 'daily' ) ? 'pass' : 'fail';
		$modern_notifications = function_exists( 'sun_ingest_domain_event' ) && function_exists( 'sun_register_notification_producer' );
		$checks['notification_provider'] = ( $modern_notifications || class_exists( 'SUN_Core' ) || has_action( 'sabri_notify' ) ) ? 'pass' : 'warn';
		$checks['claim_signing_key'] = defined( 'GDO_CLAIM_SIGNING_KEY' ) && strlen( (string) GDO_CLAIM_SIGNING_KEY ) >= 32 ? 'pass' : 'fail';
		$checks['claim_consumer'] = self::claim_consumer_available() ? 'pass' : 'warn';
		$counts = array(
			'dead_letters' => self::count_query( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'outbox' ) . " WHERE status='dead'", 'gdo_health_dead_letters' ),
			'pending_outbox' => self::count_query( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'outbox' ) . " WHERE status IN ('pending','failed','processing')", 'gdo_health_pending_outbox' ),
			'stale_claims' => self::count_query( $wpdb->prepare(
				"SELECT COUNT(*) FROM " . GDO_Schema::table( 'applications' ) . " WHERE claim_status IN ('pending','failed','rejected') AND updated_at<%s",
				gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
			), 'gdo_health_stale_claims' ),
			'open_critical_risks' => self::count_query( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'risk_signals' ) . " WHERE severity='critical' AND status IN ('open','reviewing')", 'gdo_health_critical_risks' ),
			'overdue_appeals' => self::count_query( $wpdb->prepare(
				"SELECT COUNT(*) FROM " . GDO_Schema::table( 'appeals' ) . " WHERE status='open' AND deadline_at IS NOT NULL AND deadline_at<%s",
				current_time( 'mysql', true )
			), 'gdo_health_overdue_appeals' ),
		);
		$db_ok = true;
		foreach ( $counts as $value ) {
			if ( is_wp_error( $value ) ) { $db_ok = false; break; }
		}
		$checks['database_observability'] = $db_ok ? 'pass' : 'fail';
		$dead = is_wp_error( $counts['dead_letters'] ) ? 0 : $counts['dead_letters'];
		$pending = is_wp_error( $counts['pending_outbox'] ) ? 0 : $counts['pending_outbox'];
		$stale_claims = is_wp_error( $counts['stale_claims'] ) ? 0 : $counts['stale_claims'];
		$open_critical_risks = is_wp_error( $counts['open_critical_risks'] ) ? 0 : $counts['open_critical_risks'];
		$overdue_appeals = is_wp_error( $counts['overdue_appeals'] ) ? 0 : $counts['overdue_appeals'];
		$checks['dead_letters'] = $dead ? 'warn' : 'pass';
		$checks['stale_claims'] = $stale_claims ? 'warn' : 'pass';
		$checks['critical_risks'] = $open_critical_risks ? 'warn' : 'pass';
		$checks['appeal_deadlines'] = $overdue_appeals ? 'warn' : 'pass';
		$critical = array_keys( array_filter( $checks, function( $value ) { return 'fail' === $value; } ) );
		return array(
			'status' => $critical ? 'degraded' : ( in_array( 'warn', $checks, true ) ? 'attention' : 'healthy' ),
			'safe_mode'=>self::safe_mode(), 'checks'=>$checks, 'critical'=>$critical,
			'dead_letters'=>$dead, 'pending_outbox'=>$pending, 'stale_claims'=>$stale_claims,
			'open_critical_risks'=>$open_critical_risks, 'overdue_appeals'=>$overdue_appeals, 'checked_at'=>gmdate( 'c' ),
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
		$wpdb->last_error = '';
		$expired = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,row_version FROM " . GDO_Schema::table( 'applications' ) . " WHERE state IN ('verified','reinstated','renewal_due') AND verified_until IS NOT NULL AND verified_until<%s LIMIT %d",
			$now, $limit
		) );
		if ( null === $expired || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_reconcile_query_failed', __( 'Expired verification records could not be read safely.', 'global-doctor-onboarding' ) );
		}
		$count = 0;
		foreach ( $expired as $app ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return new WP_Error( 'gdo_reconcile_transaction_failed', __( 'Verification reconciliation could not start a safe transaction.', 'global-doctor-onboarding' ) );
			}
			$result = GDO_State::transition( $app->id, 'expired', 0, 'verification_expired', 'Verification validity period ended.', $app->row_version, false );
			$claim = is_wp_error( $result ) ? $result : GDO_Claims::issue( $app->id, 'expired', array(), false );
			$notice = is_wp_error( $claim ) ? $claim : GDO_Notifications::queue( 'doctor_verification_expired', $app->user_id, array( 'application_id'=>$app->id ), false );
			if ( is_wp_error( $result ) || is_wp_error( $claim ) || is_wp_error( $notice ) ) {
				$wpdb->query( 'ROLLBACK' );
				return is_wp_error( $result ) ? $result : ( is_wp_error( $claim ) ? $claim : $notice );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				$wpdb->last_error = '';
				$current = $wpdb->get_row( $wpdb->prepare( 'SELECT state,claim_version FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d LIMIT 1', absint( $app->id ) ) );
				$app_error = ! empty( $wpdb->last_error );
				$wpdb->last_error = '';
				$notice_id = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE event_uuid=%s LIMIT 1', (string) $notice ) ) );
				$notice_error = ! empty( $wpdb->last_error );
				$claim_event = is_array( $claim ) && ! empty( $claim['event_id'] ) ? (string) $claim['event_id'] : '';
				$wpdb->last_error = '';
				$claim_outbox_id = $claim_event ? absint( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE event_uuid=%s LIMIT 1', $claim_event ) ) ) : 0;
				$claim_error = ! empty( $wpdb->last_error );
				if ( $app_error || $notice_error || $claim_error ) { return new WP_Error( 'gdo_reconcile_commit_uncertain', __( 'Verification reconciliation commit outcome is uncertain and requires reconciliation.', 'global-doctor-onboarding' ) ); }
				$committed = $current && 'expired' === sanitize_key( $current->state ) && is_array( $claim ) && absint( $current->claim_version ) === absint( $claim['claim_version'] ) && $notice_id > 0 && $claim_outbox_id > 0;
				if ( ! $committed ) { return new WP_Error( 'gdo_reconcile_commit_failed', __( 'Verification reconciliation could not be committed.', 'global-doctor-onboarding' ) ); }
				GDO_Membership_Adapter::audit( 'doctor_reconciliation_commit_reconciled', array( 'application_id'=>absint($app->id), 'claim_version'=>absint($claim['claim_version']) ) );
			}
			GDO_Audit::publish_transition( $result );
			GDO_Claims::publish( $claim );
			++$count;
		}
		$outbox = GDO_Notifications::process( $limit );
		if ( is_wp_error( $outbox ) ) { return $outbox; }
		if ( ! self::record_metric( 'reconciliation.expired', $count, array( 'limit'=>$limit ) ) ) {
			return new WP_Error( 'gdo_reconcile_metric_failed', __( 'Reconciliation completed but its operational metric could not be recorded.', 'global-doctor-onboarding' ) );
		}
		return array( 'expired_reconciled'=>$count, 'processed_at'=>gmdate( 'c' ) );
	}

	public static function repair( $action, $actor_id, $reason ) {
		$current_actor = get_current_user_id();
		if ( ! $current_actor || absint( $actor_id ) !== absint( $current_actor ) || ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $current_actor ) || ! GDO_Membership_Adapter::recent_step_up( $current_actor ) ) {
			return new WP_Error( 'gdo_repair_forbidden', __( 'Controlled repair requires current File 00 manager authorization and recent File 02 step-up.', 'global-doctor-onboarding' ) );
		}
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
			if ( ! is_wp_error( $result ) && class_exists( 'GDO_Advanced_Trust_Hardening' ) ) { $result = GDO_Advanced_Trust_Hardening::maybe_upgrade_schema(); }
		} elseif ( 'schedules' === $action ) {
			if ( ! self::recurring_schedule_ready( 'gdo_daily_retention', 'daily' ) ) {
				wp_clear_scheduled_hook( 'gdo_daily_retention' );
				$scheduled = wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gdo_daily_retention', array(), true );
				if ( is_wp_error( $scheduled ) || false === $scheduled || ! wp_next_scheduled( 'gdo_daily_retention' ) ) { return new WP_Error( 'gdo_repair_retention_schedule', __( 'The retention schedule could not be persisted safely.', 'global-doctor-onboarding' ) ); }
			}
			if ( ! self::recurring_schedule_ready( 'gdo_notification_outbox', 'hourly' ) ) {
				wp_clear_scheduled_hook( 'gdo_notification_outbox' );
				$scheduled = wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'gdo_notification_outbox', array(), true );
				if ( is_wp_error( $scheduled ) || false === $scheduled || ! wp_next_scheduled( 'gdo_notification_outbox' ) ) { return new WP_Error( 'gdo_repair_outbox_schedule', __( 'The outbox schedule could not be persisted safely.', 'global-doctor-onboarding' ) ); }
			}
			if ( ! self::recurring_schedule_ready( 'gdo_trust_continuous_monitor', 'daily' ) ) {
				wp_clear_scheduled_hook( 'gdo_trust_continuous_monitor' );
				$scheduled = wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'gdo_trust_continuous_monitor', array(), true );
				if ( is_wp_error( $scheduled ) || false === $scheduled || ! wp_next_scheduled( 'gdo_trust_continuous_monitor' ) ) { return new WP_Error( 'gdo_repair_trust_monitor_schedule', __( 'The professional trust monitor schedule could not be persisted safely.', 'global-doctor-onboarding' ) ); }
			}
		} elseif ( 'outbox' === $action ) {
			$result = GDO_Notifications::process( 100 );
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
		$current_actor = get_current_user_id();
		if ( ! $current_actor || absint( $actor_id ) !== absint( $current_actor ) || ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $current_actor ) || ! GDO_Membership_Adapter::recent_step_up( $current_actor ) ) {
			return new WP_Error( 'gdo_safe_mode_forbidden', __( 'Safe Mode changes require current File 00 manager authorization and recent File 02 step-up.', 'global-doctor-onboarding' ) );
		}
		$reason = sanitize_textarea_field( $reason );
		if ( strlen( $reason ) < 20 ) {
			return new WP_Error( 'gdo_safe_mode_reason', __( 'A reason of at least 20 characters is required.', 'global-doctor-onboarding' ) );
		}
		$desired = (bool) $enabled;
		if ( self::safe_mode() !== $desired ) {
			update_option( 'gdo_safe_mode', $desired, false );
		}
		if ( self::safe_mode() !== $desired ) {
			return new WP_Error( 'gdo_safe_mode_persist_failed', __( 'Safe Mode could not be persisted safely.', 'global-doctor-onboarding' ) );
		}
		GDO_Membership_Adapter::audit( $desired ? 'doctor_verification_safe_mode_enabled' : 'doctor_verification_safe_mode_disabled', array( 'actor_id'=>absint( $actor_id ), 'reason'=>$reason ) );
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
