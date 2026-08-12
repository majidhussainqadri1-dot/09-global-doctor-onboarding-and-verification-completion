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

hard = text('includes/class-gdo-advanced-trust-hardening.php')
adv = text('includes/class-gdo-advanced-trust.php')
claims = text('includes/class-gdo-claims.php')
app = text('includes/class-gdo-application.php')
admin = text('includes/class-gdo-admin.php')
evidence = text('includes/class-gdo-evidence.php')
privacy = text('includes/class-gdo-privacy.php')
retention = text('includes/class-gdo-retention.php')
migration = text('includes/class-gdo-migration.php')
risk = text('includes/class-gdo-risk.php')
membership = text('includes/class-gdo-membership-adapter.php')
ops = text('includes/class-gdo-operations.php')
notifications = text('includes/class-gdo-notifications.php')
workflow = text('.github/workflows/file09-rc2-final.yml')
ledger = text('REVIEW-20-ROUNDS-RC6-R20.md')
lock = json.loads(text('RELEASE-LOCK.json'))

checks = []
checks.append(require(1,
    has(hard, 'gdo_passport_commit_uncertain', 'doctor_verification_passport_commit_reconciled', 'token_hash'),
    'Hardened passport issuance reconciles ambiguous COMMIT against exact durable passport truth'))
checks.append(require(2,
    has(claims, 'gdo_claim_commit_uncertain', 'doctor_professional_claim_commit_reconciled', "claim_status'=>'pending'") and 'event_id' in claims,
    'Professional claim issuance reconciles application claim state plus durable outbox event'))
checks.append(require(3,
    has(app, 'gdo_submit_commit_uncertain', 'doctor_application_submission_commit_reconciled', 'submission_hash') and "GDO_Audit::publish_transition( $result )" in app,
    'Immutable submission reconciles committed state/hash/outbox before post-commit publication'))
checks.append(require(4,
    has(admin, 'commit_or_reconcile', 'outbox_commit_verified', 'gdo_admin_commit_uncertain') and admin.count('commit_or_reconcile(') >= 8,
    'Admin/reviewer transaction family uses shared authoritative COMMIT reconciliation'))
checks.append(require(5,
    has(evidence, 'gdo_rotation_commit_uncertain', 'gdo_evidence_review_commit_uncertain', 'gdo_evidence_grant_commit_uncertain'),
    'Evidence key rotation, review and one-time grant consumption reconcile ambiguous COMMIT outcomes'))
checks.append(require(6,
    has(adv, 'gdo_upload_session_commit_uncertain', 'gdo_upload_chunk_commit_uncertain', 'gdo_upload_finalize_commit_uncertain'),
    'Resumable session/chunk/finalize transaction ambiguity remains authoritatively reconciled'))
checks.append(require(7,
    has(adv, 'human_final_decision_required', 'provider_unavailable', 'sanitize_provider_array') and 'reviewer-assistance adapters only' in adv,
    'Issuer/provider/AI/equivalency facts remain bounded and human-final'))
checks.append(require(8,
    'reconcile_authorized_missing_deletion' in evidence and privacy.count('reconcile_authorized_missing_deletion') >= 1 and 'doctor_privacy_native_deletion_commit_reconciled' in privacy,
    'Privacy erasure preserves DB truth after authorized physical deletion and supports missing-file recovery'))
checks.append(require(9,
    has(retention, 'commit_lifecycle_or_reconcile', 'reconcile_authorized_missing_deletion', 'doctor_retention_native_deletion_commit_reconciled', 'doctor_advanced_trust_retention_commit_reconciled'),
    'Retention reconciles lifecycle commits, physical deletions and Advanced Trust retirement'))
checks.append(require(10,
    has(migration, 'gdo_schema_future_version', 'atomic compare-and-swap', 'verify_installation') and 'Legacy credential commit outcome is uncertain' in migration,
    'Migration future-schema, lock CAS, physical postconditions and legacy retry safety remain intact'))
checks.append(require(11,
    has(hard, 'processing_lease_expired', 'gdo_continuous_verification_adverse_result', 'doctor_continuous_verification_provider_degraded') and "array( 'license','registration','professional_registration' )" in hard,
    'Continuous monitoring preserves leases, professional aliases and adverse/degraded semantics'))
checks.append(require(12,
    has(risk, 'gdo_risk_query_failed', 'false_positive', 'recent_step_up') and 'Risk-state uncertainty must narrow professional verification' in risk,
    'Risk/fraud uncertainty remains fail-closed and human-resolved'))
checks.append(require(13,
    has(adv, 'requires_dual_review', 'smart_reviewer_candidates', 'reviewer_calibration') and 'reviewer_scope_allows' in adv,
    'Conflict, adaptive dual review, routing and calibration remain authority-narrowing'))
checks.append(require(14,
    has(adv, 'gdo_public_transparency_minimum_cohort', "'suppressed'=>true", "$matrix['scope_status']['license']", "$matrix['scope_status']['registration']")
    and "if ( 'license' === $document_type )" in adv
    and "in_array( $document_type, array( 'registration','professional_registration' ), true )" in adv,
    'Public transparency remains cohort-suppressed and License/Registration scope truth remains separate'))
checks.append(require(15,
    has(membership, 'identity_assurance_current_checked', 'SA_Professional_Reauthentication', 'reviewer_case_allows', 'recent_step_up') and 'Read-only compatibility boundary to Files 00 and 02' in membership,
    'File00/File02 current authorization and private-boundary contracts remain guarded'))
checks.append(require(16,
    has(ops, 'gdo_reconcile_commit_uncertain', 'doctor_reconciliation_commit_reconciled', 'claim_outbox_id') and 'GDO_Audit::publish_transition( $result )' in ops,
    'Operational expiry reconciliation preserves committed state/claim/notice and post-commit publication'))
checks.append(require(17,
    "array( 'pending','failed' )" in claims and "claim_status IN ('pending','failed')" in notifications and 'gdo_claim_ack_failed' in notifications,
    'Professional claim delivery failure remains retryable without overwriting terminal acknowledgement'))
checks.append(require(18,
    has(privacy, 'items_retained', 'legal_hold=0', 'GDO_Advanced_Trust_Hardening::privacy_erase_application', 'reconcile_authorized_missing_deletion') and "'done'=>false" in privacy,
    'Applicant export/erasure remains failure-aware, legal-hold constrained and retryable'))
checks.append(require(19,
    lock.get('twentieth_review_baseline') == 'f6ffbc43edf2679590595f5bb1db7c3fec652d25'
    and lock.get('twentieth_review_rounds') == 20
    and 'python3 tests/twenty-round-audit-r20.py' in workflow
    and 'R19 | DEFECT' in ledger,
    'R20 permanent ledger/release-lock/workflow evidence is present after R19 correction'))
checks.append(require(20,
    lock.get('twentieth_defect_rounds') == 10
    and lock.get('twentieth_clean_rounds') == 10
    and lock.get('twentieth_pending_rounds', 0) == 0
    and lock.get('release_file_count') == 62
    and '| R20 | CLEAN |' in ledger
    and not (ROOT / '.github/workflows/file09-r20-corrective-apply.yml').exists()
    and not (ROOT / 'r20-apply.json').exists(),
    'Final R20 exact-head evidence is complete and temporary corrective plumbing is absent'))

passed = sum(checks)
failed = len(checks) - passed
print(f'File 09 R20 twenty-round audit: {passed} PASS / {failed} FAIL')
if failed:
    sys.exit(1)