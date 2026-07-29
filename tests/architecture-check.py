from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
source_paths = [
    p for p in root.rglob('*')
    if p.is_file() and (p.suffix in {'.php', '.css'} or p.name == 'readme.txt') and 'tests' not in p.parts
]
text = '\n'.join(p.read_text(encoding='utf-8') for p in source_paths)


def fail(message):
    print('ERROR:', message)
    raise SystemExit(1)

required_files = {
    'global-doctor-onboarding.php',
    'includes/class-gdo-membership-adapter.php',
    'includes/class-gdo-schema.php',
    'includes/class-gdo-state.php',
    'includes/class-gdo-crypto.php',
    'includes/class-gdo-storage.php',
    'includes/class-gdo-audit.php',
    'includes/class-gdo-evidence.php',
    'includes/class-gdo-application.php',
    'includes/class-gdo-admin.php',
    'includes/class-gdo-frontend.php',
    'includes/class-gdo-privacy.php',
    'includes/class-gdo-retention.php',
    'includes/class-gdo-migration.php',
    'includes/class-gdo-api.php',
    'uninstall.php',
}
missing = sorted(p for p in required_files if not (root / p).is_file())
if missing:
    fail('missing required files: ' + ', '.join(missing))

main = (root / 'global-doctor-onboarding.php').read_text(encoding='utf-8')
readme = (root / 'readme.txt').read_text(encoding='utf-8')
if 'Version: 1.1.0' not in main or "define( 'GDO_VERSION', '1.1.0' );" not in main or "define( 'GDO_SCHEMA_VERSION', 3 );" not in main:
    fail('plugin or schema version mismatch')
if 'Stable tag: 1.1.0' not in readme:
    fail('readme stable tag mismatch')

runtime_text = '\n'.join(
    p.read_text(encoding='utf-8') for p in source_paths
    if p.name not in {'class-gdo-migration.php', 'uninstall.php'}
)
for token in (
    'SPD_Helpers', 'SDD_Helpers', 'sabri_doctor_pending', 'sabri_doctor_verified',
    "'_spd_", "'_sa_", 'wp_mail(', 'manage_global_doctor_verification',
    'sabri_unified_notifications_enqueue', '_smc_recent_step_up_at'
):
    if token in runtime_text:
        fail('legacy or forbidden runtime authority token: ' + token)

for pattern in (r'\badd_role\s*\(', r'->add_role\s*\(', r'->remove_role\s*\(', r'->add_cap\s*\(', r'->set_role\s*\('):
    if re.search(pattern, text):
        fail('role/capability mutation found: ' + pattern)

required_markers = (
    'GDO_KEYRING', 'GDO_PRIVATE_STORAGE_DIR', 'aes-256-gcm', 'GDO2',
    'application_uuid', 'approved_snapshot_json', 'row_version', 'legal_hold',
    "has_action( 'sabri_notify' )", 'SUN_Core::create',
    'Cache-Control: private, no-store', 'X-Robots-Tag: noindex',
    'gdo_credential_scan_result', 'gdo_get_verification_decision',
    'review_note', 'recommended_decision', 'source_state', 'last_error',
    'verify_step_up', 'wp_check_password', 'SMC_Security::verify_totp',
    'smc_review_verification', 'smc_view_private_documents', 'smc_manage_membership',
)
for token in required_markers:
    if token not in text:
        fail('required architecture marker missing: ' + token)

admin = (root / 'includes/class-gdo-admin.php').read_text(encoding='utf-8')
for token in (
    'assigned_reviewer_id', 'recommender_id', 'finalizer_id', 'recent_step_up',
    'Conflict declaration', 'Access purpose', 'Recommend rejection',
    'gdo_reviewer_step_up', 'source_state'
):
    if token not in admin:
        fail('review separation marker missing: ' + token)

application = (root / 'includes/class-gdo-application.php').read_text(encoding='utf-8')
if re.search(r"in_array\(\s*\$latest->state.*'rejected'.*create_draft", application, re.S):
    fail('rejected applications must not create a new draft')
if "array( 'expired','renewal_due' )" not in application:
    fail('renewal successor version marker missing')

notifications = (root / 'includes/class-gdo-notifications.php').read_text(encoding='utf-8')
if "has_action( 'sabri_notify' )" not in notifications or 'SUN_Core::create' not in notifications:
    fail('File 19 notification boundary is not implemented')

migration = (root / 'includes/class-gdo-migration.php').read_text(encoding='utf-8')
if "'.gdo2'" not in migration or 'decrypt_legacy' not in migration or 'GDO_Crypto::encrypt' not in migration:
    fail('legacy credentials are not migrated into GDO2')

print('Architecture checks passed.')
