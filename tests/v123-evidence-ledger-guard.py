#!/usr/bin/env python3
"""V12.3 P00/P01 evidence-ledger consistency, not staging acceptance.

Each declared requirement and external acceptance gate must have a distinct
source-level trace row. Live/Staging cannot be inferred from code or CI.
"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
matrix = (ROOT / "docs/FILE09-V123-P00-P01-REQUIREMENT-EVIDENCE-MATRIX-2026-11-14.md").read_text(encoding="utf-8")
trace = (ROOT / "TRACEABILITY.md").read_text(encoding="utf-8")
staging = (ROOT / "STAGING-ACCEPTANCE.md").read_text(encoding="utf-8")
release = (ROOT / "RELEASE-LOCK.json").read_text(encoding="utf-8")
receipts = (ROOT / "docs/FILE09-V123-P11-STAGING-EVIDENCE-RECEIPTS-2026-11-14.md").read_text(encoding="utf-8")

counts = {"FR": 17, "NFR": 10, "CEN": 2, "AT": 24}
source = set(re.findall(r"^\|\s*(F09-(?:FR|NFR|CEN|AT)-\d{2,3})\s*\|", trace, re.M))
mapped = re.findall(r"^\|\s*(F09-(?:FR|NFR|CEN|AT)-\d{2,3})\s*\|", matrix, re.M)
assert len(mapped) == 53 and len(set(mapped)) == 53, "source ledger must map each of 53 IDs exactly once"
assert set(mapped) == source, "ledger IDs diverge from canonical TRACEABILITY IDs"
for group, n in counts.items():
    assert sum(id.startswith("F09-"+group+"-") for id in mapped) == n, group

staging_ids = set(map(int, re.findall(r"^(\d+)\.\s+\*\*", staging, re.M)))
matrix_ids = list(map(int, re.findall(r"^\|\s*S09-(\d{2})\s*\|", matrix, re.M)))
assert staging_ids == set(range(1, 33)), f"staging contract gate identity changed: {staging_ids}"
assert matrix_ids == list(range(1, 33)), f"ledger requires exactly 32 ordered stage gates: {matrix_ids}"
receipt_ids = list(map(int, re.findall(r"^\\|\\s*S09-(\\d{2})\\s*\\|", receipts, re.M)))
assert receipt_ids == list(range(1, 33)), "stage evidence receipts are missing/duplicated or out of order"
assert all("NOT EXECUTED" in line for line in receipts.splitlines() if re.match(r"^\\|\\s*S09-\\d{2}\\s*\\|", line)), "receipt cannot preclaim acceptance"
assert "NOT EXECUTED / NOT ACCEPTED" in staging, "staging status changed: re-audit required"
assert all("NOT EXECUTED" in line for line in matrix.splitlines() if re.match(r"^\|\s*S09-\d{2}\s*\|", line)), "do not assert unperformed stage checks as PASS"
assert '"staging_accepted": false' in release, "release lock has changed: independently verify acceptance evidence"
assert '"live_deployed": false' in release, "release lock changed: independently verify deployed package"
assert '"operationally_accepted": false' in release, "release lock changed: independently verify operations"
assert "Exact deployed code ابھی unverified ہے" in matrix
print("File09 V12.3 P00/P01 ledger guard: 53 IDs + 32 gates + 3 release truths PASS")
print("No inference of Staging, Live, operational or requirement acceptance")
