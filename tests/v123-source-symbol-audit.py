#!/usr/bin/env python3
"""File 09 V12.3 regression guard: primary function entrypoint exists for 53 IDs.

This static smoke test is *not* behavioral, security, installed WordPress, staging,
or live acceptance. It never marks a capability as production ready.
"""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
PRIMARY_ENTRYPOINTS = {
    "F09-FR-001": ("includes/class-gdo-policy.php", "eligibility"),
    "F09-FR-002": ("includes/class-gdo-frontend.php", "form"),
    "F09-FR-003": ("includes/class-gdo-application.php", "ensure_draft"),
    "F09-FR-004": ("includes/class-gdo-evidence.php", "stage_upload"),
    "F09-FR-005": ("includes/class-gdo-application.php", "completeness"),
    "F09-FR-006": ("includes/class-gdo-application.php", "submit"),
    "F09-FR-007": ("includes/class-gdo-admin.php", "assign"),
    "F09-FR-008": ("includes/class-gdo-evidence.php", "issue_view_grant"),
    "F09-FR-009": ("includes/class-gdo-admin.php", "request_more_info"),
    "F09-FR-010": ("includes/class-gdo-admin.php", "finalize"),
    "F09-FR-011": ("includes/class-gdo-claims.php", "issue"),
    "F09-FR-012": ("includes/class-gdo-advanced-trust.php", "schedule_reverification"),
    "F09-FR-013": ("includes/class-gdo-admin.php", "change_state"),
    "F09-FR-014": ("includes/class-gdo-admin.php", "assign_appeal"),
    "F09-FR-015": ("includes/class-gdo-risk.php", "evaluate"),
    "F09-FR-016": ("includes/class-gdo-privacy.php", "export"),
    "F09-FR-017": ("includes/class-gdo-quality.php", "create_sample"),
    "F09-NFR-001": ("includes/class-gdo-membership-adapter.php", "can"),
    "F09-NFR-002": ("includes/class-gdo-privacy.php", "erase"),
    "F09-NFR-003": ("includes/class-gdo-operations.php", "reconcile"),
    "F09-NFR-004": ("includes/class-gdo-schema.php", "verify_installation"),
    "F09-NFR-005": ("includes/class-gdo-frontend.php", "form"),
    "F09-NFR-006": ("includes/class-gdo-operations.php", "health"),
    "F09-NFR-007": ("includes/class-gdo-migration.php", "maybe_run"),
    "F09-NFR-008": ("includes/class-gdo-operations.php", "repair"),
    "F09-NFR-009": ("includes/class-gdo-plugin.php", "run"),
    "F09-NFR-010": ("includes/class-gdo-policy.php", "normalize_date"),
    "F09-CEN-01": ("includes/class-gdo-integration-contracts.php", "projection"),
    "F09-CEN-02": ("includes/class-gdo-integration-contracts.php", "register"),
    "F09-AT-01": ("includes/class-gdo-advanced-trust.php", "primary_source_verify"),
    "F09-AT-02": ("includes/class-gdo-advanced-trust.php", "register_issuer"),
    "F09-AT-03": ("includes/class-gdo-advanced-trust.php", "authenticity_assessment"),
    "F09-AT-04": ("includes/class-gdo-advanced-trust.php", "save_jurisdiction_rule"),
    "F09-AT-05": ("includes/class-gdo-advanced-trust.php", "equivalency_assessment"),
    "F09-AT-06": ("includes/class-gdo-advanced-trust-hardening.php", "continuous_monitor"),
    "F09-AT-07": ("includes/class-gdo-advanced-trust.php", "event_reverification"),
    "F09-AT-08": ("includes/class-gdo-advanced-trust-hardening.php", "issue_passport"),
    "F09-AT-09": ("includes/class-gdo-advanced-trust-hardening.php", "verify_passport_uuid"),
    "F09-AT-10": ("includes/class-gdo-advanced-trust.php", "verification_matrix"),
    "F09-AT-11": ("includes/class-gdo-advanced-trust.php", "verify_affiliation"),
    "F09-AT-12": ("includes/class-gdo-advanced-trust.php", "public_history"),
    "F09-AT-13": ("includes/class-gdo-advanced-trust.php", "translation_assistance"),
    "F09-AT-14": ("includes/class-gdo-advanced-trust.php", "ai_assistance"),
    "F09-AT-15": ("includes/class-gdo-advanced-trust.php", "risk_explanation"),
    "F09-AT-16": ("includes/class-gdo-advanced-trust.php", "fraud_ring_scan"),
    "F09-AT-17": ("includes/class-gdo-advanced-trust.php", "declare_conflict"),
    "F09-AT-18": ("includes/class-gdo-advanced-trust.php", "requires_dual_review"),
    "F09-AT-19": ("includes/class-gdo-advanced-trust.php", "smart_reviewer_candidates"),
    "F09-AT-20": ("includes/class-gdo-advanced-trust.php", "reviewer_calibration"),
    "F09-AT-21": ("includes/class-gdo-advanced-trust.php", "command_center"),
    "F09-AT-22": ("includes/class-gdo-advanced-trust.php", "create_upload_session"),
    "F09-AT-23": ("includes/class-gdo-advanced-trust.php", "issue_viewing_room_grant"),
    "F09-AT-24": ("includes/class-gdo-advanced-trust.php", "public_transparency_snapshot"),
}

EXPECTED_COUNTS = {"FR": 17, "NFR": 10, "CEN": 2, "AT": 24}


def main() -> int:
    groups = dict.fromkeys(EXPECTED_COUNTS, 0)
    errors = []
    for requirement_id, (path, symbol) in sorted(PRIMARY_ENTRYPOINTS.items()):
        match = re.fullmatch(r"F09-(FR|NFR|CEN|AT)-\d{2,3}", requirement_id)
        if not match:
            errors.append(f"{requirement_id}: bad requirement ID")
            continue
        groups[match.group(1)] += 1
        file_path = ROOT / path
        if not file_path.is_file():
            errors.append(f"{requirement_id}: missing source file {path}")
            continue
        content = file_path.read_text(encoding="utf-8")
        signature = rf"\bfunction\s+{re.escape(symbol)}\s*\("
        if re.search(signature, content) is None:
            errors.append(f"{requirement_id}: missing method {symbol} in {path}")
    if groups != EXPECTED_COUNTS or len(PRIMARY_ENTRYPOINTS) != 53:
        errors.append(f"wrong ID distribution: {groups}")
    for line in errors:
        print(f"FAIL: {line}", file=sys.stderr)
    if errors:
        print(f"File09 V12.3 source-symbol guard: {len(errors)} FAIL", file=sys.stderr)
        return 1
    print("File09 V12.3 source-symbol guard: 53 PASS / 0 FAIL")
    print("WARNING: source symbols only; 0 installed/staging/live acceptances inferred")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
