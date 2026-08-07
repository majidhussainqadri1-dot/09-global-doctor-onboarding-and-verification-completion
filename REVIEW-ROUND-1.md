# Fresh Review/Fix Round 1 — Requirements and Architecture

Date: 2026-08-06

Findings corrected:

1. Registered lifecycle action lacked a complete transactional handler.
2. Assignment, more-information, decisions, claims and notifications were not uniformly owner-state-plus-outbox atomic.
3. Nested upload transactions could break applicant draft persistence.
4. File 00 claim delivery could be treated as success without explicit acknowledgment.
5. Evidence access grants needed session, purpose, one-time use and bounded accumulation.
6. Renewal state could prematurely remove a still-valid public projection.
7. Privacy erasure needed prior downstream revocation and physical deletion proof.
8. Migration needed a stale-safe lock, GDO1 quarantine/re-encryption and no direct File 00 role writes.
9. New schema-owned tables were missing from guarded uninstall.
10. Activation did not enforce File 02 and claim-signing dependencies.

All corrections were added to source and the full regression suite is required on the exact final head.
