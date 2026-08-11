# File 09 — Evidence-Based Status

Runtime version: **1.3.0 RC6 candidate**
Core schema: **6**
Advanced Trust schema: **2**
Advanced Trust contract: **1.1.0**
Candidate branch: `codex/file09-1.3.0-rc6-80-round-review`

## Repository assurance history

R1–R11 are preserved historical 80-round reviews. R12 is a separate fresh **10-round** corrective review against the last R11 green exact-head baseline; it does not reuse or renumber earlier rounds.

R13 is another separate fresh **10-round** corrective review against the final green R12 exact-head baseline; it does not reuse or renumber R1–R12 findings.

**Eleventh fresh 80-round corrective assurance — historical R11:** 17 defect-bearing rounds corrected, 63 clean, final 80/80 PASS on its own exact historical head.

| Review | Review size | Defect-bearing rounds corrected | Clean rounds |
|---|---:|---:|---:|
| R1 | 80 | 49 | 31 |
| R2 | 80 | 47 | 33 |
| R3 | 80 | 13 | 67 |
| R4 | 80 | 30 | 50 |
| R5 | 80 | 29 | 51 |
| R6 | 80 | 60 | 20 |
| R7 | 80 | 22 | 58 |
| R8 | 80 | 15 | 65 |
| R9 | 80 | 10 | 70 |
| R10 | 80 | 19 | 61 |
| R11 | 80 | 17 | 63 |
| R12 | 10 | 10 | 0 |
| **R13** | **10** | **6** | **4** |

## Twelfth fresh 10-round corrective assurance

- Frozen R12 baseline: `0df40731282a4dc905a12b37e5fa5aa4604dc68a`.
- Source-correction head reached during R12: `bdc23c188d7976aae85a8024697b36543da8eb0e`; later evidence/gate commits reopen exact-head CI until the final branch head is green.
- Defect-bearing rounds: **01, 02, 03, 04, 05, 06, 07, 08, 09, 10**.
- Product/source corrections cover reviewer/finalizer fail-closed reads; claim-acknowledgement-bound passport issuance; checked submission snapshots; fail-closed applicant rendering; verified draft post-write reload; migration evidence/read certainty; legacy credential migration failure/COMMIT ambiguity; version-bound claim failure handling; retention/orphan-cleanup failure propagation.
- R10 is the repository QA/release-evidence defect: permanent R12 ledger, release-lock fields, executable gate and authoritative workflow wiring were missing after source correction and are now added.
- Permanent ledger: `REVIEW-10-ROUNDS-RC6-R12.md`.
- Permanent executable gate: `tests/ten-round-audit-r12.py` — required result **10 PASS / 0 FAIL**.
- Temporary R12 apply workflow: **absent**; the sole authoritative workflow remains `.github/workflows/file09-rc2-final.yml`.
- Installable release allowlist: **62 entries**; R12 ledger/test are repository QA evidence and intentionally not packaged.

## Thirteenth fresh 10-round corrective assurance

- Frozen R13 baseline: `e5e867d235aafc49dd084644590afe8dfaf27d47`.
- Defect-bearing rounds: **01, 04, 05, 06, 07, 10**.
- Clean rounds: **02, 03, 08, 09**.
- Product/source corrections bound verified validity to the earliest required current evidence/credential expiry; introduce durable deletion-pending evidence erasure/retention; isolate dead-letter replay DB state and distinguish store failure from state conflict; require retention/outbox/continuous-monitor schedules for mutation readiness; and make public scope evidence-expiry-aware, DB-fail-closed and current professional-claim-acknowledgement-bound.
- R10 is the repository QA/release-evidence defect: permanent R13 ledger, release-lock fields, executable gate, current status/manifest evidence and authoritative workflow invocation were missing after the source corrections and are now added.
- Permanent ledger: `REVIEW-10-ROUNDS-RC6-R13.md`.
- Permanent executable gate: `tests/ten-round-audit-r13.py` — required result **10 PASS / 0 FAIL**.
- Temporary R13 apply workflow/patch parts: **absent after source correction**; the sole authoritative workflow remains `.github/workflows/file09-rc2-final.yml`.
- Installable release allowlist remains **62 entries**; R13 ledger/test are repository QA evidence and intentionally not packaged.

## Completion truth

- Latest central + File 09 plan trace: **candidate complete**
- Advanced Trust 24 approved amendment trace: **candidate complete**
- R13 ten-round repository review/fix: **complete at source-review level**
- Coded RC6/R13 candidate: **complete at repository-candidate level**
- Automated QA: **GREEN only when the authoritative workflow succeeds on the exact final commit containing all R13 source/evidence changes**
- Deterministic package: **GREEN only when that same exact-head workflow completes the double-build, 62-entry package parity and generated exact-head SPDX 2.3 SBOM verification**
- Staging accepted: **false**
- Live deployed: **false**
- Operationally accepted: **false**

Stable machine/historical-gate aliases:

Staging accepted: false
Live deployed: false
Operationally accepted: false

## Authoritative RC6/R13 repository gate

The authoritative workflow is `.github/workflows/file09-rc2-final.yml`, named **File 09 RC6 Eighty-Round Exact-Head Assurance**. It must run against the final exact commit and pass PHP 7.4 + PHP 8.3 source suites, all legacy/adversarial/plan/Advanced Trust gates, **all R1–R11 80-round executable gates plus the R12 and R13 ten-round gates**, deterministic double build, **62-entry** release parity and exact-head SPDX 2.3 generated SBOM verification. Any later repository commit reopens this gate until that later exact head is green.

All earlier workflow runs/artifacts, including the R12 exact-head artifact from `e5e867...`, are historical after R13 source changes and must not be represented as the current corrected package.

## External gates still pending

Hostinger staging must verify core schema 6 + Advanced Trust schema 2 migration, current File 00/File 02 and companion contracts, provider unavailable/mismatch/revoked/expired paths, issuer/rule governance, no provider auto-decision, conflict/dual-review routing, passport issue/supersession/expiry/revocation/public-safe output, chunk interruption/resume/race/hash/malware path, secure-room one-time authorization/no-download behavior, privacy export/erasure/retention interaction, real applicant/reviewer/more-info/resubmission/appeal/renewal/suspension/revocation journeys, mobile/RTL/accessibility/weak-network journeys, backup/restore/rollback, companion integrations and **two fresh staging review → fix → full-retest cycles** before explicit Founder acceptance.

Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status remain separate facts. This repository status does not claim RC6/R13 staging or production deployment.

## Historical compact compatibility rows

These compact rows preserve the immutable historical three-column ledger shape consumed by earlier regression gates; they are not the current-status table and do not supersede R13.

| Review | Defect-bearing rounds corrected | Clean rounds |
|---|---:|---:|
| R1 | 49 | 31 |
| R2 | 47 | 33 |
| R3 | 13 | 67 |
| R4 | 30 | 50 |
| R5 | 29 | 51 |
| R6 | 60 | 20 |
| R7 | 22 | 58 |
| R8 | 15 | 65 |
| R9 | 10 | 70 |
| R10 | 19 | 61 |
| R11 | 17 | 63 |
