#!/usr/bin/env python3
from pathlib import Path
import json
import sys

ROOT = Path(__file__).resolve().parents[1]

def read(path):
    return (ROOT / path).read_text(encoding="utf-8")

def has(src, *tokens):
    return all(token in src for token in tokens)

def check(round_no, ok, label):
    ok = bool(ok)
    print(f"R{round_no:02d} {'PASS' if ok else 'FAIL'} — {label}")
    return ok

integration = read("includes/class-gdo-integration-contracts.php")
plugin = read("includes/class-gdo-plugin.php")
membership = read("includes/class-gdo-membership-adapter.php")
application = read("includes/class-gdo-application.php")
frontend = read("includes/class-gdo-frontend.php")
evidence = read("includes/class-gdo-evidence.php")
admin = read("includes/class-gdo-admin.php")
claims = read("includes/class-gdo-claims.php")
notifications = read("includes/class-gdo-notifications.php")
state = read("includes/class-gdo-state.php")
privacy = read("includes/class-gdo-privacy.php")
retention = read("includes/class-gdo-retention.php")
advanced = read("includes/class-gdo-advanced-trust.php")
hardening = read("includes/class-gdo-advanced-trust-hardening.php")
migration = read("includes/class-gdo-migration.php")
operations = read("includes/class-gdo-operations.php")
cross = read("tests/cross-file-contracts.php")
workflow = read(".github/workflows/file09-rc2-final.yml")
status = read("STATUS.md")
trace = read("TRACEABILITY.md")
manifest = read("RELEASE-MANIFEST-1.3.0.md")
ledger = read("REVIEW-20-ROUNDS-RC6-R24.md")
lock = json.loads(read("RELEASE-LOCK.json"))
release_files = [x for x in read("RELEASE-FILES.txt").splitlines() if x.strip()]

results = []

results.append(check(1,
    has(integration, "'canonical_entity'       => 'doctor_verification'", "'canonical_mutations'    => 'owner_commands_only'", "'direct_table_meta_write'=> false"),
    "File 09 canonical ownership remains single-owner and companion writes remain prohibited"))

results.append(check(2,
    has(membership, "identity_assurance_current_checked", "recent_step_up", "reviewer_case_allows"),
    "File 00 identity and File 02 recent privileged reauthentication remain current and fail closed"))

results.append(check(3,
    has(state, "'draft'", "'submitted'", "'under_review'", "'more_information'", "'verified'", "'renewal_due'", "'suspended'", "'revoked'", "'appeal_pending'", "row_version=row_version+1"),
    "Application, verification and appeal state machines remain explicit and concurrency guarded"))

results.append(check(4,
    has(frontend, "gdo_start_application", "gdo_save_application", "gdo_submit_application", "gdo_file_appeal", "gdo_withdraw_application")
    and has(application, "completeness", "submission_hash", "save_draft"),
    "Applicant eligibility/wizard/draft/submission/more-info/appeal/withdrawal workflow remains implemented"))

results.append(check(5,
    has(evidence, "stage_upload", "issue_view_grant", "malware", "encrypt", "deletion_pending"),
    "Professional evidence remains validated, encrypted, private, reviewable and deletion-safe"))

results.append(check(6,
    has(admin, "assigned_reviewer_id", "recommender_id", "finalizer_id", "resolve_appeal", "reviewer_case_allows")
    and has(hardening, "reviewer_conflict", "dual_review"),
    "Reviewer least privilege, separation, conflict, dual review and independent appeal remain enforced"))

results.append(check(7,
    has(claims, "claim_version", "acknowledge", "issue", "publish")
    and has(notifications, "sun.event.v1", "dead"),
    "Decision claim and File 19 durable notification boundary remain explicit and retryable"))

results.append(check(8,
    has(state, "'renewal_due'", "'expired'", "'suspended'", "'revoked'", "'reinstated'")
    and "revoke_passports_for_application" in hardening,
    "Renewal/expiry/suspension/revocation/reinstatement and passport invalidation remain explicit"))

results.append(check(9,
    all(f"F09-AT-{i:02d}" in trace for i in range(1,25))
    and has(advanced, "trusted_issuers", "jurisdiction_rules", "verification_passports", "professional_history", "upload_sessions", "equivalency", "affiliation", "translation", "fraud", "calibration")
    and has(hardening, "continuous_monitor", "dual_review", "fraud"),
    "All 24 Advanced Trust requirements remain traceable to implemented owner-side capabilities"))

results.append(check(10,
    has(privacy, "export", "eras", "legal_hold")
    and has(retention, "legal_hold", "retention_pending")
    and has(hardening, "privacy_erase_application", "erasure_pending"),
    "Privacy export/erasure/legal-hold/retention remains native and Advanced-Trust aware"))

results.append(check(11,
    has(integration, "file03_public_fields", "file03_public_projection", "file03_verifiable_credentials", "'raw_evidence_exposed' => false")
    and "$profile['phone']" not in integration
    and "$profile['whatsapp']" not in integration,
    "File 03 public professional projection remains allowlisted with raw/private evidence excluded"))

results.append(check(12,
    has(integration, "gdo_file07_directory_eligibility", "'file07' => self::FILE07", "'donor_rank_advantage'    => false")
    and "directory_rank" not in integration.lower(),
    "File 07 receives verification eligibility only; directory ranking remains outside File 09"))

results.append(check(13,
    has(integration, "gdo_file08_clinic_eligibility", "'file08' => self::FILE08", "'clinical_authorization'  => false")
    and "appointment_write" not in integration.lower(),
    "File 08 receives verification eligibility only; clinic/appointment/clinical truth remains external"))

results.append(check(14,
    has(integration,
        "const FILE14 = 'gdo.file14.onboarding-destination';",
        "sabri_file09_onboarding_destination_v1",
        "gdo_file14_onboarding_destination",
        "DoctorOnboardingAvailable.v1",
        "'automatic_enrollment'   => false",
        "'automatic_verification' => false",
        "'writes_data'            => false")
    and has(cross, "File14 healthy onboarding destination contract failed", "safe_mode", "application_route_unavailable"),
    "File 14 now has a stable, read-only, fail-closed onboarding destination/readiness contract"))

results.append(check(15,
    has(integration, "private static function public_date", "self::public_date( $decision['verified_until'] ?? '' )")
    and has(cross, "'2030-12-31'!==$p['verified_until']", "public verification validity must be canonical YYYY-MM-DD"),
    "Current File 25 validity-shape parity is enforced at the public File 09 contract edge"))

results.append(check(16,
    has(integration, "gdo_file21_publishing_eligibility", "gdo_file23_dashboard_eligibility")
    and "publish_post" not in integration.lower()
    and "dashboard_write" not in integration.lower(),
    "Files 21/23 receive eligibility only; publishing/dashboard ownership is not duplicated"))

results.append(check(17,
    has(integration, "gdo.file19.notification-event", "sabri_shell_page_contracts", "FILE26_CONNECTOR", "'private_indexing' => false")
    and has(operations, "safe_mode", "health"),
    "Files 19/20/24/26 boundaries remain notification/shell/assurance/search-owner safe"))

results.append(check(18,
    "cf04" not in integration.lower()
    and "cf-04" not in plugin.lower()
    and has(migration, "GDO_SCHEMA_VERSION", "gdo_schema_migration_lock", "ROLLBACK")
    and has(operations, "required_schedules_ready", "mutation_allowed"),
    "CF-04 remains conditional while migration, schedules, Safe Mode and runtime readiness remain explicit"))

results.append(check(19,
    lock.get("twenty_fourth_review_baseline") == "a9ab697c671129be023414f5a3c32186567cb2bf"
    and lock.get("twenty_fourth_review_rounds") == 20
    and lock.get("twenty_fourth_defect_rounds") == 3
    and lock.get("twenty_fourth_clean_rounds") == 17
    and lock.get("twenty_fourth_pending_rounds") == 0
    and lock.get("twenty_fourth_postfix_reviews") == 2
    and lock.get("twenty_fourth_postfix_clean_reviews") == 2
    and lock.get("twenty_fourth_postfix_product_defects") == 0
    and "| R14 | DEFECT |" in ledger and "| R15 | DEFECT |" in ledger and "| R19 | DEFECT |" in ledger
    and "Post-fix Review 1" in ledger and "Post-fix Review 2" in ledger
    and "R24" in status and "R24" in trace and "R24" in manifest,
    "R24 release-lock/status/traceability/manifest and two clean post-fix reviews are synchronized"))

results.append(check(20,
    "python3 tests/twenty-round-audit-r24.py" in workflow
    and "php tests/cross-file-contracts.php" in workflow
    and len(release_files) == 62
    and "REVIEW-20-ROUNDS-RC6-R24.md" not in release_files
    and "tests/twenty-round-audit-r24.py" not in release_files
    and lock.get("staging_accepted") is False
    and lock.get("live_deployed") is False
    and lock.get("operationally_accepted") is False,
    "R24 exact-head CI is authoritative, installable allowlist stays 62, and external acceptance is not overclaimed"))

passed = sum(results)
failed = len(results) - passed
print(f"File 09 R24 current-plan/current-companion twenty-round audit: {passed} PASS / {failed} FAIL")
if failed:
    sys.exit(1)
