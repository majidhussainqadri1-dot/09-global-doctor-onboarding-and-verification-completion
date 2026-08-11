from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]

def text(path):
    return (root / path).read_text(encoding='utf-8')

def has(source, *tokens):
    return all(token in source for token in tokens)

admin = text('includes/class-gdo-admin.php')
app = text('includes/class-gdo-application.php')
evidence = text('includes/class-gdo-evidence.php')
hard = text('includes/class-gdo-advanced-trust-hardening.php')
trust = text('includes/class-gdo-advanced-trust.php')
frontend = text('includes/class-gdo-frontend.php')
state = text('includes/class-gdo-state.php')
retention = text('includes/class-gdo-retention.php')
privacy = text('includes/class-gdo-privacy.php')
notify = text('includes/class-gdo-notifications.php')
operations = text('includes/class-gdo-operations.php')
workflow = text('.github/workflows/file09-rc2-final.yml')
status = text('STATUS.md')
readme = text('README.md')
manifest = text('RELEASE-MANIFEST-1.3.0.md')
lock = json.loads(text('RELEASE-LOCK.json'))
release_files = [line.strip() for line in text('RELEASE-FILES.txt').splitlines() if line.strip() and not line.lstrip().startswith('#')]

checks = []
def check(round_no, title, ok):
    checks.append((round_no, title, bool(ok)))

check(1, 'Verified validity is bounded by earliest current required supporting evidence/credential expiry',
      has(evidence, 'function verification_valid_until_ceiling', 'records_checked( $application_id, true )', 'gdo_evidence_ceiling_expired', 'gdo_evidence_ceiling_license_validity')
      and has(admin, 'verification_valid_until_ceiling( $id )', '$requested_until > $evidence_ceiling')
      and has(app, 'verification_valid_until_ceiling( $app->id )', 'gdo_snapshot_validity_ceiling'))

check(2, 'Authoritative RC6 continuous monitor replacement/degraded retry remains intact',
      has(hard, "remove_action( 'gdo_trust_continuous_monitor'", "add_action( 'gdo_trust_continuous_monitor'", "monitor_status IN ('scheduled','degraded')", "monitor_status='processing'", 'processing_lease_expired')
      and has(hard, 'GDO_Evidence::records( $app->id, true )', 'gdo_trust_monitor_evidence_query'))

check(3, 'State concurrency, applicant ownership and independent appeal authorization remain guarded',
      has(state, 'row_version', 'gdo_concurrent_change', 'gdo_transition_store_failed')
      and has(frontend, 'gdo_file_appeal', 'START TRANSACTION', 'FOR UPDATE', 'appeal_pending')
      and has(admin, 'recent_step_up', 'reviewer_case_allows', 'gdo_assign_appeal'))

check(4, 'Credential deletion uses durable DB pending state before physical unlink and safe retry finalization',
      has(evidence, 'function delete_record_safely', 'deletion_pending_erasure', 'deletion_pending_retention', 'deletion_pending_superseded', 'gdo_delete_pending_store_failed', 'GDO_Storage::delete_verified', 'gdo_delete_finalize_store_failed')
      and has(privacy, "GDO_Evidence::delete_record_safely( $record, 'deleted'")
      and has(retention, 'GDO_Evidence::delete_record_safely( $record, $state, $now )', "retention_state='deletion_pending_superseded'"))

check(5, 'Dead-letter replay isolates DB state and distinguishes store failure from state conflict',
      has(notify, 'function replay', "$wpdb->last_error = '';", 'gdo_outbox_replay_query', 'gdo_outbox_replay_store_failed', 'gdo_outbox_replay_conflict'))

check(6, 'Required retention/outbox/continuous-monitor schedules are mutation-critical and health-critical',
      has(operations, 'function required_schedules_ready', "$schedules_ready = self::required_schedules_ready();", '&& $schedules_ready')
      and has(operations, "'gdo_daily_retention', 'daily'", "'gdo_notification_outbox', 'hourly'", "'gdo_trust_continuous_monitor', 'daily'")
      and has(operations, "$checks['retention_cron']", "? 'pass' : 'fail'", "$checks['trust_monitor_cron']"))

check(7, 'Public verification scope is checked-read, expiry-aware and claim-acknowledgement bound',
      has(trust, "'accepted' === sanitize_key( $app->claim_status )", 'GDO_Evidence::records_checked( $app->id, true )', "$evidence->expires_at", "$evidence->validity_until", "$matrix['current_status'] = false"))

check(8, 'REST reviewer object scope, viewing-room grants and resumable upload ownership/integrity remain guarded',
      has(hard, 'reviewer_case_allows( $reviewer, $app->user_id, $app->id )', 'rest_reviewer_permission')
      and has(trust, 'function issue_viewing_room_grant', 'GDO_Evidence::issue_view_grant', "'download_allowed'=>false")
      and has(trust, 'function create_upload_session', 'FOR UPDATE', 'is_active_doctor_candidate', 'function append_upload_chunk', 'flock($fh,LOCK_EX)', 'function finalize_upload_session', 'GDO_Evidence::stage_upload'))

check(9, 'Release identity/hygiene controls remain fixed at the existing 62-entry RC6 allowlist',
      len(release_files) == 62
      and 'global-doctor-onboarding.php' in release_files
      and has(manifest, '**62 entries**')
      and not (root / '.github/workflows/file09-r13-apply.yml').exists()
      and not (root / 'tools/file09-r13-apply.patch').exists())

check(10, 'R13 QA/release evidence is permanent and authoritative-workflow wired',
      'python3 tests/ten-round-audit-r13.py' in workflow
      and (root / 'REVIEW-10-ROUNDS-RC6-R13.md').exists()
      and lock.get('thirteenth_review_baseline') == 'e5e867d235aafc49dd084644590afe8dfaf27d47'
      and lock.get('thirteenth_review_rounds') == 10
      and lock.get('thirteenth_defect_rounds') == 6
      and lock.get('thirteenth_clean_rounds') == 4
      and 'Thirteenth fresh 10-round corrective assurance' in status
      and 'Thirteenth fresh 10-round corrective assurance' in readme
      and 'Thirteenth fresh 10-round corrective assurance' in manifest)

failed = [item for item in checks if not item[2]]
for round_no, title, ok in checks:
    print(f"R{round_no:02d} {'PASS' if ok else 'FAIL'} — {title}")

if failed:
    raise SystemExit(f"R13 ten-round audit failed: {len(failed)} / {len(checks)} rounds")

print('File 09 R13 ten-round audit: 10 PASS / 0 FAIL')
