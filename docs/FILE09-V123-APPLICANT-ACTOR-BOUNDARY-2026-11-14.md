# File 09 V12.3 — authenticated applicant-only eligibility: source remediation and evidence

Date: 2026-11-14. **Scope: repository, not deployed website.** Main base SHA: `8f52f87a6d82fc89c6c78b7da80b474dff916d97`.

## Governing owner boundary
- File00 `SMC_File09_Intake_Contract::assertion($subject_id,...)` supplies a **read-only subject prerequisite**. It may be queried on behalf of a doctor by an independently authorized File09 finalizer, and by File09 public verified-status revalidation. The owner contract grants no clinical access, badge or prescription and must NOT check that the *current caller* is the subject.
- File09 `GDO_Policy::eligibility($applicant_id,...)` is an **applicant-only private workflow** and must require an exact authenticated current actor. It must fail closed after actor changes during downstream policy hooks/DB reads and never disclose private application ID or subject UUID to a new actor.
- File09 `GDO_Membership_Adapter::is_active_doctor_candidate($subject_id)` stays a read-only on-behalf predicate for authorized reviewer and public trust projections. Its output is not authorization to mutate another user's application.

## RED-first and corrective source timeline
1. Initial File00 Draft PR #59 self-only owner guard passed targeted CI, but it was shown to regress legitimate File09 reviewer/finalizer and public verified status. **It was reverted** before merge; restored File00 source SHA `8b49210c0ae8e8b2dff86be71044b401d0bd0fda` has **9/9** green File00 source workflows. Earlier RED failures against the wrong File00 owner expectation are NOT an owner defect.
2. Applicant actor binding in `GDO_Policy::eligibility` is corrected on File09 Draft PR #17. The suite `tests/file00-preverification-intake.php` now explicitly rejects anonymous actor=0 and another logged-in actor=8 when the requested applicant is user=7. Legitimate user=7 continues to pass.
3. A distinct private disclosure condition was proven RED at File09 SHA `9bcef9b63a7b858c341d99636636c129e87fe857` (GitHub run `43552898437`, both PHP versions): `FAIL: actor switch during application lookup cannot disclose private application identifier`. Corrected by checking the authenticated actor immediately after latest application lookup, before returning any `application_id` (source fix `8812f8351ad188de443dcba9942b06cb9ec8236b`).
4. A second recheck after `gdo_eligibility_result` filter prevents stale policy-result `subject_uuid` exposure upon actor change. New positive/negative test added in `tests/file00-preverification-intake.php`; it also preserves validated on-behalf provider/consumer behavior in `tests/file00-owner-consumer-integration.php`.
5. File09 exact source SHA `fef20f0dc215c0e8cf7fc64b3009c5fd9ea4f672` -> GitHub Action `43553087692`: **5/5 jobs success**, including PHP 7.4/8.3 comprehensive QA, real File00 owner-class/real File09 consumer coupled fixtures and deterministic File09 package. This is **not** a full installed WordPress/DB or Hostinger staging/live test.
6. The exact File09 cross-owner workflow now pins the existing File00 **main** SHA `1fad051c86d530d558be3eb1890d3c6e67bce6e8`. GitHub blob verification proves the File00 provider PHP class and `MANIFEST.sha256` are **byte-identical** to restored File00 PR #59 SHA `8b49210c0ae8e8b2dff86be71044b401d0bd0fda`. File09 no longer requires the unmerged File00 PR. A fresh exact-head run must verify this new pin before release.

## Error/status accounting
| Measure | Evidence and count |
| --- | --- |
| Confirmed File09 applicant-private actor-binding defect | **1**, source fixed in Draft PR #17 |
| Independently reproduced post-lookup private-ID leak condition | **1**, fixed in same File09 policy |
| Intra-filter subject-UUID privacy regression added | **1 negative test**, passing after source guard |
| Accepted File00 owner defects from earlier initial tests | **0**, wrong expectation retracted and owner source restored |
| File09 green run | **5/5 jobs** on exact source commit above |
| File00 frozen-source CI | **9/9 jobs** on exact restored owner commit |
| Changes merged to File09 or File00 main | **0** |
| Live/staging issue resolved | **0 proven** |

## Still-open gates
- Independent human/security review of all File09 applicant mutations and reviewer permissions.
- Full dual-plugin installed WP/MySQL, File02 current session and approval/step-up tests, real File09 professional lifecycle including finalizer/reinstatement/public badge while logged in as **different** user.
- V12.3 123-ID File00 acceptance, other PR #52/#54/#55/#56 reconciliation and new approved unique release identity.
- Backups/restoration/migrations and staging → deploy → live re-test with exact package/DB parity.

| Mandatory truth | Status |
| --- | --- |
| File00 repository main | `1fad051c86d530d558be3eb1890d3c6e67bce6e8` |
| File09 repository main | `8f52f87a6d82fc89c6c78b7da80b474dff916d97` |
| Deployed Version / DB Version / Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
