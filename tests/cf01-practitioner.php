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
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function apply_filters( $hook, $value ) {
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
	public static $subject = array();
	public static $base = array();
	public static function available() { return self::$available; }
	public static function membership_assertion( $user_id, $action, $purpose, $jurisdiction = '' ) { return self::$subject; }
	public static function base_assertion( $user_id ) { return self::$base; }
}

final class GDO_API {
	public static $decision = array();
	public static function latest_decision( $user_id ) { return self::$decision; }
}

final class GDO_Application {
	public static $snapshot = array();
	public static function approved_snapshot( $application_id ) { return self::$snapshot; }
	public static function fingerprint( array $profile, array $evidence = array() ) {
		ksort( $profile );
		ksort( $evidence );
		return hash( 'sha256', wp_json_encode( array( 'profile' => $profile, 'evidence' => $evidence ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}
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

$subject_assertion = array(
	'contract' => 'smc.cf01.membership-assurance',
	'contract_version' => '1.0.0',
	'result' => 'deny',
	'reason_code' => 'capability_denied_before_file09_verification',
	'subject' => array( 'platform_uuid' => $uuid, 'record_version' => 4 ),
);
$base_active = array(
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
		'identity' => array( 'version' => 1, 'content_hmac' => str_repeat( 'b', 64 ), 'status' => 'accepted', 'validity_until' => '' ),
		'qualification' => array( 'version' => 1, 'content_hmac' => str_repeat( 'c', 64 ), 'status' => 'accepted', 'validity_until' => '' ),
		'license' => array( 'version' => 3, 'content_hmac' => str_repeat( 'd', 64 ), 'status' => 'accepted', 'validity_until' => $future_evidence ),
	),
	'verified_until' => $verified_date,
);
$fingerprint = GDO_Application::fingerprint( $snapshot_valid['profile'], $snapshot_valid['evidence'] );
$decision_verified = array(
	'application_id' => 9,
	'application_uuid' => $uuid,
	'version' => 2,
	'row_version' => 11,
	'state' => 'verified',
	'verified' => true,
	'verified_until' => $verified_until,
	'fingerprint' => $fingerprint,
	'checked_at' => gmdate( 'c' ),
);

GDO_Membership_Adapter::$subject = $subject_assertion;
GDO_Membership_Adapter::$base = $base_active;
GDO_API::$decision = $decision_verified;
GDO_Application::$snapshot = $snapshot_valid;

$GLOBALS['gdo_user_exists'] = false;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'unknown' === $result['result'] && 'subject_unavailable' === $result['reason_code'], 'missing subject fails unknown' );
$GLOBALS['gdo_user_exists'] = true;

$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'unsupported', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'unknown' === $result['result'] && 'unsupported_action_or_purpose' === $result['reason_code'], 'unsupported action fails unknown' );

$suspended = $base_active;
$suspended['suspended'] = true;
$suspended['status'] = 'suspended';
GDO_Membership_Adapter::$base = $suspended;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'membership_not_current' === $result['reason_code'], 'File 00 suspension denies professional eligibility' );
GDO_Membership_Adapter::$base = $base_active;

$identity_incomplete = $base_active;
$identity_incomplete['phone_verified'] = false;
GDO_Membership_Adapter::$base = $identity_incomplete;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'membership_identity_assurance_incomplete' === $result['reason_code'], 'explicit contact assurance is required' );
GDO_Membership_Adapter::$base = $base_active;

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

$tampered_decision = $decision_verified;
$tampered_decision['fingerprint'] = str_repeat( 'f', 64 );
GDO_API::$decision = $tampered_decision;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'approved_snapshot_invalid' === $result['reason_code'], 'snapshot fingerprint mismatch fails closed' );
GDO_API::$decision = $decision_verified;

$expired_snapshot = $snapshot_valid;
$expired_snapshot['evidence']['license']['validity_until'] = gmdate( 'Y-m-d', time() - 86400 );
$expired_decision = $decision_verified;
$expired_decision['fingerprint'] = GDO_Application::fingerprint( $expired_snapshot['profile'], $expired_snapshot['evidence'] );
GDO_Application::$snapshot = $expired_snapshot;
GDO_API::$decision = $expired_decision;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'professional_evidence_not_current' === $result['reason_code'], 'expired license evidence denies eligibility' );
GDO_Application::$snapshot = $snapshot_valid;
GDO_API::$decision = $decision_verified;

$blank_license = $snapshot_valid;
$blank_license['evidence']['license']['validity_until'] = '';
$blank_decision = $decision_verified;
$blank_decision['fingerprint'] = GDO_Application::fingerprint( $blank_license['profile'], $blank_license['evidence'] );
GDO_Application::$snapshot = $blank_license;
GDO_API::$decision = $blank_decision;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'deny' === $result['result'] && 'professional_evidence_not_current' === $result['reason_code'], 'license evidence requires an explicit future validity date' );
GDO_Application::$snapshot = $snapshot_valid;
GDO_API::$decision = $decision_verified;

$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'allow' === $result['result'], 'File 09 may verify an approved doctor without circular prior professional verification' );
gdo_runtime_assert( false === $result['grants_clinical_authorization'], 'allow result never grants clinical authorization' );
gdo_runtime_assert( ! isset( $result['professional_scope']['license_number'] ), 'license number is excluded from public assertion' );
gdo_runtime_assert( true === $result['authorization_limits']['requires_cf01_treating_relationship'], 'CF-01 treating relationship remains mandatory' );

$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care', 'jurisdiction' => 'PK' ) );
gdo_runtime_assert( 'allow' === $result['result'], 'Pakistan name and ISO code normalize to the same jurisdiction' );

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
