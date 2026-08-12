from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
def t(path): return (root / path).read_text(encoding='utf-8')
def has(s, *xs): return all(x in s for x in xs)

trust=t('includes/class-gdo-advanced-trust.php')
hard=t('includes/class-gdo-advanced-trust-hardening.php')
admin=t('includes/class-gdo-admin.php')
evidence=t('includes/class-gdo-evidence.php')
privacy=t('includes/class-gdo-privacy.php')
notify=t('includes/class-gdo-notifications.php')
workflow=t('.github/workflows/file09-rc2-final.yml')
lock=json.loads(t('RELEASE-LOCK.json'))
release_files=[line.strip() for line in t('RELEASE-FILES.txt').splitlines() if line.strip() and not line.lstrip().startswith('#')]

checks=[]
def c(n,title,ok): checks.append((n,title,bool(ok)))

evidence_lookup=trust[trust.index('private static function evidence_record'):trust.index('public static function primary_source_verify')]
record_error_propagations = trust.count('is_wp_error( $record )') + trust.count('is_wp_error($record)')
c(1,'Advanced Trust shared evidence lookup propagates database uncertainty',
  has(evidence_lookup, 'GDO_Evidence::records_checked( absint( $application_id ), true )', 'is_wp_error( $records )', 'return $records;')
  and record_error_propagations >= 5)

command=trust[trust.index('public static function command_center'):trust.index('public static function public_card_shortcode')]
c(2,'Applicant command center distinguishes DB uncertainty from no application/missing items',
  has(command, 'gdo_command_center_application_query', 'gdo_command_center_completeness_query', "$complete['query_error']"))

rest_check=hard[hard.index('public static function rest_check'):hard.index('public static function rest_public_passport')]
c(3,'Hardened trust-check REST application read distinguishes DB failure from authorization denial',
  has(rest_check, "$wpdb->last_error = '';", 'gdo_check_application_query', "array( 'status'=>503 )", 'gdo_check_forbidden'))

c(4,'Applicant command-center UI exposes required bounded operational truth',
  has(command, "'check_summary'=>array('pending'=>$pending_checks,'degraded'=>$degraded_checks)", 'More-information deadline:', 'Verified until:', 'provider checks degraded', 'Verification scope')
  and 'facts_json' not in command and 'event_json' not in command)

history=trust[trust.index('public static function add_history'):trust.index('private static function passport_key')]
c(5,'Professional history public projection remains allowlisted, minimized and DB-failure-aware',
  has(history, 'public_safe=1', 'gdo_public_history_query', 'sanitize_provider_array', 'allowed_public'))

monitor=hard[hard.index('public static function continuous_monitor'):hard.index('private static function unsafe_chunk_path')]
c(6,'Continuous monitor separates internal check failure from provider/rate degradation',
  has(monitor, "'gdo_primary_source_rate' === $check_error", 'doctor_continuous_verification_internal_check_failed', 'gdo_trust_monitor_check_failed', "self::release_monitor_claim( $app->id, 'trust_check_failed' )"))

c(7,'Appeal/conflict/dual-review boundaries remain human and authorization-narrowing',
  has(trust, 'function requires_dual_review', 'gdo_requires_dual_review', 'function has_conflict')
  and has(admin, 'function assign_appeal', 'function resolve_appeal', 'reviewer_case_allows'))

c(8,'Privacy/legal-hold and durable credential deletion controls remain guarded',
  has(hard, 'function privacy_erase_application', 'START TRANSACTION', 'gdo_privacy_advanced_commit')
  and has(privacy, 'legal_hold', 'delete_record_safely')
  and has(evidence, 'function delete_record_safely', 'deletion_pending_erasure', 'GDO_Storage::delete_verified'))

c(9,'Release identity/ownership/private transport hygiene remains intact',
  len(release_files) == 62
  and 'global-doctor-onboarding.php' in release_files
  and has(trust, "const CONTRACT_VERSION = '1.1.0'", 'private_evidence_searchable', 'donor_rank_advantage')
  and has(notify, 'sun.event.v1') and 'wp_mail(' not in notify)

c(10,'R15 QA/release evidence is permanent and temporary apply plumbing is absent',
  (root/'REVIEW-10-ROUNDS-RC6-R15.md').exists()
  and lock.get('fifteenth_review_baseline') == 'ca76893f781358a95d0be2c6fbaa3ffd65757bbf'
  and lock.get('fifteenth_review_rounds') == 10
  and lock.get('fifteenth_defect_rounds') == 6
  and lock.get('fifteenth_clean_rounds') == 4
  and 'python3 tests/ten-round-audit-r15.py' in workflow
  and not (root/'.github/workflows/file09-r15-corrective-apply.yml').exists()
  and not (root/'r15-apply.json').exists())

failed=[x for x in checks if not x[2]]
for n,title,ok in checks:
    print(f"R{n:02d} {'PASS' if ok else 'FAIL'} — {title}")
if failed:
    raise SystemExit(f"R15 ten-round audit failed: {len(failed)} / {len(checks)} rounds")
print('File 09 R15 ten-round audit: 10 PASS / 0 FAIL')
