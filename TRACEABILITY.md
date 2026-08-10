# File 09 Requirements Traceability — Latest Central + File 09 Plans

Candidate: `1.2.0 RC4` / schema `6`
Canonical owner: **File 09 — Global Doctor Onboarding and Verification**

A requirement is never accepted merely because a class/function exists. Repository traceability below proves source/test intent; exact-head automated results and required external staging evidence are separate gates.

## Functional requirements

| Requirement | Capability | Source owner/implementation | Automated / external evidence |
|---|---|---|---|
| F09-FR-001 | Eligibility precheck | `GDO_Policy::eligibility`; `GDO_Membership_Adapter` | `tests/policy-runtime.php`; `tests/membership-adapter.php`; staging negative matrix |
| F09-FR-002 | Guided wizard | `GDO_Frontend::form`; `assets/js/onboarding.js` | `tests/completion-security.py`; AJ-03 staging UI |
| F09-FR-003 | Draft save/resume | `GDO_Application::save_draft`; `GDO_REST::autosave` | policy/static tests; staging concurrency/reconnect |
| F09-FR-004 | Evidence upload | `GDO_Evidence::stage_upload`; `GDO_Crypto`; `GDO_Storage` | security tests; malicious/polyglot/private-storage staging |
| F09-FR-005 | Completeness gate | `GDO_Application::completeness` | policy runtime; 100% conditional-field staging |
| F09-FR-006 | Submission | `GDO_Application::submit`; immutable submission hash/outbox | completion-security; idempotency staging |
| F09-FR-007 | Reviewer assignment | `GDO_Admin::assign`; reviewer profiles | completion-security; role/workload/race staging |
| F09-FR-008 | Evidence review | `GDO_Evidence` grants/render/review; `GDO_Admin` | security unit; staging IDOR/step-up |
| F09-FR-009 | More information | `GDO_Admin::request_more_info`; replacement versions | completion-security; AJ-03 journey |
| F09-FR-010 | Decision | recommend/finalize; `GDO_State`; approved snapshot | completion-security; separation-of-duties staging |
| F09-FR-011 | Claim issuance | `GDO_Claims`; File 00 acknowledged delivery | policy/completion-security; real File 00 contract |
| F09-FR-012 | Renewal | `GDO_Retention::open_renewals`; predecessor projection continuity | hardening/review40; staging time travel |
| F09-FR-013 | Suspension/revocation | `GDO_Admin::change_state`; `GDO_Claims` | completion-security; lifecycle propagation |
| F09-FR-014 | Appeal | frontend appeal; independent admin assign/resolve | adversarial tests; staging independence |
| F09-FR-015 | Duplicate/fraud detection | `GDO_Risk` | policy/review40; false-positive staging |
| F09-FR-016 | Applicant data rights | `GDO_Privacy`; withdrawal/erasure/legal hold | completion-security; AJ-35 staging |
| F09-FR-017 | Reviewer quality | `GDO_Quality`; access logs/metrics | policy/review40; staging sampling |

## Non-functional requirements

| Requirement | Gate | Implementation | Evidence |
|---|---|---|---|
| F09-NFR-001 | Object/field authorization | File 00 claims + native ownership/state/version/purpose checks | membership/completion-security; staging IDOR |
| F09-NFR-002 | Privacy lifecycle | consent/export/erasure/legal hold/retention | completion-security; AJ-35 |
| F09-NFR-003 | Reliability | transactional outbox/retry/dead-letter/reconciliation/Safe Mode | policy/review40; AJ-36 |
| F09-NFR-004 | Performance | bounded queries/indexes/background work | completion-security; staging p75/p95/load |
| F09-NFR-005 | Accessibility | semantic wizard/focus/reflow/RTL/reduced motion | static; AJ-31/32 manual matrix |
| F09-NFR-006 | Observability | audit hash chain/trace/access/quality/health | policy/review40; staging alerts |
| F09-NFR-007 | Migration/rollback | lock/idempotent schema/quarantine/rollback runbook | completion-security; staging migration/rollback |
| F09-NFR-008 | Operability | health/Safe Mode/repair/queue/reconciliation | policy; staging operations |
| F09-NFR-009 | Compatibility | WP 7.0.1/PHP 8.3 target; PHP 7.4 floor; versioned contracts | CI matrix; actual staging |
| F09-NFR-010 | Localization | en-US base; Urdu/Arabic/RTL; date/time correctness | static; locale staging |

## Latest central/File-specific requirements

| Governing ID | File 09 implementation | Regression / acceptance |
|---|---|---|
| F09-CEN-01 | `GDO_Integration_Contracts` publishes canonical owner contract, free/donor-neutral/privacy/safety metadata, Files 21/23 projections and File 26 verification/search projection; private evidence remains C3 and non-indexed. | `tests/cross-file-contracts.php`; `tests/latest-plan-parity.py`; AJ-04/05/24/25 + File26 staging |
| F09-CEN-02 | Mutations remain native File 09 commands/state machine; reads/projections use versioned contracts with action-time File 00/File 09 recheck; notification event is `sun.event.v1`; File 20 page registration is versioned. | architecture/completion tests; cross-file tests; AJ-03/34/36 |
| CEN-SEARCH-001 | `gdo.file26.doctor-verification-projection` supplies only current public verification truth. File 26 owns search/ranking; File 09 connector is `contract_tested`, not silently active; verification loss fails closed. | cross-file tests + staging search corpus/deletion/rights/rank-explanation verification |

## Current companion boundaries

| File | Contract / rule |
|---|---|
| File 00 | Identity, membership, guardian/contact/2FA/sanction and general authorization truth; File 09 emits signed professional decision and rechecks current assurance. |
| File 02 | Professional reauthentication / step-up owner. |
| File 03 | `gdo.file03.doctor-profile-eligibility` public-safe current professional projection. |
| File 07 | `gdo.file07.directory-eligibility`; directory/search ranking truth is not owned by File 09. |
| File 08 | `gdo.file08.clinic-eligibility`; no appointment/clinical truth duplicated. |
| File 19 | `sun.event.v1` with producer `file09-doctor-verification`; minimized facts only; legacy adapters fallback-only. |
| File 20 | sole application shell; `sabri_shell_page_contracts` maps `gdo_page_map/apply`; no second shell. |
| File 21 | `gdo.file21.publishing-eligibility`; publication truth remains File 21. |
| File 23 | `gdo.file23.publishing-dashboard-eligibility`; dashboard only consumes eligibility. |
| File 24 | security/privacy assurance; native File 09 enforcement remains native. |
| File 26 | `gdo.file26.doctor-verification-projection`; File 26 owns search/discovery/ranking and may only consume public-safe current truth. |

## Acceptance Journey mapping

| AJ | File 09 relevance / proof |
|---|---|
| AJ-03 | Full doctor onboarding/verification/more-info/approval/renewal/appeal journey — external staging mandatory. |
| AJ-04 | Public verified doctor identity consumes current File 09 status through File 03 — external cross-file staging. |
| AJ-05 | Directory/search uses current verification and no paid/donor bias — File 07/File 26 staging. |
| AJ-24 | Donation prompt remains outside File 09 verification authority; no verification mutation or benefit tied to donation. |
| AJ-25 | Donor/non-donor equality encoded by `donor_rank_advantage=false` and no fee/rank branch; cross-platform staging required. |
| AJ-31 | Keyboard/screen-reader/zoom/reduced-motion — manual staging. |
| AJ-32 | RTL/LTR/mixed-language fields — CSS/static + manual staging. |
| AJ-33 | slow 3G/offline/reconnect/autosave — staging/browser evidence. |
| AJ-34 | current authorization/step-up/recovery/security state — File 00/02 integration staging. |
| AJ-35 | export/delete/retention/legal hold — privacy staging. |
| AJ-36 | File 19/scanner/key/storage/provider outages — no false success; outbox/reconciliation staging. |
| AJ-37 | backup restore/RPO/RTO and rights/deletion consistency — isolated restore drill. |
| AJ-38 | critical defect blocks release absent documented Founder exception. |
| AJ-39 | final multi-device/role/state screenshot/evidence corpus — staging. |
| AJ-40 | two fresh review→fix→retest rounds on exact release candidate before rollout. |

## Definition of Done mapping

| DoD | Evidence |
|---|---|
| DoD-01 | This traceability map, canonical ownership and latest plan IDs. |
| DoD-02 | RC4 release manifest, deterministic exact-head generated SBOM/package and SHA-256 artifact. |
| DoD-03 | Migration code plus external fresh/upgrade/deactivate/reactivate/uninstall staging evidence. |
| DoD-04 | Architecture, completion-security and latest-plan parity tests. |
| DoD-05 | Membership/CF-01/cross-file authorization tests plus staging IDOR matrix. |
| DoD-06 | Privacy/security static tests plus external export/erase/retention/provider-deletion evidence. |
| DoD-07 | Security unit/static tests plus real provider and File 24 assurance review. |
| DoD-08 | CSS/static checks plus manual 320–1920px, 400% zoom, keyboard, screen reader and RTL acceptance. |
| DoD-09 | Queue/outbox logic plus File 19 `sun.event.v1`, staging load/outage/SLO evidence. |
| DoD-10 | Migration/rollback runbook plus isolated backup-restore/decrypt drill. |
| DoD-11 | RC3 forty-round evidence plus RC4 latest-plan corrective review; release still requires two fresh staging review cycles. |
| DoD-12 | `STAGING-ACCEPTANCE.md` with explicit Founder acceptance; external execution pending. |
| DoD-13 | Exact-head CI zero blockers; residual external gates explicit, never hidden. |

## RC4 latest-plan correction ledger — 10 Aug 2026

| Finding | Correction | Permanent gate |
|---|---|---|
| Missing latest File 21/File 23 consumers | Added fail-closed versioned eligibility projections | `tests/cross-file-contracts.php` |
| Missing File 26 verification/search projection boundary | Added `gdo.file26.doctor-verification-projection`, contract-tested connector manifest and fail-closed use-time projection | cross-file + latest-plan tests |
| Legacy-only File 19 delivery | Added producer registration and minimized `sun.event.v1` ingestion; legacy fallback retained only for compatibility | `tests/latest-plan-parity.py`; existing outbox gates |
| File 20 page registry not declared | Added `sabri_shell_page_contracts` mapping to `gdo_page_map/apply` | cross-file test |
| Obsolete plugin title suffix | Canonical plugin header now exactly `Global Doctor Onboarding and Verification` | latest-plan/release-integrity |
| Static SBOM reopened by every source change | RC4 builder generates SPDX 2.3 from exact checked-out allowlist and binds it to source HEAD; verifier checks every declared SHA-256 | release-integrity + deterministic double build |
| Latest plan IDs not explicit in repository evidence | Added F09-CEN/CEN-SEARCH/AJ mappings | release-integrity + latest-plan parity |

## Prior RC3 assurance

RC3 completed **40** independent corrected-tree review/fix rounds: **13 defect-bearing rounds + 27 clean rounds**. That evidence remains historical support; the RC4 exact head must independently pass the complete workflow.

## Trace-chain law

`Central CV/CEN/AJ ID → File 09 requirement → design/data/API/event → test ID → defect/fix/commit → package/checksum → staging evidence → Founder approval → rollout/monitoring`.

Repository source can satisfy only the early links. Staging/live/operational links remain false until separately observed and recorded.
