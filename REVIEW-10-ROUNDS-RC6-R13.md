# File 09 — Thirteenth Fresh 10-Round Corrective Review (R13)

## Evidence boundary

- Frozen source baseline: `e5e867d235aafc49dd084644590afe8dfaf27d47` — exact R12 repository candidate that passed its own exact-head workflow.
- R13 is a new sequential repository/source review. R1–R12 remain historical evidence and are not counted as R13 findings.
- Governing basis: latest consolidated central plan + latest File 09 plan + approved F09-AT-01..24 Advanced Trust requirements.
- Runtime remains `1.3.0 RC6`; core schema `6`; Advanced Trust schema `2`; Advanced Trust contract `1.1.0`.
- Repository evidence does not establish Hostinger staging, live deployment, database migration state or operational acceptance.

## R13 result

- Total rounds: **10**
- Defect-bearing rounds: **6**
- Clean rounds: **4**
- Defect-bearing rounds: **01, 04, 05, 06, 07, 10**
- Clean rounds: **02, 03, 08, 09**
- Post-correction executable target: **10 PASS / 0 FAIL**

## Sequential review → correction record

### R01 — Verification validity could outlive required supporting evidence
Final verification and approved-snapshot refresh accepted a future `verified_until` without bounding it to the earliest current required evidence expiry/credential validity. A doctor could therefore remain publicly current after a supporting license/evidence item had ceased to be current, until another lifecycle job caught the drift. R13 adds `GDO_Evidence::verification_valid_until_ceiling()` and requires finalization/snapshot refresh to remain at or before the earliest current required support ceiling. Database uncertainty, expired support or an unbounded required record fails closed.

### R02 — Clean: authoritative continuous-monitor callback and degraded retry
The active RC6 hardening layer explicitly removes the older base continuous-monitor callback and owns the authoritative callback. The reviewed callback recovers expired leases, claims work exclusively, processes both `scheduled` and `degraded` states, isolates application/evidence DB uncertainty, preserves provider degradation/backoff and does not auto-finalize a professional decision. No new defect was established.

### R03 — Clean: authorization, state concurrency and appeal separation
Application transitions remain row-version/state guarded; applicant appeal/withdrawal paths enforce ownership and valid state transitions; reviewer/finalizer actions recheck File 00 capabilities plus File 02 recent step-up; reviewer case/scope authorization remains object-bound; appeal assignment preserves reviewer independence. No new defect was established.

### R04 — Physical evidence deletion preceded durable database deletion truth
Privacy erasure and retention could unlink encrypted credential bytes first and only then update the evidence row. A database failure after unlink left durable DB truth pointing at a now-missing private file; `delete_verified()` could also treat an already-missing file as success without a 64-hex deletion proof. R13 introduces a shared durable deletion-pending lifecycle. The source file/hash must be verified before the pending DB state is persisted; only then may unlink occur; final proof/scrubbing is a separate checked write. Interrupted post-unlink finalization remains pending and safely retryable. Privacy and retention now use the shared primitive, including pending superseded retries.

### R05 — Dead-letter replay inherited DB error state and conflated store failure with conflict
`GDO_Notifications::replay()` did not isolate `$wpdb->last_error` before its dead-letter lookup and treated `$wpdb->update() === false` as the same outcome as an optimistic zero-row conflict. R13 resets DB error state around both operations and returns a dedicated `gdo_outbox_replay_store_failed` error for infrastructure/store failure while preserving `gdo_outbox_replay_conflict` for genuine state change.

### R06 — Missing mandatory maintenance schedules did not close mutation readiness
Health exposed missing retention, notification-outbox and continuous-verification cron events only as warnings, while `mutation_allowed()` could continue accepting sensitive mutations. After a restore/repair that lost scheduled events, File 09 could therefore accept new private/professional state while mandatory maintenance work was unscheduled. R13 makes all three exact recurring schedules part of mutation readiness and critical health. Recovery endpoints remain usable through the existing privileged recovery guard so schedules can be repaired without weakening the fail-closed gate.

### R07 — Public verification scope could remain stale after expiry or before claim acknowledgement
The public scope matrix treated accepted evidence as `verified` without rechecking `expires_at`/`validity_until`, used an unchecked evidence inventory, and its public `current_status` did not require the current File 00 professional claim to be acknowledged. R13 uses checked current evidence reads, under-states public professional truth on DB uncertainty, ignores expired accepted evidence for verified scope, and binds current public professional status to `claim_status=accepted` in addition to identity/state/verified-until checks.

### R08 — Clean: REST object scope, secure viewing room and resumable uploads
Reviewer trust-check/viewing-room routes remain capability + step-up + object/case bound. Evidence IDs are resolved inside the requested application. Resumable uploads re-lock the applicant/application, enforce state and candidate authorization, serialize ordered chunks, validate size/hash, and finalize through canonical encrypted `GDO_Evidence::stage_upload()`. No new defect was established.

### R09 — Clean: package/source hygiene and release identity
PHP syntax, JavaScript syntax, CSS brace balance, obvious secret-literal patterns and path hygiene were rechecked on the corrected source. Runtime/schema/contract identities and the 62-entry deterministic release allowlist remain unchanged. No new source/package-list defect was established; the package itself must still be rebuilt and verified on the final exact R13 commit.

### R10 — New R13 corrections lacked permanent exact-head QA/release evidence
After R01–R09, the repository had no permanent R13 ledger, executable ten-round gate, R13 release-lock fields, current status/manifest evidence or authoritative workflow invocation. R13 adds this ledger, `tests/ten-round-audit-r13.py`, thirteenth release-lock metadata and workflow wiring while preserving the single authoritative `.github/workflows/file09-rc2-final.yml`. R13 QA files remain repository-only and do not change the 62-entry installable allowlist.

## Historical QA-harness compatibility corrections

Two pre-R13 static gates encoded the old location of deletion-proof logic and failed after R04 deliberately centralized physical deletion and proof persistence in `GDO_Evidence::delete_record_safely()`. `tests/completion-security.py` and round 22 of `tests/review40-adversarial.py` were adapted to assert the stronger shared durable deletion primitive instead of requiring `delete_verified`/`deletion_proof` literals in the old caller files. These are historical test-contract adaptations, not additional R13 product/source defects. Temporary harness workflows were removed after applying the corrections.

## Final exact-head gate

R13 is repository-QA complete only when the final exact commit containing all R13 corrections/evidence passes:

- PHP 7.4 and PHP 8.3 complete source/regression/R1–R13 suites;
- R13 executable **10 PASS / 0 FAIL**;
- all historical R1–R12 gates still green;
- deterministic RC6 double build with byte-for-byte equality;
- exact **62-entry** package parity;
- generated exact-head SPDX 2.3 SBOM verification;
- uploaded artifact whose `EXACT-HEAD.txt` equals that exact commit.

Any later commit reopens the gate. Even after repository QA is green: **Staging-Accepted=false, Live-Deployed=false, Operational=false** until the exact artifact is externally verified on Hostinger staging and subsequently accepted/deployed/retested under the project Live-First rule.
