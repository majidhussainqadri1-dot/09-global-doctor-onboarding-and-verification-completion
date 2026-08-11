# File 09 — Evidence-Based Status

Runtime version: **1.3.0 RC6 candidate**
Core schema: **6**
Advanced Trust schema: **2**
Advanced Trust contract: **1.1.0**
Candidate branch: `codex/file09-1.3.0-rc6-80-round-review`
Current R10 frozen baseline: `ec3ca2dd715e05b66cf29c42f2f996c80987fcd7`

## Repository assurance

File 09 has ten separately preserved fresh 80-control review ledgers. Each later review treats the previous exact candidate as historical evidence rather than assuming that an earlier green run proves later source.

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
| **R10** | **19** | **61** |

R10 defect-bearing rounds are **04, 05, 06, 07, 08, 09, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22**. The permanent ledger is `REVIEW-80-ROUNDS-RC6-R10.md`; the permanent executable gate is `tests/eighty-round-audit-r10.py`.

R10 hardens fail-visible database uncertainty throughout public verification projection, private application REST/autosave, draft/consent/submission, privileged review/appeal paths, evidence upload/decrypt/key rotation, state transitions and claim issuance; requires current step-up for privileged health reads; preserves completeness-query uncertainty; and makes post-decision quality-sampling failure observable.

## Completion truth

- Latest central + File 09 plan trace: **candidate complete**
- Advanced Trust 24 approved amendment trace: **candidate complete**
- Tenth fresh 80-round repository review/fix: **complete at source-review level**
- Coded RC6/R10 candidate: **complete at repository-candidate level**
- Automated QA: **GREEN only when the authoritative workflow succeeds on the exact final commit containing all R10 source/evidence changes**
- Deterministic package: **GREEN only when that same exact-head workflow completes the double-build, 61-entry package parity and generated SBOM verification**
- Staging accepted: **false**
- Live deployed: **false**
- Operationally accepted: **false**

Stable machine/historical-gate aliases (same truth, no additional status):

Staging accepted: false
Live deployed: false
Operationally accepted: false

## Authoritative RC6/R10 repository gate

The authoritative workflow is `.github/workflows/file09-rc2-final.yml`, named **File 09 RC6 Eighty-Round Exact-Head Assurance**. It must run against the final exact commit and pass PHP 7.4 + PHP 8.3 source suites, all legacy/adversarial/plan/Advanced Trust gates, **all R1–R10 80-round executable gates**, deterministic double build, **61-entry** release parity and exact-head SPDX 2.3 generated SBOM verification. Any later repository commit reopens this gate until that later exact head is green.

The R9 run/artifact certifies only the earlier R9 exact head `ec3ca2dd715e05b66cf29c42f2f996c80987fcd7`; it is historical after R10 source changes and must not be represented as the package for corrected R10 source.

## External gates still pending

Hostinger staging must verify core schema 6 + Advanced Trust schema 2 migration, current File 00/File 02 and companion contracts, provider unavailable/mismatch/revoked/expired paths, issuer/rule governance, no provider auto-decision, conflict/dual-review routing, passport issue/supersession/expiry/revocation/public-safe output, chunk interruption/resume/race/hash/malware path, secure-room one-time authorization/no-download behavior, privacy export/erasure/retention interaction, real applicant/reviewer/more-info/resubmission/appeal/renewal/suspension/revocation journeys, mobile/RTL/accessibility/weak-network journeys, backup/restore/rollback, companion integrations and **two fresh staging review → fix → full-retest cycles** before explicit Founder acceptance.

Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status remain separate facts. This repository status does not claim RC6/R10 staging or production deployment.

## Historical review evidence

R1–R9 ledgers and executable gates remain preserved as historical assurance evidence. Their package counts and exact-head results certify only their corresponding historical source heads. R10 establishes the current repository gate above them.

## Eleventh fresh 80-round corrective assurance (R11)
- Frozen R11 baseline: `91d9a590e18e02030e27ed558ad2147981332ed3`.
- Eleventh fresh 80-round repository review: **17 defect-bearing rounds / 63 clean rounds**.
- R11 defect-bearing rounds: **04–20**; each established defect was corrected before the review advanced.
- R11 executable gate: `tests/eighty-round-audit-r11.py` — target **80 PASS / 0 FAIL** on exact current HEAD.
- Current deterministic release allowlist: **62-entry** RC6 package truth.
- Repository maturity remains: Specified=true; Coded=true candidate; Packaged/Automated-QA Green only after the final exact-head workflow succeeds; **Staging Accepted=false; Live Deployed=false; Operationally Accepted=false**.
