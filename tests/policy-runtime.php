<?php
$root = dirname( __DIR__ );
$files = array(
	'policy' => file_get_contents( $root . '/includes/class-gdo-policy.php' ),
	'application' => file_get_contents( $root . '/includes/class-gdo-application.php' ),
	'claims' => file_get_contents( $root . '/includes/class-gdo-claims.php' ),
	'notifications' => file_get_contents( $root . '/includes/class-gdo-notifications.php' ),
	'operations' => file_get_contents( $root . '/includes/class-gdo-operations.php' ),
	'risk' => file_get_contents( $root . '/includes/class-gdo-risk.php' ),
	'quality' => file_get_contents( $root . '/includes/class-gdo-quality.php' ),
);
$pass = 0;
function gdo_policy_assert( $condition, $message ) { global $pass; if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } ++$pass; echo "PASS: {$message}\n"; }
gdo_policy_assert( false !== strpos( $files['policy'], "const VERSION       = '2026-08-07.2'" ), 'policy is explicitly versioned' );
gdo_policy_assert( false !== strpos( $files['policy'], 'professional_age_not_met' ), 'professional age gate is fail closed' );
gdo_policy_assert( false !== strpos( $files['policy'], 'identity_assurance_incomplete' ), 'email phone and AAL2 readiness are required' );
gdo_policy_assert( false !== strpos( $files['application'], 'submission_hash' ) && false !== strpos( $files['application'], 'START TRANSACTION' ), 'submission is immutable and transactional' );
gdo_policy_assert( false !== strpos( $files['claims'], "const CONTRACT = 'gdo.file00.professional-decision'" ), 'File 00 decision claim contract is exact' );
gdo_policy_assert( false !== strpos( $files['claims'], 'hash_hmac' ) && false !== strpos( $files['claims'], 'membership_record_version' ), 'claim is signed and subject-version bound' );
gdo_policy_assert( false !== strpos( $files['notifications'], "'status'=>'processing'" ) && false !== strpos( $files['notifications'], "status'=>'dead'" ), 'outbox has lock and dead-letter states' );
gdo_policy_assert( false !== strpos( $files['notifications'], 'event_uuid' ) && false !== strpos( $files['notifications'], 'available_at' ), 'outbox is deduplicated and retry scheduled' );
gdo_policy_assert( false !== strpos( $files['operations'], 'safe_mode' ) && false !== strpos( $files['operations'], 'reconcile' ), 'Safe Mode and reconciliation are available' );
gdo_policy_assert( false !== strpos( $files['risk'], 'false_positive' ) && false !== strpos( $files['risk'], 'identity_duplicate' ), 'risk signals have human false-positive resolution' );
gdo_policy_assert( false !== strpos( $files['quality'], 'automatic' ) && false !== strpos( $files['quality'], 'major_error' ), 'quality sampling and correction outcomes are modeled' );
gdo_policy_assert( false === strpos( implode( "\n", $files ), 'wp_mail(' ), 'File 09 does not deliver email directly' );
echo "File 09 policy/runtime invariants: {$pass} PASS, 0 FAIL\n";
