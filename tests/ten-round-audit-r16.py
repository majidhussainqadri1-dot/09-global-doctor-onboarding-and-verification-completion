from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
def t(path): return (root / path).read_text(encoding='utf-8')
def has(s, *xs): return all(x in s for x in xs)

trust=t('includes/class-gdo-advanced-trust.php')
hard=t('includes/class-gdo-advanced-trust-hardening.php')
events=t('includes/class-gdo-advanced-trust-events.php')
privacy=t('includes/class-gdo-privacy.php')
notify=t('includes/class-gdo-notifications.php')
workflow=t('.github/workflows/file09-rc2-final.yml')
lock=json.loads(t('RELEASE-LOCK.json'))
release_files=[line.strip() for line in t('RELEASE-FILES.txt').splitlines() if line.strip() and not line.lstrip().startswith('#')]

checks=[]
def c(n,title,ok): checks.append((n,title,bool(ok)))

app_helper=trust[trust.index('private static function application_record'):trust.index('private static function evidence_record')]
c(1,'Advanced Trust application reads propagate database uncertainty',
  has(app_helper, "$wpdb->last_error = '';", 'GDO_Application::get', 'gdo_trust_application_query', "array( 'status'=>503 )")
  and trust.count('self::application_record') >= 9)

app_decided=hard[hard.index('public static function application_decided'):hard.index('public static function claim_acknowledged')]
c(2,'Decision derivative lifecycle failures are fail-visible',
  has(app_decided, 'gdo_decision_application_query', 'doctor_verification_passport_issue_failed', 'return $passport;', 'gdo_decision_passport_revoke', 'gdo_decision_reverification_store', 'return $error;'))

transition_bridge=events[events.index("if ( 'doctor_verification_transition' === $event )"):events.index('$direct = array(')]
c(3,'Submitted transition does not duplicate canonical post-commit submission side effects',
  has(transition_bridge, "if ( 'submitted' === $to )", 'gdo_application_submitted owner hook', 'return;')
  and 'application_submitted( $application_id )' not in transition_bridge)

c(4,'Post-commit Advanced Trust lifecycle callback failures are observed',
  has(events, 'function observe_result', 'doctor_advanced_trust_lifecycle_side_effect_failed', 'gdo_advanced_trust_lifecycle_attention', 'self::observe_result('))

claim_ack=hard[hard.index('public static function claim_acknowledged'):hard.index('public static function issue_passport')]
c(5,'Passport issuance is accepted-claim bound and monitor scheduling is independently recoverable',
  has(app_decided, "if('accepted'===sanitize_key($app->claim_status))", "self::schedule_reverification($app->id,'verified'", 'return $error;')
  and has(claim_ack, 'self::ensure_passport', "self::schedule_reverification( $app->id, 'claim_accepted'", 'doctor_claim_reverification_schedule_failed'))

primary=trust[trust.index('public static function primary_source_verify'):trust.index('public static function authenticity_assessment')]
monitor=hard[hard.index('public static function continuous_monitor'):hard.index('private static function unsafe_chunk_path')]
c(6,'Unsupported/degraded primary-source provider states remain fail-safe manual attention',
  has(primary, "'manual_review_required'", "'provider_unavailable'", "'timeout'", "'malformed_response'")
  and has(monitor, "'manual_review_required'", "'issuer_unverified'", "$provider_failure = true"))

c(7,'Notification delivery remains durable retry/dead-letter with privileged replay',
  has(notify, "'status'=>$terminal ? 'dead' : 'failed'", 'gdo_outbox_failure_persist_failed', 'doctor_verification_outbox_dead_letter', 'function replay', 'gdo_outbox_replay_forbidden')
  and 'wp_mail(' not in notify)

advanced_privacy=hard[hard.index('public static function privacy_erase_application'):hard.index('private static function rest_value')]
c(8,'Resumable privacy erasure checkpoints DB intent before physical unlink',
  has(advanced_privacy, "'state'=>'erasure_pending'", 'gdo_privacy_upload_checkpoint', 'gdo_privacy_upload_checkpoint_verify', "state<>'erasure_pending'", '@unlink', 'START TRANSACTION')
  and advanced_privacy.index("'state'=>'erasure_pending'") < advanced_privacy.index('@unlink') < advanced_privacy.index("START TRANSACTION"))

erase=privacy[privacy.index('public function erase'):privacy.index('public function policy')]
c(9,'Privacy eraser completion remains retryable until every selected application finishes',
  has(erase, '$completed_apps = 0;', '++$completed_apps;', "$completed_apps === count( $apps )"))

c(10,'R16 QA/release evidence is permanent and temporary apply plumbing is absent',
  (root/'REVIEW-10-ROUNDS-RC6-R16.md').exists()
  and lock.get('sixteenth_review_baseline') == '4707d31cbd5ed8f419166d2f796bee315449e172'
  and lock.get('sixteenth_review_rounds') == 10
  and lock.get('sixteenth_defect_rounds') == 9
  and lock.get('sixteenth_clean_rounds') == 1
  and 'python3 tests/ten-round-audit-r16.py' in workflow
  and len(release_files) == 62
  and not (root/'.github/workflows/file09-r16-corrective-apply.yml').exists()
  and not (root/'r16-apply.json').exists())

failed=[x for x in checks if not x[2]]
for n,title,ok in checks:
    print(f"R{n:02d} {'PASS' if ok else 'FAIL'} — {title}")
if failed:
    raise SystemExit(f"R16 ten-round audit failed: {len(failed)} / {len(checks)} rounds")
print('File 09 R16 ten-round audit: 10 PASS / 0 FAIL')
