# File 09 — R23 Fresh 20-Round Plan + Cross-File Corrective Review

Date: 2026-10-05  
Frozen repository baseline: `d35eb982becdf0224a5b850a0c6fb4ace8bf075b`  
Scope: File 09 governing plan, Definitive Central Master Plan v3.0, Advanced Trust 24 amendment, and current repository-level companion contracts.  
Rule: repository truth only. Staging, deployed code, database state and live behavior remain separately unverified.

## Evidence hierarchy used

1. Founder-approved File 09 plan and Advanced Trust 24 amendment.
2. Definitive Integrated Master Plan v3.0.
3. Exact File 09 baseline above.
4. Current companion repository heads inspected for contract parity:
   - File 00: `2fa7c022ee9cd1b65432e900579512f304532442`
   - File 02: `224c39bcb8c28f77504c7348dbad41226753c7e8`
   - File 03: `636e3ef965423887f810718abec3cd1c11c3659d`
   - File 07: `67c32ec4af45a7de6e3d9c1dbf0f8614d6b5a844`
   - File 08: `70541974ce0ffb16aebef557c3016eb7447662f4`
   - File 19: `04078025b643ab7696e4cb4e37826bf152defa18`
   - File 20: `8a4dbcaf4fef8e926b9b834ecfde16c21a0f00ca`
   - File 21: `f2eb7e95ddea327af36ea725ffb923b029f885e6`
   - File 23: `a8a8c805f4730998ccb44bd95c87591836561759`
   - File 24: `a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb`
   - File 25: `59927df876dc92c7461351420c7b7c95c65c6a93`
   - File 26: `bbea3aad466792a4a6a62b53532bbd45c7c592de`
5. CF-04 treated only as a conditional future capability; no silent activation or ownership transfer is permitted.

## Twenty numbered review rounds

| Round | Result | Review focus | Finding / corrective disposition |
|---|---|---|---|
| R01 | CLEAN | Canonical ownership / scope | File 09 remains the sole owner of professional application, evidence, review and verification decision truth. No companion direct-write ownership was introduced. |
| R02 | CLEAN | File 00 identity + File 02 step-up | Current identity, suspension, reviewer capability and recent privileged re-authentication remain checked at protected use time. |
| R03 | CLEAN | State machines / concurrency | Draft→submission→review→decision, renewal, suspension/revocation and appeal states remain explicit with row-version/locking protection. |
| R04 | CLEAN | Applicant wizard / draft / immutable submission | Multi-step application, autosave/save, completeness, consent, evidence, immutable submission, appeal and withdrawal paths remain implemented and fail closed. |
| R05 | CLEAN | Evidence security | MIME/signature/size/malware checks, encryption, private storage, one-time viewing grants, deletion-pending semantics and access audit remain present. |
| R06 | CLEAN | Reviewer least privilege / separation | Assignment, jurisdiction/language/workload controls, current authorization, conflict/dual-review hardening and independent appeal handling remain implemented. |
| R07 | CLEAN | Decision / File 00 claim / File 19 event | Final decision, claim-version acknowledgement and durable notification outbox boundaries remain owner-safe and versioned. |
| R08 | CLEAN | Renewal / expiry / suspension / revocation / appeal | Professional status lifecycle and derivative invalidation remain explicit; no login/payment/self-claim auto-verification path exists. |
| R09 | DEFECT | File 03 exact public profile projection | The post-R22 adapter projected the entire approved File 09 profile into `approved_fields`, allowing application-only fields such as phone/WhatsApp/declarations/clinic text to cross the provider boundary. It also failed exact File 03 aliases: File 03 consumes `licence_number` and `jurisdiction` while File 09 stores `license_number` and `license_jurisdiction`. Corrected with a strict public allowlist and explicit alias mapping. |
| R10 | DEFECT | File 03 verifiable credential wallet | Registration lookup used `licence_number` even though File 09 canonical storage is `license_number`, so the registration credential could disappear. The wallet also did not emit File 03's `verification_url` shape and could be interpreted as a VC without an explicit format. Corrected to canonical source lookup, `platform_record` format and an optional read-only current passport URL without issuing a passport during profile reads. |
| R11 | CLEAN | File 07 directory | File 09 supplies current verification eligibility only; File 07 remains directory/discovery/ranking owner. No directory write or ranking duplication exists in File 09. |
| R12 | CLEAN | File 08 clinic / appointments | File 09 remains a verification prerequisite only; File 08 retains clinic/appointment truth. No clinical authorization is inferred from verification. Current File 08 may apply a stricter lifecycle gate; File 09 owner truth is not falsified to accommodate a consumer policy. |
| R13 | CLEAN | File 19 notifications | File 09 emits minimized `sun.event.v1` facts; File 19 remains notification entity/delivery owner. No duplicate mail transport is created. |
| R14 | CLEAN | File 20 shell / File 25 visual ownership | File 09 supplies private-task page contracts only. File 20 remains sole application-shell owner and File 25 remains visual/brand owner. |
| R15 | CLEAN | Files 21/23 publishing | Verification eligibility is projected only; File 09 does not own post/news publication or publishing-dashboard workflow. |
| R16 | CLEAN | File 24 assurance / native controls | Native authentication, authorization, encryption, validation, privacy, Safe Mode and repair controls remain in File 09 while File 24 stays assurance/co-ordination owner. |
| R17 | CLEAN | File 26 search/discovery | Only C0 public verification projection metadata is exposed; private applications/evidence are not search documents. File 26 owns search/ranking and connector lifecycle. |
| R18 | CLEAN | CF-04 conditional media boundary | No File 09 runtime dependency silently activates CF-04. Professional evidence remains File 09-private until a separately approved CF-04 extraction/activation migration exists. |
| R19 | CLEAN | Data, privacy, migration, rollback, Advanced Trust | Core schema 6 + Advanced Trust schema 2, export/erasure/legal hold, migration/rollback and 24 Advanced Trust capabilities remain present with human-final decision authority. |
| R20 | DEFECT | Exact-head QA / release evidence | Four post-R22 File 03 commits were outside the authoritative workflow because `tests/file03-profile-contracts.php` was not executed, and the test itself was token-only. R22 status/lock/trace therefore did not cover the current baseline. Corrected by making the File 03 test behavioral/privacy-sensitive, adding it to authoritative PHP 7.4/8.3 CI, adding this R23 executable gate and updating permanent release evidence. |

## Corrective batch after completion of all 20 review rounds

The review was completed before the corrective batch. The three defect-bearing rounds were then corrected together:

1. **R09 — public-data minimization and exact File 03 aliases**
   - Added `file03_public_fields()`.
   - Removed application-only contact, declaration, clinic/address and unsupported fields from the provider payload.
   - Mapped File 09 `license_number → licence_number` and `license_jurisdiction → jurisdiction`.

2. **R10 — credential-wallet contract parity**
   - Registration now reads canonical File 09 `license_number`.
   - Added `format=platform_record` so the payload does not falsely claim W3C VC semantics.
   - Adds a tracking-free File 09 public passport verification URL only when an already-active passport exists; the read path never issues/mutates a passport.
   - Raw evidence remains excluded.

3. **R20 — exact-head QA and permanent evidence**
   - Replaced the token-only File 03 test with behavioral privacy/mapping checks.
   - Added `php tests/file03-profile-contracts.php` to the authoritative matrix.
   - Added `tests/twenty-round-audit-r23.py` and this permanent ledger.
   - Updated release-lock/status/traceability/release-manifest/changelog evidence.

## Result

- Total rounds: **20**
- Defect-bearing rounds: **3** — R09, R10, R20
- Clean rounds: **17**
- Pending review rounds: **0**
- Repository corrections: **applied on the R23 branch**
- Installable package allowlist: remains **62 entries**; R23 ledger/test are repository QA evidence and are not added to the production package.
- Staging accepted: **false**
- Live deployed: **false**
- Operationally accepted: **false**

Final repository acceptance still requires the authoritative GitHub Actions workflow to pass on the exact corrected head. External Hostinger staging, deployment parity, DB/schema/migration verification and live re-test remain separate mandatory gates.


## Mandatory post-fix fresh reviews

### Post-fix Review 1 — source/privacy/contract review — CLEAN

Performed after the R09/R10 product-source corrections. The exact corrected `includes/class-gdo-integration-contracts.php` was re-read independently against the File 09 owner boundary and current File 03 consumer contract.

Checks completed:
- no File 09 database/table write was introduced in the integration layer;
- no phone, WhatsApp, declarations, application-only clinic/address/service data is emitted by `file03_public_projection()`;
- canonical `license_number` and `license_jurisdiction` are translated only at the File 03 contract edge;
- File 03 public projection remains read-only, current and time-bounded;
- credential-wallet reads never issue/reissue/supersede a passport;
- raw evidence remains private;
- no File 07/08/19/20/21/23/24/25/26 ownership was absorbed.

Result: **CLEAN — 0 product-source defects found.**

### Post-fix Review 2 — adversarial/release review — CLEAN

A second fresh review was then performed from the corrected branch with emphasis on negative paths and release evidence.

Checks completed:
- missing/invalid File 03 projection remains fail-closed;
- private application fields seeded into the behavioral regression fixture are rejected from the provider payload;
- the registration wallet item uses the canonical File 09 license field and explicitly avoids W3C-VC overclaim;
- File 20/File 25 ownership remains presentation-only from File 09;
- File 26 remains C0 public-verification projection only;
- CF-04 remains conditional and unactivated;
- package allowlist remains 62 entries; no QA-only R23 file enters the installable package;
- staging/live/operational flags remain false;
- exact-head CI runs both the File 03 behavioral test and the R23 twenty-round gate.

The first exact-head R23 workflow exposed three **QA-harness expectation mismatches** in R11/R12/R14: the gate looked for literal manifest strings even though the code publishes those contracts through `identities()` and the File 20 page-contract hook. Product behavior was not defective. The harness was corrected to assert the actual published contract shape; no product-source code was changed by that correction.

Result: **CLEAN — 0 product-source defects found.**

These two post-fix reviews satisfy the governing two-fresh-review rule at repository level. Exact-head automated QA/package success is still required before merge, and staging/live verification remains external.
