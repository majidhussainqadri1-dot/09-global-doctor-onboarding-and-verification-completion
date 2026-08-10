# File 09 Requirements Traceability — Latest Central + File 09 + Advanced Trust 24

Candidate: `1.3.0 RC5` / core schema `6` / Advanced Trust schema `1`  
Canonical owner: **File 09 — Global Doctor Onboarding and Verification**

A requirement is not accepted merely because a class/function exists. This document maps repository intent; exact-head automated evidence, Hostinger staging and live evidence remain separate gates.

## Existing functional requirements

| Requirement | Capability | Source / evidence |
|---|---|---|
| F09-FR-001 | Eligibility precheck | `GDO_Policy`, File 00 assertions; policy/membership tests |
| F09-FR-002 | Guided wizard | `GDO_Frontend`, onboarding JS; completion/security tests |
| F09-FR-003 | Draft save/resume | `GDO_Application`, REST autosave; concurrency staging |
| F09-FR-004 | Evidence upload | `GDO_Evidence`, crypto/storage; security tests |
| F09-FR-005 | Completeness gate | `GDO_Application::completeness`; policy tests |
| F09-FR-006 | Submission | owner state machine/outbox; idempotency tests |
| F09-FR-007 | Reviewer assignment | `GDO_Admin`, reviewer profiles; scope tests |
| F09-FR-008 | Evidence review | evidence grants/admin review; IDOR/step-up tests |
| F09-FR-009 | More information | replacement/versioned evidence; state tests |
| F09-FR-010 | Decision | recommendation/finalization/separation of duties |
| F09-FR-011 | Claim issuance | `GDO_Claims`, File 00 acknowledgment |
| F09-FR-012 | Renewal | `GDO_Retention`; predecessor continuity |
| F09-FR-013 | Suspension/revocation | File 09 state/claims lifecycle |
| F09-FR-014 | Appeal | independent appeal assignment/resolution |
| F09-FR-015 | Duplicate/fraud detection | `GDO_Risk` + Advanced Trust fraud-network clues |
| F09-FR-016 | Applicant data rights | `GDO_Privacy`, withdrawal/erasure/legal hold |
| F09-FR-017 | Reviewer quality | `GDO_Quality` + calibration summary |

## Existing non-functional requirements

| Requirement | Gate | Source / evidence |
|---|---|---|
| F09-NFR-001 | Object/field authorization | File 00 + native owner/state/version/purpose checks |
| F09-NFR-002 | Privacy lifecycle | consent/export/erasure/legal hold/retention |
| F09-NFR-003 | Reliability | outbox/retry/dead-letter/reconciliation/Safe Mode |
| F09-NFR-004 | Performance | bounded queries/indexes/background work |
| F09-NFR-005 | Accessibility | semantic wizard/focus/reflow/RTL/reduced motion |
| F09-NFR-006 | Observability | audit chain/trace/access/quality/health |
| F09-NFR-007 | Migration/rollback | idempotent schema/quarantine/runbooks |
| F09-NFR-008 | Operability | health/Safe Mode/repair/queues/reconciliation |
| F09-NFR-009 | Compatibility | WordPress/PHP versioned contracts, PHP 7.4/8.3 CI |
| F09-NFR-010 | Localization | en-US base, Urdu/Arabic/RTL, locale-safe dates |

## Central and File-specific requirements

| Governing ID | File 09 implementation |
|---|---|
| F09-CEN-01 | Canonical owner contract, free/donor-neutral/privacy/safety metadata, Files 21/23 and File 26 public-safe projections; private evidence remains non-indexed. |
| F09-CEN-02 | Owner-only mutations, versioned read/projection/event contracts, action-time authorization recheck, File 19 event and File 20 page contract. |
| CEN-SEARCH-001 | File 26 consumes public-safe current verification only; File 26 owns search/ranking and File 09 connector never silently indexes private evidence. |

## Advanced Professional Trust requirements — 24 approved enhancements

| ID | Approved enhancement | Repository implementation | Permanent gate / external acceptance |
|---|---|---|---|
| F09-AT-01 | Primary-Source Verification Hub | `GDO_Advanced_Trust::primary_source_verify`, `gdo_primary_source_verification` | `tests/advanced-trust-24.py`; real issuer staging |
| F09-AT-02 | Trusted Issuer Registry | `gdo_trusted_issuers`, `register_issuer`, `trusted_issuer` | privileged step-up; issuer governance staging |
| F09-AT-03 | Credential Authenticity Engine | `authenticity_assessment`, hash reuse/technical facts | manual-review invariant; malicious evidence staging |
| F09-AT-04 | Jurisdiction Rules Engine | `gdo_jurisdiction_rules`, save/read approved rule | version/effective-date tests; jurisdiction staging |
| F09-AT-05 | Cross-Border Credential Equivalency Map | `equivalency_assessment`, adapter filter | never legal license grant; cross-border staging |
| F09-AT-06 | Continuous License Monitoring | `gdo_monitor_state`, daily monitor | revoked/expired/provider-outage staging |
| F09-AT-07 | Event-Driven Reverification | `GDO_Advanced_Trust_Events`, `schedule_reverification` | no derivative auto-command; lifecycle staging |
| F09-AT-08 | Professional Verification Passport | signed/revocable `gdo_verification_passports` | expiry/revocation/signature staging |
| F09-AT-09 | Public Verification QR Card | public-safe `verification_url`/`qr_payload` | presentation QR renderer + no-tracking staging |
| F09-AT-10 | Verification Scope Badge | `verification_matrix`, public card shortcode/filter | File 03/25 presentation acceptance |
| F09-AT-11 | Institutional Affiliation Verification | `verify_affiliation`, adapter facts | institution adapter/manual-review staging |
| F09-AT-12 | Professional History Timeline | `gdo_professional_history`, `public_safe` projection | append-only/public-private staging |
| F09-AT-13 | Credential Translation Workspace | `translation_assistance` | original remains authoritative; locale staging |
| F09-AT-14 | AI-Assisted Evidence Review | `ai_assistance`; decision keys discarded | human-final-decision assertion; provider staging |
| F09-AT-15 | Explainable Risk Intelligence | `risk_explanation` | no opaque auto-rejection; reviewer UI staging |
| F09-AT-16 | Fraud-Ring & Collusion Detection | `fraud_ring_scan`, credential-reuse risk links | false-positive/manual-resolution staging |
| F09-AT-17 | Reviewer Conflict-of-Interest Engine | `reviewer_conflicts`, narrowing authorization filter | conflict/recusal negative matrix |
| F09-AT-18 | Adaptive Dual Review | `requires_dual_review` | high-risk/cross-border/appeal dual-review staging |
| F09-AT-19 | Smart Reviewer Routing | `smart_reviewer_candidates` | jurisdiction/language/load/conflict staging |
| F09-AT-20 | Reviewer Calibration Laboratory | `reviewer_calibration` over quality samples | anonymized calibration acceptance |
| F09-AT-21 | Applicant Verification Command Center | shortcode + `GET /trust/command-center` | owner-only/mobile/RTL staging |
| F09-AT-22 | Resumable Secure Evidence Upload | upload sessions/chunks/finalize through `GDO_Evidence::stage_upload` | interruption/order/hash/malware/encryption staging |
| F09-AT-23 | Secure Evidence Viewing Room | step-up access grant, watermark, `download_allowed=false` | IDOR/session-expiry/download-negative tests |
| F09-AT-24 | Professional Trust Transparency Dashboard | `transparency_snapshot`, public aggregate REST | minimum aggregation/privacy staging |

## Advanced Trust data and API boundary

File 09 owns only professional-trust records: `gdo_trusted_issuers`, `gdo_jurisdiction_rules`, `gdo_credential_checks`, `gdo_professional_history`, `gdo_reviewer_conflicts`, `gdo_verification_passports`, `gdo_monitor_state`, `gdo_upload_sessions`.

External providers are adapters only: `gdo_primary_source_verification`, `gdo_credential_equivalency_assessment`, `gdo_institutional_affiliation_verification`, `gdo_credential_translation_assistance`, `gdo_ai_evidence_assistance`. Their response is a fact/hint requiring authorized human review. Provider secrets are deployment secrets and never repository/table metadata.

Public REST surfaces contain only public-safe professional scope or aggregate metrics. Private evidence and application payloads are not search documents and are never exposed in QR/passport/transparency responses.

## Current companion boundaries

| File | Contract / rule |
|---|---|
| File 00 | Identity, membership, guardian/contact/MFA/sanction and general authorization truth. |
| File 02 | Professional reauthentication / step-up owner. |
| File 03 | Public profile consumes public-safe current verification/scoped badges. |
| File 07 | Directory eligibility/ranking remains native; no File 09 rank score. |
| File 08 | Clinic/appointment/clinical truth remains native. |
| File 19 | Notification projection/delivery remains native; File 09 emits facts/outbox only. |
| File 20 | Sole application shell. |
| File 21/23 | Publishing truth/dashboard consume eligibility only. |
| File 24 | Security/privacy/compliance assurance; File 09 native enforcement remains native. |
| File 26 | Search/discovery/ranking; consumes public-safe verification only. |

## Acceptance Journey mapping

AJ-03 full onboarding/more-info/approval/renewal/appeal; AJ-04 public verified identity; AJ-05 directory/search; AJ-24/AJ-25 donor neutrality; AJ-31 accessibility; AJ-32 RTL/LTR; AJ-33 weak-network/reconnect; AJ-34 authorization/step-up; AJ-35 privacy rights; AJ-36 provider/scanner/key/storage outages; AJ-37 backup/restore; AJ-38 blocker law; AJ-39 final evidence corpus; AJ-40 two fresh review→fix→retest cycles.

RC5 additionally extends AJ-03/AJ-33/AJ-34/AJ-36/AJ-37/AJ-40 with primary-source adapters, continuous monitoring, conflict/dual review, resumable upload, viewing-room, passport and Advanced Trust schema restore evidence.

## Definition of Done mapping

| DoD | RC5 evidence |
|---|---|
| DoD-01 | This traceability map + canonical ownership + F09-AT-01…24. |
| DoD-02 | `RELEASE-MANIFEST-1.3.0.md`, deterministic exact-head package/SBOM after CI. |
| DoD-03 | Core migration + `MIGRATION-ROLLBACK-1.3.0.md`; staging migration pending. |
| DoD-04 | Existing architecture/security suites + `tests/advanced-trust-24.py`. |
| DoD-05 | File 00/File 02/cross-file authorization + reviewer conflict/step-up matrix. |
| DoD-06 | Privacy/security + public passport/transparency minimization + staging data rights. |
| DoD-07 | Existing security tests + provider failure/private storage/viewing-room acceptance. |
| DoD-08 | CSS/static + manual responsive/zoom/keyboard/screen-reader/RTL acceptance. |
| DoD-09 | Outbox + continuous monitor + provider/SLO/outage staging. |
| DoD-10 | Backup/restore/decrypt + Advanced Trust schema/passport/upload-session restore drill. |
| DoD-11 | Historical RC3/RC4 reviews + RC5 fresh corrective reviews required. |
| DoD-12 | `STAGING-ACCEPTANCE.md` + explicit Founder staging acceptance. |
| DoD-13 | Exact-head CI zero blockers; staging/live/operational status never inferred from repository success. |

## Trace-chain law

`Central CV/CEN/AJ or F09-AT ID → File 09 requirement → design/data/API/event → test ID → defect/fix/commit → package/checksum → staging evidence → Founder approval → rollout/monitoring`.

Repository source can prove only the early links. Staging/live/operational links remain false until separately observed and recorded.
