<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }

function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function __( $v, $d = null ) { return $v; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function esc_url_raw( $v, $protocols = null ) { unset( $protocols ); return (string) $v; }
function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( $path, '/' ); }
function add_filter( $a, $b, $p = 10, $n = 1 ) { unset( $a, $b, $p, $n ); return true; }

class WP_Error {
	public $code;
	public $message;
	public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; }
}

class GDO_API {
	public static $decision = array();
	public static $snapshot = array();
	public static function latest_decision( $user_id ) { unset( $user_id ); return self::$decision; }
	public static function snapshot( $user_id ) { unset( $user_id ); return self::$snapshot; }
}

class GDO_Application {
	public static function approved_snapshot( $application_id ) { unset( $application_id ); return GDO_API::$snapshot; }
}

require dirname( __DIR__ ) . '/includes/class-gdo-integration-contracts.php';

$private_profile = array(
	'professional_title' => 'Homeopathic Doctor',
	'qualification' => 'DHMS',
	'license_number' => 'LIC-12345',
	'licensing_authority' => 'Professional Council',
	'license_jurisdiction' => 'PK',
	'experience_years' => '15',
	'specialty' => 'Classical Homeopathy',
	'languages' => 'Urdu, English',
	'consultation_modes' => 'Clinic, online',
	'country' => 'Pakistan',
	'city' => 'Gujrat',
	'bio' => 'Verified professional biography.',
	'phone' => '+92-300-1111111',
	'whatsapp' => '+92-300-2222222',
	'clinic' => 'Private clinic address',
	'services' => 'Private application service detail',
	'declaration_accuracy' => '1',
	'declaration_no_impersonation' => '1',
	'declaration_professional_scope' => '1',
);
GDO_API::$decision = array(
	'application_id' => 91,
	'application_uuid' => '00000000-0000-4000-8000-000000000091',
	'version' => 4,
	'state' => 'verified',
	'verified' => true,
	'limited' => false,
	'verified_until' => '2030-12-31 23:59:59',
	'fingerprint' => str_repeat( 'a', 64 ),
	'claim_version' => 8,
	'claim_status' => 'accepted',
	'checked_at' => gmdate( 'c' ),
);
GDO_API::$snapshot = array(
	'profile' => $private_profile,
	'finalizer_id' => 77,
	'captured_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ),
);

$projection = GDO_Integration_Contracts::file03_public_projection( null, 42, 'file03-contract' );
if ( empty( $projection ) || 'verified' !== $projection['status'] ) { throw new RuntimeException( 'File 03 public verification projection missing.' ); }
if ( 'LIC-12345' !== ( $projection['approved_fields']['licence_number'] ?? '' ) ) { throw new RuntimeException( 'File 09 license_number was not mapped to File 03 licence_number.' ); }
if ( 'PK' !== ( $projection['approved_fields']['jurisdiction'] ?? '' ) ) { throw new RuntimeException( 'File 09 license_jurisdiction was not mapped to File 03 jurisdiction.' ); }
foreach ( array( 'phone','whatsapp','clinic','services','declaration_accuracy','declaration_no_impersonation','declaration_professional_scope','license_number','license_jurisdiction' ) as $forbidden ) {
	if ( array_key_exists( $forbidden, $projection['approved_fields'] ) ) { throw new RuntimeException( 'Private or non-contract File 09 field leaked to File 03: ' . $forbidden ); }
}
if ( ! gdo_validate_public_projection( $projection, 42, 'file03-contract' ) ) { throw new RuntimeException( 'Exact File 03 public projection validator rejected the current owner projection.' ); }

$wallet = GDO_Integration_Contracts::file03_verifiable_credentials( null, 42, 0, 'file03-contract' );
if ( empty( $wallet['items'] ) || ! empty( $wallet['raw_evidence_exposed'] ) ) { throw new RuntimeException( 'File 03 credential wallet contract failed.' ); }
$registration = null;
foreach ( $wallet['items'] as $item ) {
	if ( 'registration' === ( $item['type'] ?? '' ) ) { $registration = $item; }
	if ( 'platform_record' !== ( $item['format'] ?? '' ) ) { throw new RuntimeException( 'Credential format must not overclaim a W3C VC.' ); }
}
if ( ! $registration || 'LIC-12345' !== $registration['name'] ) { throw new RuntimeException( 'Registration credential did not use File 09 canonical license_number.' ); }
if ( false !== strpos( wp_json_encode( $wallet ), '+92-300' ) || false !== strpos( wp_json_encode( $wallet ), 'Private clinic address' ) ) { throw new RuntimeException( 'Private File 09 application data leaked to the File 03 credential wallet.' ); }

$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-gdo-integration-contracts.php' );
foreach ( array(
	'sabri_doctor_verification_public_projection_v1',
	'sabri_file09_verifiable_credentials_v1',
	'file03_public_projection',
	'file03_verifiable_credentials',
	'file03_public_fields',
	'gdo_validate_public_projection',
	'raw_evidence_exposed',
) as $token ) {
	if ( false === strpos( $src, $token ) ) { throw new RuntimeException( 'Missing File 03 adapter token: ' . $token ); }
}
if ( false === strpos( $src, "'raw_evidence_exposed' => false" ) ) { throw new RuntimeException( 'Raw evidence guard missing.' ); }

echo "File 09 -> File 03 exact public projection and credential contracts: PASS\n";
