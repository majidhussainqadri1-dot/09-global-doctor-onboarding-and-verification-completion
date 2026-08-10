# Changelog

## 1.3.0-RC6 — Eighty-Round Corrective Assurance — 2026-08-10

### Fourth fresh 80-round corrective review (R4)
- Frozen baseline: `7875ce20a0fd40c0952960b6e45cf1e869b9c4dd`.
- 80 controls: **30 defect-bearing rounds corrected; 50 clean rounds**.
- Added physical schema postconditions and runtime schema gates for core + Advanced Trust migrations.
- Hardened activation/repair/reverification scheduling, applicant Safe Mode mutation paths, privacy/retention failure truth, resumable cleanup, rate cleanup and guarded destructive uninstall.
- Added `REVIEW-80-ROUNDS-RC6-R4.md`, `tests/eighty-round-audit-r4.py`, 55-entry release parity and fourth-review release-lock fields.

### Third fresh 80-round corrective review (R3)
- Frozen baseline: `f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361`.
- 80 controls: **13 defect-bearing rounds corrected; 67 clean rounds**.
- Hardened File 00 fail-closed profile/privileged identity use, explicit private resumable provenance, risk DB uncertainty, durable outbox persistence/replay, operational health/reconciliation, safe-mode/scheduler persistence and orphan-deletion safety.
- Synchronized historical QA assertions with the stronger current reviewer-case, Advanced Trust, rate-limit and resumable-upload contracts.
- Added `REVIEW-80-ROUNDS-RC6-R3.md`, `tests/eighty-round-audit-r3.py`, 54-entry release parity and R3 release-lock fields.

- Fresh-second independent 80-control re-review against `c3fbbadbee06d2be13b23822f5f17fce07cdab4e`: **47 defect-bearing rounds corrected; 33 clean rounds**; added `tests/eighty-round-audit-r2.py` and `REVIEW-80-ROUNDS-RC6-R2.md`.

- Performed 80 independent review→fix→re-review controls against frozen RC5 baseline `6d5c2850dbf86ce954e0c2fdef8adf36d2dbf1f1`: **49 defect-bearing rounds corrected; 31 clean rounds**.
- Preserved all 24 Founder-approved Advanced Professional Trust capabilities and retained File 09 as the only professional-verification owner.
- Corrected trusted issuer governance to proposed-first + independent initial verification; hardened provider-domain/metadata normalization and duplicate issuer prevention.
- Corrected jurisdiction rules to draft-first, independent second approval, effective-date validation and immutable approved versions.
- Minimized/bounded provider requests/results, normalized provider expiry data and added primary-source/AI rate limits without delegating final decisions.
- Corrected continuous monitoring so `degraded` rows retry with bounded exponential backoff and adverse/expiry events receive targeted reverification without silent professional-state mutation.
- Corrected professional verification passport eligibility, issuance locking/version serialization, supersession failure handling, suspension/revocation/expiry invalidation, current underlying File 09/File 00 recheck, and public read-only/no-cache semantics.
- Corrected verification-scope badge to current predecessor-aware verification plus `verified/pending/not_verified/not_applicable` semantics.
- Corrected reviewer conflict declaration/resolution, adaptive dual-review state/monotonicity, max reviewer workload and completed-sample calibration.
- Deduplicated credential-reuse/fraud-network signals and preserved explainable human-reviewed risk.
- Serialized resumable private-upload DB/file state, enforced exact chunk geometry/order/size, added atomic `open→finalizing` ownership, commit-marker diagnostics and stale-finalizing cleanup while preserving canonical malware/type/quota/encryption handoff.
- Removed active duplicate evidence-grant semantics by routing the Secure Evidence Viewing Room through canonical `GDO_Evidence::issue_view_grant()` one-time/session/step-up authorization.
- Added Advanced Trust REST object-scope reauthorization, structured per-check failure responses and fixed-window minimum-cohort public transparency.
- Extended WordPress privacy export/erasure and application-scoped retention across Advanced Trust; derivative passports are deleted on erasure/retention rather than collision-prone shared-user anonymization; `.chunk-*` private orphans are covered.
- Advanced Advanced Trust schema to `2`, contract to `1.1.0`, release identity to `1.3.0-RC6`, 53-entry deterministic package and new `tests/eighty-round-audit.py`/`REVIEW-80-ROUNDS-RC6.md` gates.
- Staging Accepted, Live Deployed and Operational remain false until the new exact-head RC6 workflow and external Hostinger acceptance are separately evidenced.

## 1.3.0-RC5 — Advanced Professional Trust & Verification — 2026-08-10

- Implemented all 24 Founder-approved Advanced Professional Trust enhancements in the File 09 canonical boundary.
- Added primary-source verification adapters, trusted issuer registry, technical credential authenticity records, versioned jurisdiction rules, cross-border equivalency and affiliation adapters.
- Added continuous/event-driven reverification with adverse-result escalation only; external provider facts never silently revoke or finalize a professional decision.
- Added signed, revocable, time-bounded professional verification passports and public-safe QR verification payloads plus a scoped verification matrix.
- Added public-safe professional history, translation assistance and AI reviewer assistance; automated approve/reject/decision keys are discarded and human final review is mandatory.
- Added explainable risk, credential-reuse/fraud-network clues, conflict-of-interest enforcement, adaptive dual review, smart reviewer routing and reviewer calibration summaries.
- Added private applicant command center, ordered resumable secure evidence uploads terminating in the canonical malware/type/quota/encryption path, and short-lived step-up-bound no-download evidence viewing-room grants.
- Added aggregate-only trust transparency metrics with no applicant-level private evidence exposure.
- Added eight additive Advanced Trust tables, contract `1.0.0`, schema `1`, migration/rollback documentation, traceability and permanent `tests/advanced-trust-24.py` regression gates.
- Advanced runtime to `1.3.0`, kept core File 09 schema at `6`, and advanced deterministic packaging to `1.3.0-RC5` with exact-head generated SPDX 2.3 SBOM.
- Staging, live deployment and operational acceptance remain false until RC5 exact-head QA and external Hostinger acceptance are separately evidenced.

## 1.2.0-RC4 — Latest central + File 09 plan parity — 2026-08-10

- Corrected the plugin display title to the canonical **Global Doctor Onboarding and Verification** name while retaining runtime `1.2.0` and schema `6`.
- Added the File 09 canonical owner contract `1.1.0` and explicit fail-closed professional-eligibility consumers for Files 21 and 23 in addition to Files 03/07/08.
- Added `gdo.file26.doctor-verification-projection` and a privacy-preserving File 26 connector negotiation. The connector remains `contract_tested` so File 09 private applications/evidence cannot become search documents by activation side effect.
- Added File 20 page-contract registration through `sabri_shell_page_contracts`, preserving File 20 as the only application shell.
- Upgraded File 19 integration to the current `sun.event.v1` producer/event contract with immutable UUID idempotency, minimized safe notification data and current provider registration; legacy File 19 adapters remain compatibility-only fallbacks.
- Removed raw notification payload forwarding from legacy presentation context.
- Added `tests/latest-plan-parity.py` and expanded cross-file contract regression coverage for F09-CEN-01, F09-CEN-02, CEN-SEARCH-001 and applicable AJ journeys.
- Replaced repository-static SBOM truth with a deterministic repository template plus exact-head SPDX 2.3 SBOM generation/verification during RC4 packaging, preventing stale checksum claims after source changes.
- Staging, live and operational acceptance remain false pending the external RC4 acceptance contract and explicit Founder authorization.

## 1.2.0-RC3 — Forty-round corrective assurance — 2026-08-07

- Performed 40 independent review/fix rounds against the corrected RC2 baseline; 13 rounds found defects and 27 rounds found no new defect.
- Made File 00 membership assertions explicitly fail-closed on `result=allow`, jurisdiction-aware, and protected authorization/policy extension points from widening baseline access or removing minimum evidence/profile requirements.
- Corrected optional-field browser validation, stale outbox `processing` recovery, strict calendar-date validation, live reviewer scope revalidation and appeal/application/workload assignment locking.
- Rechecked step-up at evidence-grant use time, enforced evidence-review expiry through final/CF-01 paths, preserved the transition audit hash chain during erasure, and preserved valid predecessor verification during an in-progress renewal.
- Serialized credential upload ownership/quota checks and made quality sampling independent; reviewer-profile persistence failures are now surfaced and successful changes audited.
- Added `tests/review40-adversarial.py`, `REVIEW-40-ROUNDS-RC3.md`, RC3 release metadata, deterministic RC3 package identity and exact-head CI enforcement.
- Staging, live deployment and operational acceptance remain explicitly false pending external Hostinger acceptance.

### Four-plan harmonization hardening — 7 Aug 2026

- Aligned File 09 with current File 00 general contract 1.2.0 / runtime 1.2.11.
- Added current identity-document and approved-doctor-grant checks without circular pre-verification.
- Corrected CF-01 adult guardian logic and approved-snapshot schema compatibility.
- Revalidated File 00 assurance and unresolved high-risk signals at verification/reinstatement/claim time.
- Refreshed approved snapshot validity metadata on reinstatement.
- Made WhatsApp optional as specified; corrected phone copy and File 20 shell naming boundary.

## RC2 Fresh Adversarial Hardening — 2026-08-07

- Closed renewal eligibility bypass on expired/renewal draft creation.
- Made professional claim issuance state-bound and snapshot-bound.
- Made schema backfill exhaustive and legacy migration bounded/resumable.
- Moved legacy-source deletion after successful new-record commit with dedupe-safe cleanup.
- Corrected privacy erasure pagination, all-version credential deletion, atomic revocation claim, and collision-free nullable subject anonymization.
- Corrected retention expiry to scrub subject/profile and credential metadata after physical deletion.
- Added a dedicated final hardening regression gate and third fresh adversarial review record.

## 1.2.0 — 2026-08-06

- Completed all File 09 FR/NFR source mappings and schema 6.
- Added guided application wizard, autosave and version-safe cross-device resume.
- Added encrypted private evidence, fail-closed scanning, metadata stripping, versioning and one-time reviewer grants.
- Added assignment by scope/workload/language/jurisdiction, conflict controls, structured findings, recommendation and independent finalization.
- Added signed/versioned File 00 claims with explicit acknowledgment before public verification.
- Added renewal, grace/limited projection, expiry, suspension, revocation, reinstatement and appeal.
- Added duplicate/document-hash signals, human resolution, quality sampling and access monitoring.
- Added privacy export, withdrawal, erasure/legal-hold and physical deletion proof.
- Added outbox retry/dead-letter/reconciliation, health, Safe Mode and bounded repairs.
- Added deterministic package, SBOM, traceability and two fresh review/fix records.

## 1.2.0-RC2 — Final corrective repository candidate

- Reconciled the valid 1.2.0 source line without applying the incomplete encoded overlay branch.
- Corrected adult guardian eligibility, immutable resubmission edit-state, autosave row-version propagation and step validation.
- Made evidence review and more-information transition atomic with row locks, optimistic state checks and validity-date controls.
- Added explicit independent appeal assignment and assignee-only resolution.
- Added bounded fail-closed File 03, File 07 and File 08 eligibility projections; retained File 00, File 02, File 19, File 20 and CF-01 boundaries.
- Removed temporary, self-mutating, stale baseline/corrective and duplicate CI workflows plus branch-marker/noop artifacts; one exact-head RC2 workflow is authoritative.
- Added RC2 adversarial assurance, PHP 7.4/8.3 exact-head CI and deterministic RC2 packaging.
