=== Global Doctor Onboarding and Verification Completion ===
Contributors: sabrihomeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Canonical, versioned and independently reviewed doctor onboarding for Sabri Social Homeopathy Platform.

== Description ==

File 09 owns doctor applications, credential evidence, independent decisions, lifecycle states, appeals, private evidence access, retention and read-only approved projections.

It requires:

* File 00 Sabri Membership Core for identity, active membership, sanctions, reviewer capabilities, two-factor step-up and canonical security audit.
* A versioned external GDO_KEYRING containing 32-byte base64 keys.
* GDO_PRIVATE_STORAGE_DIR outside public uploads and, by default, outside WP_CONTENT_DIR.
* A fail-closed malware scanner through the gdo_credential_scan_result filter.
* File 19 Unified Notifications for delivery; events remain in the File 09 outbox while File 19 is unavailable.

File 09 does not create roles, assign account types, send direct email, own the global application shell, or expose credential files through public URLs.

== Security ==

* AES-256-GCM GDO2 envelopes with versioned key identifiers and authenticated application/document metadata.
* Password plus File 00 TOTP reviewer step-up, bound to the current WordPress session for 15 minutes.
* Assigned reviewer, no self-review, conflict declaration, recommendation/finalization separation and purpose-bound access audit.
* Private storage, atomic writes, verified deletion, upload quotas, rate limits, structural PDF controls and safe image re-encoding.
* Immutable state-transition hash chain, optimistic row versions, versioned consent, appeals, expiry, suspension, revocation and renewal.

== Upgrade Notice ==

1.1.0 quarantines legacy records, removes the obsolete administrator-wide capability, and migrates decryptable GDO1 credential evidence into GDO2 private storage. Preserve the original WordPress salts and a verified backup until migration and staging acceptance are complete.

== Changelog ==

= 1.1.0 =
* Corrected the 18 release blockers identified by independent review.
* Made File 00 authoritative and removed File 03/File 07 dependency cycles and role/account mutation.
* Added canonical schema version 3, evidence decisions, approved snapshots, appeals, retention, outbox, access audit and guarded erasure.
* Added versioned encryption, private storage, key rotation, hostile-upload controls and session-bound reviewer step-up.
* Integrated File 19 and File 20 through their supported boundaries.
