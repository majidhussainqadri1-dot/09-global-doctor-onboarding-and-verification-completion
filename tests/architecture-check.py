from pathlib import Path
import re,sys
root=Path(__file__).resolve().parents[1]
def fail(m): print('ERROR:',m,file=sys.stderr); raise SystemExit(1)
required=[
'global-doctor-onboarding.php','readme.txt','uninstall.php','includes/class-gdo-membership-adapter.php','includes/class-gdo-policy.php','includes/class-gdo-schema.php','includes/class-gdo-state.php','includes/class-gdo-crypto.php','includes/class-gdo-storage.php','includes/class-gdo-audit.php','includes/class-gdo-rate-limiter.php','includes/class-gdo-risk.php','includes/class-gdo-claims.php','includes/class-gdo-quality.php','includes/class-gdo-operations.php','includes/class-gdo-notifications.php','includes/class-gdo-evidence.php','includes/class-gdo-application.php','includes/class-gdo-admin.php','includes/class-gdo-frontend.php','includes/class-gdo-privacy.php','includes/class-gdo-retention.php','includes/class-gdo-migration.php','includes/class-gdo-api.php','includes/class-gdo-cf01-practitioner-contract.php','includes/class-gdo-rest.php','includes/class-gdo-activator.php','includes/class-gdo-plugin.php']
for p in required:
    if not (root/p).is_file(): fail('missing '+p)
text='\n'.join((root/p).read_text(encoding='utf-8') for p in required)
main=(root/'global-doctor-onboarding.php').read_text(); readme=(root/'readme.txt').read_text()
for token in ['Version: 1.2.0',"define( 'GDO_VERSION', '1.2.0' );","define( 'GDO_SCHEMA_VERSION', 6 );","define( 'GDO_CF01_PRACTITIONER_CONTRACT_VERSION', '1.0.0' );"]:
    if token not in main: fail('version marker '+token)
if 'Stable tag: 1.2.0' not in readme: fail('stable tag mismatch')
for forbidden in ['_smc_totp_secret','_smc_2fa_enabled','_smc_identity_verified','_smc_doctor_verified','_smc_recovery','SMC_Security::verify_totp','wp_mail(']:
    if forbidden in text: fail('forbidden token '+forbidden)
for pattern in [r'\badd_role\s*\(',r'->add_role\s*\(',r'->remove_role\s*\(',r'->add_cap\s*\(',r'->set_role\s*\(']:
    if re.search(pattern,text): fail('role mutation '+pattern)
markers=['GDO_KEYRING','GDO_PRIVATE_STORAGE_DIR','GDO_CLAIM_SIGNING_KEY','aes-256-gcm','GDO2','application_uuid','approved_snapshot_json','row_version','legal_hold','gdo_credential_scan_result','verify_step_up','SMC_Contracts::assertions','SMC_CF01_Contract::membership_assertion','SA_Professional_Reauthentication::verify_and_record','gdo.cf01.practitioner-eligibility','gdo.file00.professional-decision','grants_clinical_authorization','professional_scope_restrictions_not_structured','submission_hash','access_grants','risk_signals','quality_samples']
for token in markers:
    if token not in text: fail('missing architecture marker '+token)
adapter=(root/'includes/class-gdo-membership-adapter.php').read_text()
if 'get_user_meta(' in adapter or 'wp_check_password(' in adapter: fail('private File00/local password access')
admin=(root/'includes/class-gdo-admin.php').read_text()
for token in ['assigned_reviewer_id','recommender_id','finalizer_id','recent_step_up','conflict','Access purpose','source_state','run_repair']:
    if token not in admin: fail('missing reviewer/operation marker '+token)
print('Architecture checks passed.')
