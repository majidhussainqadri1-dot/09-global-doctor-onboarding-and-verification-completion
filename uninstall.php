<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$constant_authorized = defined( 'SABRI_ALLOW_DESTRUCTIVE_UNINSTALL' ) && true === SABRI_ALLOW_DESTRUCTIVE_UNINSTALL;
$option_authorized   = '1' === get_option( 'gdo_allow_destructive_uninstall', '0' );
$confirmation        = (string) get_option( 'gdo_destructive_uninstall_confirmation', '' );
$expected            = hash_hmac( 'sha256', 'file09-destructive-uninstall', wp_salt( 'auth' ) );
if ( ! $constant_authorized || ! $option_authorized || ! hash_equals( $expected, $confirmation ) ) { return; }

$abort = static function( $message ) { wp_die( esc_html( $message ), 'File 09 destructive uninstall stopped', array( 'response'=>500 ) ); };
global $wpdb;
$dir = defined( 'GDO_PRIVATE_STORAGE_DIR' ) ? wp_normalize_path( untrailingslashit( GDO_PRIVATE_STORAGE_DIR ) ) : '';
$uploads = realpath( wp_upload_dir()['basedir'] );
$content = realpath( WP_CONTENT_DIR );
if ( $dir ) {
    if ( is_link( $dir ) ) { $abort( 'File 09 private storage is a symlink; destructive uninstall was stopped.' ); }
    if ( is_dir( $dir ) ) {
        $real_dir = realpath( $dir );
        if ( false === $real_dir || false === $uploads || false === $content ) { $abort( 'File 09 private storage could not be resolved safely.' ); }
        $real_dir = trailingslashit( wp_normalize_path( $real_dir ) );
        $uploads = trailingslashit( wp_normalize_path( $uploads ) );
        $content = trailingslashit( wp_normalize_path( $content ) );
        if ( 0 === strpos( $real_dir, $uploads ) || 0 === strpos( $real_dir, $content ) ) { $abort( 'File 09 private storage resolved inside a WordPress-served tree; destructive uninstall was stopped.' ); }
        $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( untrailingslashit( $real_dir ), FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $files as $file ) {
            $path = $file->getPathname();
            if ( $file->isLink() ) { if ( ! @unlink( $path ) && is_link( $path ) ) { $abort( 'A File 09 private-storage symlink could not be removed.' ); } continue; }
            if ( $file->isDir() ) { if ( ! @rmdir( $path ) && is_dir( $path ) ) { $abort( 'A File 09 private-storage directory could not be removed.' ); } }
            elseif ( ! @unlink( $path ) && file_exists( $path ) ) { $abort( 'A File 09 private-storage file could not be removed.' ); }
        }
        $base = untrailingslashit( $real_dir );
        if ( ! @rmdir( $base ) && is_dir( $base ) ) { $abort( 'The File 09 private-storage root could not be removed.' ); }
    }
}

$tables = array(
    'applications','evidence','transitions','consents','access_log','appeals','outbox','rate_limits','reviewer_profiles','risk_signals','quality_samples','access_grants','metrics',
    'trusted_issuers','jurisdiction_rules','credential_checks','professional_history','reviewer_conflicts','verification_passports','monitor_state','upload_sessions',
);
foreach ( $tables as $name ) {
    $table = $wpdb->prefix . 'gdo_' . $name;
    if ( false === $wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' ) ) { $abort( 'A File 09 database table could not be removed.' ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists ) { $abort( 'A File 09 database table still exists after destructive uninstall.' ); }
}
$map = (array) get_option( 'gdo_page_map', array() );
if ( ! empty( $map['apply'] ) && 'doctor-application' === get_post_meta( absint( $map['apply'] ), '_gdo_managed_page_key', true ) ) {
    if ( ! wp_delete_post( absint( $map['apply'] ), true ) ) { $abort( 'The File 09 managed page could not be removed.' ); }
}
$options = array(
    'gdo_page_map','gdo_version','gdo_schema_version','gdo_advanced_trust_schema','gdo_activation_evidence',
    'gdo_schema_migration_lock','gdo_last_migration','gdo_legacy_migration_user_checkpoint','gdo_safe_mode',
    'gdo_allow_destructive_uninstall','gdo_destructive_uninstall_confirmation',
);
foreach ( $options as $option ) {
    delete_option( $option );
    if ( '__gdo_missing__' !== get_option( $option, '__gdo_missing__' ) ) { $abort( 'A File 09 option could not be removed.' ); }
}
foreach ( array( 'gdo_daily_retention','gdo_notification_outbox','gdo_trust_continuous_monitor','gdo_trust_reverification_wakeup' ) as $hook ) {
    $cleared = wp_clear_scheduled_hook( $hook, array(), true );
    if ( is_wp_error( $cleared ) ) { $abort( 'A File 09 scheduled task could not be removed.' ); }
}
