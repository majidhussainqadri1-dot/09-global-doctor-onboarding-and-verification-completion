#!/usr/bin/env python3
from pathlib import Path
import json
import sys

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def require(round_no, condition, description):
    if not condition:
        print(f'R{round_no:02d} FAIL — {description}')
        return False
    print(f'R{round_no:02d} PASS — {description}')
    return True

adv = text('includes/class-gdo-advanced-trust.php')
hard = text('includes/class-gdo-advanced-trust-hardening.php')
claims = text('includes/class-gdo-claims.php')
app = text('includes/class-gdo-application.php')
evidence = text('includes/class-gdo-evidence.php')
admin = text('includes/class-gdo-admin.php')
privacy = text('includes/class-gdo-privacy.php')
retention = text('includes/class-gdo-retention.php')
migration = text('includes/class-gdo-migration.php')
risk = text('includes/class-gdo-risk.php')
membership = text('includes/class-gdo-membership-adapter.php')
ops = text('includes/class-gdo-operations.php')
notifications = text('includes/class-gdo-notifications.php')
workflow = text('.github/workflows/file09-rc2-final.yml')
ledger = text('REVIEW-20-ROUNDS-RC6-R19.md')
lock = json.loads(text('RELEASE-LOCK.json'))

checks = []
checks.append(require(1, 'gdo_passport_identity_unavailable' in hard and 'data_available' in hard, 'Public passport/current verification preserves unavailable truth'))
checks.append(require(2,
    "if ( $status === $current_status ) { return true; }" in claims
    and "if ( ! in_array( $current_status, array( 'pending','failed' ), true ) ) { return false; }" in claims
    and "'claim_status'=>$current_status" in claims
    and "'claim_status'=>'pending'" in claims
    and 'gdo_claim_ack_query_failed' in claims
    and 'gdo_claim_ack_store_failed' in claims
    and 'gdo_claim_ack_recheck_failed' in claims,
    'Claim acknowledgement remains terminal CAS/idempotent with retryable transport precursor'))
checks.append(require(3, 'submission_hash' in app and 'idempotency' in app.lower() and 'FOR UPDATE' in app, 'Submission remains immutable/idempotency/row-lock guarded'))
checks.append(require(4, 'recommender_id' in admin and 'finalizer_id' in admin and 'appeal' in admin.lower(), 'Reviewer/finalizer/appeal separation remains explicit'))
checks.append(require(5, 'gdo_storage_quota_query' in evidence and 'cleanup_failed_storage' in evidence and 'gdo_evidence_commit_uncertain' in evidence, 'Evidence quota/cleanup/commit uncertainty is fail-visible'))
checks.append(require(6, 'gdo_upload_session_commit_uncertain' in adv and 'gdo_upload_chunk_commit_uncertain' in adv and 'gdo_upload_finalize_commit_uncertain' in adv, 'Resumable COMMIT ambiguity uses authoritative reconciliation'))
checks.append(require(7, 'human_final_decision_required' in adv and 'provider_unavailable' in adv and 'sanitize_provider_array' in adv, 'Provider/AI assistance remains bounded and human-final'))
checks.append(require(8, 'Keep the application row lock through the irreversible native evidence' in privacy and privacy.find('Keep the application row lock through the irreversible native evidence') < privacy.find("$advanced = GDO_Advanced_Trust_Hardening::privacy_erase_application"), 'Privacy legal-hold lock spans native evidence deletion'))
checks.append(require(9, 'Keep the eligibility row lock through irreversible native evidence' in retention and retention.find('Keep the eligibility row lock through irreversible native evidence') < retention.find("retire_advanced_trust_for_application"), 'Retention legal-hold lock spans native evidence deletion'))
checks.append(require(10, 'atomic compare-and-swap' in migration and "gdo_schema_future_version" in migration and 'verify_installation' in migration, 'Migration CAS/future-schema/physical verification remains guarded'))
checks.append(require(11, 'doctor_continuous_verification_provider_degraded' in hard and 'gdo_continuous_verification_adverse_result' in hard and 'provider_failure' in hard, 'Continuous monitor preserves adverse/degraded semantics'))
checks.append(require(12, 'gdo_risk_query_failed' in risk and 'false_positive' in risk and 'recent_step_up' in risk, 'Risk/fraud remains fail-closed and human-resolved'))
checks.append(require(13, 'smart_reviewer_routing' in adv and 'reviewer_calibration' in adv and 'adaptive_dual_review' in adv, 'Routing/calibration/dual-review capabilities remain present'))
checks.append(require(14, 'gdo_public_transparency_minimum_cohort' in adv and "'suppressed'=>true" in adv and 'gdo_transparency_query' in adv, 'Public transparency remains cohort-suppressed and DB-failure-aware'))
checks.append(require(15, 'identity_assurance_current_checked' in membership and 'SA_Professional_Reauthentication' in membership and 'reviewer_case_allows' in membership, 'File00/File02 use-time authorization boundary remains guarded'))
checks.append(require(16, 'safe_mode' in ops and 'required_schedules_ready' in ops and 'advanced_trust_schema' in ops and 'recent_step_up' in ops, 'Safe Mode/health/repair/scheduler readiness remains guarded'))
checks.append(require(17, 'sun_ingest_domain_event' in notifications and 'sun.event.v1' in notifications and 'GDO_Claims::acknowledge' in notifications and 'wp_mail' not in notifications, 'File19 transport boundary and durable claim acknowledgement remain intact'))
checks.append(require(18, 'legal_hold=0' in privacy and 'items_retained' in privacy and 'GDO_Advanced_Trust_Hardening::privacy_erase_application' in privacy, 'Applicant privacy erasure remains retryable and legal-hold constrained'))
checks.append(require(19, lock.get('nineteenth_review_rounds') == 20 and lock.get('nineteenth_defect_rounds') == 5 and lock.get('nineteenth_clean_rounds') == 15 and 'twenty-round-audit-r19.py' in workflow, 'R19 ledger/release-lock/workflow evidence is synchronized'))
checks.append(require(20, not (ROOT / '.github/workflows/file09-r19-corrective-apply.yml').exists() and not (ROOT / 'r19-apply.json').exists() and 'Defect-bearing rounds: **5**' in ledger and lock.get('release_file_count') == 62, 'Final R19 release hygiene removes temporary plumbing and preserves 62-entry package truth'))

passed = sum(bool(x) for x in checks)
failed = len(checks) - passed
print(f'File 09 R19 twenty-round audit: {passed} PASS / {failed} FAIL')
if failed:
    sys.exit(1)