# File 09 — Advanced Professional Trust & Verification Extensions 2026

Runtime target: **1.3.0 RC6**  
Advanced Trust contract: **1.1.0**  
Advanced Trust schema: **2**  
80-round assurance: **49 defect-bearing rounds corrected; 31 clean rounds**

## Governing law

These additions extend **File 09 — Global Doctor Onboarding and Verification** only inside its canonical professional-verification boundary. File 00 remains identity/membership/security-assertion owner; File 02 remains professional-step-up owner; File 03 remains public-profile owner; File 07/File 26 remain directory/search/ranking owners; File 08 remains clinic/appointment owner; File 19 remains notification-delivery owner; File 24 remains platform-wide security/privacy/compliance assurance owner.

External issuer, equivalency, affiliation, translation and AI services are adapters. They may return facts, confidence and explanations, but **never approve, reject, suspend, revoke or otherwise finalize a professional verification decision**. Human authorized review remains mandatory. Private professional evidence is never made searchable or public by this layer.

## The 24 approved enhancements

1. **Primary-Source Verification Hub** — registered issuer adapter through `gdo_primary_source_verification`; unavailable adapters fail safe and never auto-verify. RC6 minimizes default provider requests, bounds/sanitizes responses, rate-limits checks and normalizes provider expiry timestamps.
2. **Trusted Issuer Registry** — versioned `gdo_trusted_issuers` registry; every new issuer begins `proposed` and initial verification requires a second authorized reviewer. Secrets are never stored in registry metadata.
3. **Credential Authenticity Engine** — local technical checks, hash reuse analysis and reviewer-required authenticity records; local checks never establish professional authenticity by themselves.
4. **Jurisdiction Rules Engine** — versioned evidence/renewal/scope rules. RC6 uses draft-first governance, independent second approval, effective dates and immutable approved versions; rule changes require a new version.
5. **Cross-Border Credential Equivalency Map** — adapter-backed equivalency assessment is reviewer evidence and never grants a legal license.
6. **Continuous License Monitoring** — scheduled primary-source checks with explicit degraded retry/backoff and adverse-result escalation; monitor never silently mutates professional state.
7. **Event-Driven Reverification** — exact File 09 lifecycle facts and explicit `to_state` schedule targeted reverification; fuzzy event-name matching is forbidden.
8. **Professional Verification Passport** — signed, revocable, time-bounded professional receipt. RC6 serializes issuance/supersession and rechecks current File 09 + File 00 truth on every public lookup.
9. **Public Verification QR Card** — privacy-safe current verification URL/QR payload; public GET is read-only, non-cacheable and exposes no credential evidence.
10. **Verification Scope Badge** — public-safe matrix distinguishes Identity, Qualification, Institution, Registration, License and Current Status with `verified`, `pending`, `not_verified`, `not_applicable` semantics.
11. **Institutional Affiliation Verification** — adapter-backed affiliation fact; File 03 remains profile owner.
12. **Professional History Timeline** — append-only professional history with an allowlisted `public_safe` projection; private decision/risk data remains private.
13. **Credential Translation Workspace** — translation assistance is derived aid; original credential remains authoritative.
14. **AI-Assisted Evidence Review** — bounded classification/extraction/expiry/mismatch/duplicate clues only; decision/approve/reject/professional-status/clinical-authorization outputs are discarded.
15. **Explainable Risk Intelligence** — current risk/check reasons for authorized human review; opaque automated rejection is forbidden.
16. **Fraud-Ring & Collusion Detection** — credential-reuse network clues are deduplicated and require human investigation/resolution.
17. **Reviewer Conflict-of-Interest Engine** — conflict declaration is authorized, application-bound, deduplicated, auditable and resolvable; conflict logic only narrows authorization.
18. **Adaptive Dual Review** — high/critical risk, cross-border and current appeal/suspension/revocation contexts can require independent dual review; extension filters may strengthen but not weaken baseline requirements.
19. **Smart Reviewer Routing** — current File 00 authorization, jurisdiction/language, workload, `max_open_cases` and conflicts produce reviewer candidates.
20. **Reviewer Calibration Laboratory** — agreement/overturn metrics use completed quality samples from the existing File 09 quality ledger.
21. **Applicant Verification Command Center** — private state, missing requirements, checks, verification matrix and next action; owner-only/no-cache surface.
22. **Resumable Secure Evidence Upload** — ordered private chunks, exact geometry/order/size/hash, serialized DB/file state, atomic finalization and canonical `GDO_Evidence::stage_upload()` malware/type/quota/encryption handoff. Expired `open`, `failed` and `finalizing` sessions are cleaned.
23. **Secure Evidence Viewing Room** — short-lived, step-up/session/one-time reviewer grant reuses the canonical mature `GDO_Evidence::issue_view_grant()` path; reviewer watermark and `download_allowed=false` are presentation contracts. No second grant backend is authorized.
24. **Professional Trust Transparency Dashboard** — fixed-window aggregate-only metrics with minimum-cohort suppression; no applicant-level private data or arbitrary small-window differencing.

## File 09-owned Advanced Trust data stores

- `gdo_trusted_issuers`
- `gdo_jurisdiction_rules`
- `gdo_credential_checks`
- `gdo_professional_history`
- `gdo_reviewer_conflicts`
- `gdo_verification_passports`
- `gdo_monitor_state`
- `gdo_upload_sessions`

Advanced Trust schema **2** is additive. It preserves the eight schema-1 tables and version-controls the application/status indexes used by passport lifecycle and resumable-session reconciliation. These records do not duplicate profile, search/ranking, clinic, membership or notification-delivery truth.

## REST surfaces

- `GET /wp-json/gdo/v1/trust/command-center`
- `POST /wp-json/gdo/v1/trust/issuer`
- `POST /wp-json/gdo/v1/trust/issuer/{uuid}/review`
- `POST /wp-json/gdo/v1/trust/jurisdiction`
- `POST /wp-json/gdo/v1/trust/check/{application_id}/{evidence_id}`
- `POST /wp-json/gdo/v1/trust/upload/start`
- `POST /wp-json/gdo/v1/trust/upload/{uuid}/chunk/{index}`
- `POST /wp-json/gdo/v1/trust/upload/{uuid}/finalize`
- `POST /wp-json/gdo/v1/trust/viewing-room`
- `GET /wp-json/gdo/v1/public/passport/{uuid}`
- `GET /wp-json/gdo/v1/public/transparency`

Privileged issuer/jurisdiction/reviewer operations require File 00 capability and current File 02 step-up. Reviewer object scope is rechecked at action time. Applicant upload/command-center endpoints recheck current authenticated owner and application state.

## Adapter contracts

The following filters are extension points, not decision owners:

- `gdo_primary_source_verification`
- `gdo_primary_source_request_minimized`
- `gdo_credential_equivalency_assessment`
- `gdo_institutional_affiliation_verification`
- `gdo_credential_translation_assistance`
- `gdo_ai_evidence_assistance`
- `gdo_reviewer_conflict_detected`
- `gdo_requires_dual_review`

Provider secrets belong in deployment secrets/configuration, never GitHub or WordPress public metadata. Provider facts/hints are bounded and sanitized before persistence.

## Privacy, retention and derived-credential law

Advanced Trust credential checks/history/passports/conflicts are included in WordPress personal-data export. Personal-data erasure deletes derivative passports and temporary upload/monitor records, anonymizes retained accountability rows, and preserves only justified minimal audit evidence. Application retention is scoped to the expired application; it must not globally erase a reviewer’s identity from unrelated cases. `.chunk-*` private-storage orphans are part of the cleanup graph.

## 80-round corrective assurance

The Founder-requested RC6 review is recorded in `REVIEW-80-ROUNDS-RC6.md` and executed permanently by `tests/eighty-round-audit.py`. It contains 80 independent controls: **49 defect-bearing rounds corrected and 31 clean rounds**. A defect found in any round was corrected and the same control rechecked before the next round.

## Staging acceptance requirements

Repository completion does not prove staging/live correctness. RC6 still requires exact-head PHP 7.4/8.3 QA, deterministic RC6 package/SBOM, Hostinger migration to Advanced Trust schema 2, real issuer/provider failure modes, monitor retry/adverse behavior, chunk interruption/race tests, reviewer conflict/dual-review journeys, passport issue/supersession/revocation/expiry tests, private evidence-room tests, privacy/retention tests, backup/restore, mobile/desktop, RTL/LTR, keyboard, screen reader, zoom, reduced motion, weak-connection acceptance and two fresh staging review/fix/retest cycles before Founder acceptance.
