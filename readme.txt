=== Global Doctor Onboarding and Verification ===
Contributors: majidhussainqadri1-dot
Tags: doctors, verification, credentials, privacy, onboarding, professional-trust
Requires at least: 6.0
Tested up to: 7.0.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later

Canonical File 09 doctor application, encrypted credential evidence, independent human review, signed File 00 claims, primary-source trust adapters, continuous reverification, professional verification passports, renewal, suspension, appeals, privacy rights, audit and resilience.

== Description ==
File 09 owns doctor applications, professional evidence, reviewer findings, professional verification decisions, lifecycle and signed professional-decision claims. File 00 remains identity/membership/authorization owner; File 02 owns professional reauthentication; File 19 owns notification delivery; File 20 owns the application shell; Files 03/07/08/21/23 consume current public-safe professional eligibility; File 26 owns search/discovery/ranking and consumes a verification projection without indexing File 09 private evidence.

Release 1.3.0 RC5 adds the 24 approved Advanced Professional Trust & Verification Extensions: primary-source verification, trusted issuers, authenticity assessment, jurisdiction rules, cross-border equivalency, continuous monitoring, event-driven reverification, professional verification passports and QR payloads, scoped badges, affiliation verification, professional history, translation assistance, AI reviewer assistance, explainable risk, fraud-ring clues, reviewer conflicts, adaptive dual review, smart routing, calibration, applicant command center, resumable private uploads, secure no-download viewing-room grants and aggregate trust transparency.

External issuer/AI/equivalency/translation/affiliation providers are adapters only. They cannot approve, reject, suspend, revoke or otherwise finalize a professional verification decision. Human authorized review remains mandatory.

RC5 is a source/package/automated-QA candidate only after the latest exact-head workflow passes. It is not staging-accepted, live-deployed or operationally accepted until the documented external acceptance gates pass.

== Security ==
Credential objects are encrypted outside public uploads. Production requires GDO_KEYRING, GDO_PRIVATE_STORAGE_DIR, GDO_CLAIM_SIGNING_KEY, a fail-closed malware scanner integration and compatible File 00/File 02 contracts. Private applications/evidence are never indexed by File 26, public passports expose public-safe verification scope only, resumable uploads finish through the normal File 09 validation/encryption path, and viewing-room grants are step-up-bound and no-download by contract. Provider secrets must remain in deployment secrets/configuration, never this repository.

== Installation ==
1. Install compatible File 00 and File 02 providers.
2. Configure private storage, external versioned encryption keyring and claim signing key.
3. Install current File 19/File 20 and companion consumers on staging.
4. Activate File 09; verify core schema 6 and Advanced Trust schema 1.
5. Configure trusted issuers/jurisdiction rules using privileged step-up; do not store provider secrets in WordPress metadata.
6. Complete STAGING-ACCEPTANCE.md including primary-source failure modes, conflicts/dual review, chunk interruption/resume, passport expiry/revocation and viewing-room tests before real applications.

== Changelog ==
= 1.3.0 RC5 =
* Adds all 24 approved Advanced Professional Trust & Verification Extensions while preserving canonical ownership boundaries.
* Adds an additive Advanced Trust schema with issuer/rule/check/history/conflict/passport/monitor/upload-session stores.
* Adds human-governed external verification/AI adapters, continuous reverification and explainable risk/fraud evidence.
* Adds professional verification passports/QR payloads, scoped verification matrix and aggregate transparency.
* Adds resumable secure evidence uploads and no-download secure evidence viewing-room grants.
* Adds RC5 exact-head tests, deterministic 1.3.0 package and generated SPDX 2.3 SBOM gates.
* Staging, live and operational acceptance remain explicitly false until external evidence is completed.
