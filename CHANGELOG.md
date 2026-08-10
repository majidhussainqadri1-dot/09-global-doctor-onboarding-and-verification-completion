## 1.2.0-RC2 — Final corrective repository candidate
- Reconciled the valid 1.2.0 source line without applying the incomplete encoded overlay branch.
- Corrected adult guardian eligibility, immutable resubmission edit-state, autosave row-version propagation and step validation.
- Made evidence review and more-information transition atomic with row locks, optimistic state checks and validity-date controls.
- Added explicit independent appeal assignment and assignee-only resolution.
- Added bounded fail-closed File 03, File 07 and File 08 eligibility projections; retained File 00, File 02, File 19, File 20 and CF-01 boundaries.
- Removed temporary, self-mutating, stale baseline/corrective and duplicate CI workflows plus branch-marker/noop artifacts; one exact-head RC2 workflow is authoritative.
- Added RC2 adversarial assurance, PHP 7.4/8.3 exact-head CI and deterministic RC2 packaging.

# Changelog

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

## RC2 Fresh Adversarial Hardening — 2026-08-07

- Closed renewal eligibility bypass on expired/renewal draft creation.
- Made professional claim issuance state-bound and snapshot-bound.
- Made schema backfill exhaustive and legacy migration bounded/resumable.
- Moved legacy-source deletion after successful new-record commit with dedupe-safe cleanup.
- Corrected privacy erasure pagination, all-version credential deletion, atomic revocation claim, and collision-free nullable subject anonymization.
- Corrected retention expiry to scrub subject/profile and credential metadata after physical deletion.
- Added a dedicated final hardening regression gate and third fresh adversarial review record.

### Four-plan harmonization hardening (7 Aug 2026)
- Aligned File 09 with current File 00 general contract 1.2.0 / runtime 1.2.11.
- Added current identity-document and approved-doctor-grant checks without circular pre-verification.
- Corrected CF-01 adult guardian logic and approved-snapshot schema compatibility.
- Revalidated File 00 assurance and unresolved high-risk signals at verification/reinstatement/claim time.
- Refreshed approved snapshot validity metadata on reinstatement.
- Made WhatsApp optional as specified; corrected phone copy and File 20 shell naming boundary.

## 1.2.0-RC3 — Forty-round corrective assurance — 2026-08-07

- Performed 40 independent review/fix rounds against the corrected RC2 baseline; 13 rounds found defects and 27 rounds found no new defect.
- Made File 00 membership assertions explicitly fail-closed on `result=allow`, jurisdiction-aware, and protected authorization/policy extension points from widening baseline access or removing minimum evidence/profile requirements.
- Corrected optional-field browser validation, stale outbox `processing` recovery, strict calendar-date validation, live reviewer scope revalidation and appeal/application/workload assignment locking.
- Rechecked step-up at evidence-grant use time, enforced evidence-review expiry through final/CF-01 paths, preserved the transition audit hash chain during erasure, and preserved valid predecessor verification during an in-progress renewal.
- Serialized credential upload ownership/quota checks and made quality sampling independent; reviewer-profile persistence failures are now surfaced and successful changes audited.
- Added `tests/review40-adversarial.py`, `REVIEW-40-ROUNDS-RC3.md`, RC3 release metadata, deterministic RC3 package identity and exact-head CI enforcement.
- Staging, live deployment and operational acceptance remain explicitly false pending external Hostinger acceptance.

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
