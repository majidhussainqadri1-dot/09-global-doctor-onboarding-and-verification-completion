<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'SMC_VERSION', '1.2.7' );
define( 'SMC_CONTRACT_VERSION', '1.1.2' );
define( 'SMC_CF01_CONTRACT_VERSION', '1.0.0' );
define( 'SA_PROFESSIONAL_REAUTH_VERSION', '1.0.0' );

$GLOBALS['gdo_current_user'] = 7;
$GLOBALS['gdo_user_caps'] = array( 'smc_review_verification' => true );

function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function wp_generate_uuid4() { return '123e4567-e89b-42d3-a456-426614174000'; }
function get_current_user_id() { return (int) $GLOBALS['gdo_current_user']; }
function user_can( $user_id, $capability ) { return ! empty( $GLOBALS['gdo_user_caps'][ $capability ] ); }
function apply_filters( $hook, $value ) { return $value; }
function do_action() {}
function __( $value ) { return $value; }
function smc_get_profile( $user_id ) { return array( 'display_name' => 'Doctor Candidate' ); }

final class WP_Error {
	public function __construct( $code = '', $message = '' ) {}
}

final class SMC_Contracts {
	public static $assertion = array();
	public static function assertions( $user_id ) { return self::$assertion; }
}

final class SMC_CF01_Contract {
	public static $assertion = array();
	public static function membership_assertion( $user_id, $context ) { return self::$assertion; }
}

final class SA_Professional_Reauthentication {
	public static function verify_and_record( $user_id, $password, $otp, $context ) { return array(); }
	public static function assertion( $user_id, $scope ) { return array(); }
	public static function clear_current_session() {}
}

require dirname( __DIR__ ) . '/includes/class-gdo-membership-adapter.php';

$tests = 0;
function gdo_adapter_assert( $condition, $message ) {
	global $tests;
	++$tests;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$uuid = '123e4567-e89b-42d3-a456-426614174000';
$base = array(
	'contract_version' => '1.1.2',
	'user_id' => 7,
	'membership_type' => 'doctor',
	'status' => 'approved',
	'approved' => true,
	'suspended' => false,
	'two_factor_ready' => true,
	'phone_verified' => true,
	'email_verified' => true,
	'guardian_verified' => true,
	'professional_verified' => false,
);
$subject = array(
	'contract' => 'smc.cf01.membership-assurance',
	'contract_version' => '1.0.0',
	'result' => 'deny',
	'reason_code' => 'capability_denied_before_file09_verification',
	'subject' => array( 'platform_uuid' => $uuid, 'record_version' => 3 ),
	'age_context' => array( 'known' => true, 'age_years' => 30 ),
	'issued_at' => gmdate( 'c', time() - 1 ),
	'expires_at' => gmdate( 'c', time() + 60 ),
);
SMC_Contracts::$assertion = $base;
SMC_CF01_Contract::$assertion = $subject;

gdo_adapter_assert( GDO_Membership_Adapter::available(), 'exact File 00 contracts are available' );
gdo_adapter_assert( GDO_Membership_Adapter::authentication_available(), 'exact File 02 reauthentication contract is available' );
gdo_adapter_assert( GDO_Membership_Adapter::is_active_doctor_candidate( 7 ), 'approved doctor may enter File 09 before professional verification' );

$profile = GDO_Membership_Adapter::profile( 7 );
gdo_adapter_assert( true === $profile['email_verified'], 'email verification comes from explicit File 00 field' );
gdo_adapter_assert( true === $profile['mobile_verified'], 'phone verification comes from explicit File 00 field' );
gdo_adapter_assert( false === $profile['doctor_verified'], 'legacy professional display flag does not fabricate File 09 approval' );
gdo_adapter_assert( $uuid === $profile['platform_uuid'], 'opaque File 00 subject UUID is preserved' );

$bad = $base;
$bad['email_verified'] = false;
SMC_Contracts::$assertion = $bad;
gdo_adapter_assert( ! GDO_Membership_Adapter::is_active_doctor_candidate( 7 ), 'unverified email blocks doctor application eligibility' );

SMC_Contracts::$assertion = $base;
$young = $subject;
$young['age_context']['age_years'] = 17;
SMC_CF01_Contract::$assertion = $young;
gdo_adapter_assert( ! GDO_Membership_Adapter::is_active_doctor_candidate( 7 ), 'under-18 professional application is blocked' );

SMC_CF01_Contract::$assertion = $subject;
$suspended = $base;
$suspended['suspended'] = true;
$suspended['status'] = 'suspended';
SMC_Contracts::$assertion = $suspended;
gdo_adapter_assert( GDO_Membership_Adapter::sanctioned( 7 ), 'current File 00 suspension is authoritative' );
gdo_adapter_assert( ! GDO_Membership_Adapter::can( 'sabri_verify_doctors', 7 ), 'suspended reviewer capability fails closed' );

SMC_Contracts::$assertion = $base;
gdo_adapter_assert( GDO_Membership_Adapter::can( 'sabri_verify_doctors', 7 ), 'approved reviewer capability is checked through File 00' );

$wrong_user = $base;
$wrong_user['user_id'] = 8;
SMC_Contracts::$assertion = $wrong_user;
gdo_adapter_assert( array() === GDO_Membership_Adapter::base_assertion( 7 ), 'File 00 subject mismatch fails closed' );

echo "File 09 membership adapter: {$tests} PASS, 0 FAIL\n";
