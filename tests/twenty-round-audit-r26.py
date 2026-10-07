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

ledger = text("REVIEW-20-ROUNDS-RC6-R26.md")
workflow = text(".github/workflows/file09-rc2-final.yml")
readme = text("README.md")
status = text("STATUS.md")
trace = text("TRACEABILITY.md")
manifest = text("RELEASE-MANIFEST-1.3.0.md")
changelog = text("CHANGELOG.md")
integration = text("includes/class-gdo-integration-contracts.php")
lock = json.loads(text("RELEASE-LOCK.json"))
heads = lock.get("twenty_sixth_companion_heads", {})

check(1, "448d41f34586369ca5875693583b9cd8a6133167" in ledger,
      "File 09 exact R26 pre-review baseline is frozen")
check(2, len(heads) == 14,
      "all fourteen relevant companion exact heads are release-locked")
check(3, heads.get("file07") == "2f4a89707724fd2b9946600afe10ddab27ec3c2d",
      "advanced current File 07 exact head is locked")
check(4, heads.get("file14") == "f64e7d17268daff4e3097c18ad510116e6eaf105",
      "advanced current File 14 exact head is locked")
check(5, heads.get("file25") == "e35563b7f3d8ebf0acbbc80982b7bcf2e1b78c0a",
      "advanced current File 25 exact head is locked")
check(6, all(f"| R{i:02d} |" in ledger for i in range(1, 21)),
      "all twenty numbered review rounds are permanently recorded")
check(7, ledger.count("| DEFECT |") == 1 and "1 — R20" in ledger,
      "exactly one defect-bearing round is recorded")
check(8, ledger.count("| CLEAN |") == 19,
      "exactly nineteen clean numbered rounds are recorded")
check(9, "Single corrective batch after all rounds" in ledger,
      "correction is explicitly deferred until all twenty rounds completed")
check(10, "const FILE07 = 'gdo.file07.directory-eligibility'" in integration,
      "File 07 public verification owner contract remains published")
check(11, "const FILE14 = 'gdo.file14.onboarding-destination'" in integration,
      "File 14 onboarding destination owner contract remains published")
check(12, "private static function public_date" in integration,
      "File 25-compatible public validity normalization remains present")
check(13, "python3 tests/twenty-round-audit-r26.py" in workflow,
      "R26 gate is authoritative exact-head CI input")
check(14, "Fresh 20-round review R26" in readme and "R19–R26 twenty-round gates" in readme,
      "README current assurance reaches R26")
check(15, "Twenty-sixth fresh 20-round" in status and "R26 twenty-round repository review/fix" in status,
      "current status reaches R26")
check(16, "R26 exact-current-companion reconciliation" in trace,
      "traceability records R26 current-companion reconciliation")
check(17, "R26 hourly current-plan/current-companion" in manifest and "R26 hourly current-plan/current-companion" in changelog,
      "manifest and changelog preserve R26 provenance")
check(18, lock.get("twenty_sixth_review_rounds") == 20 and lock.get("twenty_sixth_defect_rounds") == 1 and lock.get("twenty_sixth_clean_rounds") == 19,
      "release lock encodes the R26 20/1/19 disposition")
check(19, lock.get("staging_accepted") is False and lock.get("live_deployed") is False and lock.get("operationally_accepted") is False,
      "repository evidence cannot promote external acceptance")
check(20, "Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status" in status and "unverified" in ledger.lower(),
      "live-first status dimensions remain separate and unverified")

print("File 09 R26 hourly twenty-round exact-companion audit: 20 PASS, 0 FAIL")
