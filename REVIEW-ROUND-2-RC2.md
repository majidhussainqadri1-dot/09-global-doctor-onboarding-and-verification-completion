# RC2 Review Round 2 — Fresh Adversarial Review

A fresh review treats every client value, role label, workflow status, artifact and historical CI run as untrusted. It verifies server-side authorization, row-level ownership, fail-closed dependency behavior, immutable submitted snapshots, transaction rollback, replay/idempotency, private-data minimization, appeal independence, exact-head evidence and deterministic package parity.

Release gate: `tests/rc2-adversarial.py`, the full PHP/Python suites, PHP 7.4/8.3 lint, JavaScript syntax, two-build byte comparison and source/package parity must all pass at the exact PR head. Any later commit reopens this gate.

Known external dependency: Hostinger staging and Founder acceptance remain intentionally open and cannot be replaced by repository evidence.
