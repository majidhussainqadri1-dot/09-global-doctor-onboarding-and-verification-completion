=== Global Doctor Onboarding and Verification Completion ===
Contributors: sabrihomeopathy
Tags: doctor onboarding, credential verification, privacy, encrypted evidence, audit
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Canonical File 09 doctor application, evidence, decision, consent, audit, suspension, renewal, appeal, privacy, and retention service for the Sabri Social Homeopathy Platform.

== Description ==
Version 1.1.0 makes File 00 authoritative for identity, active membership, sanctions, reviewer capabilities, step-up authentication, and canonical audit. File 09 owns versioned doctor applications, private credential evidence, independent review decisions, approved snapshots, expiry, revocation, and appeals. Files 03, 04, 07, and 08 consume read-only public APIs. File 19 consumes notification outbox events. File 20 owns shell navigation.

Credential evidence uses AES-256-GCM GDO2 envelopes with an external versioned keyring and authenticated metadata. Private storage must be configured outside public uploads. Malware scanning is fail-closed through the gdo_credential_scan_result filter.

== Installation ==
1. Activate and configure File 00 Membership Core.
2. Define GDO_KEYRING with a 32-byte base64 key and an active key ID.
3. Define GDO_PRIVATE_STORAGE_DIR outside public uploads and verify web denial.
4. Configure gdo_credential_scan_result to return clean only after a real scanner accepts the file.
5. Activate File 09 and complete staging acceptance before any production use.

== Privacy ==
The plugin stores versioned consent, professional application data, encrypted credential evidence, review decisions, credential-access logs, appeals, audit transitions, and notification-outbox events. Privacy export is paginated. Erasure physically verifies eligible evidence deletion and anonymizes retained accountability records. Legal holds and configured retention can require limited retention.

== Changelog ==
= 1.1.0 =
* Replaced legacy role/meta authority with File 00 boundaries.
* Added versioned applications, evidence, consent, decisions, transitions, appeals, access audit, outbox, retention, and rate limits.
* Added independent reviewer/finalizer separation, strict state transitions, approved snapshots, expiry, suspension, revocation, and renewal.
* Added GDO2 versioned encryption, private storage, hostile-file controls, transactional compensation, verified erasure, migration quarantine, and guarded purge.
