from pathlib import Path
import re, sys
root=Path(__file__).resolve().parents[1]
source_paths=[p for p in root.rglob('*') if p.is_file() and (p.suffix in {'.php','.css'} or p.name=='readme.txt') and 'tests' not in p.parts]
text='\n'.join(p.read_text(encoding='utf-8') for p in source_paths)

def fail(message):
    print('ERROR:',message); raise SystemExit(1)
required_files={
 'global-doctor-onboarding.php','includes/class-gdo-membership-adapter.php','includes/class-gdo-schema.php',
 'includes/class-gdo-state.php','includes/class-gdo-crypto.php','includes/class-gdo-storage.php',
 'includes/class-gdo-audit.php','includes/class-gdo-evidence.php','includes/class-gdo-application.php',
 'includes/class-gdo-admin.php','includes/class-gdo-frontend.php','includes/class-gdo-privacy.php',
 'includes/class-gdo-retention.php','includes/class-gdo-migration.php','includes/class-gdo-api.php','uninstall.php'
}
missing=sorted(p for p in required_files if not (root/p).is_file())
if missing: fail('missing required files: '+', '.join(missing))
main=(root/'global-doctor-onboarding.php').read_text(encoding='utf-8')
readme=(root/'readme.txt').read_text(encoding='utf-8')
if 'Version: 1.1.0' not in main or "define( 'GDO_VERSION', '1.1.0' );" not in main or 'Stable tag: 1.1.0' not in readme: fail('version mismatch')
for token in ('SPD_Helpers','SDD_Helpers','sabri_doctor_pending','sabri_doctor_verified',"'_spd_","'_sa_",'wp_mail('):
    if token in text: fail('legacy or forbidden authority token: '+token)
for pattern in (r'\badd_role\s*\(',r'->add_role\s*\(',r'->remove_role\s*\(',r'->add_cap\s*\('):
    if re.search(pattern,text): fail('role/capability mutation found: '+pattern)
for token in ('GDO_KEYRING','GDO_PRIVATE_STORAGE_DIR','aes-256-gcm','GDO2','application_uuid','approved_snapshot_json','row_version','legal_hold','sabri_unified_notifications_enqueue','Cache-Control: private, no-store','X-Robots-Tag: noindex','gdo_credential_scan_result','gdo_get_verification_decision'):
    if token not in text: fail('required architecture marker missing: '+token)
admin=(root/'includes/class-gdo-admin.php').read_text(encoding='utf-8')
for token in ('assigned_reviewer_id','recommender_id','finalizer_id','recent_step_up','Conflict declaration','purpose'):
    if token not in admin: fail('review separation marker missing: '+token)
print('Architecture checks passed.')
