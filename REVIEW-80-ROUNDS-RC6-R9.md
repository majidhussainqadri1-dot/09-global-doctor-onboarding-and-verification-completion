# File 09 — Ninth Fresh 80-Round Corrective Review (R9)

## Evidence boundary

- Frozen source baseline reviewed: `75ea54ed5113bf7ee16e90443f17cc1b941933a9` (R8 exact-head candidate).
- Review type: fresh repository/source adversarial review; prior R1–R8 results were preserved as historical evidence and were not counted as R9 findings.
- Governing scope: latest consolidated central plan + latest File 09 plan including F09-AT-01..24.
- Runtime remains `1.3.0 RC6`; core schema `6`; Advanced Trust schema `2`; contract `1.1.0`.
- This ledger proves repository review only. It does not prove Hostinger staging, live deployment or operational acceptance.

## Final R9 count

- Total rounds: **80**
- Defect-bearing rounds: **10**
- Clean rounds: **70**
- Corrected post-review executable result target: **80 PASS / 0 FAIL**

**Defect-bearing rounds:** `04, 05, 06, 07, 08, 09, 10, 11, 12, 13`.

**Clean rounds:** `01, 02, 03, 14–80`.

## Defects found and immediately corrected

### R04 — Evidence-review owner command lacked full method-boundary reauthorization
`GDO_Evidence::review()` relied on outer UI/REST guards plus case/scope checks. A mutable owner command must independently verify the actual current actor, current File 00 reviewer capability and current File 02 step-up. The method now fails closed unless the supplied reviewer is the current user, still has `sabri_verify_doctors`, and has a valid recent step-up.

### R05 — Evidence-review DB uncertainty could collapse into a normal invalid state
Evidence/application reads in the review path did not isolate and inspect `$wpdb->last_error`. A database read failure could therefore be reported as a generic invalid review rather than an infrastructure failure. The evidence and application reads now clear DB error state and return explicit fail-visible errors.

### R06 — Credential grant consumption had a download-capability TOCTOU gap and ambiguous reads
Download capability was checked when issuing a grant but not independently rechecked when consuming it. Capability revocation between issue and use therefore had an unnecessary time-of-check/time-of-use window. Grant/evidence/application reads also lacked explicit DB-uncertainty distinction. Consumption now rechecks the current download capability and fails visibly on grant/evidence/application database uncertainty.

### R07 — Idempotent submission ignored COMMIT failure
The already-submitted/same-hash idempotent path issued `COMMIT` and immediately returned success without verifying the commit result. It now verifies COMMIT, rolls back on failure and returns `gdo_submit_idempotent_commit` instead of false success.

### R08 — Appeal rendering could hide a database failure as “no open appeal”
The manager workflow read the current open appeal without isolating database error state. A failed read could suppress appeal controls and be mistaken for absence. The UI now displays an explicit failure notice and makes no appeal-state assumption when the database read fails.

### R09 — Manual bounded outbox processing could redirect as success after a processing error
The admin recovery action called `GDO_Notifications::process(100)` but ignored a returned `WP_Error`. It now surfaces the failure as a 503 operator-visible error and redirects only after successful bounded processing.

### R10 — Dead-letter replay trusted a caller-supplied actor ID at the mutation boundary
`GDO_Notifications::replay()` changed durable outbox state but did not independently bind the supplied actor ID to the current user or recheck current File 00 verification-management authority and File 02 step-up. It now performs all three checks before mutation.

### R11 — Continuous verification lacked an exclusive processing lease
The monitor selected due rows and then performed provider/check work without atomically claiming each row. Recurring cron and event-driven wakeups could overlap, duplicate work and race final monitor state. R9 adds stale-processing-lease recovery, an atomic `scheduled/degraded → processing` claim, a bounded lease, safe claim release on failure, and conditional final persistence that cannot overwrite a newer scheduling event.

### R12 — Advanced Trust database-side privacy erasure was not atomic
Advanced Trust privacy erasure performed several deletes/anonymizations sequentially. A later DB error could leave a partially erased/anonymized database state. After filesystem chunk cleanup, all Advanced Trust DB erasure/anonymization operations now run inside one checked transaction with rollback on failure and verified COMMIT.

### R13 — Current release metadata drifted behind the actual review/package evidence
Primary current-state documentation still contained older entry-count/review wording after R8. R9 synchronizes current README/status/manifest/trace/release-lock/release allowlist and the authoritative workflow to the ninth review and a **60-entry** release allowlist. Historical R1–R8 counts remain unchanged as historical evidence.

## QA-harness correction during R9 application

The first corrective harness run exposed one stale historical R5 static assertion: it required the older textual form `false === $wpdb->delete` for orphan monitor deletion. R9 intentionally strengthened that operation so deletion is conditional on the row still being owned by the current `processing` lease. The historical assertion was updated to verify the stronger processing-state condition and explicit orphan-delete failure path. This was a regression-harness compatibility defect introduced by the stronger correction, not an additional frozen-baseline product defect; it is therefore not added to the R9 defect-round count.

## R14–R80 fresh clean controls

The remaining fresh controls rechecked evidence case binding, accepted-license validity, one-time grants, watermarking, private storage boundaries, state-machine concurrency, submission idempotency, core/Advanced Trust migration guards, provider degradation behavior, event-driven reverification, passports and cache policy, jurisdiction/issuer governance, provider minimization, reviewer conflict/dual-review/calibration, human risk decisions, File 19 outbox semantics, signed File 00 claims, current File 00/File 02 authorization, Safe Mode/repair, operational health/reconciliation, privacy/retention, resumable cleanup, File 20 shell boundary, Files 03/07/08/21/23/26 projections, File 24 ownership, rollback/security docs, FR/NFR/AT traceability and release synchronization. No additional repository defect was found in rounds 14–80.

## Final status law

R9 is complete only when the final exact commit containing this ledger, `tests/eighty-round-audit-r9.py`, the synchronized 60-entry release allowlist and authoritative workflow passes:

- PHP 7.4 source/regression/R1–R9 assurance;
- PHP 8.3 source/regression/R1–R9 assurance;
- R9 executable 80/80 PASS;
- deterministic double RC6 build;
- exact 60-entry package parity;
- generated SPDX 2.3 exact-head SBOM verification.

Until that final exact-head run succeeds, the prior R8 artifact remains historical evidence only and must not be represented as the package for the corrected R9 source.

Even after final repository QA succeeds, **Staging-Accepted=false, Live-Deployed=false, Operational=false** until the latest governing staging/production DoD is executed with the exact artifact.
