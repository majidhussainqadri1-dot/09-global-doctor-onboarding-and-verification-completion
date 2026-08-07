from pathlib import Path
root=Path.cwd()
def r(p): return (root/p).read_text(encoding='utf-8')
def w(p,s): (root/p).write_text(s,encoding='utf-8')
def rep(p,a,b,n=1):
 s=r(p)
 if s.count(a)<n: raise SystemExit(f'{p}: missing expected text: {a[:100]!r}')
 w(p,s.replace(a,b,n)); print('patched',p)

p='includes/class-gdo-application.php'
rep(p,"\t\tforeach ( array( 'phone','whatsapp' ) as $field ) {\n\t\t\tif ( empty( $profile[ $field ] ) && $allow_incomplete ) {\n\t\t\t\tcontinue;\n\t\t\t}\n\t\t\t$digits = preg_replace( '/\\D+/', '', isset( $profile[ $field ] ) ? $profile[ $field ] : '' );\n\t\t\tif ( strlen( $digits ) < 7 || strlen( $digits ) > 18 ) {\n\t\t\t\treturn new WP_Error( 'gdo_phone', __( 'Provide a valid professional phone and WhatsApp number.', 'global-doctor-onboarding' ) );\n\t\t\t}\n\t\t}","\t\tforeach ( array( 'phone','whatsapp' ) as $field ) {\n\t\t\t$value = isset( $profile[ $field ] ) ? trim( (string) $profile[ $field ] ) : '';\n\t\t\tif ( '' === $value && ( $allow_incomplete || ! in_array( $field, $required, true ) ) ) {\n\t\t\t\tcontinue;\n\t\t\t}\n\t\t\t$digits = preg_replace( '/\\D+/', '', $value );\n\t\t\tif ( strlen( $digits ) < 7 || strlen( $digits ) > 18 ) {\n\t\t\t\treturn new WP_Error( 'gdo_phone', __( 'Provide a valid professional contact number.', 'global-doctor-onboarding' ) );\n\t\t\t}\n\t\t}")
old="""\tpublic static function approved_snapshot( $application_id ) {
\t\t$app = self::get( $application_id );
\t\tif ( ! $app || empty( $app->approved_snapshot_json ) || empty( $app->approved_fingerprint ) || ! GDO_State::public_verified( $app->state ) ) {
\t\t\treturn array();
\t\t}
\t\t$snapshot = json_decode( $app->approved_snapshot_json, true );
\t\tif ( ! is_array( $snapshot ) || empty( $snapshot['profile'] ) || empty( $snapshot['evidence'] ) ) {
\t\t\treturn array();
\t\t}
\t\t$computed = self::fingerprint( (array) $snapshot['profile'], (array) $snapshot['evidence'] );
\t\treturn hash_equals( (string) $app->approved_fingerprint, $computed ) ? $snapshot : array();
\t}
"""
new="""\tpublic static function stored_approved_snapshot( $application_or_id ) {
\t\t$app = is_object( $application_or_id ) ? $application_or_id : self::get( $application_or_id );
\t\tif ( ! $app || empty( $app->approved_snapshot_json ) || empty( $app->approved_fingerprint ) ) {
\t\t\treturn array();
\t\t}
\t\t$snapshot = json_decode( $app->approved_snapshot_json, true );
\t\t$schema = is_array( $snapshot ) && isset( $snapshot['schema'] ) ? absint( $snapshot['schema'] ) : 0;
\t\tif ( ! is_array( $snapshot ) || $schema < 3 || ! defined( 'GDO_SCHEMA_VERSION' ) || $schema > absint( GDO_SCHEMA_VERSION )
\t\t\t|| empty( $snapshot['application_uuid'] ) || ! hash_equals( (string) $app->application_uuid, (string) $snapshot['application_uuid'] )
\t\t\t|| absint( $app->version ) !== absint( isset( $snapshot['application_version'] ) ? $snapshot['application_version'] : 0 )
\t\t\t|| ! isset( $snapshot['profile'], $snapshot['evidence'], $snapshot['verified_until'] ) || ! is_array( $snapshot['profile'] ) || ! is_array( $snapshot['evidence'] ) ) {
\t\t\treturn array();
\t\t}
\t\t$computed = self::fingerprint( (array) $snapshot['profile'], (array) $snapshot['evidence'] );
\t\treturn 1 === preg_match( '/^[a-f0-9]{64}$/i', (string) $computed ) && hash_equals( (string) $app->approved_fingerprint, $computed ) ? $snapshot : array();
\t}

\tpublic static function approved_snapshot( $application_id ) {
\t\t$app = self::get( $application_id );
\t\treturn $app && GDO_State::public_verified( $app->state ) ? self::stored_approved_snapshot( $app ) : array();
\t}

\tpublic static function refresh_approved_snapshot( $application_or_id, $verified_until, $actor_id ) {
\t\t$app = is_object( $application_or_id ) ? $application_or_id : self::get( $application_or_id );
\t\t$snapshot = self::stored_approved_snapshot( $app );
\t\t$timestamp = strtotime( trim( (string) $verified_until ) . ' 23:59:59 UTC' );
\t\tif ( ! $app || ! $snapshot || false === $timestamp || $timestamp <= time() ) {
\t\t\treturn new WP_Error( 'gdo_snapshot_refresh_invalid', __( 'The approved professional snapshot cannot be refreshed safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\t$snapshot['schema'] = absint( GDO_SCHEMA_VERSION );
\t\t$snapshot['verified_until'] = gmdate( 'Y-m-d', $timestamp );
\t\t$snapshot['policy_version'] = GDO_Policy::VERSION;
\t\t$snapshot['finalizer_id'] = absint( $actor_id );
\t\t$snapshot['captured_at'] = current_time( 'mysql', true );
\t\t$fingerprint = self::fingerprint( (array) $snapshot['profile'], (array) $snapshot['evidence'] );
\t\treturn array( 'snapshot'=>$snapshot, 'json'=>wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'fingerprint'=>$fingerprint, 'verified_until'=>gmdate( 'Y-m-d 23:59:59', $timestamp ) );
\t}
"""
rep(p,old,new)

p='includes/class-gdo-cf01-practitioner-contract.php'
rep(p,"\t\t$result['membership'] = array(\n\t\t\t'status'             => sanitize_key( $base['status'] ),\n\t\t\t'approved'           => ! empty( $base['approved'] ),\n\t\t\t'suspended'          => ! empty( $base['suspended'] ),\n\t\t\t'email_verified'     => ! empty( $base['email_verified'] ),\n\t\t\t'phone_verified'     => ! empty( $base['phone_verified'] ),\n\t\t\t'two_factor_ready'   => ! empty( $base['two_factor_ready'] ),\n\t\t\t'guardian_verified'  => ! empty( $base['guardian_verified'] ),\n\t\t);","\t\t$result['membership'] = array(\n\t\t\t'status'             => sanitize_key( $base['status'] ),\n\t\t\t'approved'           => ! empty( $base['approved'] ),\n\t\t\t'eligible'           => ! empty( $base['eligible'] ),\n\t\t\t'suspended'          => ! empty( $base['suspended'] ),\n\t\t\t'email_verified'     => ! empty( $base['email_verified'] ),\n\t\t\t'phone_verified'     => ! empty( $base['phone_verified'] ),\n\t\t\t'two_factor_ready'   => ! empty( $base['two_factor_ready'] ),\n\t\t\t'guardian_verified'  => ! empty( $base['guardian_verified'] ),\n\t\t\t'professional_verified' => ! empty( $base['professional_verified'] ),\n\t\t\t'identity_documents_current' => ! empty( $base['identity_documents_current'] ),\n\t\t\t'identity_assurance' => isset( $subject['membership']['identity_assurance'] ) ? sanitize_key( $subject['membership']['identity_assurance'] ) : 'none',\n\t\t);")
rep(p,"\t\tif ( empty( $base['email_verified'] ) || empty( $base['phone_verified'] ) || empty( $base['two_factor_ready'] ) || empty( $base['guardian_verified'] ) ) {","\t\tif ( empty( $base['eligible'] ) || empty( $base['professional_verified'] ) || empty( $base['identity_documents_current'] ) || empty( $base['email_verified'] ) || empty( $base['phone_verified'] ) || empty( $base['two_factor_ready'] ) || 'verified' !== $result['membership']['identity_assurance'] ) {")
old="""\tprivate static function valid_snapshot( $snapshot, $decision ) {
\t\tif ( ! is_array( $snapshot )
\t\t\t|| 3 !== absint( isset( $snapshot['schema'] ) ? $snapshot['schema'] : 0 )
\t\t\t|| ! isset( $snapshot['application_uuid'], $snapshot['application_version'], $snapshot['profile'], $snapshot['evidence'], $snapshot['verified_until'] )
\t\t\t|| ! hash_equals( (string) $decision['application_uuid'], (string) $snapshot['application_uuid'] )
\t\t\t|| absint( $decision['version'] ) !== absint( $snapshot['application_version'] )
\t\t\t|| ! is_array( $snapshot['profile'] )
\t\t\t|| ! is_array( $snapshot['evidence'] )
\t\t\t|| ! hash_equals( (string) $decision['verified_until'], gmdate( 'Y-m-d H:i:s', strtotime( (string) $snapshot['verified_until'] . ' 23:59:59 UTC' ) ) ) ) {
\t\t\treturn false;
\t\t}
\t\t$computed = GDO_Application::fingerprint( (array) $snapshot['profile'], (array) $snapshot['evidence'] );
\t\treturn 1 === preg_match( '/^[a-f0-9]{64}$/i', (string) $computed )
\t\t\t&& hash_equals( (string) $decision['fingerprint'], (string) $computed );
\t}
"""
new="""\tprivate static function valid_snapshot( $snapshot, $decision ) {
\t\t$schema = is_array( $snapshot ) && isset( $snapshot['schema'] ) ? absint( $snapshot['schema'] ) : 0;
\t\t$current_schema = defined( 'GDO_SCHEMA_VERSION' ) ? absint( GDO_SCHEMA_VERSION ) : 0;
\t\tif ( ! is_array( $snapshot ) || $schema < 3 || ! $current_schema || $schema > $current_schema
\t\t\t|| ! isset( $snapshot['application_uuid'], $snapshot['application_version'], $snapshot['profile'], $snapshot['evidence'], $snapshot['verified_until'] )
\t\t\t|| ! hash_equals( (string) $decision['application_uuid'], (string) $snapshot['application_uuid'] )
\t\t\t|| absint( $decision['version'] ) !== absint( $snapshot['application_version'] )
\t\t\t|| ! is_array( $snapshot['profile'] ) || ! is_array( $snapshot['evidence'] )
\t\t\t|| ! hash_equals( self::normalized_end_of_day( $decision['verified_until'] ), self::normalized_end_of_day( $snapshot['verified_until'] ) ) ) return false;
\t\t$computed = GDO_Application::fingerprint( (array) $snapshot['profile'], (array) $snapshot['evidence'] );
\t\treturn 1 === preg_match( '/^[a-f0-9]{64}$/i', (string) $computed ) && hash_equals( (string) $decision['fingerprint'], (string) $computed );
\t}

\tprivate static function normalized_end_of_day( $value ) {
\t\t$value = trim( (string) $value );
\t\t$timestamp = strtotime( $value . ( 10 === strlen( $value ) ? ' 23:59:59 UTC' : ' UTC' ) );
\t\treturn false === $timestamp ? '' : gmdate( 'Y-m-d 23:59:59', $timestamp );
\t}
"""
rep(p,old,new)

p='includes/class-gdo-claims.php'
rep(p,"\t\tif ( GDO_State::public_verified( $state ) && ( empty( $app->approved_snapshot_json ) || empty( $app->approved_fingerprint ) ) ) {\n\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n\t\t\treturn new WP_Error( 'gdo_claim_snapshot_missing', __( 'A verified professional claim requires an immutable approved snapshot.', 'global-doctor-onboarding' ) );\n\t\t}","\t\tif ( GDO_State::public_verified( $state ) && ! GDO_Membership_Adapter::is_active_doctor_candidate( $app->user_id ) ) {\n\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n\t\t\treturn new WP_Error( 'gdo_claim_membership_not_current', __( 'A verified professional claim requires current File 00 identity and doctor-membership assurance.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\t$approved_snapshot = GDO_State::public_verified( $state ) ? GDO_Application::stored_approved_snapshot( $app ) : array();\n\t\tif ( GDO_State::public_verified( $state ) && ! $approved_snapshot ) {\n\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n\t\t\treturn new WP_Error( 'gdo_claim_snapshot_missing', __( 'A verified professional claim requires a valid immutable approved snapshot.', 'global-doctor-onboarding' ) );\n\t\t}")
rep(p,"\t\tif ( $snapshot ) {\n\t\t\t$payload['snapshot_schema'] = isset( $snapshot['schema'] ) ? absint( $snapshot['schema'] ) : GDO_SCHEMA_VERSION;\n\t\t}","\t\t$snapshot_for_claim = $snapshot ? $snapshot : $approved_snapshot;\n\t\tif ( $snapshot_for_claim ) {\n\t\t\t$payload['snapshot_schema'] = isset( $snapshot_for_claim['schema'] ) ? absint( $snapshot_for_claim['schema'] ) : GDO_SCHEMA_VERSION;\n\t\t}")
print('snapshot/CF01/claims patch complete')
