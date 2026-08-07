# File 09 — RC2 Fresh Adversarial Review Round 3

Date: 2026-08-07

A fresh post-green review found and corrected defects that earlier static suites did not cover:

1. Renewal/expired draft creation could bypass a newly failed File 00 eligibility decision.
2. Privacy erasure used mutating OFFSET pagination and could skip application versions.
3. Privacy anonymization used `user_id=0`, conflicting with the unique `(user_id, version)` key across different erased users.
4. Public-verification erasure could commit revocation locally before the File 00 professional claim was safely queued.
5. Privacy erasure considered only active evidence and could leave superseded credential files.
6. Retention expiry removed credential bytes but did not anonymize the retained subject/profile linkage.
7. Schema backfill stopped after 5,000 rows while still promoting the schema version.
8. Legacy migration lacked bounded row/user checkpoints and could delete the legacy source before the new evidence transaction committed.
9. Professional claim issuance did not independently require the caller-supplied claim state to equal the current locked application state.

Corrections are fail-closed, migration-safe, privacy-minimized and covered by `tests/final-hardening.py`. The canonical PHP 7.4/8.3 exact-head workflow must pass again after this correction. Hostinger staging remains a separate acceptance gate.
