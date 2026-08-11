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
| Tenth fresh 80-round review R10 | 19 defect-bearing corrected; 61 clean; final exact-head gate required |
| Source candidate | `1.3.0`, core schema `6`, Advanced Trust schema `2`, contract `1.1.0`, RC6 candidate |
| Exact-head automated QA | Must pass on the final exact RC6/R10 commit |
| Deterministic package | 61-entry RC6 allowlist; exact-head workflow output only |
| Hostinger-equivalent staging | Pending external execution |
| Production/live/operational | Not authorized |

File 09 owns doctor applications, private professional evidence, professional verification review/decision, renewal, suspension/revocation, appeal, signed professional-decision claims and File 09 professional-trust records. It does **not** own general identity, login, public profiles, directory/search ranking, clinics, notification transport or platform-wide security governance.

Read `TRACEABILITY.md`, `ADVANCED-TRUST-24.md`, all `REVIEW-80-ROUNDS-RC6*.md` ledgers, `SECURITY-PRIVACY.md`, `MIGRATION-ROLLBACK-1.3.0.md`, `OPERATIONS.md`, `STAGING-ACCEPTANCE.md` and `RELEASE-MANIFEST-1.3.0.md` before deployment.

## 1.3.0 RC6 — Advanced Professional Trust + Ten Fresh 80-Round Corrective Assurance Cycles

RC6 preserves all 24 approved Advanced Professional Trust enhancements. R1–R9 remain immutable historical review evidence. R10 froze the R9 exact-head candidate `ec3ca2dd715e05b66cf29c42f2f996c80987fcd7` and performed a new independent source review rather than treating the prior green run as current proof.

R10 corrected nineteen additional repository defects in rounds 04–22. The corrections principally close fail-visible database-uncertainty gaps in public verification projection, private application REST/autosave, draft creation/save, consent/submission, privileged application/reviewer/evidence/appeal reads, evidence replacement/decrypt/key rotation, state transition, professional claim issuance and completeness evaluation; require recent step-up for privileged health access; and make post-decision quality-sampling failure observable instead of silent.

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

The ten fresh 80-round review ledgers are preserved separately so later correction never rewrites what an earlier review found. The current R10 executable gate is `tests/eighty-round-audit-r10.py`; the current immutable ledger is `REVIEW-80-ROUNDS-RC6-R10.md`.

No previous green R1–R9 run is accepted as proof for the R10-corrected source. The final exact HEAD must pass the complete PHP 7.4/8.3 workflow, all R1–R10 executable review gates, deterministic double build, **61-entry** package parity and generated exact-head SPDX SBOM before repository package/QA status can be called green.

External Hostinger staging, real provider failure modes, real applicant/reviewer lifecycle journeys, private storage/key/scanner checks, two fresh staging review→fix→full-retest cycles, backup/restore, rollback and explicit Founder acceptance remain mandatory before any live/operational claim.
