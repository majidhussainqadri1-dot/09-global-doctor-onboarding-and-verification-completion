from pathlib import Path
import re, sys
root=Path(__file__).resolve().parents[1]
def fail(msg): print('FAIL:',msg,file=sys.stderr); raise SystemExit(1)
def have(path,*tokens):
    text=(root/path).read_text(encoding='utf-8')
    for token in tokens:
        if token not in text: fail(f'{path}: missing {token}')
    return text
main=have('global-doctor-onboarding.php','Version: 1.2.0',"define( 'GDO_SCHEMA_VERSION', 6 )")
required={
'includes/class-gdo-policy.php':['eligibility','required_fields','evidence_types'],
'includes/class-gdo-application.php':['save_draft','completeness','submission_hash','draft_expires_at'],
'includes/class-gdo-evidence.php':['stage_upload','gdo_credential_scan_result','access_grants','watermark'],
'includes/class-gdo-admin.php':['assigned_reviewer_id','recommender_id','finalizer_id','request_more_info','resolve_appeal','run_repair'],
'includes/class-gdo-claims.php':['gdo.file00.professional-decision','hash_hmac','acknowledge'],
'includes/class-gdo-risk.php':['identity_duplicate','document_hash_duplicate','false_positive'],
'includes/class-gdo-quality.php':['quality_samples','major_error'],
'includes/class-gdo-privacy.php':['wp_privacy_personal_data_exporters','legal_hold','deletion_proof'],
'includes/class-gdo-retention.php':['open_renewals','verification_expired','orphan'],
'includes/class-gdo-operations.php':['safe_mode','dead_letters','repair'],
'includes/class-gdo-migration.php':['gdo_migration_lock','decrypt_legacy','GDO2'],
'assets/css/onboarding.css':['--gdo-green','focus-visible','prefers-reduced-motion','[dir="rtl"]'],
'TRACEABILITY.md':['F09-FR-001','F09-FR-017','F09-NFR-010','DoD-13'],
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

schema=have('includes/class-gdo-schema.php','reviewer_profiles','risk_signals','quality_samples','access_grants','metrics')
for table in ['applications','evidence','transitions','consents','access_log','appeals','outbox','rate_limits','reviewer_profiles','risk_signals','quality_samples','access_grants','metrics']:
    if table not in (root/'uninstall.php').read_text(): fail('uninstall missing owned table '+table)
print('File 09 completion/security static invariants passed.')
