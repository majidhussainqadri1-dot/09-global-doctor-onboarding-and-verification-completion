#!/usr/bin/env python3
from pathlib import Path


def replace_once(path, old, new):
    p = Path(path)
    s = p.read_text(encoding='utf-8')
    if old not in s:
        raise SystemExit(f'missing replacement anchor in {path}: {old[:120]!r}')
    p.write_text(s.replace(old, new, 1), encoding='utf-8')


# R3-04: partial File 00 availability must never expose a stale profile projection.
replace_once(
    'includes/class-gdo-membership-adapter.php',
    "\t\t$profile = function_exists( 'smc_get_profile' ) ? (array) smc_get_profile( $user_id ) : array();\n\t\t$base = self::base_assertion( $user_id );\n\t\t$subject = self::membership_assertion( $user_id );\n\t\tif ( ! $base ) {\n\t\t\treturn $profile;\n\t\t}\n",
    "\t\t$base = self::base_assertion( $user_id );\n\t\tif ( ! $base ) {\n\t\t\treturn array();\n\t\t}\n\t\t$profile = function_exists( 'smc_get_profile' ) ? (array) smc_get_profile( $user_id ) : array();\n\t\t$subject = self::membership_assertion( $user_id );\n",
)

# R3-31: privileged reviewer/operator capabilities require current identity assurance.
replace_once(
    'includes/class-gdo-membership-adapter.php',
    "\t\tif ( ! $base || ! $user_id || empty( $base['approved'] ) || self::sanctioned( $user_id ) ) {\n\t\t\treturn false;\n\t\t}\n",
    "\t\tif ( ! $base || ! $user_id || empty( $base['approved'] ) || self::sanctioned( $user_id ) || ! self::identity_assurance_current( $user_id ) ) {\n\t\t\treturn false;\n\t\t}\n",
)

# R3-16: extension filters may narrow native upload provenance, never widen it.
replace_once(
    'includes/class-gdo-evidence.php',
    "        $is_uploaded = is_uploaded_file( $file['tmp_name'] );\n        $is_uploaded = (bool) apply_filters( 'gdo_is_uploaded_file', $is_uploaded, $file['tmp_name'], $type );\n",
    "        $is_uploaded = is_uploaded_file( $file['tmp_name'] );\n        $filtered_uploaded = (bool) apply_filters( 'gdo_is_uploaded_file', $is_uploaded, $file['tmp_name'], $type );\n        $is_uploaded = $is_uploaded && $filtered_uploaded;\n",
)

# R3-24: duplicate-risk lookup uncertainty must fail closed.
replace_once(
    'includes/class-gdo-risk.php',
    "\t\t$existing = $wpdb->get_var( $wpdb->prepare(\n\t\t\t'SELECT id FROM ' . GDO_Schema::table( 'risk_signals' ) . \" WHERE application_id=%d AND signal_type=%s AND status IN ('open','reviewing') LIMIT 1\",\n\t\t\tabsint( $application_id ), $type\n\t\t) );\n\t\tif ( $existing ) {\n",
    "\t\t$existing = $wpdb->get_var( $wpdb->prepare(\n\t\t\t'SELECT id FROM ' . GDO_Schema::table( 'risk_signals' ) . \" WHERE application_id=%d AND signal_type=%s AND status IN ('open','reviewing') LIMIT 1\",\n\t\t\tabsint( $application_id ), $type\n\t\t) );\n\t\tif ( ! empty( $wpdb->last_error ) ) {\n\t\t\treturn new WP_Error( 'gdo_risk_query_failed', __( 'Existing professional-verification risk state could not be checked safely.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\tif ( $existing ) {\n",
)

# R3-50..54: durable outbox database uncertainty and exact replay target.
p = Path('includes/class-gdo-notifications.php')
s = p.read_text(encoding='utf-8')
start = s.index("\tpublic static function process( $limit = 25, $event_uuid = '' ) {")
end = s.index("\tprivate static function valid_uuid( $value ) {", start)
new_block = '''\tpublic static function process( $limit = 25, $event_uuid = '' ) {
\t\tglobal $wpdb;
\t\t$table = GDO_Schema::table( 'outbox' );
\t\t$now = current_time( 'mysql', true );
\t\t$lease_recovered = $wpdb->query( $wpdb->prepare(
\t\t\t"UPDATE {$table} SET status='failed', attempts=attempts+1, last_error=%s, available_at=%s WHERE status='processing' AND available_at<=%s",
\t\t\t'Processing lease expired before completion; safe retry scheduled.', $now, $now
\t\t) );
\t\tif ( false === $lease_recovered ) {
\t\t\treturn new WP_Error( 'gdo_outbox_lease_recovery_failed', __( 'Notification lease recovery could not be persisted safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\t$where = "status IN ('pending','failed') AND available_at<=%s";
\t\t$values = array( $now );
\t\tif ( $event_uuid ) {
\t\t\t$where .= ' AND event_uuid=%s';
\t\t\t$values[] = sanitize_text_field( $event_uuid );
\t\t}
\t\t$values[] = max( 1, min( 100, absint( $limit ) ) );
\t\t$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d", $values ) );
\t\tif ( null === $rows || ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'gdo_outbox_query_failed', __( 'Pending notification events could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\t$summary = array( 'processed'=>0, 'delivered'=>0, 'failed'=>0, 'dead'=>0 );
\t\tforeach ( $rows as $row ) {
\t\t\t$lease_seconds = max( 60, absint( apply_filters( 'gdo_outbox_processing_lease_seconds', 300, $row->event_type ) ) );
\t\t\t$lease_until = gmdate( 'Y-m-d H:i:s', time() + $lease_seconds );
\t\t\t$claimed = $wpdb->update( $table, array( 'status'=>'processing', 'available_at'=>$lease_until ), array( 'id'=>absint( $row->id ), 'status'=>$row->status ), array( '%s','%s' ), array( '%d','%s' ) );
\t\t\tif ( false === $claimed ) {
\t\t\t\treturn new WP_Error( 'gdo_outbox_claim_failed', __( 'A notification event processing lease could not be claimed safely.', 'global-doctor-onboarding' ) );
\t\t\t}
\t\t\tif ( 1 !== $claimed ) {
\t\t\t\tcontinue;
\t\t\t}
\t\t\t$payload = json_decode( $row->payload_json, true );
\t\t\t$payload = is_array( $payload ) ? $payload : array();
\t\t\t$payload['event_uuid'] = (string) $row->event_uuid;
\t\t\ttry {
\t\t\t\t$result = 'doctor_professional_claim' === $row->event_type
\t\t\t\t\t? self::deliver_claim( $payload )
\t\t\t\t\t: self::deliver_notification( $row->event_type, $row->recipient_user_id, $payload );
\t\t\t} catch ( Throwable $e ) {
\t\t\t\t$result = new WP_Error( 'gdo_outbox_exception', $e->getMessage() );
\t\t\t}
\t\t\t$attempts = absint( $row->attempts ) + 1;
\t\t\tif ( true === $result ) {
\t\t\t\t$persisted = $wpdb->update(
\t\t\t\t\t$table,
\t\t\t\t\tarray( 'status'=>'delivered', 'attempts'=>$attempts, 'last_error'=>null, 'delivered_at'=>current_time( 'mysql', true ), 'dead_at'=>null ),
\t\t\t\t\tarray( 'id'=>absint( $row->id ), 'status'=>'processing' ),
\t\t\t\t\tarray( '%s','%d','%s','%s','%s' ),
\t\t\t\t\tarray( '%d','%s' )
\t\t\t\t);
\t\t\t\tif ( 1 !== $persisted ) {
\t\t\t\t\tGDO_Membership_Adapter::audit( 'doctor_verification_outbox_delivery_persistence_uncertain', array( 'event_uuid'=>$row->event_uuid, 'event_type'=>$row->event_type ) );
\t\t\t\t\treturn new WP_Error( 'gdo_outbox_delivery_persist_failed', __( 'Provider delivery succeeded but the durable outbox receipt could not be persisted safely.', 'global-doctor-onboarding' ) );
\t\t\t\t}
\t\t\t\t++$summary['processed'];
\t\t\t\t++$summary['delivered'];
\t\t\t\tcontinue;
\t\t\t}
\t\t\t$error = is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : 'Provider returned no explicit success.';
\t\t\tif ( 'doctor_professional_claim' === $row->event_type && ! empty( $payload['application_id'] ) ) {
\t\t\t\t$claim_marked = $wpdb->update( GDO_Schema::table( 'applications' ), array( 'claim_status'=>'failed', 'claim_last_error'=>sanitize_textarea_field( $error ), 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>absint( $payload['application_id'] ) ), array( '%s','%s','%s' ), array( '%d' ) );
\t\t\t\tif ( false === $claim_marked ) {
\t\t\t\t\treturn new WP_Error( 'gdo_claim_failure_persist_failed', __( 'Claim delivery failed but its application status could not be persisted safely.', 'global-doctor-onboarding' ) );
\t\t\t\t}
\t\t\t}
\t\t\t$terminal = $attempts >= absint( apply_filters( 'gdo_outbox_max_attempts', 7, $row->event_type ) );
\t\t\t$delay = min( DAY_IN_SECONDS, (int) pow( 2, min( 10, $attempts ) ) * 60 );
\t\t\t$persisted = $wpdb->update(
\t\t\t\t$table,
\t\t\t\tarray(
\t\t\t\t\t'status'=>$terminal ? 'dead' : 'failed', 'attempts'=>$attempts,
\t\t\t\t\t'last_error'=>sanitize_textarea_field( $error ),
\t\t\t\t\t'available_at'=>gmdate( 'Y-m-d H:i:s', time() + $delay ),
\t\t\t\t\t'dead_at'=>$terminal ? current_time( 'mysql', true ) : null,
\t\t\t\t),
\t\t\t\tarray( 'id'=>absint( $row->id ), 'status'=>'processing' ),
\t\t\t\tarray( '%s','%d','%s','%s','%s' ),
\t\t\t\tarray( '%d','%s' )
\t\t\t);
\t\t\tif ( 1 !== $persisted ) {
\t\t\t\treturn new WP_Error( 'gdo_outbox_failure_persist_failed', __( 'Notification failure state could not be persisted safely.', 'global-doctor-onboarding' ) );
\t\t\t}
\t\t\t++$summary['processed'];
\t\t\t++$summary['failed'];
\t\t\tif ( $terminal ) {
\t\t\t\t++$summary['dead'];
\t\t\t\tGDO_Membership_Adapter::audit( 'doctor_verification_outbox_dead_letter', array( 'event_uuid'=>$row->event_uuid, 'event_type'=>$row->event_type, 'attempts'=>$attempts ) );
\t\t\t}
\t\t}
\t\treturn $summary;
\t}

\tpublic static function replay( $event_id, $actor_id, $reason ) {
\t\tglobal $wpdb;
\t\t$reason = sanitize_textarea_field( $reason );
\t\tif ( strlen( $reason ) < 20 ) {
\t\t\treturn new WP_Error( 'gdo_outbox_replay_reason', __( 'A reason of at least 20 characters is required.', 'global-doctor-onboarding' ) );
\t\t}
\t\t$row = $wpdb->get_row( $wpdb->prepare(
\t\t\t'SELECT id,event_uuid,status FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE id=%d LIMIT 1',
\t\t\tabsint( $event_id )
\t\t) );
\t\tif ( null === $row && ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'gdo_outbox_replay_query', __( 'The dead-letter event could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\tif ( ! $row || 'dead' !== sanitize_key( $row->status ) || ! self::valid_uuid( $row->event_uuid ) ) {
\t\t\treturn new WP_Error( 'gdo_outbox_replay_conflict', __( 'The dead-letter event is no longer available for replay.', 'global-doctor-onboarding' ) );
\t\t}
\t\t$updated = $wpdb->update(
\t\t\tGDO_Schema::table( 'outbox' ),
\t\t\tarray( 'status'=>'pending', 'available_at'=>current_time( 'mysql', true ), 'dead_at'=>null, 'last_error'=>null ),
\t\t\tarray( 'id'=>absint( $event_id ), 'status'=>'dead' ),
\t\t\tarray( '%s','%s','%s','%s' ),
\t\t\tarray( '%d','%s' )
\t\t);
\t\tif ( 1 !== $updated ) {
\t\t\treturn new WP_Error( 'gdo_outbox_replay_conflict', __( 'The dead-letter event is no longer available for replay.', 'global-doctor-onboarding' ) );
\t\t}
\t\tGDO_Membership_Adapter::audit( 'doctor_verification_outbox_replayed', array( 'event_id'=>absint( $event_id ), 'event_uuid'=>(string) $row->event_uuid, 'actor_id'=>absint( $actor_id ), 'reason'=>$reason ) );
\t\t$result = self::process( 1, (string) $row->event_uuid );
\t\treturn is_wp_error( $result ) ? $result : true;
\t}

'''
p.write_text(s[:start] + new_block + s[end:], encoding='utf-8')

# R3-77..79: operations health/reconcile/repair/safe mode must not claim success on uncertainty.
p = Path('includes/class-gdo-operations.php')
s = p.read_text(encoding='utf-8')
h0 = s.index("\tpublic static function health() {")
h1 = s.index("\n\tprivate static function claim_consumer_available()", h0)
health = '''\tprivate static function count_query( $sql, $error_code ) {
\t\tglobal $wpdb;
\t\t$raw = $wpdb->get_var( $sql );
\t\tif ( null === $raw || ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( sanitize_key( $error_code ), __( 'A File 09 health query could not be completed safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\treturn absint( $raw );
\t}

\tpublic static function health() {
\t\tglobal $wpdb;
\t\t$checks = array();
\t\t$checks['membership_contract'] = GDO_Membership_Adapter::available() ? 'pass' : 'fail';
\t\t$checks['reauthentication_contract'] = GDO_Membership_Adapter::authentication_available() ? 'pass' : 'fail';
\t\t$checks['crypto_keyring'] = GDO_Crypto::available() ? 'pass' : 'fail';
\t\t$checks['private_storage'] = is_wp_error( GDO_Storage::health() ) ? 'fail' : 'pass';
\t\t$checks['schema_version'] = absint( get_option( 'gdo_schema_version', 0 ) ) === GDO_SCHEMA_VERSION ? 'pass' : 'fail';
\t\t$checks['retention_cron'] = wp_next_scheduled( 'gdo_daily_retention' ) ? 'pass' : 'warn';
\t\t$checks['outbox_cron'] = wp_next_scheduled( 'gdo_notification_outbox' ) ? 'pass' : 'warn';
\t\t$modern_notifications = function_exists( 'sun_ingest_domain_event' ) && function_exists( 'sun_register_notification_producer' );
\t\t$checks['notification_provider'] = ( $modern_notifications || class_exists( 'SUN_Core' ) || has_action( 'sabri_notify' ) ) ? 'pass' : 'warn';
\t\t$checks['claim_signing_key'] = defined( 'GDO_CLAIM_SIGNING_KEY' ) && strlen( (string) GDO_CLAIM_SIGNING_KEY ) >= 32 ? 'pass' : 'fail';
\t\t$checks['claim_consumer'] = self::claim_consumer_available() ? 'pass' : 'warn';
\t\t$counts = array(
\t\t\t'dead_letters' => self::count_query( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'outbox' ) . " WHERE status='dead'", 'gdo_health_dead_letters' ),
\t\t\t'pending_outbox' => self::count_query( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'outbox' ) . " WHERE status IN ('pending','failed','processing')", 'gdo_health_pending_outbox' ),
\t\t\t'stale_claims' => self::count_query( $wpdb->prepare(
\t\t\t\t"SELECT COUNT(*) FROM " . GDO_Schema::table( 'applications' ) . " WHERE claim_status IN ('pending','failed','rejected') AND updated_at<%s",
\t\t\t\tgmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
\t\t\t), 'gdo_health_stale_claims' ),
\t\t\t'open_critical_risks' => self::count_query( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'risk_signals' ) . " WHERE severity='critical' AND status IN ('open','reviewing')", 'gdo_health_critical_risks' ),
\t\t);
\t\t$db_ok = true;
\t\tforeach ( $counts as $value ) {
\t\t\tif ( is_wp_error( $value ) ) { $db_ok = false; break; }
\t\t}
\t\t$checks['database_observability'] = $db_ok ? 'pass' : 'fail';
\t\t$dead = is_wp_error( $counts['dead_letters'] ) ? 0 : $counts['dead_letters'];
\t\t$pending = is_wp_error( $counts['pending_outbox'] ) ? 0 : $counts['pending_outbox'];
\t\t$stale_claims = is_wp_error( $counts['stale_claims'] ) ? 0 : $counts['stale_claims'];
\t\t$open_critical_risks = is_wp_error( $counts['open_critical_risks'] ) ? 0 : $counts['open_critical_risks'];
\t\t$checks['dead_letters'] = $dead ? 'warn' : 'pass';
\t\t$checks['stale_claims'] = $stale_claims ? 'warn' : 'pass';
\t\t$checks['critical_risks'] = $open_critical_risks ? 'warn' : 'pass';
\t\t$critical = array_keys( array_filter( $checks, function( $value ) { return 'fail' === $value; } ) );
\t\treturn array(
\t\t\t'status' => $critical ? 'degraded' : ( in_array( 'warn', $checks, true ) ? 'attention' : 'healthy' ),
\t\t\t'safe_mode'=>self::safe_mode(), 'checks'=>$checks, 'critical'=>$critical,
\t\t\t'dead_letters'=>$dead, 'pending_outbox'=>$pending, 'stale_claims'=>$stale_claims,
\t\t\t'open_critical_risks'=>$open_critical_risks, 'checked_at'=>gmdate( 'c' ),
\t\t\t'version'=>GDO_VERSION, 'schema'=>GDO_SCHEMA_VERSION, 'policy_version'=>GDO_Policy::VERSION,
\t\t);
\t}
'''
s = s[:h0] + health + s[h1:]
r0 = s.index("\tpublic static function reconcile( $limit = 100 ) {")
r1 = s.index("\n\tpublic static function repair(", r0)
reconcile = '''\tpublic static function reconcile( $limit = 100 ) {
\t\tglobal $wpdb;
\t\t$limit = max( 1, min( 500, absint( $limit ) ) );
\t\t$now = current_time( 'mysql', true );
\t\t$expired = $wpdb->get_results( $wpdb->prepare(
\t\t\t"SELECT id,user_id,row_version FROM " . GDO_Schema::table( 'applications' ) . " WHERE state IN ('verified','reinstated','renewal_due') AND verified_until IS NOT NULL AND verified_until<%s LIMIT %d",
\t\t\t$now, $limit
\t\t) );
\t\tif ( null === $expired || ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'gdo_reconcile_query_failed', __( 'Expired verification records could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\t$count = 0;
\t\tforeach ( $expired as $app ) {
\t\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\t\treturn new WP_Error( 'gdo_reconcile_transaction_failed', __( 'Verification reconciliation could not start a safe transaction.', 'global-doctor-onboarding' ) );
\t\t\t}
\t\t\t$result = GDO_State::transition( $app->id, 'expired', 0, 'verification_expired', 'Verification validity period ended.', $app->row_version, false );
\t\t\t$claim = is_wp_error( $result ) ? $result : GDO_Claims::issue( $app->id, 'expired', array(), false );
\t\t\t$notice = is_wp_error( $claim ) ? $claim : GDO_Notifications::queue( 'doctor_verification_expired', $app->user_id, array( 'application_id'=>$app->id ), false );
\t\t\tif ( is_wp_error( $result ) || is_wp_error( $claim ) || is_wp_error( $notice ) || false === $wpdb->query( 'COMMIT' ) ) {
\t\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t\t$error = is_wp_error( $result ) ? $result : ( is_wp_error( $claim ) ? $claim : ( is_wp_error( $notice ) ? $notice : new WP_Error( 'gdo_reconcile_commit_failed', __( 'Verification reconciliation could not be committed.', 'global-doctor-onboarding' ) ) ) );
\t\t\t\treturn $error;
\t\t\t}
\t\t\tGDO_Audit::publish_transition( $result );
\t\t\tGDO_Claims::publish( $claim );
\t\t\t++$count;
\t\t}
\t\t$outbox = GDO_Notifications::process( $limit );
\t\tif ( is_wp_error( $outbox ) ) { return $outbox; }
\t\tif ( ! self::record_metric( 'reconciliation.expired', $count, array( 'limit'=>$limit ) ) ) {
\t\t\treturn new WP_Error( 'gdo_reconcile_metric_failed', __( 'Reconciliation completed but its operational metric could not be recorded.', 'global-doctor-onboarding' ) );
\t\t}
\t\treturn array( 'expired_reconciled'=>$count, 'processed_at'=>gmdate( 'c' ) );
\t}
'''
s = s[:r0] + reconcile + s[r1:]
old = "\t\t} elseif ( 'schedules' === $action ) {\n\t\t\tif ( ! wp_next_scheduled( 'gdo_daily_retention' ) ) {\n\t\t\t\twp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gdo_daily_retention' );\n\t\t\t}\n\t\t\tif ( ! wp_next_scheduled( 'gdo_notification_outbox' ) ) {\n\t\t\t\twp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'gdo_notification_outbox' );\n\t\t\t}\n\t\t} elseif ( 'outbox' === $action ) {\n\t\t\tGDO_Notifications::process( 100 );\n"
new = "\t\t} elseif ( 'schedules' === $action ) {\n\t\t\tif ( ! wp_next_scheduled( 'gdo_daily_retention' ) ) {\n\t\t\t\t$scheduled = wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gdo_daily_retention', array(), true );\n\t\t\t\tif ( is_wp_error( $scheduled ) || false === $scheduled || ! wp_next_scheduled( 'gdo_daily_retention' ) ) { return new WP_Error( 'gdo_repair_retention_schedule', __( 'The retention schedule could not be persisted safely.', 'global-doctor-onboarding' ) ); }\n\t\t\t}\n\t\t\tif ( ! wp_next_scheduled( 'gdo_notification_outbox' ) ) {\n\t\t\t\t$scheduled = wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'gdo_notification_outbox', array(), true );\n\t\t\t\tif ( is_wp_error( $scheduled ) || false === $scheduled || ! wp_next_scheduled( 'gdo_notification_outbox' ) ) { return new WP_Error( 'gdo_repair_outbox_schedule', __( 'The outbox schedule could not be persisted safely.', 'global-doctor-onboarding' ) ); }\n\t\t\t}\n\t\t} elseif ( 'outbox' === $action ) {\n\t\t\t$result = GDO_Notifications::process( 100 );\n"
if old not in s:
    raise SystemExit('schedule/outbox repair anchor missing')
s = s.replace(old, new, 1)
old = "\t\tupdate_option( 'gdo_safe_mode', (bool) $enabled, false );\n\t\tGDO_Membership_Adapter::audit( $enabled ? 'doctor_verification_safe_mode_enabled' : 'doctor_verification_safe_mode_disabled', array( 'actor_id'=>absint( $actor_id ), 'reason'=>$reason ) );\n\t\treturn true;\n"
new = "\t\t$desired = (bool) $enabled;\n\t\tif ( self::safe_mode() !== $desired ) {\n\t\t\tupdate_option( 'gdo_safe_mode', $desired, false );\n\t\t}\n\t\tif ( self::safe_mode() !== $desired ) {\n\t\t\treturn new WP_Error( 'gdo_safe_mode_persist_failed', __( 'Safe Mode could not be persisted safely.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\tGDO_Membership_Adapter::audit( $desired ? 'doctor_verification_safe_mode_enabled' : 'doctor_verification_safe_mode_disabled', array( 'actor_id'=>absint( $actor_id ), 'reason'=>$reason ) );\n\t\treturn true;\n"
if old not in s:
    raise SystemExit('safe mode anchor missing')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

# R3-79: orphan cleanup must abort when the evidence inventory query is uncertain.
p = Path('includes/class-gdo-retention.php')
s = p.read_text(encoding='utf-8')
old = "\t\t$known = $wpdb->get_col( 'SELECT storage_name FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE deleted_at IS NULL' );\n\t\t$known = array_fill_keys( array_map( 'strval', $known ), true );\n"
new = "\t\t$known_rows = $wpdb->get_col( 'SELECT storage_name FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE deleted_at IS NULL' );\n\t\tif ( null === $known_rows || ! empty( $wpdb->last_error ) ) {\n\t\t\tGDO_Membership_Adapter::audit( 'doctor_credential_orphan_inventory_failed', array( 'reason'=>'database_inventory_unavailable' ) );\n\t\t\treturn false;\n\t\t}\n\t\t$known = array_fill_keys( array_map( 'strval', $known_rows ), true );\n"
if old not in s:
    raise SystemExit('orphan inventory anchor missing')
s = s.replace(old, new, 1)
needle = "\t\t}\n\t}\n}"
pos = s.rfind(needle)
if pos < 0:
    raise SystemExit('cleanup close anchor missing')
s = s[:pos] + "\t\t}\n\t\treturn true;\n\t}\n}" + s[pos + len(needle):]
p.write_text(s, encoding='utf-8')

# R3-80: legacy QA expected the earlier limiter expression and rejected the stronger fail-closed invariant.
replace_once(
    'tests/review40-adversarial.py',
    "require('return $hits <= $limit;' in rate,'rate limiter allow decision missing')",
    "require('return $hits > 0 && $hits <= $limit;' in rate,'rate limiter fail-closed allow decision missing')",
)

print('File 09 R3 root-cause corrections applied.')
