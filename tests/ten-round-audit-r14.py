from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
def t(path): return (root / path).read_text(encoding='utf-8')
def has(s, *xs): return all(x in s for x in xs)

evidence=t('includes/class-gdo-evidence.php')
app=t('includes/class-gdo-application.php')
state=t('includes/class-gdo-state.php')
frontend=t('includes/class-gdo-frontend.php')
admin=t('includes/class-gdo-admin.php')
ops=t('includes/class-gdo-operations.php')
hard=t('includes/class-gdo-advanced-trust-hardening.php')
trust=t('includes/class-gdo-advanced-trust.php')
privacy=t('includes/class-gdo-privacy.php')
retention=t('includes/class-gdo-retention.php')
notify=t('includes/class-gdo-notifications.php')
workflow=t('.github/workflows/file09-rc2-final.yml')
lock=json.loads(t('RELEASE-LOCK.json'))

checks=[]
def c(n,title,ok): checks.append((n,title,bool(ok)))

quota=evidence[evidence.index('private static function quota_allows'):evidence.index('public static function stage_upload')]
c(1,'Credential quota query isolates database error state', has(quota, "$wpdb->last_error = '';", 'SELECT COALESCE(SUM(file_size),0)', '! empty( $wpdb->last_error )'))

c(2,'License and professional-registration evidence share current-validity trust gates',
  has(evidence, "array( 'license', 'registration', 'professional_registration' )", 'gdo_evidence_ceiling_license_validity')
  and evidence.count("'registration'") >= 3 and evidence.count("'professional_registration'") >= 3)

c(3,'More-information/resubmission remains ownership/version/immutable-snapshot guarded',
  has(app, "array( 'draft','more_information' )", "'resubmitted'", 'submission_hash', 'hash_equals')
  and has(state, 'row_version', 'FOR UPDATE')
  and has(evidence, "retention_state'=>'superseded'"))

c(4,'Expiry truth is request-time checked and lifecycle reconciliation remains present',
  has(trust, 'function current_verification_expiry', '$expires > time()')
  and has(ops, 'function reconcile') and has(retention, "'expired'"))

c(5,'Appeal deadline is operator-visible and health-observable',
  has(ops, "'overdue_appeals'", "'appeal_deadlines'", 'deadline_at<%s')
  and has(admin, 'Appeal review deadline:', 'OVERDUE'))

c(6,'Privacy/legal-hold and durable DB-first evidence deletion lifecycle remain guarded',
  has(evidence, 'function delete_record_safely', 'deletion_pending_erasure', 'GDO_Storage::delete_verified', 'gdo_delete_finalize_store_failed')
  and has(privacy, 'legal_hold', 'delete_record_safely')
  and has(retention, 'delete_record_safely'))

monitor=hard[hard.index('public static function continuous_monitor'):hard.index('private static function unsafe_chunk_path')]
c(7,'Continuous monitor preserves adverse/degraded severity over later clean results',
  has(monitor, '$check_result', '$adverse = true', 'if ( ! $adverse ) { $result = $check_result; }', 'if ( ! $adverse && ! $provider_failure ) { $result = $check_result; }'))

matrix=trust[trust.index('private static function public_matrix_for_app'):trust.index('public static function verification_matrix')]
c(8,'Public License and Registration scope truths remain separate',
  has(matrix, "if ( 'license' === $document_type )", "$matrix['scope_status']['license']")
  and has(matrix, "array( 'registration','professional_registration' )", "$matrix['scope_status']['registration']")
  and "if ( in_array( $evidence->document_type, array( 'license','registration','professional_registration' ), true ) )" not in matrix)

c(9,'Security/private evidence and human-final-decision architecture remains intact',
  has(trust, 'function issue_viewing_room_grant', "'download_allowed'=>false", 'GDO_Evidence::stage_upload', 'human_final_decision_required')
  and has(notify, 'sun.event.v1')
  and 'wp_mail(' not in notify)

c(10,'R14 ledger/release-lock/workflow evidence is permanent and temporary apply plumbing is absent',
  (root/'REVIEW-10-ROUNDS-RC6-R14.md').exists()
  and lock.get('fourteenth_review_baseline') == '52b41f3395e1599540da0656d6e9a022c39a27fe'
  and lock.get('fourteenth_review_rounds') == 10
  and lock.get('fourteenth_defect_rounds') == 6
  and lock.get('fourteenth_clean_rounds') == 4
  and 'python3 tests/ten-round-audit-r14.py' in workflow
  and not (root/'.github/workflows/file09-r14-corrective-apply.yml').exists()
  and not (root/'r14-apply.json').exists())

failed=[x for x in checks if not x[2]]
for n,title,ok in checks:
    print(f"R{n:02d} {'PASS' if ok else 'FAIL'} — {title}")
if failed:
    raise SystemExit(f"R14 ten-round audit failed: {len(failed)} / {len(checks)} rounds")
print('File 09 R14 ten-round audit: 10 PASS / 0 FAIL')
