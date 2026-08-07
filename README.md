# File 09 — Global Doctor Onboarding and Verification

This repository contains the canonical File 09 implementation candidate for the Sabri Social Homeopathy Platform.

## Current evidence status

| Layer | Status |
|---|---|
| Plan/specification | Complete baseline |
| Source candidate | `1.2.0`, schema `6` |
| Local automated QA | Required suites defined and repeatable |
| Deterministic package | Produced by the release workflow |
| Hostinger-equivalent staging | Pending external execution |
| Production/live/operational | Not authorized |

File 09 owns doctor applications, private professional evidence, review, decision, renewal, suspension/revocation, appeal and signed professional-decision claims. It does not own general identity, login, public profiles, directory ranking, clinics or notification transport.

Read `TRACEABILITY.md`, `SECURITY-PRIVACY.md`, `MIGRATION-ROLLBACK-1.2.0.md`, `OPERATIONS.md`, `STAGING-ACCEPTANCE.md` and `RELEASE-MANIFEST-1.2.0.md` before deployment.

## RC2 integration boundary
File 09 publishes fail-closed, public-safe professional eligibility projections for File 03 profiles, File 07 directory discovery and File 08 clinics. It does not expose credential evidence or transfer canonical ownership.
