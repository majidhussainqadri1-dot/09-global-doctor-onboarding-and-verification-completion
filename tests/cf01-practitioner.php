<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'GDO_VERSION', '1.1.1' );

$GLOBALS['gdo_user_exists'] = true;
$GLOBALS['gdo_restriction_verified'] = false;
$GLOBALS['gdo_narrow_result'] = '';

function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function wp_generate_uuid4() { return '123e4567-e89b-42d3-a456-426614174000'; }
function get_userdata( $user_id ) { return $GLOBALS['gdo_user_exists'] && 7 === (int) $user_id ? (object) array( 'ID' => 7 ) : false; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function apply_filters( $hook, $value ) {
	$args = func_get_args();
	if ( 'gdo_cf01_practitioner_scope' === $hook && $GLOBALS['gdo_restriction_verified'] && is_array( $value ) ) {
		$value['restriction_status'] = 'verified';
		$value['restrictions'] = array();
	}
	if ( 'gdo_cf01_practitioner_result' === $hook && $GLOBALS['gdo_narrow_result'] && is_array( $value ) ) {
		$value['result'] = $GLOBALS['gdo_narrow_result'];
		$value['reason_code'] = 'independent_reviewer_narrowed';
	}
	return $value;
}

final class GDO_Membership_Adapter {
	public static $available = true;
	public static $assertion = array();
	public static function available() { return self::$available; }
	public static function membership_assertion( $user_id, $action, $purpose, $jurisdiction = '' ) { return self::$assertion; }
}

final class GDO_API {
	public static $decision = array();
	public static function latest_decision( $user_id ) { return self::$decision; }
}

final class GDO_Application {
	public static $snapshot = array();
	public static function approved_snapshot( $application_id ) { return self::$snapshot; }
}

require dirname( __DIR__ ) . '/includes/class-gdo-cf01-practitioner-contract.php';

$tests = 0;
function gdo_runtime_assert( $condition, $message ) {
	global $tests;
	++$tests;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$uuid = '123e4567-e89b-42d3-a456-426614174000';
$verified_date = gmdate( 'Y-m-d', time() + ( 30 * 86400 ) );
$verified_until = gmdate( 'Y-m-d 23:59:59', strtotime( $verified_date . ' 23:59:59 UTC' ) );
$future_evidence = gmdate( 'Y-m-d', time() + ( 180 * 86400 ) );

$membership_allow = array(
	'contract' => 'smc.cf01.membership-assurance',
	'contract_version' => '1.0.0',
	'result' => 'allow',
	'reason_code' => 'capability_allowed',
	'subject' => array( 'platform_uuid' => $uuid, 'record_version' => 4 ),
	'membership' => array( 'status' => 'active', 'active' => true, 'suspended' => false, 'identity_assurance' => 'verified' ),
);
$decision_verified = array(
	'application_id' => 9,
	'application_uuid' => $uuid,
	'version' => 2,
	'row_version' => 11,
	'state' => 'verified',
	'verified' => true,
	'verified_until' => $verified_until,
	'fingerprint' => str_repeat( 'a', 64 ),
	'checked_at' => gmdate( 'c' ),
);
$snapshot_valid = array(
	'schema' => 3,
	'application_uuid' => $uuid,
	'application_version' => 2,
	'profile' => array(
		'country' => 'Pakistan',
		'city' => 'Gujrat',
		'qualification' => 'DHMS',
		'licensing_authority' => 'National Council for Homeopathy',
		'specialty' => 'Classical Homeopathy',
		'consultation_modes' => 'Online and in person',
		'license_number' => 'PRIVATE-123',
	),
	'evidence' => array(
		'identity' => array( 'version' => 1, 'status' => 'accepted', 'validity_until' => '' ),
		'qualification' => array( 'version' => 1, 'status' => 'accepted', 'validity_until' => '' ),
		'license' => array( 'version' => 3, 'status' => 'accepted', 'validity_until' => $future_evidence ),
	),
	'verified_until' => $verified_date,
);

GDO_Membership_Adapter::$assertion = $membership_allow;
GDO_API::$decision = $decision_verified;
GDO_Application::$snapshot = $snapshot_valid;

$GLOBALS['gdo_user_exists'] = false;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'unknown' === $result['result'] && 'subject_unavailable' === $result['reason_code'], 'missing subject fails unknown' );
$GLOBALS['gdo_user_exists'] = true;

$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'unsupported', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'unknown' === $result['result'] && 'unsupported_action_or_purpose' === $result['reason_code'], 'unsupported action fails unknown' );

$membership_deny = $membership_allow;
$membership_deny['result'] = 'deny';
$membership_deny['reason_code'] = 'membership_suspended';
$membership_deny['membership']['suspended'] = true;
GDO_Membership_Adapter::$assertion = $membership_deny;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'], 'File 00 suspension denies professional eligibility' );
GDO_Membership_Adapter::$assertion = $membership_allow;

$unverified = $decision_verified;
$unverified['verified'] = false;
$unverified['state'] = 'suspended';
GDO_API::$decision = $unverified;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'professional_status_suspended' === $result['reason_code'], 'suspended File 09 decision denies eligibility' );
GDO_API::$decision = $decision_verified;

GDO_Application::$snapshot = array();
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'approved_snapshot_invalid' === $result['reason_code'], 'missing approved snapshot fails closed' );
GDO_Application::$snapshot = $snapshot_valid;

$expired_snapshot = $snapshot_valid;
$expired_snapshot['evidence']['license']['validity_until'] = gmdate( 'Y-m-d', time() - 86400 );
GDO_Application::$snapshot = $expired_snapshot;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'professional_evidence_not_current' === $result['reason_code'], 'expired license evidence denies eligibility' );
GDO_Application::$snapshot = $snapshot_valid;

$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'allow' === $result['result'], 'current verified practitioner is eligible for bounded clinical-read consideration' );
gdo_runtime_assert( false === $result['grants_clinical_authorization'], 'allow result never grants clinical authorization' );
gdo_runtime_assert( ! isset( $result['professional_scope']['license_number'] ), 'license number is excluded from public assertion' );
gdo_runtime_assert( true === $result['authorization_limits']['requires_cf01_treating_relationship'], 'CF-01 treating relationship remains mandatory' );

$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care', 'jurisdiction' => 'India' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'professional_jurisdiction_mismatch' === $result['reason_code'], 'explicit jurisdiction mismatch denies eligibility' );

$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'prescription_sign', 'purpose' => 'prescription', 'jurisdiction' => 'Pakistan' ) );
gdo_runtime_assert( 'unknown' === $result['result'] && 'professional_scope_restrictions_not_structured' === $result['reason_code'], 'prescription signing remains blocked without structured restrictions' );

$GLOBALS['gdo_restriction_verified'] = true;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'prescription_sign', 'purpose' => 'prescription', 'jurisdiction' => 'Pakistan' ) );
gdo_runtime_assert( 'allow' === $result['result'], 'verified structured scope can admit bounded prescription eligibility' );

$GLOBALS['gdo_narrow_result'] = 'deny';
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'independent_reviewer_narrowed' === $result['reason_code'], 'extension filter may revoke an allow result' );
$GLOBALS['gdo_narrow_result'] = '';

$contract = GDO_CF01_Practitioner_Contract::contract();
gdo_runtime_assert( 'File 09' === $contract['owner'], 'contract owner is File 09' );
gdo_runtime_assert( false === $contract['writes_data'], 'provider contract is read-only' );
gdo_runtime_assert( in_array( 'treating_relationship', $contract['excludes'], true ), 'treating relationship exclusion is explicit' );

echo "File 09 CF-01 practitioner runtime: {$tests} PASS, 0 FAIL\n";
