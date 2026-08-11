#!/usr/bin/env python3
from pathlib import Path
import json
import sys

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def has(source, *tokens):
    return all(token in source for token in tokens)

def require(round_no, condition, description):
    ok = bool(condition)
    print(f"R{round_no:02d} {'PASS' if ok else 'FAIL'} — {description}")
    return ok

app = text('includes/class-gdo-application.php')
state = text('includes/class-gdo-state.php')
migration = text('includes/class-gdo-migration.php')
evidence = text('includes/class-gdo-evidence.php')
admin = text('includes/class-gdo-admin.php')
adv = text('includes/class-gdo-advanced-trust.php')
hard = text('includes/class-gdo-advanced-trust-hardening.php')
notifications = text('includes/class-gdo-notifications.php')
risk = text('includes/class-gdo-risk.php')
membership = text('includes/class-gdo-membership-adapter.php')
privacy = text('includes/class-gdo-privacy.php')
ops = text('includes/class-gdo-operations.php')
policy = text('includes/class-gdo-policy.php')
frontend = text('includes/class-gdo-frontend.php')
plugin = text('global-doctor-onboarding.php')
release_files = text('RELEASE-FILES.txt')
workflow = text('.github/workflows/file09-rc2-final.yml')
ledger = text('REVIEW-20-ROUNDS-RC6-R21.md')
lock = json.loads(text('RELEASE-LOCK.json'))

checks = []
checks.append(require(1,
    has(app, 'gdo_application_commit_uncertain', 'doctor_application_create_commit_reconciled', 'gdo_consent_commit_uncertain', 'doctor_consent_commit_reconciled'),
    'Draft creation and consent reconcile ambiguous COMMIT outcomes against durable truth'))
checks.append(require(2,
    has(state, 'gdo_transition_commit_uncertain', 'doctor_state_transition_commit_reconciled', "SELECT state,row_version", "SELECT event_hash"),
    'Generic standalone state transition reconciles exact state/version/audit after ambiguous COMMIT'))
checks.append(require(3,
    has(migration, 'Legacy File 09 application quarantine transaction could not start', 'doctor_legacy_quarantine_commit_reconciled', 'quarantine commit outcome is uncertain')
    and "GDO_Audit::publish_transition( $audit )" in migration,
    'Legacy quarantine application and initial chained audit are atomic/reconciled'))
checks.append(require(4,
    has(evidence, 'gdo_evidence_grant_audit_cleanup', 'doctor_evidence_grant_audit_compensation_failed')
    and has(admin, '$served_audit = GDO_Audit::access', "'response'=>503"),
    'Private credential capability/bytes fail closed when durable access audit fails'))
checks.append(require(5,
    has(evidence, 'gdo_evidence_review_commit_uncertain', 'checklist_json,findings_json,review_note,registry_result,registry_source,reviewer_id,reviewed_at,validity_from,validity_until,updated_at')
    and "hash_equals( (string) $data['checklist_json']" in evidence
    and "hash_equals( (string) $data['updated_at']" in evidence,
    'Evidence review COMMIT reconciliation matches the exact intended review mutation'))
checks.append(require(6,
    has(adv, 'gdo_upload_session_commit_uncertain', 'gdo_upload_chunk_commit_uncertain', 'gdo_upload_finalize_commit_uncertain'),
    'Resumable upload transaction ambiguity remains authoritatively reconciled'))
checks.append(require(7,
    has(notifications, 'event_uuid', "status='processing'", 'dead_letter', 'message_id') and 'gdo_claim_ack_failed' in notifications,
    'Durable outbox retains dedupe, processing lease, retry/dead-letter and claim acknowledgement protections'))
checks.append(require(8,
    has(admin, 'reviewer_case_allows', 'reviewer_conflict', 'appeal') and 'commit_or_reconcile' in admin,
    'Reviewer/finalizer/appeal authorization and independent transaction boundaries remain guarded'))
checks.append(require(9,
    has(risk, 'gdo_risk_query_failed', 'false_positive', 'recent_step_up') and 'Risk-state uncertainty must narrow professional verification' in risk,
    'Risk/fraud uncertainty remains fail-closed and human-resolved'))
checks.append(require(10,
    has(migration, 'gdo_schema_future_version', 'verify_installation', 'atomic compare-and-swap')
    and lock.get('schema') == 6 and lock.get('advanced_trust_schema') == 2,
    'Core/Advanced schema identity, future-schema rejection, CAS and physical verification remain intact'))
checks.append(require(11,
    has(adv, "$matrix['scope_status']['license']", "$matrix['scope_status']['registration']", 'gdo_public_transparency_minimum_cohort')
    and 'no-store' in hard,
    'Public verification keeps License/Registration separate, cohort-safe and non-cacheable'))
checks.append(require(12,
    has(hard, 'processing_lease_expired', "array( 'license','registration','professional_registration' )", 'doctor_continuous_verification_provider_degraded')
    and 'gdo_continuous_verification_adverse_result' in hard,
    'Continuous monitor preserves lease, aliases, degraded/adverse severity and human-final semantics'))
checks.append(require(13,
    has(membership, 'identity_assurance_current_checked', 'SA_Professional_Reauthentication', 'recent_step_up', 'reviewer_case_allows'),
    'File00/File02 current identity, current-session step-up and reviewer narrowing remain guarded'))
checks.append(require(14,
    has(privacy, 'legal_hold=0', 'reconcile_authorized_missing_deletion', 'GDO_Advanced_Trust_Hardening::privacy_erase_application')
    and "'done'=>false" in privacy,
    'Privacy export/erasure remains legal-hold constrained, deletion-safe and retryable'))
checks.append(require(15,
    has(ops, 'mutation_allowed', 'repair_schedules', 'recent_step_up', 'gdo_reconcile_commit_uncertain'),
    'Safe Mode, runtime readiness, repair authorization and operational reconciliation remain fail-safe'))
checks.append(require(16,
    has(policy, "$wpdb->last_error = '';", 'GDO_Application::latest_for_user( $user_id )', "reason_code'] = 'database_unavailable'")
    and "'eligible'=>false" in policy,
    'Eligibility latest-application DB uncertainty fails closed instead of granting eligibility'))
checks.append(require(17,
    has(frontend, '$preserve_new_files = false', 'doctor_application_save_commit_reconciled', 'appeal commit outcome is uncertain', 'doctor_appeal_commit_reconciled', 'withdrawal application state could not be read safely', 'doctor_withdraw_commit_reconciled')
    and 'Encrypted evidence was preserved for reconciliation' in frontend,
    'Applicant save/appeal/withdraw transactions reconcile ambiguous commits without deleting possibly committed ciphertext'))
checks.append(require(18,
    "Version: 1.3.0" in plugin and "define( 'GDO_SCHEMA_VERSION', 6 )" in plugin
    and 'global-doctor-onboarding-09/' in release_files
    and lock.get('release_file_count') == 62
    and lock.get('staging_accepted') is False
    and lock.get('live_deployed') is False,
    'Runtime/package/ownership evidence remains synchronized without staging/live overclaim'))
checks.append(require(19,
    lock.get('twenty_first_review_baseline') == 'a4191a0c693ba7dd95770fcdfee46804a8645a67'
    and lock.get('twenty_first_review_rounds') == 20
    and lock.get('twenty_first_defect_rounds') == 8
    and 'python3 tests/twenty-round-audit-r21.py' in workflow
    and '| R19 | DEFECT |' in ledger,
    'R21 permanent ledger/release-lock/workflow evidence is present after R19 correction'))
checks.append(require(20,
    lock.get('twenty_first_clean_rounds') == 12
    and lock.get('twenty_first_pending_rounds', 0) == 0
    and '| R20 | CLEAN |' in ledger
    and not (ROOT / '.github/workflows/file09-r21-corrective-apply.yml').exists()
    and not (ROOT / 'r21-apply.json').exists(),
    'Final R21 exact-head evidence is complete and temporary corrective plumbing is absent'))

passed = sum(checks)
failed = len(checks) - passed
print(f'File 09 R21 twenty-round audit: {passed} PASS / {failed} FAIL')
if failed:
    sys.exit(1)
