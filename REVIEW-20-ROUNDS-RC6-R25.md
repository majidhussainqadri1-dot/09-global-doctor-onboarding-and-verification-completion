# File 09 — R25 Hourly 20-Round Current-Plan + Exact-Companion Audit

Date: 2026-10-07  
Frozen File 09 baseline: `9639f75ba046ac1a36e39d5e9aae56c7bae3279b`  
Scope: current File 09 governing plan, Definitive Central Master Plan v3.0, Advanced Trust 24 amendment, and current exact-HEAD companion contracts.  
Method: all 20 review rounds were completed and recorded before any correction was applied.  
Status law: repository evidence only. Deployed package, database/schema, migration, staging and live behavior are independent facts and are not inferred here.

## Evidence frozen before review

| Companion | Exact main HEAD |
|---|---|
| File 00 | `2fa7c022ee9cd1b65432e900579512f304532442` |
| File 02 | `224c39bcb8c28f77504c7348dbad41226753c7e8` |
| File 03 | `636e3ef965423887f810718abec3cd1c11c3659d` |
| File 07 | `67c32ec4af45a7de6e3d9c1dbf0f8614d6b5a844` |
| File 08 | `70541974ce0ffb16aebef557c3016eb7447662f4` |
| File 14 | `55e44d38b23304d50fad22b4d3a2c67fe4721209` |
| File 19 | `04078025b643ab7696e4cb4e37826bf152defa18` |
| File 20 | `8a4dbcaf4fef8e926b9b834ecfde16c21a0f00ca` |
| File 21 (`sabri-complete-home-news-feed`) | `f2eb7e95ddea327af36ea725ffb923b029f885e6` |
| File 23 | `dcae138e6073f4d0ff596623deb05b9940b8271b` |
| File 24 | `a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb` |
| File 25 | `2d02c93356b050313e30e29aeceb57080771c2a5` |
| File 26 | `bbea3aad466792a4a6a62b53532bbd45c7c592de` |
| CF-04 | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |

The previous R24 audit could not discover a current File 14 repository. R25 did discover it, froze its exact head, inspected its actual File 09 consumer path, and verified File 14 exact-head GitHub Actions success. File 25 also advanced by 16 commits from the R24-reviewed baseline and its new exact head was separately inspected and verified green. These are repository/CI facts, not deployment facts.

## Twenty complete review rounds

| Round | Result | Review focus | Finding |
|---|---|---|---|
| R01 | CLEAN | Exact baseline freeze | File 09 and every relevant discoverable companion were frozen before review; unavailable/live facts were not inferred. |
| R02 | CLEAN | Canonical ownership | File 09 alone owns professional applications, private evidence, manual decisions, renewal, suspension, revocation and appeals; no companion write ownership was absorbed. |
| R03 | CLEAN | File 00 identity/claims | Current identity, membership, sanction and professional-claim acknowledgement remain File 00-owned and fail closed at the File 09 boundary. |
| R04 | CLEAN | File 02 step-up | Reviewer/finalizer/repair/Safe Mode privileged mutations remain current-user and recent-step-up constrained. |
| R05 | CLEAN | Applicant lifecycle | Eligibility, guided draft/resume, completeness, immutable submission, status, more-info, resubmission, withdrawal and appeal remain represented. |
| R06 | CLEAN | Private evidence | Type/MIME/malware/quota checks, encrypted private storage, replacement versions, grants, secure viewing and deletion proof remain bounded. |
| R07 | CLEAN | Reviewer governance | Assignment, scope, conflict, dual review, finalizer separation, appeal independence and quality sampling remain human-controlled. |
| R08 | CLEAN | Decision/claim/outbox | Final decisions, signed File 00 claims and durable minimized File 19 outbox semantics remain explicit and fail-visible. |
| R09 | CLEAN | Renewal/suspension/revocation | Expiry, renewal, suspension, revocation, reinstatement, passport invalidation and derivative correction remain implemented. |
| R10 | CLEAN | Privacy/retention | Export, erasure, legal hold, retention, private file deletion and Advanced Trust cleanup remain owner-scoped and failure-aware. |
| R11 | CLEAN | Advanced Trust 24 | Issuers/rules, primary-source facts, monitoring, passport, history, AI-assistance-only, risk, routing, resumable upload, viewing room and transparency boundaries remain present. |
| R12 | CLEAN | File 03 | Current public profile and credential projections remain explicit, minimized, read-only and free of private application/evidence leakage. |
| R13 | CLEAN | Files 07/08 | File 09 supplies current verification eligibility only; directory/ranking and clinic/appointment truth remain in their owners. |
| R14 | CLEAN | Current File 14 runtime | File 14 exact head now directly consumes `gdo_file14_onboarding_destination()` and accepts File 09's owner, availability, canonical URL, reason and contract version without automatic enrollment or verification. |
| R15 | CLEAN | Current File 25 runtime | File 25's advanced exact head still requires File 09 contract `1.1.0`, exact `file09/file03` provenance, boolean verified truth, lowercase SHA-256 and valid `YYYY-MM-DD`; File 09 satisfies that shape. |
| R16 | CLEAN | Files 19/20/21/23/24/26 and CF-04 | Notification transport, sole shell, publishing, assurance, search/ranking and conditional media boundaries remain external and versioned. |
| R17 | CLEAN | Schema/migration/operations | Core schema 6, Advanced Trust schema 2, migration/rollback, schedules, Safe Mode, health and repair boundaries remain fail-closed. |
| R18 | CLEAN | Security/privacy/adversarial | No new secret, public evidence path, direct companion write, auto-verification, cure guarantee, clinical authorization or donor/ranking advantage was found. |
| R19 | DEFECT | Complete executable-suite integrity | `tests/forty-round-assurance.py` was still pinned to the obsolete `1.2.0-RC2` ZIP name and was omitted from authoritative CI; executing every Python test exposed the failure. |
| R20 | DEFECT | Current release/trace truth | README/current assurance text stopped at R22/R20 and did not represent R23/R24, the now-existing File 14 consumer, current File 25 head, or this R25 review. Permanent current evidence was missing. |

## One corrective batch after all rounds

1. **R19 — executable-suite integrity**
   - Updated the preserved 40-round gate to the current deterministic `1.3.0-RC6` package identity.
   - Added the maintained 40-round gate to authoritative PHP 7.4/8.3 CI.
   - Added this R25 executable gate to authoritative exact-head CI.

2. **R20 — current evidence and traceability**
   - Added this permanent R25 ledger, exact companion-head map and release-lock evidence.
   - Updated README, STATUS, TRACEABILITY, RELEASE-MANIFEST and CHANGELOG to current R25 truth.
   - Recorded File 14 as a real current consumer and File 25's advanced current head without turning repository/CI evidence into staging/live evidence.
   - Kept the installable release allowlist at 62 entries; R25 QA evidence remains repository-only.

No product-runtime source change was required: the current File 14 and File 25 contracts are compatible with the existing File 09 provider implementation.

## Mandatory post-fix fresh reviews

### Post-fix Review 1 — source/ownership/privacy — CLEAN

Re-read File 09 public projections, File 14 destination provider, File 25 date/fingerprint projection, File 03 privacy allowlist, File 07/08 boundaries, File 19 transport, File 20 shell, File 24 assurance and File 26 search projection. Corrections are confined to QA/release evidence and do not expand product ownership or expose private data.

Result: **CLEAN — 0 product-source defects.**

### Post-fix Review 2 — CI/package/status truth — CLEAN

Re-ran the maintained Python gates including the previously failing 40-round gate, verified deterministic double-build byte parity and 62-entry package verification, and rechecked that all deployed/DB/migration/live statuses remain explicitly unverified.

Result: **CLEAN — 0 repository defects in the affected scope.**

## R25 result

- Numbered review rounds: **20/20 complete**
- Defect-bearing rounds: **2 — R19, R20**
- Clean rounds: **18**
- Pending rounds: **0**
- Post-fix fresh reviews: **2 CLEAN**
- Product-runtime changes: **none required**
- Repository QA/evidence corrections: **complete on the R25 candidate branch**
- Installable allowlist: **62 entries, unchanged**
- Staging accepted: **false**
- Live deployed: **false**
- Operationally accepted: **false**

Authoritative exact-head CI/package must pass on the final corrected branch commit and again on the final merged `main` head before repository QA/package completion is current. Hostinger staging, deployed artifact parity, database/schema/migration state and live re-test remain separate external gates.
