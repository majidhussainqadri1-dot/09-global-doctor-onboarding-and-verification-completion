# File 09 — RC6 R18 Fresh Ten-Round Corrective Assurance

Frozen baseline: `67a3e999fa44e527bc4a795759a1b48cb8e7766d`  
Review model: **sequential Review → Fix → Review**. A later numbered round did not begin until every established defect from the preceding round had been corrected in repository source.

## Result ledger

| Round | Result | Review / correction |
|---|---|---|
| R01 | DEFECT → FIXED | An existing active professional passport whose use-time verification returned a transient DB/scope/identity error could fall through to fresh issuance. Base and hardened `ensure_passport()` now preserve the active-passport verification error; new issuance occurs only when no active passport exists. |
| R02 | DEFECT → FIXED | Professional-claim acknowledgements were not terminal-state compare-and-set operations, so an out-of-order conflicting acknowledgement for the same claim version could overwrite an earlier terminal acknowledgement, and DB uncertainty collapsed to `false`. Acknowledgement is now `pending → accepted/rejected` CAS, same-status replay is idempotent, conflicting terminal replay is denied, and DB read/write/recheck uncertainty is fail-visible. |
| R03 | CLEAN | Reviewer/current-authority checks, applicant/self-review denial, recommender/finalizer separation, row-version locking, conflict controls and independently assigned appeal resolution remained guarded. |
| R04 | CLEAN | Resumable upload session ownership, aggregate quota locking, ordered/exactly-once chunks, fsync/hash/size verification, retryable finalization, hardened expiry cleanup and privacy/retention upload checkpoints remained correct. |
| R05 | CLEAN | Primary-source/equivalency/affiliation/translation/AI adapters remained exception-contained, minimized and advisory; malformed/degraded provider states remain fail-safe and human authorized review remains final. |
| R06 | DEFECT → FIXED | Retention/privacy erasure could select `legal_hold=0`, then perform irreversible credential/Advanced-Trust deletion before a row-lock recheck, allowing a concurrently committed legal hold or unsafe lifecycle state to arrive too late. Both retention and WordPress erasure now establish a serialized `FOR UPDATE` legal-hold/eligibility authorization point before irreversible deletion. |
| R07 | DEFECT → FIXED | After R02 made claim acknowledgement DB failures return `WP_Error`, `deliver_claim()` still used only boolean negation. Because a `WP_Error` object is truthy, acknowledgement persistence failure could be treated as delivery success. The notification caller now handles `WP_Error` explicitly before any delivered result can be accepted. |
| R08 | DEFECT → FIXED | Stale schema-migration takeover used read → unconditional `delete_option()` → `add_option()`. Two stale-lock contenders could allow one worker to delete another worker's newly acquired lock and run migrations concurrently. Stale takeover is now an atomic compare-and-swap on the exact serialized option value and fails closed if another worker changed the lock. |
| R09 | DEFECT → FIXED | File 00 assertion exceptions/dependency failure were intentionally collapsed to `false` for authorization, but public verification/passport code could misstate that uncertainty as a definitive `Identity: Not verified`/inactive result. A checked identity-assurance path now distinguishes dependency/contract failure from a true negative; public matrix and passport issue/use-time paths return unavailable/503 for uncertainty while authorization remains fail-closed. |
| R10 | DEFECT → FIXED | Permanent R18 ledger, executable regression gate, release-lock fields, authoritative workflow invocation and current status evidence were absent while temporary R18 corrective plumbing remained. R18 release evidence is now synchronized and temporary helper/spec are removed before final exact-head assurance. |

## Counts

- Review rounds: **10**
- Defect-bearing rounds: **7** — `01, 02, 06, 07, 08, 09, 10`
- Clean rounds: **3** — `03, 04, 05`
- Required post-correction executable result: **10 PASS / 0 FAIL**

## Evidence boundary

This ledger certifies repository review intent only when `tests/ten-round-audit-r18.py` and the authoritative exact-head workflow pass on the same final commit after temporary R18 corrective plumbing is absent. It is not evidence of Hostinger staging acceptance, deployed database/schema state, live deployment or operational acceptance.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
