# File 09 — Fourteenth Fresh 10-Round Corrective Review (R14)

## Evidence boundary

- Frozen repository baseline: `52b41f3395e1599540da0656d6e9a022c39a27fe` — final green R13 exact-head candidate.
- R14 is a new sequential review. Each defect was corrected before the next numbered review started.
- Governing specification: latest consolidated central plan + File 09 v1.2 RC6 reviewed final + approved F09-AT-01..24.
- Runtime remains `1.3.0 RC6`; core schema `6`; Advanced Trust schema `2`; Advanced Trust contract `1.1.0`.
- Repository evidence does not prove Hostinger staging/live state.

## Result

- Total rounds: **10**
- Defect-bearing rounds: **6** — **01, 02, 05, 07, 08, 10**
- Clean rounds: **4** — **03, 04, 06, 09**
- Required post-correction executable result: **10 PASS / 0 FAIL**

## Sequential review → fix record

### R01 — Credential quota read could inherit stale database error state
`GDO_Evidence::quota_allows()` checked `$wpdb->last_error` after its aggregate usage query but did not clear pre-existing DB error state before that query. A stale error could therefore convert a successful quota read into a false fail-closed denial. The query now explicitly resets DB error state before the read.

### R02 — Professional registration aliases did not receive the same current-validity trust rules as license evidence
Continuous verification already recognized `license`, `registration` and `professional_registration`, but final evidence acceptance, verification-validity ceiling and the all-accepted gate applied explicit registry/future-validity requirements only to literal `license`. Both correction passes were completed before R03: reviewer acceptance and validity ceiling now cover all three professional-current credential types, and the subsequent R02 re-check closed the same gap in `all_accepted()`.

### R03 — Clean: more-information/resubmission concurrency and replacement evidence
Reviewer/applicant ownership, application row-version/locking, evidence replacement versioning/supersession, immutable resubmission hash and notification transaction boundaries were rechecked. No new repository-level defect was established.

### R04 — Clean: renewal/expiry request-time truth and reconciliation
Public/current verification expiry is rechecked at request time rather than relying solely on cron. Reconciliation/retention expiry transitions remain claim/event coupled and renewal continuity does not shadow an actually expired decision. No new repository-level defect was established.

### R05 — Appeal deadlines existed but were operationally invisible
Appeal `deadline_at` was persisted but was neither surfaced to the review operator nor represented in health/overdue observability. R14 adds an overdue-open-appeal count and health warning plus reviewer/admin deadline display, including an explicit overdue indication. Deadline expiry does not auto-deny or prejudice the applicant.

### R06 — Clean: privacy/legal-hold/durable deletion lifecycle
Legal hold, public-verification revocation before erasure, durable DB-first deletion-pending state, interrupted deletion retry, Advanced Trust cleanup and anonymization transaction boundaries were rechecked. No new repository-level defect was established.

### R07 — Continuous-monitor result could hide an earlier adverse/degraded credential result
When one application had multiple professional credentials, each provider result overwrote `last_result`. A later clean credential could therefore mask an earlier revoked/expired/not-matched or provider-degraded result in monitor observability. R14 now aggregates severity: adverse wins; degraded/provider failure is preserved over later clean; clean updates the aggregate only while neither stronger condition has been observed.

### R08 — Public License and Registration scope truths were incorrectly coupled
The public verification matrix marked both License and Registration verified whenever any one of `license`, `registration` or `professional_registration` evidence was accepted. F09-AT-10 requires separate scope semantics. R14 now maps `license` only to License scope and `registration`/`professional_registration` only to Registration scope, including matching pending semantics.

### R09 — Clean: security/privacy/object scope and release architecture
Private-evidence/public-projection separation, reviewer object/case authorization, viewing-room one-time/session controls, resumable upload integrity, provider/AI advisory-only human-decision boundary, File 19 transport ownership and the existing release allowlist architecture were rechecked. No new repository-level defect was established.

### R10 — R14 corrections lacked permanent exact-head release evidence
After R01–R09 the repository did not yet contain a permanent R14 ledger, executable R14 gate, R14 release-lock fields or authoritative workflow invocation. R14 adds this ledger, `tests/ten-round-audit-r14.py`, fourteenth-review lock metadata and authoritative CI wiring. Temporary apply plumbing must be removed before the final exact-head gate.

## Final repository gate

R14 is repository-QA complete only when the final exact commit passes PHP 7.4 and PHP 8.3 complete suites, all historical R1–R13 gates, R14 **10 PASS / 0 FAIL**, deterministic double build, exact release allowlist/package verification, generated SPDX SBOM verification and uploaded artifact whose `EXACT-HEAD.txt` equals that final commit.

Even after that: **Staging-Accepted=false · Live-Deployed=false · Operational=false** until the exact artifact is externally verified under the project Live-First rule.
