# File 09 — Tenth Fresh 80-Round Corrective Review (R10)

## Evidence boundary

- Frozen source baseline reviewed: `ec3ca2dd715e05b66cf29c42f2f996c80987fcd7` — the exact R9 candidate that had passed its own exact-head workflow.
- Review type: new repository/source adversarial review. R1–R9 remain historical evidence and are not counted as R10 findings.
- Governing basis: latest consolidated central plan + latest File 09 plan + approved F09-AT-01..24 Advanced Trust amendment.
- Runtime remains `1.3.0 RC6`; core schema `6`; Advanced Trust schema `2`; contract `1.1.0`.
- This ledger proves repository review only. It does not establish Hostinger staging, live deployment or operational acceptance.

## R10 result

- Total rounds: **80**
- Defect-bearing rounds: **19**
- Clean rounds: **61**
- Defect-bearing rounds: **04, 05, 06, 07, 08, 09, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22**
- Clean rounds: **01, 02, 03, 23–80**
- Post-correction executable target: **80 PASS / 0 FAIL**

## Defects found and corrected immediately

### R04 — Public verification projection DB uncertainty
`GDO_API::latest_decision()` could turn a failed verification-record query into the ordinary `not_applied` projection. It now isolates `$wpdb->last_error`, returns `state=unavailable`, `verified=false`, and `reason_code=database_unavailable`, and reuses the already-read approved snapshot instead of opening another avoidable read window.

### R05 — Private application REST read ambiguity
The private application GET/autosave paths could represent a failed application read as an ordinary absent/inaccessible application. They now return explicit 503 `WP_Error` states for database uncertainty.

### R06 — Privileged health permission lacked recent step-up
The operator permission callback required verification-management capability but did not independently require the current File 02 recent privileged step-up. The callback now binds to the current user, current File 00 management capability and recent step-up.

### R07 — Draft creation could misread DB failure as “no application”
`ensure_draft()` previously consumed `latest_for_user()` without isolating database failure. A read failure could proceed toward first-draft creation. It now fails visibly with `gdo_application_latest_query`.

### R08 — Consent DB reads were not fully fail-visible
The locked application and existing consent-history reads are now independently checked after clearing DB error state. Failed reads return `gdo_consent_application_query` or `gdo_consent_history_query` and roll back.

### R09 — Submission row-lock DB uncertainty
The submission command now distinguishes application lock/read failure from normal access/state denial and rolls back with `gdo_submit_application_query`.

### R10 — Admin application lock/read ambiguity
The common privileged `lock_application()` helper could translate a failed `FOR UPDATE` read into ordinary missing/conflict handling. It now clears DB state and aborts the transaction with an operator-visible 503 on database uncertainty.

### R11 — Reviewer eligibility DB uncertainty
Reviewer-profile lookup now distinguishes database failure from a missing/inactive reviewer. Workload-count reads now isolate their DB error state before applying workload policy.

### R12 — Assignment/evidence-review preflight DB uncertainty
Reviewer-profile locks, evidence/application preflight reads, and appeal-assignment reads now surface database uncertainty. Appeal assignment also reuses the hardened application lock helper instead of an avoidable split lock/read sequence.

### R13 — Appeal-resolution DB uncertainty
An open-appeal `FOR UPDATE` failure could previously appear as a stale or missing appeal. It is now an explicit 503 transaction failure.

### R14 — Evidence upload replacement DB uncertainty
`GDO_Evidence::stage_upload()` now checks both the locked application read and current-evidence replacement read before quota/replacement logic. Database failure cannot be treated as an absent application or absent prior credential.

### R15 — Evidence decrypt/key-rotation DB uncertainty
Credential decryption now fails explicitly if its application cannot be read safely. Key rotation now distinguishes evidence-query failure from “record absent / no rotation needed” and propagates keyring errors instead of returning false success.

### R16 — Quality-sampling failure was silent after final decision
`GDO_Quality::create_sample()` formerly returned `0` on runtime/store failure. It now returns structured `WP_Error` values. Final decision flow records a dedicated `doctor_verification_quality_sample_failed` audit fact if sampling fails after the already-committed professional decision, instead of silently discarding the QA failure.

### R17 — Front-end application-state DB ambiguity
The application UI now distinguishes a failed private application read from a normal “no application/current state” path and renders an explicit unavailable status.

### R18 — Applicant appeal lock/read ambiguity
The applicant appeal path now checks the locked application read and returns 503 on database uncertainty rather than calling the failure an invalid appeal.

### R19 — Generic state-transition lock/read ambiguity
`GDO_State::transition()` could convert a failed application row lock into `gdo_invalid_transition`. The state-machine primitive now reports `gdo_transition_application_query`, preserving the distinction between infrastructure failure and a genuinely forbidden transition.

### R20 — Professional-claim issuance lock/read ambiguity
`GDO_Claims::issue()` now distinguishes a failed locked application read from an actually missing application and rolls back with `gdo_claim_application_query`.

### R21 — Completeness DB uncertainty could be reported as incomplete application
Completeness calculation now tracks database uncertainty across required-evidence and consent reads. Submission treats `query_error` as `gdo_submit_completeness_query`, not as a user-caused missing credential/consent condition.

### R22 — Draft-save application read ambiguity
`save_draft()` now isolates the initial application read and returns `gdo_draft_application_query` if the database read fails, instead of reporting ordinary edit/access denial.

## R23–R80 clean controls

The remaining fresh controls rechecked state-machine concurrency, immutable transition audit chain, submission idempotency, professional evidence validity, current reviewer authorization/case binding, one-time/session-bound evidence grants, watermark/no-download handling, encrypted private storage boundaries, core and Advanced Trust schema guards, continuous-verification leases/degraded-provider behavior/no-auto-decision invariant, event-driven reverification, passport current-state/cache/serialization rules, jurisdiction/issuer governance, provider minimization/secret filtering, AI/equivalency/translation human-final-decision boundary, conflicts/dual review/routing/calibration, risk handling, File 19 durable outbox, signed File 00 professional claims, File 00/File 02 current assurance, Safe Mode/repair/reconciliation, privacy/retention, resumable cleanup, File 20 shell boundary, Files 03/07/08/21/23/26 public-safe projections, donor/ranking neutrality, full FR/NFR/AT traceability and preservation of R1–R9 historical ledgers. No additional repository defect was identified in rounds 23–80.

## Final exact-head gate

R10 is not repository-QA complete merely because corrections exist. The final exact commit containing this ledger, `tests/eighty-round-audit-r10.py`, synchronized release metadata, 61-entry allowlist and authoritative workflow must pass:

- PHP 7.4 complete source/regression/R1–R10 suite;
- PHP 8.3 complete source/regression/R1–R10 suite;
- R10 executable **80 PASS / 0 FAIL**;
- deterministic RC6 build twice with byte-for-byte equality;
- exact **61-entry** package parity;
- generated SPDX 2.3 exact-head SBOM verification.

Until that exact-head run is green, every earlier artifact is historical only.

Even after repository QA is green: **Staging-Accepted=false, Live-Deployed=false, Operational=false** until the latest Hostinger staging/production Definition of Done is executed on the exact artifact.
