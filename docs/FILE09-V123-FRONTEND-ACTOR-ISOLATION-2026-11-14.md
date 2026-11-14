# File09 V12.3 — Frontend applicant-status actor-switch source regression

Date: 2026-11-14. Source/CI only; **no installed, staging, live or DB claim**.

## Frozen parent and confirmed RED

- Parent: File09 Draft PR #17 branch `audit/file09-file00-actor-binding-20261114`. Main remains `8f52f87a6d82fc89c6c78b7da80b474dff916d97`.
- Distinct source defect: `GDO_Frontend::form()` captures logged-in applicant, reads `GDO_Application::latest_for_user($user)`, and then can render `status_panel($latest)` even after WordPress current user changes during the DB lookup or eligibility filter. This can expose private state, claim status, validity date and appeal/withdrawal metadata to a different current actor.
- Real GDO_Frontend class fixture: `tests/v123-frontend-actor-switch.php`.
- **RED exact SHA** `52172a054e8df7d23ee2d82dcbe31ecc596fc418`; GitHub Actions run `43612970997`, PHP 7.4 and 8.3 complete-suite jobs failed with `FAIL: actor switch during latest application query does not disclose applicant status`. Paired File00/File09 jobs succeeded; package skipped due to expected RED.
- This is **one unique Frontend source privacy defect family**, separate from PR #17's eligibility actor checks. Synthetic private values are not real user data.

## Narrow correction

- Source patch `e0de98448f34484f7c61b98e2835eaf851ea45b3` adds exact authenticated applicant checks after latest lookup, after eligibility, after ensure_draft, after evidence retrieval and policy field filtering, and before the final rendered form is returned.
- If the active principal changes, return a generic public-safe session-change notice, not a private status/applicant identifier.
- Do not restrict File00's read-only on-behalf reviewer/public prerequisite contract.
- No DB/schema, professional approval, role authority, or clinical access changes.

## Still-unverified gates / next step

- Exact-head **GREEN run not yet observed at document time**. Do not claim source correction accepted until PHP 7.4/8.3, paired owner/consumer, deterministic package and full source QA are green at the final exact head.
- Further review of early `status_panel()` construction and template-hook actor changes; actual installed WordPress File00/File02/File09 role/session testing; 32 staging gates and 2 independent reviews.
- Do not merge/deploy merely from static tests or synthetic fixture; unique version/package approval needed.
- Deployed Version / DB Version / Migration State: **UNVERIFIED**. Live Verification: **NOT PERFORMED**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
