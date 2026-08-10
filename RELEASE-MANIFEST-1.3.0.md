# File 09 — Release Manifest 1.3.0 RC5

Canonical plugin: **Global Doctor Onboarding and Verification**  
Package root: `global-doctor-onboarding-09/`  
Runtime: `1.3.0`  
Core schema: `6`  
Advanced trust schema: `1`  
Advanced trust contract: `1.0.0`

## Scope

1.3.0 RC5 contains the complete prior File 09 application/evidence/review/decision/renewal/suspension/revocation/appeal/privacy implementation plus the 24 approved **Advanced Professional Trust & Verification Extensions 2026** described in `ADVANCED-TRUST-24.md`.

## Safety invariants

- File 09 owns professional verification decisions; no external issuer, AI, translation, equivalency or affiliation provider may finalize a decision.
- File 00 remains identity/membership/security authority.
- Private evidence remains encrypted and non-searchable.
- Public passport/card responses contain only public-safe verification scope, expiry and disclaimer data.
- Search/ranking remains File 07/File 26 responsibility; donation/payment never affects verification or rank.
- Continuous monitoring raises a reverification requirement on adverse external facts; it does not silently auto-revoke.
- Resumable uploads must finish through the existing `GDO_Evidence::stage_upload()` malware/type/quota/encryption path.
- Secure viewing-room grants are short-lived, step-up-bound and no-download by contract.

## Deterministic package gate

`tools/build-release.py` builds `global-doctor-onboarding-09-1.3.0-RC5.zip` twice using the exact release allowlist. `tools/verify-release.py` requires identical entry order, fixed ZIP timestamps, source/package SHA-256 parity and an exact-head generated SPDX 2.3 SBOM whose package identity is `1.3.0-RC5`.

## Acceptance state

- Specified: candidate complete
- Coded: candidate complete
- Packaged: only after latest exact-head workflow succeeds
- Automated QA: only after latest exact-head workflow succeeds on PHP 7.4 and PHP 8.3
- Staging Accepted: false
- Live Deployed: false
- Operational: false

Hostinger staging remains mandatory for real WordPress/MySQL migration, provider adapters, chunk interruption/resume, private storage, reviewer conflict/dual review, passport expiry/revocation, public-safe projections, accessibility, backup/restore and two fresh staging review/fix/retest rounds.
