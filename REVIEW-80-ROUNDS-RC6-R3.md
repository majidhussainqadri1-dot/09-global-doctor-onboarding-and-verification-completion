# File 09 RC6 — Third Fresh Eighty-Round Corrective Review (R3)

**Review date:** 2026-08-10  
**Frozen review baseline:** `f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361`  
**Runtime target:** `1.3.0-RC6`  
**Core schema:** `6`  
**Advanced Trust schema / contract:** `2` / `1.1.0`  
**Method:** freeze exact repository baseline → inspect one independent control → when a defect is found, stop that control → identify root cause → correct it immediately → rerun the same control and relevant regressions → only then advance to the next control.

This is a repository/code review. It is **not** Hostinger staging acceptance, live deployment evidence, or operational acceptance. Exact deployed code remains separately verifiable under the platform Live-First Exact-Deployed-State Rule.

## Governing basis

The review uses the current File 09 plan including Advanced Trust 24, the consolidated central-plan corpus, File 00/File 02 trust boundaries, File 19 notification ownership, and the repository's current owner contracts. Repository evidence cannot silently alter plan scope, and a green static/CI gate is not a substitute for staging/live verification.

## Result

- Total review controls: **80**
- Defect-bearing rounds: **13**
- Clean rounds: **67**
- Defect-bearing rounds: **04, 16, 24, 31, 50, 51, 52, 53, 54, 77, 78, 79, 80**
- Rule: every listed defect-bearing round was corrected before proceeding; the final exact-head workflow independently re-executes the executable R3 gate after this ledger and release evidence are synchronized.

## Eighty-round ledger

| Round | Review control | Result after immediate correction | Defect / correction record |
|---:|---|---|---|
| 01 | Exact frozen baseline and no prior transport residue treated as product code | PASS | Clean |
| 02 | File 09 canonical ownership; no parallel verification truth | PASS | Clean |
| 03 | Repository/staging/live/operational truth separation | PASS | Clean |
| 04 | File 00 profile/assertion dependency under partial outage | PASS | **DEFECT** — `profile()` could return a stale profile when the current base assertion was unavailable. Corrected to return an empty projection/fail closed. |
| 05 | File 00 hard dependency and membership approval gate | PASS | Clean |
| 06 | File 02 recent step-up for sensitive reviewer paths | PASS | Clean |
| 07 | Profile/contact assertion sanitization and public/private separation | PASS | Clean |
| 08 | Jurisdiction normalization and policy versioning | PASS | Clean |
| 09 | Draft/application expiry semantics | PASS | Clean |
| 10 | Optimistic row-version concurrency on applicant writes | PASS | Clean |
| 11 | Transaction-start/commit failure handling | PASS | Clean |
| 12 | Submission consent/snapshot immutability | PASS | Clean |
| 13 | Evidence expiry/review validity propagation | PASS | Clean |
| 14 | Evidence write re-locks the current application | PASS | Clean |
| 15 | Persistent evidence quota and DB uncertainty | PASS | Clean |
| 16 | Native upload provenance and resumable internal provenance | PASS | **DEFECT** — a generic filter could widen `is_uploaded_file(false)` to trusted. Corrected to monotonic narrowing for HTTP uploads plus an explicit, exact private `.chunk-*` internal path for resumable finalization. |
| 17 | MIME/signature and PDF active-content rejection | PASS | Clean |
| 18 | Malware scan is mandatory/fail closed | PASS | Clean |
| 19 | Encryption/private storage/symlink boundary | PASS | Clean |
| 20 | Evidence replacement versioning and supersession | PASS | Clean |
| 21 | Submission transaction and idempotency | PASS | Clean |
| 22 | Approved snapshot/hash binding | PASS | Clean |
| 23 | Risk-evaluation failure propagation | PASS | Clean |
| 24 | Existing risk-signal lookup DB uncertainty | PASS | **DEFECT** — duplicate/open-risk lookup failure could continue toward a misleading create path. Corrected to explicit `gdo_risk_query_failed` fail-closed result. |
| 25 | Risk false-positive resolution and auditability | PASS | Clean |
| 26 | State transition row locking | PASS | Clean |
| 27 | Invalid-transition fail-closed semantics | PASS | Clean |
| 28 | Tamper-evident audit-chain integrity | PASS | Clean |
| 29 | Transition facts publish only after durable owner commit | PASS | Clean |
| 30 | Reviewer scope is case-bound/IDOR-safe | PASS | Clean |
| 31 | Privileged reviewer/operator capability requires current identity assurance | PASS | **DEFECT** — approved + capability + no sanction was insufficiently strict. Corrected to require current File 00 identity assurance for privileged capability checks. |
| 32 | Reviewer profile/authorization DB uncertainty | PASS | Clean |
| 33 | Reviewer workload uncertainty and bounded assignment | PASS | Clean |
| 34 | Self-review prevention | PASS | Clean |
| 35 | Exact applicant/application reviewer relationship | PASS | Clean |
| 36 | Conflict-of-interest uncertainty fails closed | PASS | Clean |
| 37 | Conflict extension is monotonic/narrow-only | PASS | Clean |
| 38 | Dual-review uncertainty fails closed | PASS | Clean |
| 39 | Independent finalizer enforcement | PASS | Clean |
| 40 | Independent appeal/reassignment path | PASS | Clean |
| 41 | Duplicate/open appeal serialization | PASS | Clean |
| 42 | Appeal DB-read/write failure propagation | PASS | Clean |
| 43 | Recommendation revalidates locked current state | PASS | Clean |
| 44 | Finalization revalidates locked current state | PASS | Clean |
| 45 | Approved-snapshot integrity before public professional truth | PASS | Clean |
| 46 | Professional claim signing and subject/version binding | PASS | Clean |
| 47 | Claim/outbox owner transaction coupling | PASS | Clean |
| 48 | Professional claim acknowledgement semantics | PASS | Clean |
| 49 | Outbox event UUID idempotency | PASS | Clean |
| 50 | Stale processing-lease recovery DB failure | PASS | **DEFECT** — lease-recovery UPDATE failure was not surfaced. Corrected to explicit error propagation. |
| 51 | Pending/failed outbox SELECT DB failure | PASS | **DEFECT** — query uncertainty could appear as an empty queue. Corrected to `gdo_outbox_query_failed`. |
| 52 | Successful provider delivery receipt persistence | PASS | **DEFECT** — provider success could be returned even if the durable `delivered` receipt was not stored. Corrected to fail visibly and audit persistence uncertainty. |
| 53 | Failed/dead outbox-state persistence | PASS | **DEFECT** — retry/dead state write failure was ignored. Corrected to explicit persistence failure and no false processing summary. |
| 54 | Dead-letter replay targets the exact selected event | PASS | **DEFECT** — replay reset one row but generic `process(1)` could process another event first. Corrected with exact `event_uuid` targeting and state/CAS recheck. |
| 55 | File 19 transport requires explicit acknowledgement | PASS | Clean |
| 56 | File 19 payload is privacy-minimized | PASS | Clean |
| 57 | Provider processed/duplicate acknowledgement semantics | PASS | Clean |
| 58 | Primary-source request allowlist after extension filters | PASS | Clean |
| 59 | External provider reference token minimization | PASS | Clean |
| 60 | Affiliation adapter data minimization | PASS | Clean |
| 61 | Translation adapter data minimization | PASS | Clean |
| 62 | AI assistance minimization and human-final decision invariant | PASS | Clean |
| 63 | Resumable aggregate quota across open/finalizing sessions | PASS | Clean |
| 64 | Chunk order, exact size and bounded dimensions | PASS | Clean |
| 65 | Atomic open→finalizing claim | PASS | Clean |
| 66 | Expired/abandoned private chunk cleanup | PASS | Clean |
| 67 | Evidence-view grant exact case binding | PASS | Clean |
| 68 | One-time/session/step-up evidence grant | PASS | Clean |
| 69 | Viewing room no-download/watermark contract | PASS | Clean |
| 70 | Passport snapshot/current-state/identity binding | PASS | Clean |
| 71 | Public passport read path does not mutate owner truth | PASS | Clean |
| 72 | Public cache/robots/privacy boundary | PASS | Clean |
| 73 | Renewal/predecessor continuity | PASS | Clean |
| 74 | Privacy export covers Advanced Trust records | PASS | Clean |
| 75 | Erasure covers application-scoped Advanced Trust derivatives | PASS | Clean |
| 76 | Retention/erasure DB failure remains observable | PASS | Clean |
| 77 | Health dashboard DB uncertainty and modern File 19 detection | PASS | **DEFECT** — failed COUNT queries could be coerced to zero/healthy and the modern File 19 contract could appear unavailable. Corrected with explicit DB-observability failure and current provider detection. |
| 78 | Reconciliation propagates query/transaction/claim/notification/metric failure | PASS | **DEFECT** — multiple reconciliation failures could be suppressed while reporting completion. Corrected with explicit error propagation and transaction boundaries. |
| 79 | Safe-mode persistence, schedule repair and orphan deletion fail closed | PASS | **DEFECT** — safe-mode write was unverified; schedule repair ignored scheduler failure; and a failed evidence-inventory query could be treated as an empty set before orphan deletion. Corrected all three paths, with orphan cleanup aborting on DB uncertainty. |
| 80 | Historical QA assertions, current contract parity, final R3 ledger/release/exact-head gate | PASS after correction | **DEFECT** — several historical static assertions still encoded superseded reviewer-scope, Advanced Trust provider/version, rate-limiter, and resumable-upload assumptions; R3 ledger/release evidence was also absent. Assertions were updated to the stronger current contracts, and R3 is added as an authoritative exact-head CI/release gate. |

## Corrective themes

The R3 defects were not feature-list omissions. They concentrated in **fail-closed uncertainty handling**, **durable delivery semantics**, **privileged identity freshness**, **upload provenance**, **operational truthfulness**, and **test/evidence drift**. The corrective rule was root-cause-first: no compensating bypass or patch stack was accepted where the underlying authority or persistence invariant could be repaired directly.

## Truth-status boundary

This ledger establishes the R3 repository review record only. It does not state that Hostinger staging or live production is accepted. Repository exact-head CI and deterministic package evidence must be attached to the final HEAD after the R3 ledger/release synchronization. Staging/live acceptance remains a separate gate.
