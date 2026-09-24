from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]

def text(path):
    return (root / path).read_text(encoding='utf-8')

def has(source, *tokens):
    return all(token in source for token in tokens)

admin = text('includes/class-gdo-admin.php')
hard = text('includes/class-gdo-advanced-trust-hardening.php')
claims = text('includes/class-gdo-claims.php')
app = text('includes/class-gdo-application.php')
front = text('includes/class-gdo-frontend.php')
migration = text('includes/class-gdo-migration.php')
notify = text('includes/class-gdo-notifications.php')
retention = text('includes/class-gdo-retention.php')
workflow = text('.github/workflows/file09-rc2-final.yml')
status = text('STATUS.md')
readme = text('README.md')
manifest = text('RELEASE-MANIFEST-1.3.0.md')
lock = json.loads(text('RELEASE-LOCK.json'))

checks = []
def check(round_no, title, ok):
    checks.append((round_no, title, bool(ok)))

check(1, 'Reviewer/finalizer critical evidence and immutable profile snapshot reads fail closed',
      has(admin, 'GDO_Evidence::records_checked( $app->id, true )', 'Credential evidence could not be read safely for this review.')
      and has(admin, "if ( ! is_array( $profile ) )", 'The immutable professional profile snapshot is unreadable')
      and has(admin, 'GDO_Evidence::records_checked( $id, true )', 'The immutable credential snapshot could not be read safely for final decision.'))

check(2, 'Professional passport issuance is downstream-claim-acknowledgement bound',
      has(claims, "do_action( 'gdo_professional_claim_acknowledged'", 'claim_version')
      and has(hard, "add_action( 'gdo_professional_claim_acknowledged'", 'claim_acknowledged')
      and has(hard, "'accepted' !== sanitize_key( $app->claim_status )")
      and has(hard, "'accepted'!==sanitize_key($app->claim_status)"))

check(3, 'Immutable application submission uses checked evidence inventory and aborts on DB uncertainty',
      has(app, 'GDO_Evidence::records_checked( $app->id, true )', 'gdo_submit_evidence_query', 'ROLLBACK'))

check(4, 'Applicant form rendering fails closed on completeness/evidence DB uncertainty and reuses one checked evidence inventory',
      has(front, "! empty( $complete['query_error'] )", 'gdo_frontend_completeness_query')
      and has(front, 'GDO_Evidence::records_checked( $app->id, true )', '$evidence_by_type')
      and 'foreach ( GDO_Evidence::records( $app->id, true )' not in front)

check(5, 'Draft save isolates application pre-read and verifies post-write reload before continuing uploads',
      has(front, "$wpdb->last_error = '';", 'The private application could not be read safely.', 'Application reload failed after draft save.'))

check(6, 'Core migration persists completion evidence and isolates backfill/quarantine DB reads',
      has(migration, '$migration_evidence = array(', 'File 09 last-migration evidence could not be persisted.')
      and has(migration, 'File 09 application backfill query failed.', 'Legacy File 09 user migration query failed.', 'Legacy File 09 credential migration query failed.')
      and migration.count("$wpdb->last_error = '';") >= 4)

check(7, 'Legacy credential migration no longer silently swallows encryption/storage/transaction/hash/cleanup failure or ambiguous COMMIT outcome',
      has(migration, 'Legacy credential encryption failed:', 'Legacy credential private storage failed:', 'Legacy credential transaction could not be started.')
      and has(migration, 'Legacy credential database/hash commit preparation failed.', 'Legacy credential commit outcome is uncertain and requires safe retry/reconciliation.')
      and has(migration, 'Legacy credential migrated but source cleanup is pending; checkpoint advancement was stopped.'))

check(8, 'Notification outbox isolates DB reads and stale failed delivery cannot overwrite a newer professional claim version',
      has(notify, "$wpdb->last_error = '';", 'gdo_outbox_query_failed', 'gdo_claim_failure_version_missing')
      and has(notify, "'claim_version'=>$payload_claim_version", 'doctor_professional_claim_failure_not_current'))

check(9, 'Retention uses checked evidence inventory and orphan-cleanup deletion failure propagates instead of false success',
      has(retention, 'GDO_Evidence::records_checked( $app->id, false )', "$wpdb->query( 'ROLLBACK' ); return $evidence_rows;")
      and has(retention, '$cleanup_ok = true;', '$cleanup_ok = false;', 'return $cleanup_ok;'))

check(10, 'R12 QA/release evidence is permanent, executable, authoritative-workflow wired, and temporary apply workflow removed',
      'python3 tests/ten-round-audit-r12.py' in workflow
      and not (root / '.github/workflows/file09-r12-apply.yml').exists()
      and lock.get('twelfth_review_baseline') == '0df40731282a4dc905a12b37e5fa5aa4604dc68a'
      and lock.get('twelfth_review_rounds') == 10
      and lock.get('twelfth_defect_rounds') == 10
      and lock.get('twelfth_clean_rounds') == 0
      and 'Twelfth fresh 10-round corrective assurance' in status
      and 'Twelfth fresh 10-round corrective assurance' in readme
      and 'Twelfth fresh 10-round corrective assurance' in manifest)

failed = [item for item in checks if not item[2]]
for round_no, title, ok in checks:
    print(f"R{round_no:02d} {'PASS' if ok else 'FAIL'} — {title}")

if failed:
    raise SystemExit(f"R12 ten-round audit failed: {len(failed)} / {len(checks)} rounds")

print('File 09 R12 ten-round audit: 10 PASS / 0 FAIL')
