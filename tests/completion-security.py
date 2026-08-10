from pathlib import Path
import re, sys
root=Path(__file__).resolve().parents[1]
def fail(msg): print('FAIL:',msg,file=sys.stderr); raise SystemExit(1)
def have(path,*tokens):
    text=(root/path).read_text(encoding='utf-8')
    for token in tokens:
        if token not in text: fail(f'{path}: missing {token}')
    return text
main=have('global-doctor-onboarding.php','Version: 1.3.0',"define( 'GDO_SCHEMA_VERSION', 6 )",'class-gdo-advanced-trust.php','class-gdo-advanced-trust-hardening.php','class-gdo-advanced-trust-events.php')
required={
'includes/class-gdo-policy.php':['eligibility','required_fields','evidence_types'],
'includes/class-gdo-application.php':['save_draft','completeness','submission_hash','draft_expires_at'],
'includes/class-gdo-evidence.php':['stage_upload','gdo_credential_scan_result','access_grants','watermark'],
'includes/class-gdo-admin.php':['assigned_reviewer_id','recommender_id','finalizer_id','request_more_info','assign_appeal','resolve_appeal','run_repair'],
'includes/class-gdo-claims.php':['gdo.file00.professional-decision','hash_hmac','acknowledge'],
'includes/class-gdo-risk.php':['identity_duplicate','document_hash_duplicate','false_positive'],
'includes/class-gdo-quality.php':['quality_samples','major_error'],
'includes/class-gdo-privacy.php':['wp_privacy_personal_data_exporters','legal_hold','deletion_proof'],
'includes/class-gdo-retention.php':['open_renewals','verification_expired','orphan'],
'includes/class-gdo-operations.php':['safe_mode','dead_letters','repair'],
'includes/class-gdo-migration.php':['gdo_migration_lock','decrypt_legacy','GDO2'],
'assets/css/onboarding.css':['--gdo-green','focus-visible','prefers-reduced-motion','[dir="rtl"]'],
'includes/class-gdo-integration-contracts.php':['gdo.file03.doctor-profile-eligibility','gdo.file07.directory-eligibility','gdo.file08.clinic-eligibility','gdo.file26.doctor-verification-projection'],
'includes/class-gdo-advanced-trust.php':['primary_source_verify','trusted_issuers','jurisdiction_rules','continuous_monitor','issue_passport','ai_assistance','risk_explanation','requires_dual_review','smart_reviewer_candidates','create_upload_session','issue_viewing_room_grant','transparency_snapshot','human_final_decision_required'],
'includes/class-gdo-advanced-trust-hardening.php':['SCHEMA_VERSION = 2','CONTRACT_VERSION = \'1.1.0\'','verify_passport_uuid','monitor_status IN'],
'includes/class-gdo-advanced-trust-events.php':['gdo_canonical_audit_event','gdo_professional_reverification_required'],
'TRACEABILITY.md':['F09-FR-001','F09-FR-017','F09-NFR-010','F09-AT-01','F09-AT-24','DoD-13'],
}
for path,tokens in required.items(): have(path,*tokens)
runtime_paths=[root/'global-doctor-onboarding.php',root/'uninstall.php']+list((root/'includes').glob('*.php'))
all_php='\n'.join(p.read_text(encoding='utf-8') for p in runtime_paths)
for forbidden in ['_smc_totp_secret','_smc_recovery','SMC_Security::verify_totp','wp_mail(']:
    if forbidden in all_php: fail('forbidden authority/secret marker: '+forbidden)
admin=have('includes/class-gdo-admin.php','admin_post_gdo_')
actions=set(re.findall(r"add_action\(\s*'admin_post_(gdo_[^']+)'\s*,\s*array\(\s*\$this\s*,\s*'([^']+)'",admin))
for action,method in actions:
    if not re.search(r'public function\s+'+re.escape(method)+r'\s*\(',admin): fail(f'admin action {action} missing public handler {method}')

application=(root/'includes/class-gdo-application.php').read_text(encoding='utf-8')
if 'gdo_submit_risk_review' in application: fail('duplicate/risk signals must reach human review rather than block submission')
if "array( 'draft','more_information','resubmitted' )" in application: fail('resubmitted application must be immutable')
state=(root/'includes/class-gdo-state.php').read_text(encoding='utf-8')
for source in ['recommended','suspended','appeal_pending']:
    match=re.search(r"^\s*'"+re.escape(source)+r"'\s*=>\s*array\(([^)]*)\)", state, re.M)
    if not match or "'withdrawn'" not in match.group(1): fail('privacy withdrawal path missing for '+source)
evidence=(root/'includes/class-gdo-evidence.php').read_text(encoding='utf-8')
if 'doctor_credential_accessed' not in evidence: fail('applicant notification missing for credential access')
retention=(root/'includes/class-gdo-retention.php').read_text(encoding='utf-8')
if 'requires_operator_replay' not in retention or "'dead' === $event->status" not in retention: fail('dead claim events require explicit operator replay')

trust=(root/'includes/class-gdo-advanced-trust.php').read_text(encoding='utf-8')
events=(root/'includes/class-gdo-advanced-trust-events.php').read_text(encoding='utf-8')
ai=trust[trust.index('function ai_assistance'):trust.index('function fraud_ring_scan')]
for key in ['decision','approve','reject','professional_status','clinical_authorization']:
    if not re.search(r"unset\([^;]*\$result\['"+re.escape(key)+r"'\][^;]*\);", ai): fail('AI final/authority key is not discarded: '+key)
if "return $allowed && ! self::has_conflict" not in trust: fail('reviewer conflict must narrow, never widen authorization')
if 'GDO_Evidence::stage_upload' not in trust: fail('resumable upload bypasses canonical evidence path')
if "'download_allowed'=>false" not in trust: fail('secure viewing room no-download contract missing')
if 'GDO_State::transition' in events: fail('advanced trust derivative event bridge must not directly mutate professional state')
hard=(root/'includes/class-gdo-advanced-trust-hardening.php').read_text(encoding='utf-8')
public_passport=hard[hard.index('function verify_passport_uuid'):hard.index('function continuous_monitor')]
for forbidden in ['profile_json','storage_name','source_sha256','review_note']:
    if forbidden in public_passport: fail('public passport leaks '+forbidden)
if 'revoke_passports_for_application' in public_passport: fail('public passport GET must remain read-only')

schema=have('includes/class-gdo-schema.php','reviewer_profiles','risk_signals','quality_samples','access_grants','metrics')
for table in ['applications','evidence','transitions','consents','access_log','appeals','outbox','rate_limits','reviewer_profiles','risk_signals','quality_samples','access_grants','metrics']:
    if table not in (root/'uninstall.php').read_text(): fail('uninstall missing owned table '+table)
print('File 09 1.3.0 RC6 completion/security static invariants passed.')
