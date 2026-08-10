# File 09 — Global Doctor Onboarding and Verification

This repository contains the canonical File 09 implementation candidate for the Sabri Social Homeopathy Platform.

## Current evidence status

| Layer | Status |
|---|---|
| Latest central + File 09 specification | Traced in `TRACEABILITY.md` |
| Source candidate | `1.2.0`, schema `6`, RC4 candidate |
| Exact-head automated QA | Must pass on the current exact commit |
| Deterministic package | RC4 workflow output after exact-head QA |
| Hostinger-equivalent staging | Pending external execution |
| Production/live/operational | Not authorized |

File 09 owns doctor applications, private professional evidence, review, decision, renewal, suspension/revocation, appeal and signed professional-decision claims. It does **not** own general identity, login, public profiles, directory ranking, clinics, search ranking or notification transport.

Read `TRACEABILITY.md`, `SECURITY-PRIVACY.md`, `MIGRATION-ROLLBACK-1.2.0.md`, `OPERATIONS.md`, `STAGING-ACCEPTANCE.md` and `RELEASE-MANIFEST-1.2.0.md` before deployment.

## RC4 latest-plan integration boundary

RC4 closes the latest central-plan/File-09 repository gaps without taking ownership from companion modules:

- File 03, File 07 and File 08 continue to consume fail-closed verification eligibility projections.
- File 21 publishing and File 23 publishing-dashboard eligibility now receive the same current, public-safe File 09 professional truth.
- File 26 receives a versioned doctor-verification projection/connector contract. The File 09 connector is deliberately `contract_tested`, not auto-activated, so private applications/evidence are never indexed and canonical public doctor documents remain with their public-profile/directory owners.
- File 19 uses the current `sun.event.v1` producer/event contract first; the older `SUN_Core`/`sabri_notify` paths remain compatibility fallbacks only. File 09 does not send email/SMS/push itself.
- File 20 receives File 09's managed application page through `sabri_shell_page_contracts`; File 20 remains the only application-shell owner.
- All projections identify File 09 as source of professional truth, recheck current File 00/File 09 state at use time, expose no credential evidence, grant no clinical authorization and confer no donor/payment ranking advantage.

## Assurance history

The RC3 corrected tree completed 40 independent review/fix rounds: **13 rounds produced defects that were corrected; 27 rounds produced no new defect**. RC4 adds a fresh latest-plan parity review and permanent regression gate for F09-CEN-01/F09-CEN-02, File 19, File 20 and File 26 contracts, canonical naming and exact-head generated SBOM integrity.

External Hostinger staging, two fresh staging reviews, backup/restore, rollback and explicit Founder acceptance remain mandatory before any live/operational claim.
