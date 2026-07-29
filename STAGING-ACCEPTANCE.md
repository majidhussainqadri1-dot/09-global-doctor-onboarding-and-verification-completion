# File 09 Staging Acceptance Protocol — Required Before Merge

## Governing rule

File 09 `1.1.0-RC1` may be installed only on the approved WordPress staging environment. Passing source CI or producing a ZIP does not authorize merge, production deployment, or live credential collection.

## Exact candidate under test

- Package: `09-global-doctor-onboarding-and-verification-completion-1.1.0-RC1.zip`
- Package SHA-256: `46d8d6dffce49fb78932ab3e9f8d60ff1443a303d686ce456a37701cb1b6d90d`
- Canonical source-tree SHA-256: `f49230b0266954a21cb29f02bce917eafb4707dd463f734c64ddeaf0155f5495`
- Release: `1.1.0`
- Schema: `3`
- Corrected-source review commit: `ec82bd7c2ff72e612df0e2555337d3fb20dc71e5`
- Packaging workflow commit: `e465b4664ad84870bc9d13dd25bc7fa5c520fa75`
- Immutable baseline commit: `00967193c0825dd11e797de5b640a9eefcf421bb`

## Evidence header

Record before testing:

| Field | Required value/evidence |
|---|---|
| Staging URL | Exact non-live URL |
| Test date/time/time zone | ISO/local value |
| Tester | Named authorized tester |
| WordPress | Exact version |
| PHP | Exact version and extensions |
| Database | Engine and exact version |
| Web server | LiteSpeed/Apache/Nginx and version |
| File 00 | Exact package/version/commit |
| Files 03/04/07/08/19/20 | Exact active versions/commits |
| File 09 package hash | Must match the RC1 SHA-256 above |
| Database backup | Backup identifier and successful restore proof |
| Files backup | Backup identifier and successful restore proof |
| Private storage | Absolute path, owner, permissions, and web-denial evidence |
| Keyring | Key IDs only; never record key material |
| Scanner | Product/service, version, and fail-closed test evidence |

## Gate A — Installation and dependency authority

- [ ] Fresh staging backup is complete and restorable.
- [ ] RC ZIP checksum matches before upload.
- [ ] ZIP has one correct top-level WordPress plugin folder and no unexpected entries.
- [ ] With File 00 inactive, File 09 fails closed without fatal error, role creation, account-type mutation, page ownership, or credential collection.
- [ ] With File 00 active, File 09 recognizes its public APIs and exact capabilities.
- [ ] File 09 does not require File 03 or File 07 for activation/runtime.
- [ ] No administrator receives File 09 reviewer authority merely because the account is an administrator.
- [ ] File 00 suspended/blocked/revoked accounts cannot apply or review.
- [ ] Activation is idempotent; a second activation does not duplicate tables, pages, schedules, or records.
- [ ] Deactivation stops scheduled work without deleting evidence.

**Pass condition:** File 00 remains the sole identity/capability authority and no circular dependency or privilege grant appears.

## Gate B — Schema, migration, and rollback

- [ ] Fresh install creates every schema-v3 table/index/constraint exactly once.
- [ ] Required unique constraints reject duplicate active application/evidence versions.
- [ ] Existing `1.0.0` metadata/documents are copied or quarantined without automatic verification.
- [ ] Legacy `GDO1` evidence is decrypt-only and cannot silently become approved.
- [ ] Migration can resume safely after interruption.
- [ ] A failed migration leaves prior data recoverable and records an audit failure.
- [ ] Concurrent migration/activation does not create duplicate records.
- [ ] Database rollback restores the exact pre-test state.
- [ ] Files rollback restores the exact pre-test state.
- [ ] Reinstall after rollback succeeds without orphan ownership or stale cron state.

**Pass condition:** fresh install, upgrade, interrupted upgrade, and rollback are all repeatable and lossless.

## Gate C — Keyring, encryption, and private storage

- [ ] Missing `GDO_KEYRING` fails closed before evidence acceptance.
- [ ] Malformed base64, wrong key length, duplicate key ID, and missing active key ID fail closed.
- [ ] Missing `GDO_PRIVATE_STORAGE_DIR`, uploads-tree path, web-root path, unwritable path, symlink escape, and insecure permissions fail closed.
- [ ] Direct HTTP requests to private evidence are denied by the actual staging web server.
- [ ] GDO2 encrypt/decrypt round-trip succeeds with the active key.
- [ ] User ID, application ID, document type, or version tampering causes authenticated decryption failure.
- [ ] Ciphertext/tag/content-HMAC tampering is rejected and audited.
- [ ] Old-key records remain readable during controlled rotation.
- [ ] Rotation re-encrypts to the new key ID without changing approved evidence identity.
- [ ] Interrupted rotation resumes safely and never destroys the last readable copy.
- [ ] Backup restore plus protected keyring recovery successfully decrypts a test record.
- [ ] Losing/removing an old key produces a visible health failure; it does not delete evidence.

**Pass condition:** no document is accepted without healthy private storage and a recoverable, versioned cryptographic lifecycle.

## Gate D — Hostile upload and transactional evidence handling

Use synthetic/non-personal test files only.

- [ ] Non-HTTP-uploaded temp path is rejected.
- [ ] Zero-byte, too-small, oversized, double-extension, spoofed MIME, and unsupported type are rejected.
- [ ] Malformed/truncated PDF is rejected.
- [ ] PDF containing JavaScript, launch actions, embedded files, forms, or prohibited active content is rejected.
- [ ] Excessive PDF object/structure limits fail closed.
- [ ] Image pixel/decompression bomb is rejected.
- [ ] Accepted images are safely re-encoded and EXIF/GPS metadata is absent afterward.
- [ ] Malware-positive, scanner-error, scanner-timeout, and scanner-unconfigured cases all fail closed.
- [ ] Per-user quota and repeated-upload rate limits work and are auditable.
- [ ] Three-document submission commits only when every required document passes.
- [ ] Failure on document two or three rolls back database and filesystem changes.
- [ ] Replacement failure preserves the previous valid evidence/version.
- [ ] Simulated database insert failure removes the staged/new file and preserves the old record.
- [ ] Simulated atomic-rename/filesystem failure rolls back the database transaction.
- [ ] Orphan cleanup finds only genuine File 09-owned orphans and never deletes active evidence.

**Pass condition:** hostile content is denied and no partial submission, evidence loss, duplicate version, or orphan leakage remains.

## Gate E — Applicant eligibility and state machine

Create separate synthetic accounts for doctor, patient, student, rejected doctor, suspended doctor, duplicate identity, and underage professional applicant.

- [ ] Logged-out user cannot submit.
- [ ] Patient/student/general member cannot self-convert into the doctor workflow.
- [ ] Unapproved membership, unverified email, unverified identity, duplicate identity, sanctioned account, and insufficient professional age fail closed.
- [ ] Eligible File 00 doctor candidate can create exactly one active application.
- [ ] Allowed path works: `draft → submitted → under_review → more_information_required → resubmitted → recommended → approved`.
- [ ] Rejected, suspended, revoked, expired, appealed, renewal, and reinstatement paths follow only documented transitions.
- [ ] Direct status overwrite, replayed nonce, stale row version, and concurrent reviewer update are rejected.
- [ ] A rejected/suspended/revoked applicant cannot reset state by resubmitting the public form.
- [ ] Renewal creates/uses the intended successor version and preserves prior approved history.
- [ ] Every transition records actor, previous state, new state, reason, timestamp, row version, and audit linkage.

**Pass condition:** no actor can skip or rewrite the canonical lifecycle.

## Gate F — Reviewer governance and evidence decisions

Use at least two different authorized reviewer accounts and one finalizer account.

- [ ] Unassigned reviewer cannot open/download/review the case.
- [ ] Assigned reviewer scope is enforced by country/jurisdiction test filters where configured.
- [ ] Applicant cannot review or finalize their own case.
- [ ] Reviewer conflict declaration is mandatory and auditable.
- [ ] Password-only, TOTP-only, invalid password, invalid TOTP, and expired step-up all fail.
- [ ] Successful password-plus-TOTP step-up is bound to the current user session and expires within the configured window.
- [ ] Recommender cannot be the finalizer.
- [ ] Final approval is unavailable until every required evidence item has an accepted decision.
- [ ] Identity name/photo match, qualification authenticity, licensing authority/number, registry result, jurisdiction, and validity/expiry fields are enforced.
- [ ] Unreadable, rejected, expired, suspected-fraud, and replacement-required evidence blocks approval.
- [ ] Approval creates one immutable snapshot and deterministic fingerprint.
- [ ] Material professional/evidence change does not overwrite the approved snapshot and enters review/invalidation flow.
- [ ] Suspension, revocation, expiry, renewal, appeal, and reinstatement require authorized actors, reasons, and audit events.

**Pass condition:** verification represents reviewed evidence, not field presence, and separation of duties cannot be bypassed.

## Gate G — Credential access and response security

- [ ] Every credential view/download requires assignment, scope, recent step-up, explicit purpose, nonce, and rate allowance.
- [ ] Allowed, denied, failed, and rate-limited access attempts create immutable access-audit records.
- [ ] Applicant notification/outbox event is produced for permitted credential access according to policy.
- [ ] Download uses controlled streaming and does not disclose the private filesystem path.
- [ ] Response includes restrictive content type/disposition, `nosniff`, no-store/private cache policy, no-referrer, frame denial, and applicable permissions policy.
- [ ] Output buffers cannot prepend corrupt data.
- [ ] Direct URL, IDOR, changed application ID, changed evidence ID, and cross-user access are denied.
- [ ] Unusual/repeated download behavior triggers rate denial and an audit event.

**Pass condition:** broad credential browsing or unaudited download is impossible.

## Gate H — Consent, privacy, retention, and uninstall

- [ ] Submission stores exact consent/privacy/retention text versions, purpose, timestamp, actor/application, and event hash.
- [ ] Changed consent text produces a new version; prior accepted evidence remains historically attributable.
- [ ] Withdrawal state is recorded without falsifying prior processing history.
- [ ] WordPress privacy export paginates and includes application values, transitions, decisions, evidence metadata, consent, access logs, appeals, and retained audit data allowed for export.
- [ ] Erasure physically verifies eligible evidence deletion before deleting its database locator.
- [ ] Failed physical deletion is retained for retry and reported; it is not marked complete.
- [ ] Public verification/projection becomes unavailable immediately on valid erasure/revocation.
- [ ] Retained accountability records are anonymized/pseudonymized according to policy.
- [ ] Legal hold blocks prohibited deletion and records the reason.
- [ ] Retention cron is idempotent, bounded, and auditable.
- [ ] Guarded uninstall without both explicit authorizations preserves all sensitive data.
- [ ] Guarded destructive uninstall with both authorizations removes only File 09-owned tables/options/pages/files/schedules and produces deletion evidence.

**Pass condition:** privacy rights, accountability retention, and physical evidence handling remain internally consistent.

## Gate I — Cross-file integrations and public projections

- [ ] File 03 reads only the approved public snapshot API and never private/raw application data.
- [ ] File 04 publishing eligibility changes correctly after approval, suspension, revocation, expiry, and reinstatement.
- [ ] File 07 lists only currently approved doctors and immediately removes invalidated doctors.
- [ ] File 08 receives only the permitted verified-doctor state/snapshot.
- [ ] File 19 consumes outbox events without File 09 directly calling `wp_mail()`.
- [ ] File 20 owns navigation/shell; File 09 creates no duplicate global header, bell, or navigation authority.
- [ ] API responses expose only documented public fields and enforce cache/indexing contracts.
- [ ] Missing consumer module does not break File 09 canonical workflow.

**Pass condition:** File 09 is the verification authority while consumers remain read-only and privacy-bounded.

## Gate J — Managed page, usability, performance, and recovery

- [ ] Existing unrelated `doctor-application` slug is not claimed or modified.
- [ ] File 09-owned page is identified by explicit ownership metadata and remains recoverable after permalink flush.
- [ ] Applicant page is `private, no-store`, `noindex, nofollow, noarchive`, no-referrer, frame-denied, and `nosniff`.
- [ ] Public form errors do not expose paths, SQL, key IDs beyond policy, or sensitive evidence details.
- [ ] Keyboard navigation, labels, focus order, validation errors, and 320–1920px layouts are acceptable.
- [ ] Admin queue is paginated/bounded and does not omit rejected/suspended/appealed states.
- [ ] Large test queue does not cause unbounded memory/query behavior.
- [ ] Backup restore returns application, evidence, key readability, audit, and state exactly.
- [ ] Emergency plugin deactivation leaves File 00 and other modules operational.
- [ ] Post-restore smoke test passes all critical paths.

**Pass condition:** the corrected system is operable, bounded, recoverable, and does not take ownership of unrelated content.

## Acceptance result record

| Gate | Result | Evidence reference | Defects opened | Retest result |
|---|---|---|---|---|
| A — Authority/dependency | Pending |  |  |  |
| B — Schema/migration/rollback | Pending |  |  |  |
| C — Crypto/private storage | Pending |  |  |  |
| D — Upload/transactions | Pending |  |  |  |
| E — Eligibility/state machine | Pending |  |  |  |
| F — Reviewer governance | Pending |  |  |  |
| G — Credential access | Pending |  |  |  |
| H — Privacy/retention/uninstall | Pending |  |  |  |
| I — Integrations/projections | Pending |  |  |  |
| J — Usability/recovery | Pending |  |  |  |

## Final acceptance law

A gate is not PASS when merely attempted. Every defect discovered during staging must be corrected, retested, and closed before the affected gate becomes PASS. Merge, production, and live-installation authorization remain `false` until all ten gates pass, backup/rollback proof is attached, an independent staging review is recorded, and the Founder explicitly accepts the release.
