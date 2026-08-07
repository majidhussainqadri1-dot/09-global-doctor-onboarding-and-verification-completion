from pathlib import Path
import sys
root=Path(__file__).resolve().parents[1]
def fail(m): print('FAIL:',m,file=sys.stderr); raise SystemExit(1)
def text(p): return (root/p).read_text(encoding='utf-8')
app=text('includes/class-gdo-application.php'); adapter=text('includes/class-gdo-membership-adapter.php'); policy=text('includes/class-gdo-policy.php'); admin=text('includes/class-gdo-admin.php'); ev=text('includes/class-gdo-evidence.php'); privacy=text('includes/class-gdo-privacy.php'); claims=text('includes/class-gdo-claims.php'); api=text('includes/class-gdo-api.php'); cf01=text('includes/class-gdo-cf01-practitioner-contract.php'); migration=text('includes/class-gdo-migration.php'); retention=text('includes/class-gdo-retention.php')
for tok in ["if ( empty( $eligibility['eligible'] ) )",'verification_record_for_user','renewed_from_id','refresh_approved_snapshot']:
    if tok not in app: fail('application hardening missing '+tok)
for tok in ["const FILE00_BASE_VERSION  = '1.2.0'",'membership_allows','identity_documents_current','approved_membership_types','return $eligible && $filtered;','return $allowed && $filtered;']:
    if tok not in adapter: fail('membership hardening missing '+tok)
if "apply_filters( 'gdo_file00_capability_map'" in adapter: fail('baseline capability map remains widenable')
for tok in ['normalize_date','normalize_future_date','array_merge( $minimum, $filtered )','array_merge( $fields, $filtered )']:
    if tok not in policy: fail('policy hardening missing '+tok)
for tok in ['GDO_Membership_Adapter::is_active_doctor_candidate( $app->user_id, $app->jurisdiction )','GDO_Application::refresh_approved_snapshot',"'expires_at'=>$record->expires_at",'FOR UPDATE']:
    if tok not in admin: fail('admin decision/concurrency hardening missing '+tok)
for tok in ['gdo_evidence_owner_denied','recent_step_up( $reviewer_id )','$record->expires_at','FOR UPDATE']:
    if tok not in ev: fail('evidence hardening missing '+tok)
if "transitions' ), array( 'actor_id'=>null" in privacy: fail('privacy erasure mutates hash chain')
for tok in ['gdo_claim_membership_not_current','GDO_Application::stored_approved_snapshot','is_active_doctor_candidate']:
    if tok not in claims: fail('claim hardening missing '+tok)
if "'accepted' === sanitize_key( $app->claim_status )" not in api: fail('public claim ack is bypassable')
for tok in ['GDO_SCHEMA_VERSION','membership_allows( $subject )','review_expiry','identity_documents_current','professional_verified']:
    if tok not in cf01: fail('CF01 hardening missing '+tok)
for tok in ['gdo_legacy_migration_user_checkpoint','LIMIT 500','LIMIT 25','LIMIT 100','doctor_legacy_source_cleanup_pending']:
    if tok not in migration: fail('migration hardening missing '+tok)
for tok in ['doctor_verification_retention_anonymized',"'profile_json'=>'{}'",'legal_hold=0']:
    if tok not in retention: fail('retention hardening missing '+tok)
print('File 09 final hardening invariants passed.')
