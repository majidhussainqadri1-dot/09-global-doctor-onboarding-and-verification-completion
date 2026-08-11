# File 09 — Evidence-Based Status

Runtime version: **1.3.0 RC6 candidate**
Core schema: **6**
Advanced Trust schema: **2**
Advanced Trust contract: **1.1.0**
Candidate branch: `codex/file09-1.3.0-rc6-80-round-review`
Current R9 frozen baseline: `75ea54ed5113bf7ee16e90443f17cc1b941933a9`

## Repository assurance

File 09 has nine separately preserved fresh 80-control review ledgers. Each later review treats the previous exact candidate as historical evidence rather than assuming that an earlier green run proves the later source.

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
| **R9** | **10** | **70** |

R9 defect-bearing rounds are **04, 05, 06, 07, 08, 09, 10, 11, 12, 13**. The permanent ledger is `REVIEW-80-ROUNDS-RC6-R9.md`; the permanent executable gate is `tests/eighty-round-audit-r9.py`.

R9 corrects owner-command reviewer reauthorization, evidence/grant DB uncertainty, download-capability use-time recheck, idempotent submission COMMIT truth, appeal DB-failure visibility, bounded outbox error visibility, dead-letter replay reauthorization, continuous-monitor exclusive processing leases, atomic Advanced Trust DB erasure and current release-evidence drift.

R9 corrective application also exposed QA-harness-only defects: one historical R5 token assertion had to follow the stronger processing-lease semantics; the R2/R7 historical gates were decoupled from obsolete current-status wording while preserving their historical truth; and the new R9 executable gate required one syntax repair plus semantic assertion repairs where the test had named stale/noncanonical tokens despite the underlying control being present. These harness repairs are not additional frozen-baseline File 09 product defects and are therefore not added to the ten R9 defect-bearing rounds.

## Completion truth

- Latest central + File 09 plan trace: **candidate complete**
- Advanced Trust 24 approved amendment trace: **candidate complete**
- Ninth fresh 80-round repository review/fix: **complete at source-review level**
- Coded RC6/R9 candidate: **complete at repository-candidate level**
- Automated QA: **GREEN only when the authoritative workflow succeeds on the exact final commit containing all R9 source/evidence changes**
- Deterministic package: **GREEN only when that same exact-head workflow completes the double-build, 60-entry package parity and generated SBOM verification**
- Staging accepted: **false**
- Live deployed: **false**
- Operationally accepted: **false**

Stable machine/historical-gate aliases (same truth, no additional status):

Staging accepted: false
Live deployed: false
Operationally accepted: false

## Authoritative RC6/R9 repository gate

The authoritative workflow is `.github/workflows/file09-rc2-final.yml`, named **File 09 RC6 Eighty-Round Exact-Head Assurance**. It must run against the final exact commit and pass PHP 7.4 + PHP 8.3 source suites, all legacy/adversarial/plan/Advanced Trust gates, **all R1–R9 80-round executable gates**, deterministic double build, **60-entry** release parity and exact-head SPDX 2.3 generated SBOM verification. Any later repository commit reopens this gate until that later exact head is green.

The prior R8 run/artifact certifies only the earlier R8 exact head `75ea54ed5113bf7ee16e90443f17cc1b941933a9`; it is historical after R9 source changes and must not be represented as the package for the corrected R9 source.

## External gates still pending

Hostinger staging must verify core schema 6 + Advanced Trust schema 2 migration, current File 00/File 02 and companion contracts, provider unavailable/mismatch/revoked/expired paths, issuer/rule governance, no provider auto-decision, conflict/dual-review routing, passport issue/supersession/expiry/revocation/public-safe output, chunk interruption/resume/race/hash/malware path, secure-room one-time authorization/no-download behavior, privacy export/erasure/retention interaction, real applicant/reviewer/more-info/resubmission/appeal/renewal/suspension/revocation journeys, mobile/RTL/accessibility/weak-network journeys, backup/restore/rollback, companion integrations and **two fresh staging review → fix → full-retest cycles** before explicit Founder acceptance.

Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status remain separate facts. This repository status does not claim RC6/R9 staging or production deployment.

## Historical review evidence

R1–R8 ledgers and executable gates remain in the repository unchanged as historical assurance evidence. Their former package-entry counts and exact-head workflow results certify only their corresponding historical source heads. R9 does not rewrite those historical results; it establishes a new current repository gate above them.
