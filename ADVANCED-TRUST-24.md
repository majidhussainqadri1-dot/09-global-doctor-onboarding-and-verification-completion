# File 09 — Advanced Professional Trust & Verification Extensions 2026

Runtime target: **1.3.0 RC5**  
Advanced trust contract: **1.0.0**  
Advanced trust schema: **1**

## Governing law

These additions extend **File 09 — Global Doctor Onboarding and Verification** only inside its canonical professional-verification boundary. File 00 remains identity/membership/security-assertion owner; File 03 remains public-profile owner; File 07/File 26 remain directory/search/ranking owners; File 08 remains clinic/appointment owner; File 19 remains notification-delivery owner; File 24 remains platform-wide security/privacy/compliance assurance owner.

External issuer, equivalency, affiliation, translation and AI services are adapters. They may return facts, confidence and explanations, but **never approve, reject, suspend, revoke or otherwise finalize a professional verification decision**. Human authorized review remains mandatory. Private professional evidence is never made searchable or public by this layer.

## The 24 approved enhancements

1. **Primary-Source Verification Hub** — `primary_source_verify()` calls a registered issuer adapter through `gdo_primary_source_verification`; unavailable adapters fail to `provider_unavailable` and never auto-verify.
2. **Trusted Issuer Registry** — versioned `gdo_trusted_issuers` registry with jurisdiction, issuer type, assurance state and adapter key; secrets are never stored in the public repository or registry metadata.
3. **Credential Authenticity Engine** — local technical checks, hash reuse analysis and reviewer-required authenticity records; local checks explicitly cannot establish professional authenticity by themselves.
4. **Jurisdiction Rules Engine** — approved versioned jurisdiction rule records for evidence, renewal and scope policy; runtime consumers can retrieve the current approved rule without duplicating File 00 identity policy.
5. **Cross-Border Credential Equivalency Map** — adapter-backed equivalency assessments recorded as reviewer evidence; equivalency never grants a legal license.
6. **Continuous License Monitoring** — scheduled monitor state with periodic primary-source checks and adverse-result escalation.
7. **Event-Driven Reverification** — canonical File 09 audit facts schedule targeted reverification after submission, professional status changes, renewal, appeal, suspension or other material events.
8. **Professional Verification Passport** — signed, revocable, time-bounded professional verification receipt with public-safe scope only.
9. **Public Verification QR Card** — passport issuance returns a privacy-safe `verification_url` and `qr_payload`; presentation layers may render the payload as QR without exposing evidence.
10. **Verification Scope Badge** — public-safe matrix distinguishes Identity, Qualification, Institution, Registration, License and Current Status instead of one ambiguous tick.
11. **Institutional Affiliation Verification** — adapter-backed affiliation check stored as a reviewer-required professional fact.
12. **Professional History Timeline** — immutable-style append-only professional history projection with an explicit `public_safe` flag; private events are not exposed publicly.
13. **Credential Translation Workspace** — translation assistance is stored separately; the original document remains authoritative.
14. **AI-Assisted Evidence Review** — only classification, field extraction, expiry detection, mismatch highlighting and duplicate clues; decision/approve/reject keys are discarded.
15. **Explainable Risk Intelligence** — current risk signals and credential-check explanations are exposed to authorized review instead of opaque rejection scores.
16. **Fraud-Ring & Collusion Detection** — repeated credential hashes across otherwise separate applications create related risk signals for human investigation.
17. **Reviewer Conflict-of-Interest Engine** — declared conflicts narrow `gdo_reviewer_scope_allows`; conflict logic cannot broaden authorization.
18. **Adaptive Dual Review** — high/critical risk, cross-border cases and appeal/suspension/revocation states can require independent dual review.
19. **Smart Reviewer Routing** — current File 00 authorization, reviewer jurisdiction/language, open workload and conflicts produce ranked candidate reviewers.
20. **Reviewer Calibration Laboratory** — quality-sample agreement and overturn rates are aggregated from the existing reviewer-quality ledger.
21. **Applicant Verification Command Center** — private status, missing requirements, check status, verification matrix and next action are available by shortcode and REST.
22. **Resumable Secure Evidence Upload** — ordered private chunks, strict size/count/hash validation and final handoff into the existing `GDO_Evidence::stage_upload()` validation/encryption path.
23. **Secure Evidence Viewing Room** — short-lived step-up-bound `secure_room` access grant, reviewer watermark and `download_allowed=false` contract; raw evidence is not returned by the grant endpoint.
24. **Professional Trust Transparency Dashboard** — aggregate-only decision, verification, appeal, quality and fraud metrics; no applicant-level private data.

## New data stores

- `gdo_trusted_issuers`
- `gdo_jurisdiction_rules`
- `gdo_credential_checks`
- `gdo_professional_history`
- `gdo_reviewer_conflicts`
- `gdo_verification_passports`
- `gdo_monitor_state`
- `gdo_upload_sessions`

These are File 09-owned professional-trust records. They do not duplicate profile, search/ranking, clinic, membership or notification-delivery truth.

## New REST surfaces

- `GET /wp-json/gdo/v1/trust/command-center`
- `POST /wp-json/gdo/v1/trust/issuer`
- `POST /wp-json/gdo/v1/trust/jurisdiction`
- `POST /wp-json/gdo/v1/trust/check/{application_id}/{evidence_id}`
- `POST /wp-json/gdo/v1/trust/upload/start`
- `POST /wp-json/gdo/v1/trust/upload/{uuid}/chunk/{index}`
- `POST /wp-json/gdo/v1/trust/upload/{uuid}/finalize`
- `POST /wp-json/gdo/v1/trust/viewing-room`
- `GET /wp-json/gdo/v1/public/passport/{uuid}`
- `GET /wp-json/gdo/v1/public/transparency`

Privileged issuer/jurisdiction/reviewer operations require File 00 capability and a current File 02 professional step-up. Applicant upload/command-center endpoints recheck the current authenticated owner and application state.

## Adapter contracts

The following filters are extension points, not decision owners:

- `gdo_primary_source_verification`
- `gdo_credential_equivalency_assessment`
- `gdo_institutional_affiliation_verification`
- `gdo_credential_translation_assistance`
- `gdo_ai_evidence_assistance`
- `gdo_reviewer_conflict_detected`
- `gdo_requires_dual_review`

Provider secrets belong in deployment secrets/configuration, never GitHub or WordPress public metadata.

## Release truth

Repository/code completion does not prove staging/live correctness. RC5 still requires exact-head PHP 7.4/8.3 QA, deterministic package/SBOM, Hostinger migration of the advanced trust schema, real issuer-adapter failure modes, chunked-upload interruption tests, reviewer conflict/dual-review journeys, public-passport revocation/expiry tests, private evidence-room tests, backup/restore, accessibility and two fresh staging review/fix/retest cycles before Founder acceptance.
