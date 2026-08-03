=== Global Doctor Onboarding and Verification Completion ===
Contributors: sabrihomeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later

Canonical, versioned doctor onboarding, professional verification and privacy-minimal practitioner eligibility for the Sabri Social Homeopathy Platform.

== Description ==

File 09 owns doctor applications, credential evidence, independent decisions, lifecycle states, appeals, private evidence access, retention, approved snapshots and read-only professional-eligibility projections.

It requires:

* File 00 Sabri Membership Core 1.2.7 or later with `smc.cf01.membership-assurance` contract 1.0.0 for opaque identity, active membership, sanctions, age, identity assurance and reviewer authority.
* File 02 Sabri Authentication and Accounts 0.3.0 with `sa.professional-reauthentication` contract 1.0.0 for current-password plus session-bound AAL2 reviewer authentication.
* A versioned external GDO_KEYRING containing 32-byte base64 keys.
* GDO_PRIVATE_STORAGE_DIR outside public uploads and, by default, outside WP_CONTENT_DIR.
* A fail-closed malware scanner through the gdo_credential_scan_result filter.
* File 19 Unified Notifications for delivery; events remain in the File 09 outbox while File 19 is unavailable.

File 09 does not create roles, assign account types, verify File 00 passwords/TOTP itself, read File 00 private metadata or recovery storage, send direct email, own the global application shell, or expose credential files through public URLs.

== CF-01 practitioner eligibility ==

Contract `gdo.cf01.practitioner-eligibility` version `1.0.0` provides a short-lived, privacy-minimal professional assertion containing:

* File 00 opaque platform subject UUID and membership record version;
* current File 09 application generation and row version;
* verified/reinstated professional state, validity and approved-snapshot fingerprint;
* accepted and current identity, qualification and license evidence status;
* bounded professional scope projection and jurisdiction decision;
* explicit authorization limits requiring separate File 02 authentication and CF-01 relationship, consent, guardian, purpose, object, field and record-version checks.

An `allow` result is professional eligibility evidence only. It never grants access to a patient, chart, field, prescription, treating relationship, consent, guardian authority or break-glass action. Appointment completion and public verification badges never create clinical authority.

Prescription-signing and break-glass eligibility fail unknown until structured professional restrictions and scope have been independently modeled and approved. License numbers, credential documents, evidence HMACs, private reviewer notes and clinical data are excluded from the public assertion.

== Security ==

* AES-256-GCM GDO2 envelopes with versioned key identifiers and authenticated application/document metadata.
* File 02-owned current-password plus session-bound AAL2 reviewer reauthentication; File 09 never reads File 00 TOTP or recovery secrets.
* Assigned reviewer, no self-review, conflict declaration, recommendation/finalization separation and purpose-bound access audit.
* Private storage, atomic writes, verified deletion, upload quotas, rate limits, structural PDF controls and safe image re-encoding.
* Immutable state-transition hash chain, optimistic row versions, versioned consent, appeals, expiry, suspension, revocation and renewal.
* Exact contract names/versions, bounded timestamps, subject binding and fail-closed unavailable/invalid dependencies.

== Upgrade Notice ==

1.1.1 is a contract candidate stacked on the unmerged 1.1.0 corrective branch. Resolve and accept the 1.1.0 base first, then rebase and retest 1.1.1 against accepted File 00 and File 02 contracts. No staging, production or live-deployment approval is implied.

== Changelog ==

= 1.1.1 =
* Added `gdo.cf01.practitioner-eligibility` contract 1.0.0.
* Removed direct reads of File 00 private verification/TOTP metadata and recovery storage.
* Delegated reviewer password plus AAL2 authentication to File 02 professional reauthentication contract.
* Added action-time row-version and checked-at evidence to the professional decision API.
* Added approved-snapshot, evidence-validity, jurisdiction and structured-scope gates.
* Added explicit non-authorization limits for clinical objects, relationships, consent, prescriptions and break-glass.
* Added static, runtime, architecture and PHP 7.4/8.3 contract tests.

= 1.1.0 =
* Corrected the 18 release blockers identified by independent review.
* Made File 00 authoritative and removed File 03/File 07 dependency cycles and role/account mutation.
* Added canonical schema version 3, evidence decisions, approved snapshots, appeals, retention, outbox, access audit and guarded erasure.
* Added versioned encryption, private storage, key rotation, hostile-upload controls and session-bound reviewer step-up.
* Integrated File 19 and File 20 through their supported boundaries.
