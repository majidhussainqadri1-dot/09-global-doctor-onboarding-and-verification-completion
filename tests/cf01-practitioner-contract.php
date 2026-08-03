<?php

$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/global-doctor-onboarding.php' );
$adapter = file_get_contents( $root . '/includes/class-gdo-membership-adapter.php' );
$api = file_get_contents( $root . '/includes/class-gdo-api.php' );
$contract = file_get_contents( $root . '/includes/class-gdo-cf01-practitioner-contract.php' );
$includes = '';
foreach ( glob( $root . '/includes/*.php' ) as $path ) {
	$includes .= "\n" . file_get_contents( $path );
}

$tests = 0;
function gdo_cf01_static_assert( $condition, $message ) {
	global $tests;
	++$tests;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

gdo_cf01_static_assert( false !== strpos( $main, 'Version: 1.1.1' ), 'plugin header is File 09 1.1.1' );
gdo_cf01_static_assert( false !== strpos( $main, "define( 'GDO_VERSION', '1.1.1' )" ), 'runtime version is File 09 1.1.1' );
gdo_cf01_static_assert( false !== strpos( $main, "define( 'GDO_CF01_PRACTITIONER_CONTRACT_VERSION', '1.0.0' )" ), 'practitioner contract version is explicit' );
gdo_cf01_static_assert( false !== strpos( $main, 'class-gdo-cf01-practitioner-contract.php' ), 'practitioner provider loads from bootstrap' );
gdo_cf01_static_assert( false !== strpos( $contract, "'gdo.cf01.practitioner-eligibility'" ), 'contract name is exact' );
gdo_cf01_static_assert( false !== strpos( $contract, "const CONTRACT_VERSION = '1.0.0'" ), 'contract version is exact' );
gdo_cf01_static_assert( false !== strpos( $contract, "'grants_clinical_authorization' => false" ), 'professional eligibility never grants clinical authorization' );
gdo_cf01_static_assert( false !== strpos( $contract, "'requires_cf01_treating_relationship' => true" ), 'treating relationship remains a CF-01 requirement' );
gdo_cf01_static_assert( false !== strpos( $contract, "'appointment_does_not_create_relationship' => true" ), 'appointment never creates a treating relationship' );
gdo_cf01_static_assert( false !== strpos( $contract, "'prescription_sign'" ) && false !== strpos( $contract, "'professional_scope_restrictions_not_structured'" ), 'prescription signing fails closed without structured scope restrictions' );
gdo_cf01_static_assert( false !== strpos( $contract, "'break_glass'" ), 'break-glass professional eligibility is explicitly modeled' );
gdo_cf01_static_assert( false !== strpos( $contract, 'apply_monotonic_filter' ), 'extension filtering is monotonic and revoke-only' );
gdo_cf01_static_assert( false !== strpos( $adapter, 'SMC_CF01_Contract::membership_assertion' ), 'File 09 consumes File 00 public membership contract' );
gdo_cf01_static_assert( false !== strpos( $adapter, 'SA_Professional_Reauthentication::verify_and_record' ), 'File 09 consumes File 02 professional reauthentication contract' );
gdo_cf01_static_assert( false === strpos( $adapter, 'get_user_meta(' ), 'membership adapter does not read File 00 metadata directly' );
gdo_cf01_static_assert( false === strpos( $adapter, 'wp_check_password(' ), 'File 09 does not verify passwords inside its adapter' );
foreach ( array( '_smc_totp_secret', '_smc_totp_secret_enc', '_smc_2fa_enabled', '_smc_identity_verified', '_smc_doctor_verified', '_smc_recovery' ) as $private_key ) {
	gdo_cf01_static_assert( false === strpos( $includes, $private_key ), 'private File 00 storage key is absent: ' . $private_key );
}
gdo_cf01_static_assert( false !== strpos( $api, "'row_version'" ), 'decision API exposes optimistic row version' );
gdo_cf01_static_assert( false !== strpos( $api, "'checked_at'" ), 'decision API exposes action-time check timestamp' );
gdo_cf01_static_assert( false === strpos( $contract, "'license_number'" ), 'public practitioner assertion excludes license number' );
gdo_cf01_static_assert( false !== strpos( $contract, 'gdo_cf01_practitioner_assertion' ), 'owner-executed practitioner assertion function exists' );
gdo_cf01_static_assert( false !== strpos( $contract, 'gdo_cf01_practitioner_contract' ), 'contract metadata function exists' );

echo "File 09 CF-01 static contract: {$tests} PASS, 0 FAIL\n";
