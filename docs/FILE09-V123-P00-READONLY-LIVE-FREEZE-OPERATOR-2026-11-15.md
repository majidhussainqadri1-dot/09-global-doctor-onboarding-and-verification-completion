# File09 V12.3 P00 — read-only deployed reality freeze operator instructions

Date: 2026-11-15. **NOT EXECUTED on Hostinger/Live.** Source-only preparation.

## Purpose
The exact deployed plugin/package, WordPress/PHP/DB environment and migration state must be inventoried **before** diagnosing a live File09 defect. The source repository, WordPress staging and live site are three distinct realities.

## Script
`tools/file09-readonly-freeze.php` is a WordPress CLI read-only inventory collector. It reads:
- installed File09, File00, File02 **plugin header version and activation status**;
- WordPress/PHP/database server versions;
- **only** the two whitelisted numeric File09 migration markers `gdo_schema_version`, `gdo_advanced_trust_schema`;
- File09 plugin directory code/document checksum list and a deterministic sorted tree digest (explicitly NOT a release ZIP checksum);
- File09 database **table/column/index names only**.

It does **not** read database row values, patient/doctor records, credential images, secrets, passwords, `wp-config.php`, logs, raw options or private storage. It does not run migration, activate plugins or perform writes.

## Operator steps — require authorized shell
1. Verify you are operating on the intended host/environment using the hosting operator's own records; independently confirm approved read-only access.
2. Verify collector source against its exact Git commit. Run from a safely copied local script inside a trusted WP-CLI operator workspace; do not install as an active plugin.
3. From the authorized WordPress root, use `wp eval-file /absolute/operator-path/file09-readonly-freeze.php --path=/absolute/wordpress/root`.
4. Review output locally before sharing and redact any unanticipated hostname or path. The tool's table and column metadata are intended to be non-personal, but operator review still applies.
5. Record date/time, exact runtime build, code hashes, actual ZIP checksum separately if the real deployed artifact is available, database/migration snapshot and redacted error excerpts obtained through distinct authorized read-only operations.
6. Compare to the exact GitHub release candidate **only after** both evidence sets exist. If mismatched, stop normal debugging and conduct Deployment-Parity Audit.
7. Never say Live Verified or Resolved until real post-deploy tests and all required release checks pass.

## Limits
- WP-CLI can load active plugins and normal WordPress hooks; the script itself makes no explicit writes. **Do not claim a globally side-effect-free WordPress bootstrap.** For strict forensic collection use offline filesystem/database snapshots with operator access.
- Schema output alone does not establish actual migration success, data consistency, valid backup, postconditions, current capability grants or use-time action authorization.
- The tree digest covers allowlisted code/doc files under the installed File09 folder; it is **not** the ZIP SHA-256, may differ if hidden or unsupported files exist, and must be compared to the equivalent canonical manifest.
- Live/wp_options configured secrets deliberately excluded. A separate secure configuration-presence check can be performed in an authorized environment without revealing values.
- The collector is not a way around WordPress/Jetpack authorization or WPVibe HTTP 530. Site access remains blocked unless restored by the operator.

## Frozen current evidence
Repository main SHA `8f52f87a6d82fc89c6c78b7da80b474dff916d97`; integrated Draft #22 `6d9d919a3fcb14d308f2fab27a94cc549ffa55a7`, source-only CI 6/6. Hostinger Staging 0/32 accepted. Deployed Version, DB Version and Migration State UNVERIFIED; Live Verification NOT PERFORMED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
