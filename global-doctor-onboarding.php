<?php
/**
 * Plugin Name: Global Doctor Onboarding and Verification Completion
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Canonical doctor application, private credential evidence, independent review, verification, suspension, renewal, appeal, privacy, and audit workflow.
 * Version: 1.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: global-doctor-onboarding
 */
defined( 'ABSPATH' ) || exit;

define( 'GDO_VERSION', '1.2.0' );
define( 'GDO_SCHEMA_VERSION', 6 );
define( 'GDO_CF01_PRACTITIONER_CONTRACT_VERSION', '1.0.0' );
define( 'GDO_FILE', __FILE__ );
define( 'GDO_DIR', plugin_dir_path( __FILE__ ) );
define( 'GDO_URL', plugin_dir_url( __FILE__ ) );

$gdo_files = array(
	'class-gdo-membership-adapter.php',
	'class-gdo-policy.php',
	'class-gdo-schema.php',
	'class-gdo-state.php',
	'class-gdo-crypto.php',
	'class-gdo-storage.php',
	'class-gdo-audit.php',
	'class-gdo-rate-limiter.php',
	'class-gdo-risk.php',
	'class-gdo-claims.php',
	'class-gdo-quality.php',
	'class-gdo-operations.php',
	'class-gdo-notifications.php',
	'class-gdo-evidence.php',
	'class-gdo-application.php',
	'class-gdo-admin.php',
	'class-gdo-frontend.php',
	'class-gdo-privacy.php',
	'class-gdo-retention.php',
	'class-gdo-migration.php',
	'class-gdo-api.php',
	'class-gdo-cf01-practitioner-contract.php',
	'class-gdo-integration-contracts.php',
	'class-gdo-rest.php',
	'class-gdo-activator.php',
	'class-gdo-plugin.php',
);
foreach ( $gdo_files as $gdo_file ) {
	require_once GDO_DIR . 'includes/' . $gdo_file;
}
unset( $gdo_files, $gdo_file );

register_activation_hook( GDO_FILE, array( 'GDO_Activator', 'activate' ) );
register_deactivation_hook( GDO_FILE, array( 'GDO_Activator', 'deactivate' ) );

function gdo_start() {
	$plugin = new GDO_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'gdo_start', 45 );
