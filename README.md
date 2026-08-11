# File 09 — Global Doctor Onboarding and Verification

This repository contains the canonical File 09 implementation candidate for the Sabri Social Homeopathy Platform.

## Current evidence status

| Layer | Status |
|---|---|
| Latest central + File 09 specification | Traced in `TRACEABILITY.md` |
| Approved Advanced Trust 24 amendment | Implemented/traced candidate in `ADVANCED-TRUST-24.md` |
| 80-round review R1 | 49 defect-bearing corrected; 31 clean |
| 80-round review R2 | 47 defect-bearing corrected; 33 clean |
| 80-round review R3 | 13 defect-bearing corrected; 67 clean |
| 80-round review R4 | 30 defect-bearing corrected; 50 clean |
| 80-round review R5 | 29 defect-bearing corrected; 51 clean |
| 80-round review R6 | 60 defect-bearing corrected; 20 clean |
| 80-round review R7 | 22 defect-bearing corrected; 58 clean |
| 80-round review R8 | 15 defect-bearing corrected; 65 clean |
| 80-round review R9 | 10 defect-bearing corrected; 70 clean |
| 80-round review R10 | 19 defect-bearing corrected; 61 clean |
| 80-round review R11 | 17 defect-bearing corrected; 63 clean |
| Fresh 10-round review R12 | 10 defect-bearing corrected; 0 clean |
| Fresh 10-round review R13 | 6 defect-bearing corrected; 4 clean |
| Fresh 10-round review R14 | 6 defect-bearing corrected; 4 clean |
| Fresh 10-round review R15 | 6 defect-bearing corrected; 4 clean |
| Fresh 10-round review R16 | 9 defect-bearing corrected; 1 clean |
| Fresh 10-round review R17 | 7 defect-bearing corrected; 3 clean |
| Fresh 10-round review R18 | 7 defect-bearing corrected; 3 clean |
| Fresh 20-round review R19 | 5 defect-bearing corrected; 15 clean |
| **Fresh 20-round review R20** | **R01–R19 complete: 10 defect-bearing corrected; 9 clean; R20 final exact-head round pending** |
| Source candidate | `1.3.0`, core schema `6`, Advanced Trust schema `2`, contract `1.1.0`, RC6 candidate |
| Exact-head automated QA | Must pass on the final exact RC6/R20 commit |
| Deterministic package | 62-entry RC6 allowlist; exact-head workflow output only |
| Hostinger-equivalent staging | Pending external execution |
| Production/live/operational | Not authorized |

**Eleventh fresh 80-round corrective assurance — historical R11:** 17 defect-bearing rounds corrected; 63 clean; its own exact-head result was 80/80 PASS. R12–R18 ten-round reviews and R19 twenty-round review supersede it only as current repository review evidence, not as historical evidence.

File 09 owns doctor applications, private professional evidence, professional verification review/decision, renewal, suspension/revocation, appeal, signed professional-decision claims and File 09 professional-trust records. It does **not** own general identity, login, public profiles, directory/search ranking, clinics, notification transport or platform-wide security governance.

Read `TRACEABILITY.md`, `ADVANCED-TRUST-24.md`, all `REVIEW-80-ROUNDS-RC6*.md` ledgers, `REVIEW-10-ROUNDS-RC6-R12.md`, `REVIEW-10-ROUNDS-RC6-R13.md`, `REVIEW-10-ROUNDS-RC6-R14.md`, `REVIEW-10-ROUNDS-RC6-R15.md`, `REVIEW-10-ROUNDS-RC6-R16.md`, `REVIEW-10-ROUNDS-RC6-R17.md`, `REVIEW-10-ROUNDS-RC6-R18.md`, `REVIEW-20-ROUNDS-RC6-R19.md`, `REVIEW-20-ROUNDS-RC6-R20.md`, `SECURITY-PRIVACY.md`, `MIGRATION-ROLLBACK-1.3.0.md`, `OPERATIONS.md`, `STAGING-ACCEPTANCE.md` and `RELEASE-MANIFEST-1.3.0.md` before deployment.

## 1.3.0 RC6 — Advanced Professional Trust assurance history

RC6 preserves all 24 approved Advanced Professional Trust enhancements. R1–R11 remain immutable historical review evidence. No previous green run is accepted as proof for later source: every later correction reopens exact-head source, QA and package evidence.

The principal trust invariants remain:

- proposed-first trusted issuer governance with independent initial verification;
- draft-first, independently approved and immutable jurisdiction-rule versions;
- minimized/bounded provider requests/results, rate limits and provider expiry normalization;
- degraded monitor retry/backoff and event-driven reverification with exclusive processing leases;
- signed professional passports serialized under application locking, current-state rechecked and non-cacheable on public lookup;
- four-state verification-scope semantics and renewal-predecessor continuity;
- conflict declaration/resolution, dual-review monotonicity, reviewer workload limits and completed-sample calibration;
- deduplicated fraud-ring signals and explainable human-reviewed risk;
- resumable private upload DB/file serialization, exact chunk geometry, atomic finalization and stale-finalizing cleanup;
- canonical one-time/session/step-up evidence viewing grants, with download capability rechecked at use time;
- object-scope REST authorization and structured per-check failures;
- Advanced Trust privacy export, atomic erasure, application-scoped retention and `.chunk-*` orphan cleanup;
- additive Advanced Trust schema 2 migration and synchronized RC6 release evidence.

External providers are advisory/fact sources only. Human authorized File 09 review remains final. Provider secrets are deployment secrets and are not stored in this repository.

## Cross-file ownership boundary

- File 00: identity/membership/security assertions.
- File 02: professional reauthentication/step-up.
- File 03: public doctor/profile presentation.
- File 07 and File 26: directory/search/discovery/ranking.
- File 08: clinic and appointment truth.
- File 19: notification projection/delivery.
- File 20: one application shell.
- File 21/File 23: publishing truth/dashboard operations.
- File 24: platform-wide security/privacy/compliance assurance.

File 09 exposes public-safe current verification facts only. Private application/evidence data remain non-indexed and non-public; donation/payment confers no verification, visibility or ranking advantage.

## Assurance history and release law

R1–R11 review ledgers and executable gates remain preserved as historical source assurance. Their exact-head runs/artifacts certify only their corresponding historical source heads.

The final exact HEAD must pass the complete PHP 7.4/8.3 workflow, all R1–R11 executable 80-round gates, the R12, R13, R14, R15, R16, R17 and R18 ten-round gates, the R19 twenty-round gate, deterministic double build, **62-entry** package parity and generated exact-head SPDX SBOM before repository package/QA status can be called green.

External Hostinger staging, real provider failure modes, real applicant/reviewer lifecycle journeys, private storage/key/scanner checks, two fresh staging review→fix→full-retest cycles, backup/restore, rollback and explicit Founder acceptance remain mandatory before any live/operational claim.

## Twelfth fresh 10-round corrective assurance (R12)

R12 froze the previously green R11 exact head `0df40731282a4dc905a12b37e5fa5aa4604dc68a` and performed a new independent ten-round review. **Every one of the 10 rounds found a repository defect and was corrected before the next round advanced.**

R12 corrected: reviewer/finalizer unchecked evidence/profile snapshot reads; passport issuance preceding explicit current professional-claim acknowledgement; unchecked immutable submission evidence inventory; frontend completeness/evidence DB uncertainty and repeated evidence reads; draft pre-read/post-write reload uncertainty; migration-evidence/backfill/quarantine read uncertainty; silent/ambiguous legacy credential migration failures; notification outbox stale DB state and stale-claim failure clobbering; retention unchecked evidence/orphan-cleanup false-success; and missing permanent R12 executable/release evidence.

The permanent R12 ledger is `REVIEW-10-ROUNDS-RC6-R12.md`; the executable gate is `tests/ten-round-audit-r12.py` and must report **10 PASS / 0 FAIL**. The authoritative workflow remains `.github/workflows/file09-rc2-final.yml`. R12 QA files are repository evidence and are not added to the installable allowlist, which remains **62 entries**. Because package-owned PHP source changed, the final RC6 ZIP checksum/artifact must nevertheless be regenerated from the exact final HEAD.

## Thirteenth fresh 10-round corrective assurance (R13)

R13 froze the green R12 exact head `e5e867d235aafc49dd084644590afe8dfaf27d47` and performed ten new sequential review → fix → review controls. Defects were established in rounds **01, 04, 05, 06, 07 and 10**; rounds **02, 03, 08 and 09** were clean. Each established defect was corrected before the next round advanced.

R13 bounds verification validity to the earliest required current credential/evidence expiry; makes evidence erasure/retention use a durable deletion-pending state before physical unlink; isolates dead-letter replay DB state and distinguishes store failure from concurrency; fails closed when required retention/outbox/continuous-monitor schedules are missing; makes public verification scopes current-evidence-aware and current-status claim-acknowledgement-bound; and adds permanent R13 executable/release evidence.

The permanent R13 ledger is `REVIEW-10-ROUNDS-RC6-R13.md`; the executable gate is `tests/ten-round-audit-r13.py` and must report **10 PASS / 0 FAIL**. R13 QA evidence remains repository-only, so the installable allowlist remains **62 entries**; because package-owned PHP/docs changed, every older ZIP/hash/artifact is historical and the exact-head deterministic package/SBOM must be regenerated. Staging/live/operational acceptance remain external and false until separately evidenced.

## Fourteenth fresh 10-round corrective assurance (R14)

R14 froze `52b41f3395e1599540da0656d6e9a022c39a27fe`. Defects were corrected in rounds **01, 02, 05, 07, 08 and 10**; rounds **03, 04, 06 and 09** were clean. R14 separated License/Registration public scope truth, strengthened professional-registration validity parity, operationalized appeal deadlines and preserved worst continuous-monitor results.

The permanent R14 ledger is `REVIEW-10-ROUNDS-RC6-R14.md`; the executable gate is `tests/ten-round-audit-r14.py` and must report **10 PASS / 0 FAIL**.

## Fifteenth fresh 10-round corrective assurance (R15)

R15 froze the green R14 exact head `ca76893f781358a95d0be2c6fbaa3ffd65757bbf` and performed ten new sequential review → fix → review controls. Defects were established in rounds **01, 02, 03, 04, 06 and 10**; rounds **05, 07, 08 and 09** were clean. Each established defect was corrected before the next numbered round advanced.

R15 makes shared Advanced Trust evidence lookup use checked DB reads; prevents the Applicant Verification Command Center from converting DB uncertainty into false application/completeness truth; distinguishes hardened trust-check application DB failure from authorization denial; renders the required bounded command-center validity/deadline/scope/pending/degraded state; and prevents internal monitor query/store failure from being misclassified as provider degradation. R10 permanently records and executes this assurance.

The permanent R15 ledger is `REVIEW-10-ROUNDS-RC6-R15.md`; the executable gate is `tests/ten-round-audit-r15.py` and must report **10 PASS / 0 FAIL**. The installable allowlist remains **62 entries**. Staging/live/operational acceptance remain external and false until separately evidenced.

## Sixteenth fresh 10-round corrective assurance (R16)

R16 froze the green R15 exact head `4707d31cbd5ed8f419166d2f796bee315449e172` and performed ten new sequential review → fix → review controls. Defects were established in rounds **01, 02, 03, 04, 05, 06, 08, 09 and 10**; round **07** was clean. Each established defect was corrected before the next numbered round advanced.

R16 makes Advanced Trust application reads DB-fail-visible; propagates post-decision derivative failures; removes duplicate submit side effects; records post-commit lifecycle failure attention; keeps passport issuance downstream of accepted File 00 claim while independently healing monitoring; preserves unsupported/degraded primary-source states as manual attention; checkpoints resumable erasure before physical unlink; and keeps WordPress privacy erasure retryable until every selected application is complete.

The permanent R16 ledger is `REVIEW-10-ROUNDS-RC6-R16.md`; the executable gate is `tests/ten-round-audit-r16.py` and must report **10 PASS / 0 FAIL**. The installable allowlist remains **62 entries**. Staging/live/operational acceptance remain external and false until separately evidenced.

## Seventeenth fresh 10-round corrective assurance (R17)

R17 froze the green R16 exact head `66e43bcb42904a381728509144cd0a1e6c77c6ac` and performed ten new sequential review → fix → review controls. Defects were established in rounds **01, 03, 05, 06, 08, 09 and 10**; rounds **02, 04 and 07** were clean. Each established defect was corrected before the next numbered round advanced.

R17 makes more-information application reads DB-fail-visible; contains external primary-source/equivalency/affiliation/translation/AI exceptions; preserves resumable-upload retry under application DB uncertainty; distinguishes unavailable public verification/passport scope from `Not verified`; makes ordinary retention checkpoint filesystem deletion and transactionally anonymize DB state; and contains File00/File02 companion contract exceptions at the File09 boundary. R10 permanently records and executes this assurance.

The permanent R17 ledger is `REVIEW-10-ROUNDS-RC6-R17.md`; the executable gate is `tests/ten-round-audit-r17.py` and must report **10 PASS / 0 FAIL**. The installable allowlist remains **62 entries**. Staging/live/operational acceptance remain external and false until separately evidenced.

## Eighteenth fresh 10-round corrective assurance (R18)

R18 froze the green R17 exact head `67a3e999fa44e527bc4a795759a1b48cb8e7766d` and performed ten new sequential review → fix → review controls. Defects were established in rounds **01, 02, 06, 07, 08, 09 and 10**; rounds **03, 04 and 05** were clean. Each established defect was corrected before the next numbered round advanced.

R18 preserves active-passport verification errors instead of triggering replacement issuance; makes professional-claim acknowledgement a terminal-state CAS with idempotent same-status replay; serializes legal-hold/retention eligibility before irreversible deletion; prevents acknowledgement `WP_Error` from becoming outbox delivery success; replaces stale migration-lock delete/re-add with atomic CAS takeover; separates File00 identity dependency uncertainty from a factual `Not verified` public projection; and permanently records/exercises R18 release evidence.

The permanent R18 ledger is `REVIEW-10-ROUNDS-RC6-R18.md`; the executable gate is `tests/ten-round-audit-r18.py` and must report **10 PASS / 0 FAIL**. The installable allowlist remains **62 entries**. Staging/live/operational acceptance remain external and false until separately evidenced.

## Nineteenth fresh 20-round corrective assurance (R19)

R19 froze the green R18 exact head `634bae9795bf6cb744eda333cfdf2a770868c133` and performed twenty new sequential review → fix → review controls. Defects were established in rounds **05, 06, 08, 09 and 19**; rounds **01, 02, 03, 04, 07, 10, 11, 12, 13, 14, 15, 16, 17, 18 and 20** were clean. Each established product/source defect was corrected before the next numbered round advanced.

R19 makes evidence quota DB uncertainty explicit; makes encrypted evidence compensation and ambiguous COMMIT outcomes fail-visible/reconciled; makes resumable session creation, chunk append and finalize claims reconcile ambiguous COMMIT outcomes instead of speculatively unlinking/truncating; and holds application eligibility row locks through irreversible native evidence deletion so legal-hold writes serialize correctly in privacy erasure and retention. R19 also adds the permanent twenty-round ledger, executable gate, release-lock metadata and authoritative workflow wiring.

The permanent R19 ledger is `REVIEW-20-ROUNDS-RC6-R19.md`; the executable gate is `tests/twenty-round-audit-r19.py` and must report **20 PASS / 0 FAIL**. The installable allowlist remains **62 entries**. Staging/live/operational acceptance remain external and false until separately evidenced.

## Twentieth fresh 20-round sequential corrective assurance (R20)

R20 froze the green R19 exact head `f6ffbc43edf2679590595f5bb1db7c3fec652d25`. R01–R19 have been completed sequentially; defects were established and corrected in rounds **01, 02, 03, 04, 05, 08, 09, 16, 17 and 19**. Clean completed rounds are **06, 07, 10, 11, 12, 13, 14, 15 and 18**. R20 itself remains the pending final exact-head release-integrity round.

R20 hardens ambiguous COMMIT recovery for professional passports, signed claims/outbox, immutable application submission, reviewer/admin workflows, evidence rotation/review/grants, privacy erasure, retention and operational expiry reconciliation. It also fixes temporary professional-claim delivery so a retry can still reach terminal File 00 acknowledgement without stale failure overwriting accepted/rejected state.

The permanent R20 ledger is `REVIEW-20-ROUNDS-RC6-R20.md`; the executable gate is `tests/twenty-round-audit-r20.py`. Its final required result is **20 PASS / 0 FAIL** after the temporary R20 corrective helper is removed and the exact-head authoritative workflow passes. The installable allowlist remains **62 entries**. Staging/live/operational acceptance remain external and false until separately evidenced.
