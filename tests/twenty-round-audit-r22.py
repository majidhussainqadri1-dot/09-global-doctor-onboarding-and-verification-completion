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

retention = text('includes/class-gdo-retention.php')
application = text('includes/class-gdo-application.php')
evidence = text('includes/class-gdo-evidence.php')
admin = text('includes/class-gdo-admin.php')
notifications = text('includes/class-gdo-notifications.php')
migration = text('includes/class-gdo-migration.php')
hard = text('includes/class-gdo-advanced-trust-hardening.php')
membership = text('includes/class-gdo-membership-adapter.php')
ops = text('includes/class-gdo-operations.php')
rest = text('includes/class-gdo-rest.php')
risk = text('includes/class-gdo-risk.php')
quality = text('includes/class-gdo-quality.php')
adv = text('includes/class-gdo-advanced-trust.php')
integration = text('includes/class-gdo-integration-contracts.php')
trace = text('TRACEABILITY.md')
plugin = text('global-doctor-onboarding.php')
release_files = text('RELEASE-FILES.txt')
workflow = text('.github/workflows/file09-rc2-final.yml')
ledger = text('REVIEW-20-ROUNDS-RC6-R22.md')
lock = json.loads(text('RELEASE-LOCK.json'))

checks = []
checks.append(require(1,
    retention.count("$wpdb->last_error = '';") >= 12
    and "gdo_retention_draft_warning_query" in retention
    and "gdo_retention_claim_outbox_query" in retention,
    'Retention critical reads isolate current DB error state instead of inheriting stale errors'))
checks.append(require(2,
    has(application, 'doctor_application_create_commit_reconciled', 'doctor_consent_commit_reconciled', 'gdo_application_commit_uncertain', 'gdo_consent_commit_uncertain'),
    'Application and consent transaction ambiguity remains authoritatively reconciled'))
checks.append(require(3,
    has(evidence, 'deletion_pending', 'deletion_proof', 'gdo_evidence_review_commit_uncertain', 'gdo_evidence_grant_audit_cleanup'),
    'Private evidence review/access/deletion lifecycle remains durable and fail closed'))
checks.append(require(4,
    has(admin, 'recent_step_up', 'reviewer_case_allows', 'commit_or_reconcile', 'FOR UPDATE'),
    'Reviewer/admin commands remain current-authorized, case-bound and transactionally reconciled'))
checks.append(require(5,
    has(notifications, 'event_uuid', "status='processing'", 'dead', 'gdo_claim_ack_failed', 'sun.event.v1')
    and 'wp_mail(' not in notifications,
    'Durable File19/claim outbox retains idempotency, lease, retry/dead-letter and explicit acknowledgement'))
checks.append(require(6,
    has(migration, 'option_value', 'maybe_serialize( $lock )', '$wpdb->delete(', 'doctor_verification_schema_migration_lock_release_failed')
    and 'atomic compare-and-swap' in migration,
    'Migration stale takeover and release both preserve successor lock ownership'))
checks.append(require(7,
    has(hard, 'verify_passport_uuid', 'no-store', 'gdo_passport_scope_unavailable')
    and has(adv, "$matrix['scope_status']['license']", "$matrix['scope_status']['registration']"),
    'Public verification remains current, non-cacheable and keeps License/Registration scope separate'))
checks.append(require(8,
    has(hard, 'processing_lease_expired', "monitor_status='processing'", 'doctor_continuous_verification_provider_degraded', 'gdo_continuous_verification_adverse_result'),
    'Continuous verification keeps exclusive leases, degraded/adverse truth and human-final semantics'))
checks.append(require(9,
    has(membership, 'SMC_CONTRACT_VERSION', 'SA_PROFESSIONAL_REAUTH_VERSION', 'identity_assurance_current_checked', 'reviewer_case_allows', 'recent_step_up'),
    'File00/File02 exact contract, current identity and reviewer/step-up boundaries remain guarded'))
checks.append(require(10,
    "$wpdb->get_col(" in retention
    and "$wpdb->last_error = '';\n\t\t$ids = $wpdb->get_col(" in retention,
    'Retention key-rotation get_col inventory explicitly isolates DB error state'))
checks.append(require(11,
    has(ops, 'mutation_allowed', 'required_schedules_ready', 'gdo_reconcile_commit_uncertain', 'recent_step_up', 'record_metric'),
    'Safe Mode/runtime readiness, schedules, controlled reconciliation and operator authorization remain fail safe'))
checks.append(require(12,
    has(rest, 'gdo_application_autosave_read_failed', 'gdo_application_autosave_reload_failed', 'mutation_allowed', 'row_version', "'status'=>503"),
    'REST private reads/autosave remain owner-scoped, DB-failure-aware and mutation gated'))
checks.append(require(13,
    has(risk, 'gdo_risk_query_failed', 'false_positive', 'recent_step_up')
    and has(quality, 'gdo_quality_query_failed', 'gdo_quality_metrics_query_failed', 'reviewer_id'),
    'Risk/fraud/quality uncertainty stays fail closed and human/independent'))
checks.append(require(14,
    has(adv, 'gdo_upload_session_commit_uncertain', 'gdo_upload_chunk_commit_uncertain', 'gdo_upload_finalize_commit_uncertain', 'fsync', 'hash_file', 'GDO_Evidence::stage_upload'),
    'Resumable private upload retains ordered durable chunks, exact finalization and encrypted canonical handoff'))
checks.append(require(15,
    has(adv, 'human_final_decision_required', 'gdo_primary_source_request_minimized', 'sanitize_provider_array', 'original_remains_authoritative')
    and 'decision_authority' in adv,
    'Issuer/provider/AI/equivalency/translation paths remain minimized and human-final'))
checks.append(require(16,
    has(integration, "'owner'                  => 'file09'", 'FILE19_EVENT', 'FILE26_CONNECTOR', 'private_indexing', 'donor_rank_advantage')
    and 'direct_table_meta_write' in integration,
    'Cross-file owner/event/shell/search boundaries remain public-safe and donor-neutral'))
checks.append(require(17,
    "Version: 1.3.0" in plugin
    and "define( 'GDO_SCHEMA_VERSION', 6 )" in plugin
    and len([line for line in release_files.splitlines() if line.strip()]) == 62
    and lock.get('staging_accepted') is False
    and lock.get('live_deployed') is False,
    'Runtime/schema/62-entry release identity remains synchronized without staging/live overclaim'))
checks.append(require(18,
    'exactly **DoD-01 through DoD-12**' in trace
    and '| DoD-03 | Fresh install, every supported upgrade, deactivation/reactivation and non-destructive uninstall |' in trace
    and '| DoD-04 | Canonical ownership; no duplicate data/workflow/UI owner or direct companion writes |' in trace
    and '| DoD-13 |' not in trace,
    'Traceability maps the current governing DoD-01…DoD-12 meanings without obsolete DoD-13'))
checks.append(require(19,
    lock.get('twenty_second_review_baseline') == '532fdeeb0411284397d7418f73d0d9170e941bb8'
    and lock.get('twenty_second_review_rounds') == 20
    and lock.get('twenty_second_defect_rounds') == 5
    and 'python3 tests/twenty-round-audit-r22.py' in workflow
    and '| R19 | DEFECT |' in ledger,
    'R22 permanent ledger/release-lock/workflow evidence is present after R19 correction'))
checks.append(require(20,
    lock.get('twenty_second_clean_rounds') == 15
    and lock.get('twenty_second_pending_rounds', 0) == 0
    and '| R20 | CLEAN |' in ledger
    and not (ROOT / '.github/workflows/file09-r22-corrective-apply.yml').exists()
    and not (ROOT / 'r22-apply.json').exists(),
    'Final R22 exact-head evidence is complete and temporary corrective plumbing is absent'))

passed = sum(checks)
failed = len(checks) - passed
print(f'File 09 R22 twenty-round audit: {passed} PASS / {failed} FAIL')
if failed:
    sys.exit(1)
