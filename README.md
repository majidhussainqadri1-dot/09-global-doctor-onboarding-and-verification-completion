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
| **Fresh 10-round review R12** | **10 defect-bearing corrected; 0 clean; exact-head gate required** |
| Source candidate | `1.3.0`, core schema `6`, Advanced Trust schema `2`, contract `1.1.0`, RC6 candidate |
| Exact-head automated QA | Must pass on the final exact RC6/R12 commit |
| Deterministic package | 62-entry RC6 allowlist; exact-head workflow output only |
| Hostinger-equivalent staging | Pending external execution |
| Production/live/operational | Not authorized |

**Eleventh fresh 80-round corrective assurance — historical R11:** 17 defect-bearing rounds corrected; 63 clean; its own exact-head result was 80/80 PASS. R12 supersedes it only as current repository review evidence, not as historical evidence.

File 09 owns doctor applications, private professional evidence, professional verification review/decision, renewal, suspension/revocation, appeal, signed professional-decision claims and File 09 professional-trust records. It does **not** own general identity, login, public profiles, directory/search ranking, clinics, notification transport or platform-wide security governance.

Read `TRACEABILITY.md`, `ADVANCED-TRUST-24.md`, all `REVIEW-80-ROUNDS-RC6*.md` ledgers, `REVIEW-10-ROUNDS-RC6-R12.md`, `SECURITY-PRIVACY.md`, `MIGRATION-ROLLBACK-1.3.0.md`, `OPERATIONS.md`, `STAGING-ACCEPTANCE.md` and `RELEASE-MANIFEST-1.3.0.md` before deployment.

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

The final exact HEAD must pass the complete PHP 7.4/8.3 workflow, all R1–R11 executable 80-round gates, the R12 ten-round gate, deterministic double build, **62-entry** package parity and generated exact-head SPDX SBOM before repository package/QA status can be called green.

External Hostinger staging, real provider failure modes, real applicant/reviewer lifecycle journeys, private storage/key/scanner checks, two fresh staging review→fix→full-retest cycles, backup/restore, rollback and explicit Founder acceptance remain mandatory before any live/operational claim.

## Twelfth fresh 10-round corrective assurance (R12)

R12 froze the previously green R11 exact head `0df40731282a4dc905a12b37e5fa5aa4604dc68a` and performed a new independent ten-round review. **Every one of the 10 rounds found a repository defect and was corrected before the next round advanced.**

R12 corrected: reviewer/finalizer unchecked evidence/profile snapshot reads; passport issuance preceding explicit current professional-claim acknowledgement; unchecked immutable submission evidence inventory; frontend completeness/evidence DB uncertainty and repeated evidence reads; draft pre-read/post-write reload uncertainty; migration-evidence/backfill/quarantine read uncertainty; silent/ambiguous legacy credential migration failures; notification outbox stale DB state and stale-claim failure clobbering; retention unchecked evidence/orphan-cleanup false-success; and missing permanent R12 executable/release evidence.

The permanent R12 ledger is `REVIEW-10-ROUNDS-RC6-R12.md`; the executable gate is `tests/ten-round-audit-r12.py` and must report **10 PASS / 0 FAIL**. The authoritative workflow remains `.github/workflows/file09-rc2-final.yml`. R12 QA files are repository evidence and are not added to the installable allowlist, which remains **62 entries**. Because package-owned PHP source changed, the final RC6 ZIP checksum/artifact must nevertheless be regenerated from the exact final HEAD.
