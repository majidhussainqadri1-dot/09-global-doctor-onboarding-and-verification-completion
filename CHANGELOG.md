# Changelog

## Twenty-second fresh 20-round sequential corrective assurance — R22

- Frozen baseline: `532fdeeb0411284397d7418f73d0d9170e941bb8`.
- R01–R20 completed sequentially; defects corrected in `01,06,10,18,19`; clean rounds `02,03,04,05,07,08,09,11,12,13,14,15,16,17,20`.
- Isolated retention database error state before current reads so stale `$wpdb->last_error` cannot falsely fail retention, renewal, expiry or key-rotation inventory work.
- Made migration lock release compare-and-delete the exact serialized owned lock, preventing an overlong predecessor from deleting a successor worker's fresh CAS-replaced lock.
- Realigned `TRACEABILITY.md` to the current governing File09 DoD-01…DoD-12 meanings and removed obsolete DoD-13; historical assertions were updated only after plan/source semantics were verified.
- Added permanent R22 ledger, executable gate, release-lock metadata and authoritative workflow invocation.
- External Hostinger staging, live deployment and operational acceptance remain false and require separate evidence.

## Twenty-first fresh 20-round sequential corrective assurance — R21

- Frozen baseline: `a4191a0c693ba7dd95770fcdfee46804a8645a67`.
- R01–R20 completed sequentially; defects corrected in `01,02,03,04,05,16,17,19`; clean rounds `06,07,08,09,10,11,12,13,14,15,18,20`.
- Added authoritative COMMIT reconciliation for private draft creation/consent and generic standalone state transitions.
- Made legacy quarantine application + initial chained audit atomic/reconciled instead of allowing permanent partial migration history.
- Made private credential grants/served bytes fail closed on durable access-audit failure and strengthened evidence-review reconciliation to exact mutation state.
- Made eligibility fail closed when latest-application DB state is unavailable.
- Hardened applicant save/appeal/withdraw COMMIT recovery; possibly committed encrypted evidence is preserved for reconciliation instead of blind deletion.
- Added permanent R21 ledger/executable/release-lock/workflow evidence; five final source-shape assertion mismatches were corrected only after source semantics were verified and are not product-defect rounds.
- External Hostinger staging, live deployment and operational acceptance remain false and require separate evidence.

## Twentieth fresh 20-round sequential corrective assurance — R20

- Frozen baseline: `f6ffbc43edf2679590595f5bb1db7c3fec652d25`.
- R01–R20 completed sequentially; defects corrected in `01,02,03,04,05,08,09,16,17,19`; clean rounds `06,07,10,11,12,13,14,15,18,20`.
- Added authoritative lost-COMMIT reconciliation across professional passport issuance, professional claim/outbox issuance, immutable submission and reviewer/admin transaction families.
- Hardened evidence rotation/review/grant COMMIT recovery and added authorized missing-file recovery so irreversible credential unlink cannot be rolled back into an active DB row.
- Applied the same physical-deletion durability to privacy erasure and retention; reconciled lifecycle, Advanced Trust and anonymization commits.
- Made operational expiry reconciliation crash-safe and made temporary professional-claim transport failure retryable without clobbering accepted/rejected acknowledgement.
- Added permanent R20 ledger, executable gate and release-lock metadata; the 20 numbered rounds are complete, while final automated-QA/package evidence remains exact-HEAD-specific.
- External Hostinger staging, live deployment and operational acceptance remain false and require separate evidence.

## Nineteenth fresh 20-round corrective assurance — R19

- Froze the green R18 exact head `634bae9795bf6cb744eda333cfdf2a770868c133` and performed a new independent 20-round sequential corrective review.
- Corrected **5** defect-bearing rounds (`05,06,08,09,19`); **15** rounds were clean.
- Made evidence quota database uncertainty explicit rather than reporting false quota exhaustion, checked encrypted orphan cleanup failures, and reconciled ambiguous evidence COMMIT outcomes before any physical deletion.
- Made resumable upload session-create, chunk-append and finalization-claim COMMIT ambiguity authoritative-state reconciled rather than speculatively unlinking or truncating private bytes.
- Kept legal-hold/application eligibility row locks through irreversible native evidence deletion in both WordPress privacy erasure and ordinary retention.
- Added permanent R19 twenty-round ledger, executable gate, release-lock metadata, current status documentation and authoritative workflow wiring; temporary R19 corrective plumbing is removed before final exact-head assurance.
- External Hostinger staging, live deployment and operational acceptance remain false and require separate evidence.

## Eighteenth fresh 10-round corrective assurance — R18

- Froze the green R17 exact head `67a3e999fa44e527bc4a795759a1b48cb8e7766d` and performed a new independent 10-round sequential corrective review.
- Corrected **7** defect-bearing rounds (`01,02,06,07,08,09,10`); rounds `03,04,05` were clean.
- Preserved active-passport verification errors instead of triggering replacement issuance.
- Made professional-claim acknowledgement terminal CAS/idempotent and fail-visible; claim delivery no longer mistakes acknowledgement `WP_Error` for success.
- Added legal-hold pre-delete serialization, atomic stale migration-lock CAS and File00 identity dependency/public-truth separation.
- Added permanent R18 ledger/executable/release evidence.
- External staging/live/operational gates remain false.

## Seventeenth fresh 10-round corrective assurance — R17

- Froze the green R16 exact head `66e43bcb42904a381728509144cd0a1e6c77c6ac` and performed a new independent 10-round sequential corrective review.
- Corrected **7** defect-bearing rounds (`01,03,05,06,08,09,10`); rounds `02,04,07` were clean.
- Made the reviewer more-information command distinguish application-table DB uncertainty from ordinary invalid/state denial.
- Contained external primary-source, equivalency, affiliation, translation and AI assistance `Throwable` failures as bounded degraded/manual-review states without raw exception leakage.
- Preserved resumable-upload finalization retry when the application reload is DB-uncertain instead of permanently failing the session.
- Added explicit public verification `data_available` truth so DB uncertainty is not rendered as `Not verified`; passport issue/read now fail visibly while scope truth is unavailable.
- Made ordinary retention persist and verify `retention_pending` before resumable-file unlink, transactionally retire Advanced Trust state, row-lock and atomically anonymize native related records, and clear successful `retention_until` work.
- Contained File 00/File 02 assertion/profile/reauthentication exceptions at the File 09 boundary with fail-closed or bounded error semantics.
- Added permanent R17 ledger, executable gate, release-lock metadata, authoritative workflow wiring and current status evidence; temporary R17 corrective plumbing was removed before final exact-head assurance.
- External Hostinger staging, live deployment and operational acceptance remain false and require separate evidence.

## Sixteenth fresh 10-round corrective assurance — R16

- Froze the green R15 exact head `4707d31cbd5ed8f419166d2f796bee315449e172` and performed a new independent 10-round sequential corrective review.
- Corrected **9** defect-bearing rounds (`01,02,03,04,05,06,08,09,10`); round `07` was clean.
- Hardened Advanced Trust application DB reads and propagated professional-decision derivative lifecycle failures.
- Removed duplicate post-submit trust side effects and made post-commit lifecycle failures explicitly auditable/operator-visible.
- Aligned passport issuance with accepted File 00 professional claims while keeping monitor scheduling independently recoverable.
- Preserved unsupported/degraded primary-source states as fail-safe manual attention rather than clean monitoring.
- Added durable `erasure_pending` checkpoints before resumable-upload physical unlink and made WordPress privacy erasure retry until selected application work is actually complete.
- Added permanent R16 ledger/executable gate/release-lock/workflow evidence while keeping the installable allowlist at 62 entries.
- External staging/live/operational gates remain false.

## Tenth fresh 80-round corrective assurance — R10

- Froze the R9 exact-head candidate `ec3ca2dd715e05b66cf29c42f2f996c80987fcd7` and performed a new independent 80-control review.
- Corrected **19** defect-bearing rounds (`04–22`); **61** rounds were clean.
- Made public verification projection, private application REST/autosave, draft creation/save, consent/submission and completeness checks fail visibly on DB uncertainty.
- Hardened privileged application/reviewer/evidence/appeal lock/read paths and required recent File 02 step-up for the privileged health endpoint.
- Hardened evidence upload/replacement/decrypt/key rotation, generic state transition and professional claim issuance against DB-read ambiguity.
- Made post-decision quality-sampling failure structured and auditable instead of silent.
- Added R10 permanent ledger/executable gate, 61-entry release parity and R10 release-lock evidence.
- External staging/live/operational gates remain false.


## Ninth fresh 80-round corrective assurance — R9

- Froze R8 exact-head candidate `75ea54ed5113bf7ee16e90443f17cc1b941933a9` and performed a new 80-control review rather than reusing earlier green evidence.
- Corrected 10 defect-bearing rounds: `04,05,06,07,08,09,10,11,12,13`; 70 rounds were clean.
- Reauthorized evidence review and dead-letter replay at their mutation-method boundaries with current File 00 capability + File 02 step-up.
- Made evidence review/grant and appeal reads fail visibly on DB uncertainty; rechecked download capability at grant consumption.
- Verified idempotent submission COMMIT and bounded outbox processing success.
- Added continuous-monitor exclusive processing leases/stale-lease recovery and prevented stale monitor work from overwriting newer scheduling.
- Made Advanced Trust database-side privacy erasure atomic with rollback/COMMIT verification.
- Synchronized R9 ledger, executable gate, 60-entry release allowlist and current release/status evidence.
- External staging/live/operational gates remain false.


## 1.3.0-RC6 — Eighty-Round Corrective Assurance — 2026-08-10

### Fifth fresh 80-round corrective review (R5)
- Frozen baseline: `58313a67e1d21ad17c9a066e9a29c34245a0763e`.
- 80 controls: **29 defect-bearing rounds corrected; 51 clean rounds**.
- Hardened future-schema rejection, runtime hook ordering, Advanced Trust REST/background mutation gates, monitor DB/provider failure truth, recurring/wakeup scheduling separation, activation persistence, canonical private storage, resumable erasure safety, manager Safe Mode boundaries and guarded destructive uninstall.
- Added `REVIEW-80-ROUNDS-RC6-R5.md`, `tests/eighty-round-audit-r5.py`, 56-entry release parity and fifth-review release-lock fields.

### Fourth fresh 80-round corrective review (R4)
- Frozen baseline: `7875ce20a0fd40c0952960b6e45cf1e869b9c4dd`.
- 80 controls: **30 defect-bearing rounds corrected; 50 clean rounds**.
- Added physical schema postconditions and runtime schema gates for core + Advanced Trust migrations.
- Hardened activation/repair/reverification scheduling, applicant Safe Mode mutation paths, privacy/retention failure truth, resumable cleanup, rate cleanup and guarded destructive uninstall.
- Added `REVIEW-80-ROUNDS-RC6-R4.md`, `tests/eighty-round-audit-r4.py`, 55-entry release parity and fourth-review release-lock fields.

### Third fresh 80-round corrective review (R3)
- Frozen baseline: `f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361`.
- 80 controls: **13 defect-bearing rounds corrected; 67 clean rounds**.
- Hardened File 00 fail-closed profile/privileged identity use, explicit private resumable provenance, risk DB uncertainty, durable outbox persistence/replay, operational health/reconciliation, safe-mode/scheduler persistence and orphan-deletion safety.
- Synchronized historical QA assertions with the stronger current reviewer-case, Advanced Trust, rate-limit and resumable-upload contracts.
- Added `REVIEW-80-ROUNDS-RC6-R3.md`, `tests/eighty-round-audit-r3.py`, 54-entry release parity and R3 release-lock fields.

- Fresh-second independent 80-control re-review against `c3fbbadbee06d2be13b23822f5f17fce07cdab4e`: **47 defect-bearing rounds corrected; 33 clean rounds**; added `tests/eighty-round-audit-r2.py` and `REVIEW-80-ROUNDS-RC6-R2.md`.

- Performed 80 independent review→fix→re-review controls against frozen RC5 baseline `6d5c2850dbf86ce954e0c2fdef8adf36d2dbf1f1`: **49 defect-bearing rounds corrected; 31 clean rounds**.
- Preserved all 24 Founder-approved Advanced Professional Trust capabilities and retained File 09 as the only professional-verification owner.
- Corrected trusted issuer governance to proposed-first + independent initial verification; hardened provider-domain/metadata normalization and duplicate issuer prevention.
- Corrected jurisdiction rules to draft-first, independent second approval, effective-date validation and immutable approved versions.
- Minimized/bounded provider requests/results, normalized provider expiry data and added primary-source/AI rate limits without delegating final decisions.
- Corrected continuous monitoring so `degraded` rows retry with bounded exponential backoff and adverse/expiry events receive targeted reverification without silent professional-state mutation.
- Corrected professional verification passport eligibility, issuance locking/version serialization, supersession failure handling, suspension/revocation/expiry invalidation, current underlying File 09/File 00 recheck, and public read-only/no-cache semantics.
- Corrected verification-scope badge to current predecessor-aware verification plus `verified/pending/not_verified/not_applicable` semantics.
- Corrected reviewer conflict declaration/resolution, adaptive dual-review state/monotonicity, max reviewer workload and completed-sample calibration.
- Deduplicated credential-reuse/fraud-network signals and preserved explainable human-reviewed risk.
- Serialized resumable private-upload DB/file state, enforced exact chunk geometry/order/size, added atomic `open→finalizing` ownership, commit-marker diagnostics and stale-finalizing cleanup while preserving canonical malware/type/quota/encryption handoff.
- Removed active duplicate evidence-grant semantics by routing the Secure Evidence Viewing Room through canonical `GDO_Evidence::issue_view_grant()` one-time/session/step-up authorization.
- Added Advanced Trust REST object-scope reauthorization, structured per-check failure responses and fixed-window minimum-cohort public transparency.
- Extended WordPress privacy export/erasure and application-scoped retention across Advanced Trust; derivative passports are deleted on erasure/retention rather than collision-prone shared-user anonymization; `.chunk-*` private orphans are covered.
- Advanced Advanced Trust schema to `2`, contract to `1.1.0`, release identity to `1.3.0-RC6`, 53-entry deterministic package and new `tests/eighty-round-audit.py`/`REVIEW-80-ROUNDS-RC6.md` gates.
- Staging Accepted, Live Deployed and Operational remain false until the new exact-head RC6 workflow and external Hostinger acceptance are separately evidenced.

## 1.3.0-RC5 — Advanced Professional Trust & Verification — 2026-08-10

- Implemented all 24 Founder-approved Advanced Professional Trust enhancements in the File 09 canonical boundary.
- Added primary-source verification adapters, trusted issuer registry, technical credential authenticity records, versioned jurisdiction rules, cross-border equivalency and affiliation adapters.
- Added continuous/event-driven reverification with adverse-result escalation only; external provider facts never silently revoke or finalize a professional decision.
- Added signed, revocable, time-bounded professional verification passports and public-safe QR verification payloads plus a scoped verification matrix.
- Added public-safe professional history, translation assistance and AI reviewer assistance; automated approve/reject/decision keys are discarded and human final review is mandatory.
- Added explainable risk, credential-reuse/fraud-network clues, conflict-of-interest enforcement, adaptive dual review, smart reviewer routing and reviewer calibration summaries.
- Added private applicant command center, ordered resumable secure evidence uploads terminating in the canonical malware/type/quota/encryption path, and short-lived step-up-bound no-download evidence viewing-room grants.
- Added aggregate-only trust transparency metrics with no applicant-level private evidence exposure.
- Added eight additive Advanced Trust tables, contract `1.0.0`, schema `1`, migration/rollback documentation, traceability and permanent `tests/advanced-trust-24.py` regression gates.
- Advanced runtime to `1.3.0`, kept core File 09 schema at `6`, and advanced deterministic packaging to `1.3.0-RC5` with exact-head generated SPDX 2.3 SBOM.
- Staging, live deployment and operational acceptance remain false until RC5 exact-head QA and external Hostinger acceptance are separately evidenced.

## 1.2.0-RC4 — Latest central + File 09 plan parity — 2026-08-10

- Corrected the plugin display title to the canonical **Global Doctor Onboarding and Verification** name while retaining runtime `1.2.0` and schema `6`.
- Added the File 09 canonical owner contract `1.1.0` and explicit fail-closed professional-eligibility consumers for Files 21 and 23 in addition to Files 03/07/08.
- Added `gdo.file26.doctor-verification-projection` and a privacy-preserving File 26 connector negotiation. The connector remains `contract_tested` so File 09 private applications/evidence cannot become search documents by activation side effect.
- Added File 20 page-contract registration through `sabri_shell_page_contracts`, preserving File 20 as the only application shell.
