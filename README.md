# File 09 — Global Doctor Onboarding and Verification

This repository contains the canonical File 09 implementation candidate for the Sabri Social Homeopathy Platform.

## Current evidence status

| Layer | Status |
|---|---|
| Latest central + File 09 specification | Traced in `TRACEABILITY.md` |
| Approved Advanced Trust 24 amendment | Implemented/traced candidate in `ADVANCED-TRUST-24.md` |
| Founder-requested 80-round review | `REVIEW-80-ROUNDS-RC6.md`: 80 rounds; 49 defect-bearing corrected; 31 clean |
| Source candidate | `1.3.0`, core schema `6`, Advanced Trust schema `2`, contract `1.1.0`, RC6 candidate |
| Exact-head automated QA | Must pass on the final exact RC6 commit |
| Deterministic package | RC6 workflow output after final exact-head QA |
| Hostinger-equivalent staging | Pending external execution |
| Production/live/operational | Not authorized |

File 09 owns doctor applications, private professional evidence, professional verification review/decision, renewal, suspension/revocation, appeal, signed professional-decision claims and File 09 professional-trust records. It does **not** own general identity, login, public profiles, directory/search ranking, clinics, notification transport or platform-wide security governance.

Read `TRACEABILITY.md`, `ADVANCED-TRUST-24.md`, `REVIEW-80-ROUNDS-RC6.md`, `SECURITY-PRIVACY.md`, `MIGRATION-ROLLBACK-1.3.0.md`, `OPERATIONS.md`, `STAGING-ACCEPTANCE.md` and `RELEASE-MANIFEST-1.3.0.md` before deployment.

## 1.3.0 RC6 — Advanced Professional Trust + Eighty-Round Corrective Assurance

RC6 preserves all 24 approved Advanced Professional Trust enhancements and corrects the defects found by 80 independent review controls. The principal corrected areas are:

- proposed-first trusted issuer governance with independent initial verification;
- draft-first, independently approved and immutable jurisdiction-rule versions;
- minimized/bounded provider requests/results, rate limits and provider expiry normalization;
- degraded monitor retry/backoff and exact event-driven reverification;
- signed professional passports serialized under application locking, current-state rechecked and non-cacheable on public lookup;
- four-state verification-scope semantics and renewal-predecessor continuity;
- conflict declaration/resolution, dual-review monotonicity, reviewer workload limits and completed-sample calibration;
- deduplicated fraud-ring signals and explainable human-reviewed risk;
- resumable private upload DB/file serialization, exact chunk geometry, atomic finalization and stale-finalizing cleanup;
- canonical one-time/session/step-up evidence viewing grants rather than a second grant backend;
- object-scope REST authorization and structured per-check failures;
- fixed-window, minimum-cohort public trust transparency;
- Advanced Trust privacy export, erasure, application-scoped retention and `.chunk-*` orphan cleanup;
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

## Assurance history

RC3 completed 40 independent review/fix rounds: **13 defect-bearing rounds corrected; 27 clean rounds**. RC4 closed later central-plan/cross-file/SBOM gaps. RC5 implemented Advanced Trust 24 and became the frozen baseline for the Founder-requested review. RC6 then completed **80 additional independent controls: 49 defect-bearing rounds corrected and 31 clean rounds**.

No previous green RC3/RC4/RC5 run is accepted as proof for RC6. The final RC6 exact HEAD must pass the complete PHP 7.4/8.3 workflow, 80-round executable gate, deterministic double build, 52-entry package parity and generated exact-head SPDX SBOM before repository package/QA status can be called green. External Hostinger staging, real provider failure modes, two fresh staging reviews, backup/restore, rollback and explicit Founder acceptance remain mandatory before any live/operational claim.
