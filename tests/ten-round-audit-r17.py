from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
def t(path): return (root / path).read_text(encoding='utf-8')
def has(s, *xs): return all(x in s for x in xs)

admin=t('includes/class-gdo-admin.php')
trust=t('includes/class-gdo-advanced-trust.php')
hard=t('includes/class-gdo-advanced-trust-hardening.php')
evidence=t('includes/class-gdo-evidence.php')
retention=t('includes/class-gdo-retention.php')
notify=t('includes/class-gdo-notifications.php')
membership=t('includes/class-gdo-membership-adapter.php')
workflow=t('.github/workflows/file09-rc2-final.yml')
lock=json.loads(t('RELEASE-LOCK.json'))
release_files=[line.strip() for line in t('RELEASE-FILES.txt').splitlines() if line.strip() and not line.lstrip().startswith('#')]

checks=[]
def c(n,title,ok): checks.append((n,title,bool(ok)))

more_info=admin[admin.index('public function request_more_info'):admin.index('public function finalize')]
c(1,'More-information command surfaces application DB uncertainty',
  has(more_info, "$wpdb->last_error = '';", 'GDO_Application::get( $id )', 'professional application could not be read safely', "'response'=>503"))

rotate=evidence[evidence.index('public static function rotate_key'):]
c(2,'Private evidence rotation retains bounded orphan recovery',
  has(rotate, 'doctor_credential_rotation_orphaned_old_file')
  and has(retention, 'private static function cleanup_orphans', 'doctor_credential_orphan_removed', 'doctor_credential_orphan_remove_failed'))

primary=trust[trust.index('public static function primary_source_verify'):trust.index('public static function authenticity_assessment')]
c(3,'External trust/provider assistance exceptions are bounded',
  has(primary, 'catch ( Throwable $e )', "'provider_unavailable'", "'request_filter_exception'", "'provider_exception'")
  and trust.count('catch ( Throwable $e )') >= 5
  and has(trust, 'gdo_credential_equivalency_assessment', 'gdo_institutional_affiliation_verification', 'gdo_credential_translation_assistance', 'gdo_ai_evidence_assistance'))

c(4,'Reviewer/finalizer/conflict/appeal separation remains guarded',
  has(admin, 'function assign_appeal', 'function resolve_appeal', 'reviewer_case_allows', 'recommender_id')
  and has(admin, 'sabri_finalize_doctor_verification'))

finalize_upload=trust[trust.index('public static function finalize_upload_session'):trust.index('public static function cleanup_upload_sessions')]
c(5,'Resumable finalize preserves retry on application DB uncertainty',
  has(finalize_upload, 'self::application_record($row->application_id)', "'finalizing','open'", 'gdo_upload_application_changed')
  and finalize_upload.index("'finalizing','open'") < finalize_upload.index('gdo_upload_application_changed'))

matrix=trust[trust.index('private static function public_matrix_for_app'):trust.index('public static function public_card_shortcode')]
passport=hard[hard.index('public static function issue_passport'):]
c(6,'Public verification and passport distinguish unavailable truth',
  has(matrix, "'data_available'=>true", "$matrix['data_available'] = false", "verification_record_for_user")
  and has(trust, 'Professional verification status is temporarily unavailable.')
  and has(passport, "gdo_passport_scope_unavailable", "empty( $scope['data_available'] )")
  and has(hard, "empty($verification['data_available'])", 'Professional verification scope is temporarily unavailable.'))

c(7,'Notification delivery remains durable retry/dead-letter with privileged replay',
  has(notify, "'status'=>$terminal ? 'dead' : 'failed'", 'gdo_outbox_failure_persist_failed', 'doctor_verification_outbox_dead_letter', 'function replay', 'gdo_outbox_replay_forbidden')
  and has(notify, 'catch ( Throwable $e )', 'gdo_outbox_delivery_persist_failed')
  and 'wp_mail(' not in notify)

retire=retention[retention.index('private function retire_advanced_trust_for_application'):retention.index('private function cleanup_access')]
apply_retention=retention[retention.index('private function apply_retention'):retention.index('private function retire_advanced_trust_for_application')]
c(8,'Retention checkpoints irreversible files and commits anonymization atomically',
  has(retire, "'state'=>'retention_pending'", "state<>'retention_pending'", '@unlink', 'START TRANSACTION', "query( 'COMMIT' )")
  and retire.index("'state'=>'retention_pending'") < retire.index('@unlink') < retire.index('START TRANSACTION')
  and has(apply_retention, "SELECT id,legal_hold,retention_until", 'FOR UPDATE', "'retention_until'=>null", "query( 'COMMIT' )"))

c(9,'File00/File02 companion exceptions fail closed at the adapter boundary',
  membership.count('catch ( Throwable $e )') >= 6
  and has(membership, 'SMC_Contracts::assertions', 'SMC_CF01_Contract::membership_assertion', 'smc_get_profile', 'SA_Professional_Reauthentication::assertion', 'SA_Professional_Reauthentication::verify_and_record', 'gdo_step_up_dependency_failure'))

c(10,'R17 QA/release evidence is permanent and temporary apply plumbing is absent',
  (root/'REVIEW-10-ROUNDS-RC6-R17.md').exists()
  and lock.get('seventeenth_review_baseline') == '66e43bcb42904a381728509144cd0a1e6c77c6ac'
  and lock.get('seventeenth_review_rounds') == 10
  and lock.get('seventeenth_defect_rounds') == 7
  and lock.get('seventeenth_clean_rounds') == 3
  and 'python3 tests/ten-round-audit-r17.py' in workflow
  and len(release_files) == 62
  and not (root/'.github/workflows/file09-r17-corrective-apply.yml').exists()
  and not (root/'r17-apply.json').exists())

failed=[x for x in checks if not x[2]]
for n,title,ok in checks:
    print(f"R{n:02d} {'PASS' if ok else 'FAIL'} — {title}")
if failed:
    raise SystemExit(f"R17 ten-round audit failed: {len(failed)} / {len(checks)} rounds")
print('File 09 R17 ten-round audit: 10 PASS / 0 FAIL')
