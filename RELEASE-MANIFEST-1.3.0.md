# File 09 — Release Manifest 1.3.0 RC6

Canonical plugin: **Global Doctor Onboarding and Verification**  
Package root: `global-doctor-onboarding-09/`  
Runtime: `1.3.0`  
Core schema: `6`  
Advanced Trust schema: `2`  
Advanced Trust contract: `1.1.0`  
Review baseline: `6d5c2850dbf86ce954e0c2fdef8adf36d2dbf1f1`

## Scope

RC6 preserves the complete prior File 09 application/evidence/review/decision/renewal/suspension/revocation/appeal/privacy implementation and all 24 approved **Advanced Professional Trust & Verification Extensions 2026**. It additionally contains the Founder-requested 80-round corrective assurance: **49 defect-bearing rounds corrected; 31 clean rounds**.

The RC6 corrective layer fixes lifecycle-state drift, issuer/rule governance, monitoring retry/backoff, passport validity/supersession/read semantics, resumable-upload concurrency, viewing-room grant duplication, REST object authorization, provider-data minimization, Advanced Trust privacy/retention coverage, schema/index migration and release/plan evidence drift.

## Safety invariants

- File 09 owns professional verification decisions; no external issuer, AI, translation, equivalency or affiliation provider may finalize a decision.
- File 00 remains identity/membership/security authority; File 02 remains professional step-up authority.
- Private evidence remains encrypted and non-searchable.
- Public passport/card responses contain only current public-safe verification scope, expiry and disclaimer data; public passport GET is read-only and non-cacheable.
- Search/ranking remains File 07/File 26 responsibility; donation/payment never affects verification or rank.
- Continuous monitoring raises a reverification requirement on adverse external facts; it does not silently auto-revoke.
- Resumable uploads finish through `GDO_Evidence::stage_upload()` and use serialized chunk/finalize state.
- Secure viewing-room grants reuse the canonical one-time/session-bound evidence-grant path and are no-download by contract.
- Advanced Trust privacy export, erasure and app-scoped retention are part of the lifecycle graph.

## Deterministic package gate

`tools/build-release.py` builds `global-doctor-onboarding-09-1.3.0-RC6.zip` twice using the exact **52-entry** release allowlist. `tools/verify-release.py` requires identical entry order, fixed ZIP timestamps, source/package SHA-256 parity and an exact-head generated SPDX 2.3 SBOM whose package identity is `1.3.0-RC6`.

The exact-head source gate runs all legacy suites plus `tests/advanced-trust-24.py` and `tests/eighty-round-audit.py` on PHP 7.4 and PHP 8.3. No earlier RC5 run can certify RC6. Because this manifest itself is inside the release allowlist, **the authoritative source/package/QA result is always the workflow result for the exact final commit that contains this manifest**; any later commit automatically reopens the gate.

## Acceptance state

- Specified: candidate complete
- Coded: RC6 corrective candidate complete at repository-source level
- 80-round review: complete — 49 defect-bearing rounds corrected; 31 clean rounds
- Packaged: determined only by the authoritative exact-final-commit RC6 package job
- Automated QA: determined only by the authoritative exact-final-commit PHP 7.4/8.3 RC6 workflow
- Staging Accepted: false
- Live Deployed: false
- Operational: false

Hostinger staging remains mandatory for real WordPress/MySQL schema-2 migration, issuer/provider adapters, monitor retry/adverse behavior, chunk interruption/resume/races, private storage, reviewer conflict/dual review, passport issue/supersession/expiry/revocation, privacy/retention, public-safe projections, accessibility, backup/restore and two fresh staging review/fix/retest rounds.
