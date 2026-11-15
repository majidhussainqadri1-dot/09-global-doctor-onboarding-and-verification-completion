# File 09 V12.3 F09-AT-24 — Complementary small-cell privacy: red/green source review

Date: 2026-11-14. **Scope:** source code and deterministic PHP fixtures only; not evidence of production behavior or installed Staging acceptance.

## Exact baseline and root cause
- Repository main baseline: `8f52f87a6d82fc89c6c78b7da80b474dff916d97`.
- Source method: `GDO_Advanced_Trust::public_transparency_snapshot()` in `includes/class-gdo-advanced-trust.php`.
- Governing specification: V12.3, F09-AT-24, Professional Trust Transparency Dashboard, small-count re-identification prohibition, plus F09-NFR-002 privacy.
- **Root cause:** public aggregation suppressed only each nested cell when the cell itself was below the minimum (default 5). A published parent count combined with a large nested count reveals a **small excluded complement** by arithmetic subtraction; suppressing only small direct values is insufficient.
- Reproduction fixture A: `decisions=21, verified=20` exposes a nonverified complement of 1.
- Reproduction fixture B: `appeals_resolved=20, appeals_overturned=19` exposes a nonoverturned complement of 1.
- This is **one unique privacy root-cause family**, covering two parent/subset pairs. Neither example is actual user information; all numbers are synthetic.

## RED-first → GREEN evidence
| Step | Exact source SHA | GitHub Actions run | Result |
|---|---|---|---|
| New real PHP-class regression fixture in canonical workflow | `037d506e772640e482888f136f34952dae706d5c` | `43612179057` | **RED** on both PHP 7.4 and PHP 8.3 comprehensive suites; owner/consumer coupled jobs PASS; package skipped |
| Minimal only-in-File09 code correction | `1d1d4882156fcd9ac8809eb007816b05780d4bdc` | `43612274342` | **GREEN: 5/5** jobs, including PHP 7.4 and PHP 8.3 comprehensive suites, exact owner/consumer integration and deterministic package |

### Genuine regression cases
`tests/v123-transparency-inference.php` loads `includes/class-gdo-advanced-trust.php` directly with a deterministic fake WordPress DB result contract and checks:
1. `decisions=21 / verified=20`: the large `verified` cell is also hidden because the published parent would reveal the complementary cell of 1.
2. `appeals_resolved=20 / appeals_overturned=19`: the same protection for the nested appeal statistic.
3. Large non-sensitive nested and complementary cells remain visible.
4. Original direct small-cell masking remains intact.
5. Primary minimum-cohort suppression remains intact.
6. Configurable `minimum_cell=20` masks a complement of 15 even when the nested value 65 is not directly small.
7. A complementary count exactly at `minimum_cell=20` does not trigger over-suppression; normal aggregate disclosure remains available.

## Minimal correction and boundaries
After the original direct small-cell suppression, evaluate only approved parent→subset pairs and suppress the nested public count if `0 < parent - subset < minimum_cell`; mark it in `suppressed_fields`. Do not change the canonical private source aggregate, storage schema, privilege/role, retention, donor policy, or dashboard design.
No new external dataset, users, live exposure, code installation or database migration has been inspected. This test establishes a source-level privacy weakness and a green candidate fix only.

## Independent and external gate remains open
- Independent security review must assess related disclosures across public history/passport/QR, rolling-day inference and cache behavior, and test actual user roles.
- Real browser/API and DB-staging tests, 32 staged acceptance gates, two fresh independent reviews, backup/restore and explicit release approval are outstanding.
- `Deployed Version=UNVERIFIED`, `DB Version=UNVERIFIED`, `Migration State=UNVERIFIED`, `Live Verification Status=NOT PERFORMED`.
- **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## Defect accounting
| Description | Count |
|---|---:|
| Source-level confirmed root cause in this PR | 1 |
| Tested complementary privacy examples | 2 |
| Source-level candidate fixes | 1 |
| Final exact-head CI previously verified before adding this documentation | 5/5 |
| Main merge | 0 |
| Staging/Live verified resolutions | 0 |

Status: **SOURCE FIX PROVED; RELEASE HOLD**. Source code has not been merged to main or deployed.

## Latest strengthened-regression source result
- Extended `tests/v123-transparency-inference.php` with boundary tests at configured `minimum_cell=20` (7 total PHP fixture assertions), exact SHA `48fd9526b46c1ee37a3f69fc26ef83836b4b41d6`.
- GitHub run `43614221036`: **5/5 jobs PASS** across PHP 7.4, PHP 8.3, actual File00 owner+File09 consumer source fixtures and deterministic release package.
- This is supplemental source proof only; the earlier RED run `43612179057` and GREEN correction commit `1d1d4882156fcd9ac8809eb007816b05780d4bdc` remain immutable historical evidence. Staging and Live stay unverified.
