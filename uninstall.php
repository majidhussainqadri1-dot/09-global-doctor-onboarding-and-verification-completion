<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$constant_authorized = defined( 'SABRI_ALLOW_DESTRUCTIVE_UNINSTALL' ) && true === SABRI_ALLOW_DESTRUCTIVE_UNINSTALL;
$option_authorized   = '1' === get_option( 'gdo_allow_destructive_uninstall', '0' );
$confirmation        = (string) get_option( 'gdo_destructive_uninstall_confirmation', '' );
$expected            = hash_hmac( 'sha256', 'file09-destructive-uninstall', wp_salt( 'auth' ) );
if ( ! $constant_authorized || ! $option_authorized || ! hash_equals( $expected, $confirmation ) ) {
	return;
}

global $wpdb;
$dir = defined( 'GDO_PRIVATE_STORAGE_DIR' ) ? wp_normalize_path( untrailingslashit( GDO_PRIVATE_STORAGE_DIR ) ) : '';
$uploads = wp_normalize_path( untrailingslashit( wp_upload_dir()['basedir'] ) );
$content = wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) );
if ( $dir && is_dir( $dir ) && 0 !== strpos( $dir . '/', $uploads . '/' ) && 0 !== strpos( $dir . '/', $content . '/' ) && ! is_link( $dir ) ) {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $files as $file ) {
		if ( $file->isLink() ) {
			continue;
		}
		$file->isDir() ? @rmdir( $file->getPathname() ) : @unlink( $file->getPathname() );
	}
	@rmdir( $dir );
}

$tables = array(
    'applications','evidence','transitions','consents','access_log','appeals','outbox','rate_limits','reviewer_profiles','risk_signals','quality_samples','access_grants','metrics',
    'trusted_issuers','jurisdiction_rules','credential_checks','professional_history','reviewer_conflicts','verification_passports','monitor_state','upload_sessions',
);
foreach ( $tables as $name ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'gdo_' . $name . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
$map = (array) get_option( 'gdo_page_map', array() );
if ( ! empty( $map['apply'] ) && 'doctor-application' === get_post_meta( absint( $map['apply'] ), '_gdo_managed_page_key', true ) ) {
	wp_delete_post( absint( $map['apply'] ), true );
}
$options = array(
    'gdo_page_map','gdo_version','gdo_schema_version','gdo_advanced_trust_schema','gdo_activation_evidence',
    'gdo_schema_migration_lock','gdo_last_migration','gdo_legacy_migration_user_checkpoint','gdo_safe_mode',
    'gdo_allow_destructive_uninstall','gdo_destructive_uninstall_confirmation',
);
foreach ( $options as $option ) {
	delete_option( $option );
}
wp_clear_scheduled_hook( 'gdo_daily_retention' );
wp_clear_scheduled_hook( 'gdo_notification_outbox' );
wp_clear_scheduled_hook( 'gdo_trust_continuous_monitor' );
