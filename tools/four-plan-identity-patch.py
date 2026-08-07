from pathlib import Path
root=Path.cwd()
def r(p): return (root/p).read_text(encoding='utf-8')
def w(p,s): (root/p).write_text(s,encoding='utf-8')
def rep(p,a,b,n=1):
 s=r(p)
 if s.count(a)<n: raise SystemExit(f'{p}: missing expected text: {a[:100]!r}')
 w(p,s.replace(a,b,n)); print('patched',p)

p='includes/class-gdo-membership-adapter.php'
rep(p,"const FILE00_BASE_VERSION  = '1.1.2';","const FILE00_BASE_VERSION  = '1.2.0';")
rep(p,"\t\t$profile['identity_verified'] = ! empty( $base['email_verified'] ) && ! empty( $base['phone_verified'] );\n\t\t$profile['doctor_verified']   = ! empty( $base['professional_verified'] ); // Legacy display compatibility only; never File 09 authority.","\t\t$profile['identity_verified'] = self::identity_assurance_current( $user_id );\n\t\t$profile['doctor_verified']   = function_exists( 'gdo_user_is_verified' ) ? (bool) gdo_user_is_verified( $user_id ) : false; // File 09 owns professional verification truth.")
old="""\tpublic static function is_active_doctor_candidate( $user_id ) {
\t\t$user_id = absint( $user_id );
\t\t$base = self::base_assertion( $user_id );
\t\t$subject = self::membership_assertion( $user_id, 'clinical_identity_link', 'doctor_application' );
\t\tif ( ! $base || ! $subject || self::sanctioned( $user_id ) ) {
\t\t\treturn false;
\t\t}
\t\t$age = isset( $subject['age_context'] ) && is_array( $subject['age_context'] ) ? $subject['age_context'] : array();
\t\t$age_years = ! empty( $age['known'] ) ? absint( $age['age_years'] ) : 0;
\t\t$minimum_age = max( 18, absint( apply_filters( 'gdo_minimum_professional_age', 18, $user_id, $base, $subject ) ) );
\t\t$guardian_required = $age_years > 0 && $age_years < 18;
\t\t$guardian_ok = ! $guardian_required || ! empty( $base['guardian_verified'] );
\t\t$eligible = 'doctor' === sanitize_key( $base['membership_type'] )
\t\t\t&& ! empty( $base['approved'] )
\t\t\t&& ! empty( $base['email_verified'] )
\t\t\t&& ! empty( $base['phone_verified'] )
\t\t\t&& ! empty( $base['two_factor_ready'] )
\t\t\t&& $guardian_ok
\t\t\t&& $age_years >= $minimum_age;
\t\treturn (bool) apply_filters( 'gdo_file00_doctor_application_eligible', $eligible, $user_id, $base, $subject );
\t}
"""
new="""\tpublic static function identity_assurance_current( $user_id ) {
\t\t$base = self::base_assertion( $user_id );
\t\treturn $base
\t\t\t&& ! empty( $base['application_exists'] )
\t\t\t&& 'approved' === sanitize_key( $base['status'] )
\t\t\t&& ! empty( $base['approved'] )
\t\t\t&& ! empty( $base['identity_documents_current'] )
\t\t\t&& ! empty( $base['email_verified'] )
\t\t\t&& ! empty( $base['phone_verified'] )
\t\t\t&& ! empty( $base['two_factor_ready'] );
\t}

\tpublic static function is_active_doctor_candidate( $user_id ) {
\t\t$user_id = absint( $user_id );
\t\t$base = self::base_assertion( $user_id );
\t\t$subject = self::membership_assertion( $user_id, 'clinical_identity_link', 'doctor_application' );
\t\tif ( ! $base || ! $subject || self::sanctioned( $user_id ) || ! self::identity_assurance_current( $user_id ) ) {
\t\t\treturn false;
\t\t}
\t\t$age = isset( $subject['age_context'] ) && is_array( $subject['age_context'] ) ? $subject['age_context'] : array();
\t\t$age_years = ! empty( $age['known'] ) ? absint( $age['age_years'] ) : 0;
\t\t$minimum_age = max( 18, absint( apply_filters( 'gdo_minimum_professional_age', 18, $user_id, $base, $subject ) ) );
\t\t$approved_types = isset( $base['approved_membership_types'] ) && is_array( $base['approved_membership_types'] ) ? array_map( 'sanitize_key', $base['approved_membership_types'] ) : array();
\t\t$eligible = 'doctor' === sanitize_key( $base['membership_type'] )
\t\t\t&& in_array( 'doctor', $approved_types, true )
\t\t\t&& $age_years >= $minimum_age;
\t\t// Professional verification itself is intentionally not required here: File 09 is the professional verifier.
\t\treturn (bool) apply_filters( 'gdo_file00_doctor_application_eligible', $eligible, $user_id, $base, $subject );
\t}
"""
rep(p,old,new)
rep(p,"\t\t$required = array( 'contract_version', 'user_id', 'membership_type', 'status', 'approved', 'suspended', 'two_factor_ready', 'phone_verified', 'email_verified', 'guardian_verified' );","\t\t$required = array( 'contract_version', 'user_id', 'application_exists', 'account_class', 'membership_type', 'approved_membership_types', 'status', 'approved', 'suspended', 'eligible', 'two_factor_ready', 'phone_verified', 'email_verified', 'guardian_verified', 'professional_verified', 'identity_documents_current' );")
rep(p,"\t\treturn absint( $assertion['user_id'] ) === absint( $user_id );","\t\treturn absint( $assertion['user_id'] ) === absint( $user_id )\n\t\t\t&& is_array( $assertion['approved_membership_types'] );")
rep(p,"\t\t\t|| ! isset( $assertion['subject']['platform_uuid'], $assertion['issued_at'], $assertion['expires_at'] ) ) {","\t\t\t|| ! isset( $assertion['subject']['platform_uuid'], $assertion['subject']['record_version'], $assertion['membership'], $assertion['age_context'], $assertion['jurisdiction_context'], $assertion['issued_at'], $assertion['expires_at'] )\n\t\t\t|| ! is_array( $assertion['membership'] ) || ! is_array( $assertion['age_context'] ) || ! is_array( $assertion['jurisdiction_context'] ) ) {")
rep(p,"\t\treturn self::valid_uuid( $assertion['subject']['platform_uuid'] )\n\t\t\t&& false !== $issued","\t\treturn self::valid_uuid( $assertion['subject']['platform_uuid'] )\n\t\t\t&& absint( $assertion['subject']['record_version'] ) > 0\n\t\t\t&& false !== $issued")

p='includes/class-gdo-policy.php'
rep(p,"const VERSION       = '2026-08-06.1';","const VERSION       = '2026-08-07.2';")
rep(p,"const TERMS_VERSION = 'doctor-verification-2026-08-06';","const TERMS_VERSION = 'doctor-verification-2026-08-07';")
rep(p,"\t\tif ( 'doctor' !== sanitize_key( $base['membership_type'] ) || empty( $base['approved'] ) ) {\n\t\t\t$result['reason_code'] = 'doctor_membership_not_approved';\n\t\t\treturn $result;\n\t\t}\n\t\tif ( empty( $base['email_verified'] ) || empty( $base['phone_verified'] ) || empty( $base['two_factor_ready'] ) ) {","\t\t$approved_types = isset( $base['approved_membership_types'] ) && is_array( $base['approved_membership_types'] ) ? array_map( 'sanitize_key', $base['approved_membership_types'] ) : array();\n\t\tif ( empty( $base['application_exists'] ) || 'approved' !== sanitize_key( $base['status'] ) || 'doctor' !== sanitize_key( $base['membership_type'] ) || empty( $base['approved'] ) || ! in_array( 'doctor', $approved_types, true ) ) {\n\t\t\t$result['reason_code'] = 'doctor_membership_not_approved';\n\t\t\treturn $result;\n\t\t}\n\t\tif ( empty( $base['identity_documents_current'] ) || empty( $base['email_verified'] ) || empty( $base['phone_verified'] ) || empty( $base['two_factor_ready'] ) ) {")

p='includes/class-gdo-frontend.php'
rep(p,'<main class="gdo-shell"','<main class="gdo-application"',2)
rep(p,"'Verified professional phone'","'Professional contact phone'")
print('identity/policy/UI patch complete')
