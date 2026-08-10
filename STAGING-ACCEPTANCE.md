# File 09 — RC6 Staging Acceptance Contract

**Status:** NOT EXECUTED / NOT ACCEPTED  
**Runtime:** 1.3.0 RC6 candidate  
**Core schema:** 6  
**Advanced Trust schema:** 2  
**Advanced Trust contract:** 1.1.0  
**Repository review:** 80 rounds; 49 defect-bearing corrected; 31 clean

Repository and CI evidence cannot set this file to accepted. The exact final RC6 artifact must be installed and exercised on Hostinger-equivalent WordPress/PHP/MySQL staging.

## Mandatory gates

1. **Exact artifact / restore point** — record RC6 ZIP SHA-256, exact Git commit, 53-entry manifest/SBOM, pre-install database/files backup, isolated restore proof and rollback window.
2. **Fresh install / activation** — File 00, File 02, keyring, private storage, claim-signing key and scanner fail closed when absent; successful activation records core schema 6 and Advanced Trust schema 2 only after migrations/index checks/storage health pass.
3. **RC5/schema-1 → RC6/schema-2 upgrade** — idempotently reconcile the eight Advanced Trust tables plus `application_status` passport and `application_state` upload indexes; interrupted/concurrent upgrade, downgrade warning and rollback preserve all existing professional records.
4. **AJ-03 doctor journey** — save/resume → evidence upload → submit → reviewer assignment → more-info → replacement evidence → independent decision → acknowledged File 00 claim → passport issue → renewal → suspension/reinstatement/revocation/expiry → independent appeal.
5. **Primary-source / issuer governance** — issuer starts `proposed`; the creator cannot independently initial-verify it; domain normalization/duplicate issuer rules work; matched/not-matched/revoked/expired/pending/provider-error/unavailable/malformed/timeout paths are recorded without auto-decision.
6. **Jurisdiction-rule governance** — draft-first creation, second-reviewer approval of unchanged rules/dates, immutable approved version, retirement and new-version replacement; invalid/effective-date conflicts fail closed.
7. **Provider privacy/robustness** — provider secrets are not persisted; requests are minimized; oversized/secret-like provider outputs are stripped/bounded; provider expiry timestamps normalize safely; request rate limits and provider outage behavior are observable.
8. **Continuous/event-driven reverification** — `scheduled` and `degraded` monitor rows both execute; bounded backoff and one-time retry wakeups work; adverse/expiry paths schedule targeted reverification and notification without silently changing professional state.
9. **Passport/QR lifecycle** — serialized issue/supersession, current File 09 + File 00 recheck, verification expiry, suspension/revocation/expiry invalidation, duplicate/concurrent issuance, invalid UUID/token, public-safe payload, read-only GET, `no-store/no-cache` and no private-field leakage.
10. **Verification Scope Badge / history** — current predecessor verification remains visible during renewal; scope distinguishes verified/pending/not-verified/not-applicable; only allowlisted professional-history events are public-safe.
11. **Reviewer conflict / dual review / routing / calibration** — self-review, declared conflict, conflict resolution, stale scope, dual-review same-person prevention, cross-border/high-risk/appeal cases, max-open workload and completed-sample calibration.
12. **Fraud/explainable risk** — credential-reuse signals deduplicate, false positives can be resolved, factors/reasons are visible only to authorized review, and risk never auto-rejects.
13. **Resumable evidence upload** — exclusive session creation; total/chunk geometry; exact order/size; duplicate/replay/out-of-order chunks; DB/file race; symlink/temp-file mismatch; wrong hash; interrupted/finalizing recovery; two concurrent finalizers; canonical malware/MIME/PDF/polyglot/quota/encryption handoff; no `.chunk-*` orphan.
14. **Secure Evidence Viewing Room** — wrong reviewer/application/evidence/session, stale step-up, expired/used token, direct-storage attempt and repeated use fail closed; runtime uses the mature canonical one-time evidence grant; watermark/no-download intent is present without impossible copy-proof claims.
15. **Authorization / IDOR** — applicant, reviewer, senior reviewer, Founder, support and forged/stale/suspended identities across every persistent state; object/field/state/version/purpose and trust-check object scope revalidated server-side.
16. **Current File 00/File 02** — current membership/identity/contact/2FA/sanction/jurisdiction state and professional reauthentication; deny/unknown/provider-unavailable cases remain fail closed.
17. **File 19 `sun.event.v1`** — register `file09-doctor-verification`; ingest minimized versioned events including reverification-required notification; duplicate retry idempotent; evidence/reviewer notes never appear in notification payload; provider outage retains safe outbox/dead-letter/replay behavior.
18. **Files 03/07/08/21/23 projections** — current verify/expiry/suspension/revocation/claim changes propagate within approved SLA, never expose File 09 evidence and never grant clinical authority.
19. **File 26 / CEN-SEARCH-001** — private applications/evidence absent from search corpus; public doctor owners consume current verification truth; loss of verification restricts/removes eligible projection; deletion/rights/freshness preserved; no paid/donor rank influence.
20. **File 20 shell** — application/command-center surfaces mount through sole shell/declarations; deep links same-origin/current-authorized; no duplicate header/nav/shell; all private surfaces noindex/no-cache.
21. **Privacy export / AJ-35** — Advanced Trust credential checks, professional history, passport metadata and conflicts appear in the authorized personal-data export without secrets/private evidence blobs.
22. **Privacy erasure / legal hold** — public verification first becomes non-current; physical evidence deletion proof; Advanced Trust temporary/monitor/passport cleanup; retained checks/history/conflicts anonymized; no `(user_id,version)` collision; legal-held applications are preserved and reported.
23. **Retention** — one expired application is anonymized without erasing a reviewer identity from unrelated applications; derivative passports and temp/monitor rows removed; trust accountability rows minimized; `.chunk-*` orphan policy executes.
24. **Reliability / AJ-36** — File 19/scanner/key/storage/provider failure, stale/degraded monitor, queue outage, timeout, duplicate tap, session expiry, race and reconciliation produce no false success, stale public status or duplicate professional decision.
25. **Backup/restore / AJ-37** — restore database + private objects + keys/config in isolation; decrypt representative evidence; restore core schema 6 + Advanced Trust schema 2, passport/revocation, monitor state, history/privacy status; verify documented RPO/RTO objective.
26. **Experience / AJ-31–33** — 320–1920px, desktop/tablet/mobile, keyboard-only, screen reader, 200–400% zoom, focus, reduced motion, RTL/LTR/mixed fields, Urdu/Arabic/English, slow 3G and reconnect/resume.
27. **Security / AJ-34** — CSRF/XSS/SQLi/SSRF/path/race/replay/brute-force/privilege/cache-leak tests, step-up loss and current authorization rechecks; no secret/PII/internal stack leakage.
28. **Donation neutrality / AJ-24–25** — donor/non-donor application, review, verification/passport/badge, directory/search projection and support behavior identical; File 09 contains no fee/paywall/rank advantage.
29. **Medical/ethical gate** — verification is professional-eligibility only; no cure guarantee, clinical authorization or autonomous diagnosis/prescription semantics.
30. **Observability** — audit hash chain, trace IDs, access logs, credential checks, monitor degradation, conflict/passport/upload failures, quality samples, queue/dead-letter, health/Safe Mode and privacy-safe diagnostics are reviewable; no raw evidence in ordinary logs.
31. **RC6 80-round regression** — the exact deployed staging source/package matches the exact-head RC6 candidate that passed `advanced-trust-24.py`, `eighty-round-audit.py` and all historical adversarial suites; no locally edited staging code.
32. **Release evidence / AJ-38–40** — screenshot/evidence corpus recorded; any critical defect blocks rollout unless a dated Founder risk acceptance exists; two fresh staging review→fix→full-retest rounds end with zero known unresolved release blockers.

## Founder acceptance

Founder acceptance: **PENDING**  
Production authorization: **NO**  
Live-deployed: **NO**  
Operationally accepted: **NO**

A green repository workflow is not staging acceptance. A staging acceptance is not live deployment. Live deployment is not operational acceptance until monitoring, backup/restore and incident/operational evidence are separately verified.
