# File 09 Requirements Traceability — Latest Central + File 09 + Advanced Trust 24 + RC6

Candidate: `1.3.0 RC6` / core schema `6` / Advanced Trust schema `2` / Advanced Trust contract `1.1.0`  
Canonical owner: **File 09 — Global Doctor Onboarding and Verification**  
80-round baseline: `6d5c2850dbf86ce954e0c2fdef8adf36d2dbf1f1`

A requirement is not accepted merely because a class/function exists. Repository source/tests/package, Hostinger staging and live evidence are separate gates.

## Existing functional requirements

| Requirement | Capability | Source / evidence |
|---|---|---|
| F09-FR-001 | Eligibility precheck | `GDO_Policy`, File 00 assertions; policy/membership tests |
| F09-FR-002 | Guided wizard | `GDO_Frontend`, onboarding JS |
| F09-FR-003 | Draft save/resume | `GDO_Application`, REST autosave/concurrency |
| F09-FR-004 | Evidence upload | `GDO_Evidence`, private crypto/storage |
| F09-FR-005 | Completeness gate | `GDO_Application::completeness` |
| F09-FR-006 | Submission | owner state machine/outbox |
| F09-FR-007 | Reviewer assignment | `GDO_Admin`, reviewer profiles |
| F09-FR-008 | Evidence review | canonical one-time evidence grants; IDOR/step-up tests |
| F09-FR-009 | More information | versioned replacement evidence |
| F09-FR-010 | Decision | recommendation/finalization/separation of duties |
| F09-FR-011 | Claim issuance | `GDO_Claims`, File 00 acknowledgment |
| F09-FR-012 | Renewal | predecessor continuity + retention lifecycle |
| F09-FR-013 | Suspension/revocation | File 09 state/claims + RC6 derivative-passport invalidation |
| F09-FR-014 | Appeal | independent appeal assignment/resolution |
| F09-FR-015 | Duplicate/fraud detection | `GDO_Risk` + deduplicated credential-reuse clues |
| F09-FR-016 | Applicant data rights | `GDO_Privacy` + Advanced Trust export/erasure/retention |
| F09-FR-017 | Reviewer quality | `GDO_Quality` + completed-sample calibration |

## Existing non-functional requirements

| Requirement | Gate | Source / evidence |
|---|---|---|
| F09-NFR-001 | Object/field authorization | File 00 + native owner/state/version/purpose + RC6 object-scope REST recheck |
| F09-NFR-002 | Privacy lifecycle | consent/export/erasure/legal hold/retention + trust-table coverage |
| F09-NFR-003 | Reliability | outbox/retry/dead-letter/reconciliation/Safe Mode + monitor backoff |
| F09-NFR-004 | Performance | bounded queries/indexes/background work; Advanced Trust schema 2 indexes |
| F09-NFR-005 | Accessibility | semantic wizard/focus/reflow/RTL/reduced motion; manual staging gate |
| F09-NFR-006 | Observability | audit chain/trace/access/quality/health/trust failure audit |
| F09-NFR-007 | Migration/rollback | core schema 6 + Advanced Trust schema 2 additive migration |
| F09-NFR-008 | Operability | health/Safe Mode/repair/queues/reconciliation/orphan cleanup |
| F09-NFR-009 | Compatibility | WordPress/PHP versioned contracts, PHP 7.4/8.3 exact-head CI |
| F09-NFR-010 | Localization | en-US base, Urdu/Arabic/RTL, locale-safe dates |

## Central and File-specific requirements

| Governing ID | File 09 implementation |
|---|---|
| F09-CEN-01 | Canonical owner contract, free/donor-neutral/privacy/safety metadata, Files 21/23 and File 26 public-safe projections; private evidence non-indexed. |
| F09-CEN-02 | Owner-only mutations, versioned read/projection/event contracts, action-time authorization recheck, File 19 event and File 20 page contract. |
| CEN-SEARCH-001 | File 26 consumes public-safe current verification only; File 26 owns search/ranking and never indexes File 09 private application/evidence. |

## Advanced Professional Trust requirements — 24 approved enhancements

| ID | Approved enhancement | RC6 repository implementation | Permanent gate / external acceptance |
|---|---|---|---|
| F09-AT-01 | Primary-Source Verification Hub | base adapter + RC6 request/result minimization, rate/date hardening | `advanced-trust-24.py`, issuer staging |
| F09-AT-02 | Trusted Issuer Registry | proposed-first registry + independent issuer review | issuer governance staging |
| F09-AT-03 | Credential Authenticity Engine | technical/hash reuse facts; human review | malicious evidence staging |
| F09-AT-04 | Jurisdiction Rules Engine | RC6 draft-first/second-review/effective-date/immutable-version REST path | jurisdiction staging |
| F09-AT-05 | Cross-Border Credential Equivalency Map | adapter fact; never legal license | cross-border staging |
| F09-AT-06 | Continuous License Monitoring | RC6 scheduled+degraded selection, persisted backoff, single retry wakeup | provider-outage/adverse staging |
| F09-AT-07 | Event-Driven Reverification | exact transition event + explicit `to_state` mapping | lifecycle staging |
| F09-AT-08 | Professional Verification Passport | serialized signed/revocable passport; underlying truth rechecked | concurrency/expiry/revocation staging |
| F09-AT-09 | Public Verification QR Card | current public-safe URL; GET read-only/non-cacheable | QR/no-tracking staging |
| F09-AT-10 | Verification Scope Badge | current public verification record + four-state scope semantics | File 03/25 presentation acceptance |
| F09-AT-11 | Institutional Affiliation Verification | adapter fact | institution staging |
| F09-AT-12 | Professional History Timeline | append-only rows + public-event allowlist | public/private staging |
| F09-AT-13 | Credential Translation Workspace | derived translation; original authoritative | locale staging |
| F09-AT-14 | AI-Assisted Evidence Review | bounded hints; decision keys discarded | provider/privacy staging |
| F09-AT-15 | Explainable Risk Intelligence | reasons/factors; no opaque rejection | reviewer UI staging |
| F09-AT-16 | Fraud-Ring & Collusion Detection | deduplicated credential-reuse network signals | false-positive resolution staging |
| F09-AT-17 | Reviewer Conflict-of-Interest Engine | authorized declaration, dedupe, resolution, narrowing filter | conflict/recusal matrix |
| F09-AT-18 | Adaptive Dual Review | current states + monotonic strengthening | high-risk/cross-border/appeal staging |
| F09-AT-19 | Smart Reviewer Routing | jurisdiction/language/load/max-open/conflict | routing staging |
| F09-AT-20 | Reviewer Calibration Laboratory | completed quality-sample metrics | calibration acceptance |
| F09-AT-21 | Applicant Verification Command Center | private owner state/checks/next action | mobile/RTL staging |
| F09-AT-22 | Resumable Secure Evidence Upload | serialized chunks/finalize, exact dimensions/hash, canonical encrypted handoff | interruption/race/malware staging |
| F09-AT-23 | Secure Evidence Viewing Room | canonical `GDO_Evidence::issue_view_grant`; one-time/session/step-up | IDOR/expiry/no-download tests |
| F09-AT-24 | Professional Trust Transparency Dashboard | fixed public window + minimum-cohort suppression | privacy staging |

## RC6 80-round corrective trace

`REVIEW-80-ROUNDS-RC6.md` records **80 review rounds: 49 defect-bearing rounds corrected and 31 clean rounds**. `tests/eighty-round-audit.py` is a permanent exact-head gate and is executed together with all earlier security/adversarial suites. The corrective runtime is loaded in this order: base Advanced Trust → `GDO_Advanced_Trust_Hardening` → exact lifecycle event bridge. A replaced RC5 callback is removed before its RC6 callback is added; no second professional-verification owner is created.

Advanced Trust schema 2 remains eight File 09-owned tables: `gdo_trusted_issuers`, `gdo_jurisdiction_rules`, `gdo_credential_checks`, `gdo_professional_history`, `gdo_reviewer_conflicts`, `gdo_verification_passports`, `gdo_monitor_state`, `gdo_upload_sessions`. Schema 2 version-controls the application/status indexes and associated reconciliation behavior; no companion native table is mutated.

## Current companion boundaries

| File | Contract / rule |
|---|---|
| File 00 | Identity, membership, guardian/contact/MFA/sanction and general authorization truth. |
| File 02 | Professional reauthentication / step-up owner. |
| File 03 | Public profile consumes current public-safe verification/scoped badges. |
| File 07 | Directory eligibility/ranking remains native; no File 09 rank score. |
| File 08 | Clinic/appointment/clinical truth remains native. |
| File 19 | Notification projection/delivery remains native; File 09 emits minimized facts/outbox only. |
| File 20 | Sole application shell. |
| File 21/23 | Publishing truth/dashboard consume eligibility only. |
| File 24 | Security/privacy/compliance assurance; File 09 native enforcement remains native. |
| File 26 | Search/discovery/ranking consumes public-safe verification only. |

## Acceptance Journey mapping

AJ-03 onboarding/more-info/approval/renewal/appeal; AJ-04 public verified identity; AJ-05 directory/search; AJ-24/AJ-25 donor neutrality; AJ-31 accessibility; AJ-32 RTL/LTR; AJ-33 weak-network/reconnect; AJ-34 authorization/step-up; AJ-35 privacy rights; AJ-36 provider/scanner/key/storage outages; AJ-37 backup/restore; AJ-38 blocker law; AJ-39 final evidence corpus; AJ-40 two fresh review→fix→retest cycles.

RC6 extends AJ-03/AJ-33/AJ-34/AJ-35/AJ-36/AJ-37/AJ-40 with schema-2 migration, provider backoff/adverse paths, passport concurrency/read semantics, resumable race/finalizing cleanup, canonical viewing grants and Advanced Trust privacy/retention evidence.

## Definition of Done mapping

| DoD | RC6 evidence |
|---|---|
| DoD-01 | F09-FR/F09-NFR/F09-CEN/F09-AT trace + canonical ownership. |
| DoD-02 | `RELEASE-MANIFEST-1.3.0.md`, deterministic RC6 exact-head package/SBOM after CI. |
| DoD-03 | Core schema 6 + Advanced Trust schema 2 + `MIGRATION-ROLLBACK-1.3.0.md`; staging migration pending. |
| DoD-04 | Existing suites + `tests/advanced-trust-24.py` + `tests/eighty-round-audit.py`. |
| DoD-05 | File 00/File 02/cross-file authorization + conflict/step-up/object-scope matrix. |
| DoD-06 | Privacy export/erasure/retention + passport/transparency minimization. |
| DoD-07 | Provider failure/private storage/viewing-room/resumable security acceptance. |
| DoD-08 | Manual responsive/zoom/keyboard/screen-reader/RTL acceptance. |
| DoD-09 | Outbox + monitor retry/backoff + provider/SLO/outage staging. |
| DoD-10 | Backup/restore/decrypt + schema/passport/upload-session restore drill. |
| DoD-11 | Historical RC3/RC4/RC5 + RC6 80-round corrective evidence. |
| DoD-12 | `STAGING-ACCEPTANCE.md` + explicit Founder staging acceptance. |
| DoD-13 | Exact-head CI zero blockers; staging/live/operational never inferred from repository success. |

## Trace-chain law

`Central CEN/AJ or F09-AT ID → File 09 requirement → design/data/API/event → test ID → review round/defect/fix/commit → package/checksum → staging evidence → Founder approval → rollout/live verification`.

Repository source proves only the early links. Staging/live/operational links remain false until separately observed and recorded.

## Fifth fresh 80-round corrective trace — R5

Baseline `58313a67e1d21ad17c9a066e9a29c34245a0763e` → source corrections → `tests/eighty-round-audit-r5.py` → `REVIEW-80-ROUNDS-RC6-R5.md` → exact-head PHP 7.4/8.3 workflow → deterministic 56-entry package/SBOM. R5 specifically closes future-schema fail-open behavior, pre-migration normal-hook exposure, Advanced Trust REST/monitor mutation-gate gaps, monitor DB/provider/cleanup failure semantics, recurring/wakeup scheduler ambiguity, activation persistence gaps, canonical private-storage/symlink use-time safety, Safe Mode manager bypass and fail-silent destructive uninstall paths. External staging/live acceptance remains separate.


## Sixth fresh 80-round corrective trace — R6

Baseline `6fa0a5cb7063b6b821bd50c105c735470f589b80` → 80-control review → immediate root-cause corrections in Advanced Trust/private storage/privacy → `tests/eighty-round-audit-r6.py` → `REVIEW-80-ROUNDS-RC6-R6.md` → exact-head PHP 7.4/8.3 authoritative workflow → deterministic **57-entry** package/source-parity/generated-SBOM gate. R6 specifically strengthens F09-FR-004/006/008/012/013/014/015/016/017, F09-NFR-001/002/003/006/007/008 and F09-AT-01/02/03/04/06/08/12/14/15/16/17/20/21/22/23/24 without changing canonical ownership.

R6 preserves the trace-chain law: `governing ID → File 09 design/owner → code/data/API/event → executable test → defect/fix ledger → exact-head package/checksum → staging evidence → Founder approval → live verification`. The repository currently proves only the repository-side links; staging/live/operational links remain unverified and must not be inferred.

## R7 corrective assurance trace

The seventh fresh 80-control review is frozen in `REVIEW-80-ROUNDS-RC6-R7.md` and executable in `tests/eighty-round-audit-r7.py`. It adds regression evidence for F09-AT-02 issuer registry semantics; jurisdiction/conflict mutation readiness; F09-AT-08 passport atomicity/public-safe token boundaries; F09-AT-19 reviewer routing uncertainty; F09-AT-24 small-cell transparency privacy; F09-FR-003 nonce-protected draft start; F09-NFR-001/002/003/010; and the exact-head release chain.

## R8 corrective trace
R8 maps File 09 authorization/privacy/reliability/operability requirements and F09-AT-08/09/15/20 controls to `GDO_Operations`, `GDO_Migration`, `GDO_Admin`, `GDO_Advanced_Trust_Hardening`, `GDO_Risk`, `GDO_Quality`, `tests/eighty-round-audit-r8.py`, `REVIEW-80-ROUNDS-RC6-R8.md`, exact-head CI and deterministic package/SBOM evidence. Frozen baseline: `f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2`.


## R9 — Ninth fresh 80-round corrective trace

Frozen baseline: `75ea54ed5113bf7ee16e90443f17cc1b941933a9`. Defect-bearing rounds: `04–13`; clean rounds: `01–03, 14–80`.

| R9 round | Requirement/control family | Corrected source/evidence | Permanent gate |
|---|---|---|---|
| 04 | F09-NFR-001 / reviewer authorization | `GDO_Evidence::review()` current actor + File00 capability + File02 step-up | `tests/eighty-round-audit-r9.py` |
| 05 | F09-NFR-003/008 / DB reliability | explicit evidence/application review DB uncertainty | R9 gate |
| 06 | F09-FR-008, F09-AT-23 | use-time download capability + grant/evidence/application DB checks | R9 gate |
| 07 | F09-FR-006 / idempotency | idempotent submission COMMIT verification | R9 gate |
| 08 | F09-FR-014 / appeal operability | appeal DB-failure visibility | R9 gate |
| 09 | F09-NFR-003/008, File19 boundary | bounded outbox processing failure visibility | R9 gate |
| 10 | F09-NFR-001 / recovery mutation | dead-letter replay current actor/capability/step-up | R9 gate |
| 11 | F09-AT-06/07, F09-NFR-003 | continuous-monitor exclusive lease + stale-lease recovery | R9 gate |
| 12 | F09-FR-016, F09-NFR-002 | atomic Advanced Trust DB privacy erasure | R9 gate |
| 13 | DoD/release evidence | R9 ledger/test/lock/docs + 60-entry allowlist | R9 gate + authoritative workflow |

R9 does not change canonical ownership. File 09 remains professional verification/evidence decision owner; File 00 identity, File 02 authentication/step-up, Files 03/07/26 public profile/search/ranking, File 08 clinic, File 19 notification transport, File 20 shell and File 24 security/privacy assurance remain native owners.
