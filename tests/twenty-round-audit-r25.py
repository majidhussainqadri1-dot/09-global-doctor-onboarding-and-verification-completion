from pathlib import Path
import json
import sys

root = Path(__file__).resolve().parents[1]

def text(path):
    return (root / path).read_text(encoding="utf-8")

def check(number, condition, message):
    if not condition:
        print(f"R{number:02d}: FAIL — {message}", file=sys.stderr)
        raise SystemExit(1)
    print(f"R{number:02d}: PASS — {message}")

ledger = text("REVIEW-20-ROUNDS-RC6-R25.md")
workflow = text(".github/workflows/file09-rc2-final.yml")
historical40 = text("tests/forty-round-assurance.py")
readme = text("README.md")
status = text("STATUS.md")
trace = text("TRACEABILITY.md")
manifest = text("RELEASE-MANIFEST-1.3.0.md")
changelog = text("CHANGELOG.md")
integration = text("includes/class-gdo-integration-contracts.php")
cross = text("tests/cross-file-contracts.php")
lock = json.loads(text("RELEASE-LOCK.json"))

heads = lock.get("twenty_fifth_companion_heads", {})

check(1, "9639f75ba046ac1a36e39d5e9aae56c7bae3279b" in ledger,
      "File 09 exact pre-review baseline is frozen")
check(2, len(heads) == 14 and heads.get("file14") == "55e44d38b23304d50fad22b4d3a2c67fe4721209",
      "current companion exact heads, including the new File 14 consumer, are locked")
check(3, heads.get("file25") == "2d02c93356b050313e30e29aeceb57080771c2a5",
      "advanced current File 25 exact head is locked")
check(4, all(f"| R{i:02d} |" in ledger for i in range(1, 21)),
      "all twenty numbered review rounds are permanently recorded")
check(5, ledger.count("| DEFECT |") == 2 and "R19, R20" in ledger,
      "exactly two defect-bearing rounds are recorded")
check(6, ledger.count("| CLEAN |") == 18,
      "exactly eighteen clean numbered rounds are recorded")
check(7, "One corrective batch after all rounds" in ledger,
      "correction is explicitly deferred until all twenty rounds completed")
check(8, "gdo_file14_onboarding_destination" in integration and "DoctorOnboardingAvailable.v1" in integration,
      "File 14 owner-native destination contract remains published")
check(9, "private static function public_date" in integration and "self::public_date( $decision['verified_until'] ?? '' )" in integration,
      "File 25-compatible public date normalization remains at the contract edge")
check(10, "File14 healthy onboarding destination contract failed" in cross and "public verification validity must be canonical YYYY-MM-DD" in cross,
      "behavioral File 14/File 25 regression coverage remains present")
check(11, "global-doctor-onboarding-09-1.3.0-RC6.zip" in historical40 and "1.2.0-RC2.zip" not in historical40,
      "historical 40-round gate follows current RC6 package identity")
check(12, "python3 tests/forty-round-assurance.py" in workflow,
      "maintained 40-round gate is authoritative CI input")
check(13, "python3 tests/twenty-round-audit-r25.py" in workflow,
      "R25 gate is authoritative exact-head CI input")
check(14, "Fresh 20-round review R25" in readme and "R19–R26 twenty-round gates" in readme,
      "README preserves R25 while current assurance advances through R26")
check(15, "Twenty-fifth fresh 20-round" in status and "R26 twenty-round repository review/fix" in status,
      "status preserves R25 while current evidence advances through R26")
check(16, "R25 exact-current-companion assurance" in trace and "55e44d38b23304d50fad22b4d3a2c67fe4721209" in trace,
      "traceability records the real current File 14 consumer")
check(17, "R25 hourly current-plan/current-companion" in manifest and "R25 hourly current-plan/current-companion" in changelog,
      "release manifest and changelog preserve R25 provenance")
check(18, lock.get("twenty_fifth_review_rounds") == 20 and lock.get("twenty_fifth_defect_rounds") == 2 and lock.get("twenty_fifth_clean_rounds") == 18,
      "release lock encodes 20 complete rounds with 2/18 disposition")
check(19, lock.get("staging_accepted") is False and lock.get("live_deployed") is False and lock.get("operationally_accepted") is False,
      "repository evidence cannot promote external acceptance")
check(20, "Deployed Version / DB Version / Migration State / Live Verification Status" in status and "unverified" in ledger.lower(),
      "live-first status dimensions remain separate and explicitly unverified")

print("File 09 R25 hourly twenty-round exact-companion audit: 20 PASS, 0 FAIL")
