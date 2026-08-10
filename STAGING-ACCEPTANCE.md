# File 09 — RC4 Staging Acceptance Contract

**Status:** NOT EXECUTED / NOT ACCEPTED  
**Runtime:** 1.2.0 RC4 candidate  
**Schema:** 6

Repository and CI evidence cannot set this file to accepted. The exact RC4 artifact must be installed and exercised on Hostinger-equivalent WordPress/PHP/MySQL staging.

## Mandatory gates

1. **Exact artifact / restore point** — record RC4 ZIP SHA-256, exact Git commit, pre-install database/files backup, isolated restore proof and rollback window.
2. **Fresh install / activation** — File 00, File 02, keyring, private storage, claim-signing key and scanner fail closed when absent; successful activation records schema/version only after migrations and storage checks pass.
3. **Upgrade / legacy migration** — supported legacy/intermediate data, interrupted batch, concurrent upgrade, quarantine, reconciliation and rollback/compensation show no lost approvals, orphan evidence or split state.
4. **AJ-03 doctor journey** — save/resume → evidence upload → submit → reviewer assignment → more-info → replacement evidence → independent decision → acknowledged File 00 claim → renewal → suspension/reinstatement/revocation → independent appeal.
5. **Evidence security** — direct-web denial, symlink/path traversal, MIME/signature/polyglot/active-PDF/malware rejection, image re-encoding, metadata removal, quota/race handling, expiring reviewer grants and current step-up at use time.
6. **Authorization / IDOR** — applicant, reviewer, senior reviewer, Founder, support and forged/stale/suspended identities across every persistent state; object/field/state/version/purpose checks revalidated server-side.
7. **Current File 00/File 02** — current membership/identity/contact/2FA/sanction/jurisdiction state and professional reauthentication; deny/unknown/provider-unavailable cases remain fail closed.
8. **File 19 `sun.event.v1`** — register `file09-doctor-verification`; ingest minimized versioned events; duplicate retry is idempotent; evidence/reviewer notes never appear in notification payload; provider outage retains safe outbox/dead-letter/replay behavior.
9. **Files 03/07/08/21/23 projections** — current verify/expiry/suspension/revocation/claim changes propagate within the approved SLA, never expose File 09 evidence and never grant clinical authority.
10. **File 26 / CEN-SEARCH-001** — File 09 private applications/evidence are absent from search corpus; public doctor owners consume current File 09 verification truth; loss of verification restricts/removes eligible doctor projection; deletion/rights/verification freshness is preserved; no paid/donor ranking influence.
11. **File 20 shell** — `gdo_page_map/apply` mounts through the sole shell, deep links are same-origin/current-authorized, no duplicate header/nav/shell is emitted and all private routes are noindex/no-cache.
12. **Privacy lifecycle / AJ-35** — own export/correction/withdrawal/erasure, legal hold, dependent deletion, physical evidence-deletion proof and retained immutable accountability are verified without data leakage.
13. **Reliability / AJ-36** — File 19/scanner/key/storage/provider failure, queue outage, stale lease, timeout, duplicate tap, session expiry and reconciliation produce no false success or duplicate decision.
14. **Backup/restore / AJ-37** — restore database + private objects + keys/config in isolation; decrypt representative evidence; rebuild derived state; verify rights/deletion/claim state and documented RPO/RTO objective.
15. **Experience / AJ-31–33** — 320–1920px, desktop/tablet/mobile, keyboard-only, screen reader, 200–400% zoom, focus, reduced motion, RTL/LTR/mixed fields, Urdu/Arabic/English, slow 3G and reconnect/resume.
16. **Security / AJ-34** — CSRF/XSS/SQLi/SSRF/path/race/replay/brute-force/privilege/cache-leak tests, step-up loss and current authorization rechecks; no secret/PII/internal stack leakage.
17. **Donation neutrality / AJ-24–25** — donor/non-donor doctor application, review, verification, badge/eligibility, directory/search projection and support behavior are identical; File 09 contains no fee/paywall/rank advantage.
18. **Medical/ethical gate** — verification copy is professional-eligibility only; no cure guarantee, clinical authorization or autonomous diagnosis/prescription semantics.
19. **Observability** — audit hash chain, trace IDs, access logs, quality samples, queue/dead-letter, health/Safe Mode and privacy-safe diagnostics are reviewable; no raw evidence in ordinary logs.
20. **Release evidence / AJ-38–40** — screenshot/evidence corpus recorded; any critical defect blocks rollout unless a dated Founder risk acceptance exists; two fresh staging review→fix→retest rounds end with zero known unresolved release blockers.

## Founder acceptance

Founder acceptance: **PENDING**  
Production authorization: **NO**  
Live-deployed: **NO**  
Operationally accepted: **NO**
