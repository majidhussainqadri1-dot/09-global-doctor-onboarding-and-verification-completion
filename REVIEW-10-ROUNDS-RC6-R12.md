# File 09 — RC6 R12 Fresh Ten-Round Corrective Review

## Governing truth

- Review series: **R12**
- Frozen baseline: `0df40731282a4dc905a12b37e5fa5aa4604dc68a`
- Source-correction head produced during this review: `bdc23c188d7976aae85a8024697b36543da8eb0e`
- Runtime: **1.3.0 RC6**
- Core schema: **6**
- Advanced Trust schema: **2**
- Review count: **10**
- Defect-bearing rounds: **10**
- Clean rounds: **0**
- Post-correction executable target: **10 PASS / 0 FAIL**
- Staging accepted: **false**
- Live deployed: **false**
- Operational: **false**

This R12 review does not reuse R1–R11 counts. Each round reviewed the corrected state produced by the preceding round. The repository source, Hostinger staging and live system remain separate truths.

## Round-by-round ledger

| Round | Finding | Immediate root-cause correction |
|---|---|---|
| **R01** | Reviewer/finalizer paths could still consume credential rows or an immutable professional profile snapshot without a checked fail-closed read. | Reviewer evidence now uses `GDO_Evidence::records_checked()`; finalization rejects unreadable profile JSON and aborts if the immutable credential snapshot cannot be read safely. |
| **R02** | A public professional passport could become eligible from verified application state before downstream professional-claim acknowledgement had explicitly reached the current claim version. | Added `gdo_professional_claim_acknowledged`; accepted current-version acknowledgement triggers passport issuance, and issue/verify paths require `claim_status=accepted`. |
| **R03** | Immutable application submission still used an unchecked evidence inventory, so DB uncertainty could be mistaken for an empty/valid snapshot. | Submission now uses `records_checked()` and rolls back with `gdo_submit_evidence_query` on uncertainty. |
| **R04** | Applicant form rendering could continue after completeness/evidence DB uncertainty and repeatedly re-query current evidence during rendering. | Form rendering fails closed on completeness/evidence uncertainty and builds one checked evidence inventory/index for the request. |
| **R05** | Draft save could inherit DB error state on the ownership pre-read or continue after a failed post-write application reload. | Pre-read now isolates DB error state; post-save reload is mandatory before evidence upload processing continues. |
| **R06** | Core migration could report success without durable `gdo_last_migration` evidence, and several backfill/quarantine reads did not independently isolate DB error state. | Migration-evidence persistence is verified; application/user/document batch reads and post-insert reloads now fail closed on DB uncertainty. |
| **R07** | Legacy credential migration silently returned on encryption/storage/transaction/hash failures, could delete evidence storage after an ambiguous COMMIT result, and could advance despite failed legacy-source cleanup. | Failure paths now throw and stop checkpoint advancement; ambiguous COMMIT preserves both sides for reconciliation; hash verification is explicit; source-cleanup failure is fail-visible. |
| **R08** | Notification outbox processing could inherit stale DB error state, and failure of an older claim-delivery event could overwrite failure status on a newer professional claim version. | Outbox reads isolate DB state; failed claim status is version-bound and stale failures are audited rather than clobbering newer claim state. |
| **R09** | Retention still used an unchecked evidence inventory, while orphan-file deletion failure could be audited yet return overall success. | Retention uses checked evidence inventory; orphan cleanup accumulates deletion failures and returns false when cleanup is incomplete. |
| **R10** | The R12 source corrections were not yet represented by a permanent executable gate/review ledger/release lock, the authoritative workflow did not run R12, and the temporary apply workflow had to remain absent. | Added `tests/ten-round-audit-r12.py`, this permanent ledger, R12 release-lock fields, and authoritative workflow wiring; temporary corrective workflow remains removed. |

## Executable acceptance

`tests/ten-round-audit-r12.py` is the permanent R12 regression gate. It must report:

`File 09 R12 ten-round audit: 10 PASS / 0 FAIL`

The authoritative `.github/workflows/file09-rc2-final.yml` must run this gate on both PHP matrix jobs together with all prior R1–R11 gates and then rebuild the deterministic RC6 package from the exact final source HEAD.

## Release boundary

The R12 ledger/test are repository QA evidence and are intentionally not added to the installable 62-entry plugin allowlist. The installable package therefore remains a **62-entry RC6 package**, while its bytes/checksum must be regenerated because R12 changed package-owned PHP source. Any artifact from `0df407...` is historical after these corrections.

## External acceptance remains pending

No repository review can substitute for Hostinger staging. Core schema 6, Advanced Trust schema 2, migration state, File 00/File 02 contracts, real applicant/reviewer/more-info/resubmission/appeal/renewal/suspension/revocation journeys, provider outage/mismatch/revocation paths, private evidence/storage/key/scanner behavior, privacy export/erasure/retention, backup/restore, rollback, RTL/accessibility and low-bandwidth behavior remain external gates.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
