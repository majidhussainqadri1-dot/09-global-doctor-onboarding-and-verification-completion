## Fresh Second Eighty-Round Re-Review — 10 August 2026

Frozen second-review baseline: `c3fbbadbee06d2be13b23822f5f17fce07cdab4e`.

Method: each control was reviewed independently; when a defect was found, root-cause correction was applied immediately and the same control was re-reviewed before continuing. This is repository/source assurance only; staging/live remain separate realities.

Result: **80 rounds; 47 defect-bearing rounds corrected; 33 clean rounds.**

| Round | Control | Initial result | Correction / final state |
|---:|---|---|---|
| 01 | Canonical File 09 ownership and no cross-file direct writes | CLEAN | No new defect found in this control; prior invariant retained. |
| 02 | Specified/Coded/Packaged/Staging/Live/Operational truth separation | CLEAN | No new defect found in this control; prior invariant retained. |
| 03 | RC6 identity and evidence freshness | DEFECT | Previous static evidence snapshot would become stale after new source changes; reopened evidence truth for fresh exact-head. |
| 04 | Bootstrap/load order | CLEAN | No new defect found in this control; prior invariant retained. |
| 05 | File 00 hard dependency | CLEAN | No new defect found in this control; prior invariant retained. |
| 06 | File 02 recent step-up | CLEAN | No new defect found in this control; prior invariant retained. |
| 07 | Eligibility/age/jurisdiction policy | CLEAN | No new defect found in this control; prior invariant retained. |
| 08 | Consent ledger concurrency and immutability | DEFECT | Consent could race submission and REPLACE historical acceptance; application/consent rows now locked and existing acceptance is not destructively replaced. |
| 09 | Completeness rejects expired evidence | DEFECT | Completeness accepted expired current evidence; expiry now blocks completeness. |
| 10 | Draft/save optimistic concurrency | CLEAN | No new defect found in this control; prior invariant retained. |
| 11 | Submission snapshot/evidence lock | DEFECT | Submission hash/completeness/risk could be computed before the immutable application/evidence lock; moved under transaction and FOR UPDATE. |
| 12 | Resubmission immutability | CLEAN | No new defect found in this control; prior invariant retained. |
| 13 | Audit hash-chain read integrity | DEFECT | Audit previous-hash read DB failure could be misread as genesis; chain read uncertainty now fails closed. |
| 14 | Post-commit transition publication | DEFECT | External transition fact could escape a later rolled-back transaction; external publication now occurs only after COMMIT. |
| 15 | Claim signing contract and transaction start | DEFECT | Claim issuance did not fail closed if START TRANSACTION itself failed; issuance now requires a successfully started transaction before any locked claim mutation, while HMAC subject/version binding remains intact. |
| 16 | Post-commit claim publication | DEFECT | Professional claim issue could publish before outer COMMIT; claim publication is now a separate post-commit step. |
| 17 | Reviewer scope monotonicity | CLEAN | No new defect found in this control; prior invariant retained. |
| 18 | Case-bound reviewer authorization/IDOR | DEFECT | Broad reviewer routing scope was being used as private-evidence authority; reviewer_case_allows now binds access to assigned/finalizer/appeal/lifecycle case relation. |
| 19 | Self-review prohibition | CLEAN | No new defect found in this control; prior invariant retained. |
| 20 | Reviewer workload fail-closed | DEFECT | Reviewer workload COUNT query failure could look like zero; DB uncertainty now rejects assignment. |
| 21 | Appeal reopen independent reassignment | DEFECT | Appeal reopening could leave under_review with no reviewer and no legal reassignment path; reopened cases can now be independently reassigned with audit/version control. |
| 22 | Recommendation locked revalidation | DEFECT | Recommendation relied on mutable pre-lock facts; current app/scope/risk/evidence/File00 are revalidated under lock. |
| 23 | Finalization locked revalidation | DEFECT | Finalization mutable facts were checked before lock; current row/version/evidence/risk/File00/snapshot are now revalidated atomically. |
| 24 | Lifecycle/appeal locked revalidation | DEFECT | Lifecycle and appeal high-trust decisions used stale pre-lock state; current application/appeal rows are locked before decision. |
| 25 | Approved snapshot integrity | CLEAN | No new defect found in this control; prior invariant retained. |
| 26 | Risk detection failure propagation | DEFECT | Risk duplicate/query/store failures could be ignored; evaluation now propagates DB uncertainty/failure. |
| 27 | Unresolved-risk uncertainty | DEFECT | Unresolved-risk DB failure could look like no risk; uncertainty now blocks high-trust approval. |
| 28 | Rate limiter DB uncertainty | DEFECT | Rate-limit increment succeeded but hit reread failure became 0/allow; read/write uncertainty now fails closed. |
| 29 | Claim acknowledgement gate | CLEAN | No new defect found in this control; prior invariant retained. |
| 30 | File 19 legacy delivery acknowledgement | DEFECT | Legacy File19 action path reported success without delivery acknowledgement; explicit ack support and ack result are required. |
| 31 | Evidence upload application lock | DEFECT | Evidence stage used caller-stale application state; current application is locked and revalidated before storage. |
| 32 | Persistent evidence quota fail-closed | DEFECT | Persistent evidence quota query failure could look like zero usage; quota uncertainty now blocks upload. |
| 33 | Evidence upload audit transaction boundary | DEFECT | Evidence audit fact could be emitted before an outer save transaction committed; publication moved after successful outer COMMIT. |
| 34 | Resumable aggregate temp quota | DEFECT | Many resumable sessions could reserve excessive temp bytes; user-wide open/finalizing reservation quota added. |
| 35 | Resumable quota DB uncertainty | DEFECT | Resumable reservation SUM failure could become zero; DB uncertainty now blocks session creation. |
| 36 | Chunk DB/file serialization | CLEAN | No new defect found in this control; prior invariant retained. |
| 37 | Chunk order/exact size | CLEAN | No new defect found in this control; prior invariant retained. |
| 38 | Finalize open→finalizing atomic claim | DEFECT | Concurrent finalizers could both proceed from open; open→finalizing is claimed atomically. |
| 39 | Finalize marker repairability | CLEAN | No new defect found in this control; prior invariant retained. |
| 40 | Canonical malware/encryption handoff | CLEAN | No new defect found in this control; prior invariant retained. |
| 41 | Evidence grant case binding | DEFECT | Grant issue/consume relied on broad reviewer eligibility; current case relationship is rechecked. |
| 42 | One-time/session/step-up grant | CLEAN | No new defect found in this control; prior invariant retained. |
| 43 | Secure room no-download/watermark | CLEAN | No new defect found in this control; prior invariant retained. |
| 44 | Primary-source unavailable fail-safe | CLEAN | No new defect found in this control; prior invariant retained. |
| 45 | Provider request minimization after filters | DEFECT | Minimization filter/provider context could widen request or receive raw app/evidence context; output is re-allowlisted and provider contexts minimized. |
| 46 | External-reference token minimization | DEFECT | Provider external_reference could persist token-bearing URLs/secrets; unsafe references are stored only as SHA-256 digests. |
| 47 | Equivalency legal boundary | CLEAN | No new defect found in this control; prior invariant retained. |
| 48 | Affiliation adapter minimization | DEFECT | Affiliation adapter received raw application/user context; it now receives a minimized request. |
| 49 | Translation adapter minimization | DEFECT | Translation adapter received raw app/evidence objects; it now receives only the minimized payload. |
| 50 | AI adapter minimization/human final decision | DEFECT | AI adapter received raw app/evidence objects; minimized payload only, with final decision keys still discarded. |
| 51 | Conflict query uncertainty | DEFECT | Reviewer conflict COUNT DB failure could look like no conflict; uncertainty now returns conflict/fail-closed. |
| 52 | Conflict filter monotonicity | DEFECT | Conflict-detection extension filter could clear a native conflict; filters may now only add conflict. |
| 53 | Dual-review risk-query uncertainty | DEFECT | Dual-review risk COUNT DB failure could disable dual review; uncertainty now requires dual review. |
| 54 | Smart routing DB uncertainty | DEFECT | Smart routing DB profile/workload failures could undercount load; uncertain candidates are excluded. |
| 55 | Calibration denominator | CLEAN | No new defect found in this control; prior invariant retained. |
| 56 | Continuous monitor no auto-revoke | CLEAN | No new defect found in this control; prior invariant retained. |
| 57 | Monitor retry/backoff | CLEAN | No new defect found in this control; prior invariant retained. |
| 58 | Event-driven exact reverification facts | CLEAN | No new defect found in this control; prior invariant retained. |
| 59 | Passport explicit validity/snapshot | DEFECT | Passport could be issued/read without explicit future verified_until or intact approved snapshot; both are now mandatory. |
| 60 | Passport transaction/version/supersession | DEFECT | Passport transaction/version/supersession failures were incompletely checked in base/corrective paths; all fail closed. |
| 61 | Passport renewal/reinstatement lifecycle | DEFECT | An old active passport could be reused across renewal/reinstatement snapshot cycles; existing passport is reused only if current verification succeeds. |
| 62 | Public passport current-truth recheck | DEFECT | Public passport did not fully bind current approved snapshot/identity truth; read-time underlying truth is rechecked. |
| 63 | Public passport read-only | DEFECT | Base public passport read could mutate derivative state; public verification is now strictly read-only. |
| 64 | Public cache/robots | CLEAN | No new defect found in this control; prior invariant retained. |
| 65 | Renewal predecessor continuity | DEFECT | Renewal draft could shadow a still-current predecessor verification; verification_record_for_user preserves valid predecessor continuity. |
| 66 | Scope-badge four states | CLEAN | No new defect found in this control; prior invariant retained. |
| 67 | Public history allowlist | CLEAN | No new defect found in this control; prior invariant retained. |
| 68 | Transparency cohort suppression | CLEAN | No new defect found in this control; prior invariant retained. |
| 69 | REST case-level authorization | DEFECT | Advanced REST reviewer capability alone was too broad at object level; case relation is revalidated for the target application. |
| 70 | REST structured errors | CLEAN | No new defect found in this control; prior invariant retained. |
| 71 | Privacy export Advanced Trust coverage | CLEAN | No new defect found in this control; prior invariant retained. |
| 72 | Native privacy DB atomic anonymization | DEFECT | Native privacy erasure could partially anonymize child rows after detaching application user, making retry impossible; child graph + app detachment now commit atomically. |
| 73 | Deleted-evidence identity detach | DEFECT | Previously deleted evidence user_id detach failure was ignored; erasure now pauses and reports retention. |
| 74 | Advanced Trust privacy erasure inventory | CLEAN | No new defect found in this control; prior invariant retained. |
| 75 | Advanced Trust retention DB failure | DEFECT | Advanced Trust retention ignored upload inventory/delete/update DB failures; failures are now observable and block completion. |
| 76 | Native retention child DB failure | DEFECT | Native retention child anonymization failures were ignored; retention completion now blocks and audits on failure. |
| 77 | Orphan chunk cleanup | CLEAN | No new defect found in this control; prior invariant retained. |
| 78 | Migration/schema/rollback truth | CLEAN | No new defect found in this control; prior invariant retained. |
| 79 | Fresh-second exact-head CI gate | DEFECT | Existing CI had no executable fresh-second 80-round gate; workflow now runs tests/eighty-round-audit-r2.py on PHP 7.4 and 8.3 before package. |
| 80 | Word/status/artifact evidence parity | DEFECT | Word/repository evidence from the earlier RC6 run becomes stale after this new correction series; fresh-second ledger/status is synchronized and final Word evidence must use the final exact-head run/artifact. |

Defect-bearing rounds: **03, 08, 09, 11, 13, 14, 15, 16, 18, 20, 21, 22, 23, 24, 26, 27, 28, 30, 31, 32, 33, 34, 35, 38, 41, 45, 46, 48, 49, 50, 51, 52, 53, 54, 59, 60, 61, 62, 63, 65, 69, 72, 73, 75, 76, 79, 80**.

Clean rounds: **01, 02, 04, 05, 06, 07, 10, 12, 17, 19, 25, 29, 36, 37, 39, 40, 42, 43, 44, 47, 55, 56, 57, 58, 64, 66, 67, 68, 70, 71, 74, 77, 78**.

Permanent executable gate: `tests/eighty-round-audit-r2.py`. The authoritative QA/package evidence is the latest workflow run for the exact current source HEAD; an earlier RC6 workflow/artifact cannot certify this second-review head.
