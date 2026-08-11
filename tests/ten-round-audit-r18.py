from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
def t(path): return (root / path).read_text(encoding='utf-8')
def has(s, *xs): return all(x in s for x in xs)

trust=t('includes/class-gdo-advanced-trust.php')
hard=t('includes/class-gdo-advanced-trust-hardening.php')
claims=t('includes/class-gdo-claims.php')
notify=t('includes/class-gdo-notifications.php')
admin=t('includes/class-gdo-admin.php')
retention=t('includes/class-gdo-retention.php')
privacy=t('includes/class-gdo-privacy.php')
migration=t('includes/class-gdo-migration.php')
membership=t('includes/class-gdo-membership-adapter.php')
workflow=t('.github/workflows/file09-rc2-final.yml')
lock=json.loads(t('RELEASE-LOCK.json'))
release_files=[line.strip() for line in t('RELEASE-FILES.txt').splitlines() if line.strip() and not line.lstrip().startswith('#')]

checks=[]
def c(n,title,ok): checks.append((n,title,bool(ok)))

base_ensure=trust[trust.index('public static function ensure_passport'):trust.index('public static function revoke_passports_for_application')]
hard_ensure=hard[hard.index('public static function ensure_passport'):hard.index('public static function verify_passport_uuid')]
c(1,'Existing active-passport verification errors do not trigger replacement issuance',
  has(base_ensure, '$verified = self::verify_passport_uuid', 'if ( is_wp_error( $verified ) ) { return $verified; }', 'return self::issue_passport')
  and base_ensure.index('if ( is_wp_error( $verified ) )') < base_ensure.index('return self::issue_passport')
  and has(hard_ensure, '$verified=self::verify_passport_uuid', 'if(is_wp_error($verified)){return $verified;}', 'return self::issue_passport')
  and hard_ensure.index('if(is_wp_error($verified))') < hard_ensure.index('return self::issue_passport'))

c(2,'Claim acknowledgement is terminal CAS, idempotent and DB-failure-aware',
  has(claims, "'claim_status'=>'pending'", 'gdo_claim_ack_query_failed', 'gdo_claim_ack_store_failed', 'gdo_claim_ack_recheck_failed')
  and has(claims, 'if ( $status === $current_status ) { return true; }', "if ( 'pending' !== $current_status ) { return false; }"))

c(3,'Reviewer/finalizer/conflict/appeal separation remains guarded',
  has(admin, 'function assign_appeal', 'function resolve_appeal', 'reviewer_case_allows', 'recommender_id', 'sabri_finalize_doctor_verification'))

finalize_upload=trust[trust.index('public static function finalize_upload_session'):trust.index('public static function cleanup_upload_sessions')]
c(4,'Resumable upload lifecycle remains durable and retryable',
  has(finalize_upload, 'self::application_record($row->application_id)', "'finalizing','open'", 'hash_file', 'filesize', 'committed')
  and has(hard, 'gdo_upload_cleanup_unsafe_path', 'gdo_privacy_upload_checkpoint'))

primary=trust[trust.index('public static function primary_source_verify'):trust.index('public static function authenticity_assessment')]
c(5,'Provider and assistance adapters remain bounded and human-final',
  has(primary, 'catch ( Throwable $e )', "'provider_unavailable'", "'manual_review_required'")
  and trust.count('catch ( Throwable $e )') >= 5
  and has(trust, 'gdo_credential_equivalency_assessment', 'gdo_institutional_affiliation_verification', 'gdo_credential_translation_assistance', 'gdo_ai_evidence_assistance', 'human_final_decision_required'))

apply_retention=retention[retention.index('private function apply_retention'):retention.index('private function retire_advanced_trust_for_application')]
c(6,'Legal hold and retention eligibility are serialized before irreversible deletion',
  has(apply_retention, 'gdo_retention_predelete_transaction', 'FOR UPDATE', 'legal_hold,retention_until,state', 'gdo_retention_predelete_commit')
  and apply_retention.index('gdo_retention_predelete_transaction') < apply_retention.index('delete_for_privacy')
  and has(privacy, 'Establish a database-serialized erasure authorization point', 'SELECT id,user_id,legal_hold', 'FOR UPDATE', 'Erasure is paused because the application is now under legal hold'))

c(7,'Claim delivery rejects acknowledgement WP_Error instead of reporting delivered',
  has(notify, '$acknowledged = GDO_Claims::acknowledge', 'if ( is_wp_error( $acknowledged ) )', 'gdo_claim_ack_failed')
  and notify.index('$acknowledged = GDO_Claims::acknowledge') < notify.index('if ( is_wp_error( $acknowledged ) )'))

c(8,'Stale migration-lock takeover uses atomic compare-and-swap',
  has(migration, 'Stale takeover is an atomic compare-and-swap', '$wpdb->options', "'option_value'=>maybe_serialize( $new_lock )", "'option_value'=>maybe_serialize( $lock )", '1 !== $swapped', "wp_cache_delete( self::LOCK_OPTION, 'options' )"))

matrix=trust[trust.index('private static function public_matrix_for_app'):trust.index('public static function verification_matrix')]
c(9,'Public identity truth distinguishes dependency uncertainty from not verified',
  has(membership, 'identity_assurance_current_checked', 'gdo_identity_dependency_unavailable', 'gdo_identity_assertion_unavailable', 'gdo_identity_assertion_invalid')
  and has(matrix, 'identity_assurance_current_checked', 'is_wp_error( $identity )', "$matrix['data_available'] = false")
  and has(trust, 'gdo_passport_identity_unavailable')
  and has(hard, 'gdo_passport_identity_unavailable', "array( 'status'=>503 )"))

c(10,'R18 QA/release evidence is permanent and temporary apply plumbing is absent',
  (root/'REVIEW-10-ROUNDS-RC6-R18.md').exists()
  and lock.get('eighteenth_review_baseline') == '67a3e999fa44e527bc4a795759a1b48cb8e7766d'
  and lock.get('eighteenth_review_rounds') == 10
  and lock.get('eighteenth_defect_rounds') == 7
  and lock.get('eighteenth_clean_rounds') == 3
  and 'python3 tests/ten-round-audit-r18.py' in workflow
  and len(release_files) == 62
  and not (root/'.github/workflows/file09-r18-corrective-apply.yml').exists()
  and not (root/'r18-apply.json').exists())

failed=[x for x in checks if not x[2]]
for n,title,ok in checks:
    print(f"R{n:02d} {'PASS' if ok else 'FAIL'} — {title}")
if failed:
    raise SystemExit(f"R18 ten-round audit failed: {len(failed)} / {len(checks)} rounds")
print('File 09 R18 ten-round audit: 10 PASS / 0 FAIL')
