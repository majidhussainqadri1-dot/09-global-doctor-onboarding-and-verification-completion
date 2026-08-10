# File 09 — Global Doctor Onboarding and Verification

This repository contains the canonical File 09 implementation candidate for the Sabri Social Homeopathy Platform.

## Current evidence status

| Layer | Status |
|---|---|
| Latest central + File 09 specification | Traced in `TRACEABILITY.md` |
| Approved Advanced Trust 24 amendment | Implemented/traced candidate in `ADVANCED-TRUST-24.md` |
| Source candidate | `1.3.0`, core schema `6`, advanced trust schema `1`, RC5 candidate |
| Exact-head automated QA | Must pass on the current exact commit |
| Deterministic package | RC5 workflow output after exact-head QA |
| Hostinger-equivalent staging | Pending external execution |
| Production/live/operational | Not authorized |

File 09 owns doctor applications, private professional evidence, professional verification review/decision, renewal, suspension/revocation, appeal, signed professional-decision claims and the new File 09 professional-trust records. It does **not** own general identity, login, public profiles, directory/search ranking, clinics, notification transport or platform-wide security governance.

Read `TRACEABILITY.md`, `ADVANCED-TRUST-24.md`, `SECURITY-PRIVACY.md`, `MIGRATION-ROLLBACK-1.3.0.md`, `OPERATIONS.md`, `STAGING-ACCEPTANCE.md` and `RELEASE-MANIFEST-1.3.0.md` before deployment.

## 1.3.0 RC5 — Advanced Professional Trust

The approved 24 enhancements are implemented as an additive trust layer over the existing File 09 workflow:

- primary-source verification adapters and trusted issuer registry;
- technical credential authenticity records and jurisdiction policy registry;
- cross-border equivalency and institutional-affiliation adapters;
- continuous/event-driven reverification without silent auto-revocation;
- signed, revocable professional verification passports with public-safe QR payload;
- scoped verification matrix rather than an ambiguous single verified tick;
- public-safe professional history projection;
- translation and AI reviewer-assistance adapters with automated final decisions forbidden;
- explainable risk and credential-reuse/fraud-ring clues;
- conflict-of-interest enforcement, adaptive dual review and smart reviewer routing;
- reviewer calibration metrics;
- applicant command center;
- ordered resumable private evidence upload that still terminates in the canonical encrypted evidence path;
- short-lived, step-up-bound, no-download secure evidence viewing-room grants;
- aggregate-only professional-trust transparency metrics.

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

File 09 exposes public-safe verification facts only. Private application/evidence data remain non-indexed and non-public; donation/payment confers no verification, visibility or ranking advantage.

## Assurance history

The RC3 corrected tree completed 40 independent review/fix rounds: **13 rounds produced defects that were corrected; 27 rounds produced no new defect**. RC4 then closed newer central/File-09 cross-file and exact-head SBOM gaps. RC5 reopens exact-head assurance because the approved Advanced Trust 24 amendment changes source, schema, release documents and workflow.

No previous green RC3/RC4 run is accepted as proof for RC5. External Hostinger staging, real provider failure modes, two fresh staging reviews, backup/restore, rollback and explicit Founder acceptance remain mandatory before any live/operational claim.
