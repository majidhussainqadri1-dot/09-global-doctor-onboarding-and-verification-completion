# File 09 — RC6 Sixth Fresh 80-Round Corrective Assurance — R6

**Review date:** 10 August 2026  
**Frozen baseline:** `6fa0a5cb7063b6b821bd50c105c735470f589b80`  
**Scope:** File 09 runtime/source, Advanced Trust, private evidence, privacy/retention, lifecycle callbacks, release integrity and current governing-plan boundaries.  
**Method:** Each numbered control was examined against the frozen exact-head baseline. If a defect was found, its root cause was corrected immediately and the same control was re-reviewed before moving to the next round. A defect-bearing round records what the baseline exposed; the final executable R6 gate records the corrected-tree result.

## Governing truth boundary

This is repository-candidate evidence only. It does **not** establish Hostinger staging acceptance, deployed package parity, live database/schema state, production migration state, live workflow correctness or operational acceptance. Those remain separate external gates.

## Result

- Total rounds: **80**
- Defect-bearing rounds on the frozen baseline: **60**
- Clean rounds on the frozen baseline: **20**
- Final corrected-tree target: **80 PASS / 0 FAIL** under `tests/eighty-round-audit-r6.py`, followed by the authoritative exact-head PHP 7.4/PHP 8.3 workflow and deterministic package gate.

**Defect-bearing rounds:** 04, 05, 06, 07, 08, 09, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 26, 28, 29, 30, 31, 32, 33, 35, 36, 37, 38, 39, 40, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 80.

**Clean rounds:** 01, 02, 03, 25, 27, 34, 41, 42, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79.

## Round ledger

| Round | Baseline result | Review control | Immediate correction / disposition |
|---:|:---:|---|---|
| 01 | CLEAN | Runtime identity/version/schema truth | No new defect found; existing control retained and included in final regression assurance. |
| 02 | CLEAN | Repository status truth boundary | No new defect found; existing control retained and included in final regression assurance. |
| 03 | CLEAN | Canonical File 09 ownership/boundaries | No new defect found; existing control retained and included in final regression assurance. |
| 04 | DEFECT → FIXED | Issuer registration runtime mutation gate | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 05 | DEFECT → FIXED | Issuer duplicate-read DB uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 06 | DEFECT → FIXED | Issuer review lookup DB uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 07 | DEFECT → FIXED | Trusted issuer lookup DB uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 08 | DEFECT → FIXED | Jurisdiction-rule lookup DB uncertainty | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 09 | DEFECT → FIXED | Credential-check existence query uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 10 | DEFECT → FIXED | Authenticity reuse-count DB uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 11 | DEFECT → FIXED | Fraud-ring evidence-link query uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 12 | DEFECT → FIXED | Fraud-ring duplicate-signal query uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 13 | DEFECT → FIXED | Risk explanation query uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 14 | DEFECT → FIXED | Reviewer conflict duplicate query uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 15 | DEFECT → FIXED | Reviewer calibration query uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 16 | DEFECT → FIXED | Public professional-history query uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 17 | DEFECT → FIXED | Active verification-passport lookup uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 18 | DEFECT → FIXED | Public passport lookup uncertainty | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 19 | DEFECT → FIXED | Passport-token lookup uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 20 | DEFECT → FIXED | Applicant command-center trust-check query uncertainty | Changed the affected read/mutation path to distinguish database uncertainty from a legitimate empty result and fail closed with a stable WP_Error/audit path. |
| 21 | DEFECT → FIXED | Resumable upload-session runtime mutation gate | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 22 | DEFECT → FIXED | Upload-session transaction-start verification | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 23 | DEFECT → FIXED | Aggregate application-lock query verification | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 24 | DEFECT → FIXED | Target application row-lock query verification | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 25 | CLEAN | Temporary aggregate quota DB uncertainty | No new defect found; existing control retained and included in final regression assurance. |
| 26 | DEFECT → FIXED | Temporary upload-file permission persistence | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 27 | CLEAN | Upload-session insert/commit durability | No new defect found; existing control retained and included in final regression assurance. |
| 28 | DEFECT → FIXED | Chunk-append runtime mutation gate | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 29 | DEFECT → FIXED | Chunk transaction-start verification | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 30 | DEFECT → FIXED | Chunk row-lock DB uncertainty | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 31 | DEFECT → FIXED | Chunk seek result handling | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 32 | DEFECT → FIXED | Chunk fsync durability handling | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 33 | DEFECT → FIXED | Chunk rollback/truncation failure handling | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 34 | CLEAN | Chunk optimistic DB update/commit durability | No new defect found; existing control retained and included in final regression assurance. |
| 35 | DEFECT → FIXED | Finalize runtime mutation gate | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 36 | DEFECT → FIXED | Finalize transaction-start verification | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 37 | DEFECT → FIXED | Finalize row-lock DB uncertainty | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 38 | DEFECT → FIXED | Finalize recovery-state persistence | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 39 | DEFECT → FIXED | Finalize hash_file failure handling | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 40 | DEFECT → FIXED | Finalize filesize failure handling | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 41 | CLEAN | Finalize canonical evidence-stage error preservation | No new defect found; existing control retained and included in final regression assurance. |
| 42 | CLEAN | Finalize committed-marker requirement | No new defect found; existing control retained and included in final regression assurance. |
| 43 | DEFECT → FIXED | Committed temp-file deletion failure visibility | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 44 | DEFECT → FIXED | Post-finalize authenticity-check failure audit | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 45 | DEFECT → FIXED | Post-finalize AI-assist failure audit | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 46 | DEFECT → FIXED | Base expired-upload cleanup query uncertainty | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 47 | DEFECT → FIXED | Advanced Trust privacy-export query uncertainty | Hardened privacy/transparency reads and erasure so incomplete DB/storage evidence cannot be reported as a complete export, safe aggregate, or completed deletion. |
| 48 | DEFECT → FIXED | Native WordPress exporter degraded-state propagation | Hardened privacy/transparency reads and erasure so incomplete DB/storage evidence cannot be reported as a complete export, safe aggregate, or completed deletion. |
| 49 | DEFECT → FIXED | Transparency aggregate query uncertainty | Hardened privacy/transparency reads and erasure so incomplete DB/storage evidence cannot be reported as a complete export, safe aggregate, or completed deletion. |
| 50 | DEFECT → FIXED | Public transparency degraded/error propagation | Hardened privacy/transparency reads and erasure so incomplete DB/storage evidence cannot be reported as a complete export, safe aggregate, or completed deletion. |
| 51 | DEFECT → FIXED | Issuer REST mutation readiness | Added canonical runtime mutation-readiness enforcement to the privileged/REST path rather than relying on login/capability alone. |
| 52 | DEFECT → FIXED | Issuer-review REST mutation readiness | Added canonical runtime mutation-readiness enforcement to the privileged/REST path rather than relying on login/capability alone. |
| 53 | DEFECT → FIXED | Chunk/finalize mutation-readiness consistency | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 54 | DEFECT → FIXED | Secure viewing-room mutation readiness | Added canonical runtime mutation-readiness enforcement to the privileged/REST path rather than relying on login/capability alone. |
| 55 | DEFECT → FIXED | Reverification lookup/store DB uncertainty | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 56 | DEFECT → FIXED | Submission equivalency existence query uncertainty | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 57 | DEFECT → FIXED | Submission equivalency failure audit/propagation | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 58 | DEFECT → FIXED | Submission fraud-scan failure audit/propagation | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 59 | DEFECT → FIXED | Submission reverification-schedule failure audit | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 60 | DEFECT → FIXED | Professional-history idempotency lookup uncertainty | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 61 | DEFECT → FIXED | Professional-history insert failure visibility | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 62 | DEFECT → FIXED | Decision passport-revocation failure audit | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 63 | DEFECT → FIXED | Decision reverification-schedule failure audit | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 64 | DEFECT → FIXED | ensure_passport propagation of uncertain active-passport lookup | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 65 | DEFECT → FIXED | Hardened public-passport DB lookup uncertainty | Hardened the Advanced Trust lifecycle callback/query so DB/provider/store uncertainty is propagated or durably audited instead of being converted into success/absence. |
| 66 | DEFECT → FIXED | Hardened expired-upload cleanup storage health | Hardened resumable-upload transaction, storage, durability and cleanup semantics; unsafe or uncertain filesystem/DB results are now fail-visible and resumable/recoverable. |
| 67 | DEFECT → FIXED | Advanced privacy erasure storage health before physical proof | Hardened privacy/transparency reads and erasure so incomplete DB/storage evidence cannot be reported as a complete export, safe aggregate, or completed deletion. |
| 68 | CLEAN | Hardened cleanup DB-state write verification | No new defect found; existing control retained and included in final regression assurance. |
| 69 | CLEAN | Advanced privacy erasure operational-table cleanup verification | No new defect found; existing control retained and included in final regression assurance. |
| 70 | CLEAN | Public verification projection medical-safety semantics | No new defect found; existing control retained and included in final regression assurance. |
| 71 | CLEAN | Provider payload minimization/bounds | No new defect found; existing control retained and included in final regression assurance. |
| 72 | CLEAN | Human-final-decision invariant | No new defect found; existing control retained and included in final regression assurance. |
| 73 | CLEAN | Ordinary manager mutations obey Safe Mode | No new defect found; existing control retained and included in final regression assurance. |
| 74 | CLEAN | Canonical private-storage path/symlink controls | No new defect found; existing control retained and included in final regression assurance. |
| 75 | CLEAN | Core and Advanced Trust schema runtime gates | No new defect found; existing control retained and included in final regression assurance. |
| 76 | CLEAN | Destructive uninstall triple authorization | No new defect found; existing control retained and included in final regression assurance. |
| 77 | CLEAN | Privacy legal-hold fail-closed behavior | No new defect found; existing control retained and included in final regression assurance. |
| 78 | CLEAN | All 17 FR identifiers traceable | No new defect found; existing control retained and included in final regression assurance. |
| 79 | CLEAN | All 10 NFR + 24 AT identifiers traceable | No new defect found; existing control retained and included in final regression assurance. |
| 80 | DEFECT → FIXED | R6 ledger/gate/release-lock/package synchronization | Added the sixth immutable 80-round ledger, executable R6 gate, release-lock fields, traceability/status/manifest synchronization and 57-entry release allowlist. |

## Principal root-cause families corrected

- **Database uncertainty was sometimes indistinguishable from a valid empty result.** Trusted issuer, jurisdiction, credential-check, risk, conflict, calibration, history, passport, command-center and transparency reads now have explicit fail-closed DB-error semantics.
- **Resumable evidence upload had durability and recovery gaps.** Runtime mutation readiness, transaction start, row locking, seek/write/fsync, rollback truncation, hash/size reads, recovery-state persistence, temp cleanup and storage-health checks are now explicit and fail-visible.
- **Advanced Trust lifecycle callbacks could lose degraded results.** Submission, professional-history, passport, reverification and provider-assistance failures are propagated or durably audited rather than silently converted into success.
- **Privacy/export/transparency completion truth was too optimistic under DB/storage uncertainty.** The native exporter, Advanced Trust export, transparency snapshot and erasure now pause/fail safely instead of reporting incomplete work as complete.
- **Privileged mutation readiness was inconsistent.** Issuer, issuer-review, resumable upload/finalize and secure viewing-room mutations now use canonical runtime/Safe Mode readiness in addition to object/capability/step-up checks.

## Files corrected in R6

- `includes/class-gdo-advanced-trust.php`
- `includes/class-gdo-advanced-trust-hardening.php`
- `includes/class-gdo-privacy.php`
- `includes/class-gdo-storage.php`

Release/evidence synchronization additionally updates the R6 executable gate, release lock, release allowlist, release manifest, status, traceability, changelog and authoritative workflow.

## External gates still pending

Real WordPress/MySQL staging migration, current companion contracts, real applicant/reviewer/more-info/appeal/renewal/suspension/revocation journeys, provider degradation, storage/key/scanner behavior, private viewing-room behavior, privacy rights, backup/restore/rollback, accessibility/RTL/weak-network acceptance and the required fresh staging review→fix→retest cycles remain outside this repository-only R6 evidence.
