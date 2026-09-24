# File 09 — RC6 R17 Fresh Ten-Round Corrective Assurance

Frozen baseline: `66e43bcb42904a381728509144cd0a1e6c77c6ac`  
Review model: **sequential Review → Fix → Review**. A later numbered round did not begin until every established defect from the preceding round had been corrected in repository source.

## Result ledger

| Round | Result | Review / correction |
|---|---|---|
| R01 | DEFECT → FIXED | The reviewer `request_more_info()` command could collapse an application-table DB read failure into an ordinary invalid/unauthorized 400. It now clears/checks DB error state and fails visibly with a bounded 503 before any information-request mutation. |
| R02 | CLEAN | Private credential key rotation, old-file recovery and bounded retention orphan cleanup remained failure-visible; no new repository-level defect was established. |
| R03 | DEFECT → FIXED | External primary-source and reviewer-assistance hooks could throw `Throwable` and escape as fatal requests. Primary-source request/verification, equivalency, affiliation, translation and AI assistance now contain provider exceptions and map them to bounded degraded/manual-review states without persisting raw exception messages or stacks. |
| R04 | CLEAN | Reviewer routing, current authority, workload/jurisdiction/language scope, recommender/finalizer separation, conflict handling and independent appeal assignment remained guarded. |
| R05 | DEFECT → FIXED | Resumable evidence finalization reloaded the application without distinguishing DB uncertainty from a true application change, which could permanently fail a retryable upload. Finalization now uses the checked application read and returns `finalizing → open` on DB uncertainty while retaining `failed` for real owner/state mismatch. |
| R06 | DEFECT → FIXED | Public verification scope could convert DB uncertainty into false `Not verified` facts, and passport issue/read could consume that conservative matrix. Verification matrices now carry explicit `data_available` truth; public cards show temporary unavailability, and passport issue/read fail visibly while scope truth is unverifiable. |
| R07 | CLEAN | Durable File 09 outbox delivery, File 19 acknowledgement, provider exception containment, retry/backoff, dead-letter persistence, version-safe claim failure handling and privileged replay remained correct. |
| R08 | DEFECT → FIXED | Ordinary retention still unlinked resumable chunk files before durable DB intent and performed application/native anonymization through non-atomic writes. Retention now checkpoints `retention_pending` before unlink, verifies that checkpoint, transactionally retires Advanced Trust rows, transactionally row-locks/anonymizes native records, and clears `retention_until` after successful completion to prevent repeated processing. |
| R09 | DEFECT → FIXED | Direct File 00/File 02 assertion/profile/reauthentication calls could throw companion exceptions through the File 09 boundary. Base and membership assertions now fail closed to empty/deny, profile projection degrades safely, recent step-up returns false, and interactive step-up returns a bounded File 02 dependency error without leaking raw companion exceptions. |
| R10 | DEFECT → FIXED | Permanent R17 ledger, executable regression gate, release-lock metadata, authoritative workflow invocation and current status documentation were absent, while temporary R17 corrective plumbing remained. R17 release evidence is now synchronized and the temporary helper/spec are removed before final exact-head assurance. |

## Counts

- Review rounds: **10**
- Defect-bearing rounds: **7** — `01, 03, 05, 06, 08, 09, 10`
- Clean rounds: **3** — `02, 04, 07`
- Required post-correction executable result: **10 PASS / 0 FAIL**

## Evidence boundary

This ledger certifies repository review intent only when `tests/ten-round-audit-r17.py` and the authoritative exact-head workflow pass on the same final commit after temporary R17 corrective plumbing is absent. It is not evidence of Hostinger staging acceptance, deployed database/schema state, live deployment or operational acceptance.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
