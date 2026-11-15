# V12.3 F09-AT-24 — additional installed WordPress / MariaDB public-count regression
Date: 2026-11-15 (Asia/Karachi).
Parent source: PR #22 integrated candidate commit `6d9d919a3fcb14d308f2fab27a94cc549ffa55a7` (not `main`).

## Why this test
The prior `tests/v123-transparency-inference.php` is an isolated PHP-class fake-database fixture, even though the PR #22 exact-head workflow now includes a genuine File00 + File02 + File09 WordPress 7.0.1 / MariaDB 11.4 smoke job. That installed workflow does not yet assert the two public professional-trust complementary-cell scenarios against persisted aggregate rows.

## New test — NOT RUN
`tests/wpdb-transparency-smoke.php` builds synthetic CI-only persisted application and appeal rows in the genuine installed `wp_gdo_applications` and `wp_gdo_appeals` tables; never use it against any real-data environment. It is fail-closed unless WP_CLI and `WP_ENVIRONMENT_TYPE=staging` are present. It tests:
1. decisions 21, verified 20 — the excluded cohort of 1 must not be inferable;
2. resolved appeals 20, overturned appeals 19 — nonoverturned cohort of 1 must not be inferable;
3. decisions 25, verified 20 — safe complementary size 5 remains visible;
4. decisions 25, verified 4 — direct small-cell suppression remains;
5. decisions 19 — whole cohort suppression remains.
Rows are synthetic; a `finally` block clears inserted IDs.

## Execution blocker
CI workflow wiring has **not been confirmed or committed**. Thus source PHP fixture exists, but there is **no new RED/GREEN evidence and NO verified passing MariaDB test**. An attempted CI workflow update was blocked by a safety control and not performed; do not describe this scenario as accepted.

After independent code review, add the dedicated `wp eval-file tests/wpdb-transparency-smoke.php` invocation to an authorized ephemeral WordPress+MariaDB CI job, and check every case and cleanup on the exact head. This is a strengthened source integration test only; Hostinger staging still requires the 32 external gates, roles, cache/QR/time-differencing privacy checks and operational review.

**No production/staging deployment performed, no live DB read or changes.** Exact deployed version and migration are unverified; main remains source-only.
