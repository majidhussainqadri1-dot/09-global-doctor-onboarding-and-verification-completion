=== Global Doctor Onboarding and Verification ===
Contributors: majidhussainqadri1-dot
Tags: doctors, verification, credentials, privacy, onboarding
Requires at least: 6.0
Tested up to: 7.0.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Canonical File 09 doctor application, encrypted credential evidence, independent review, signed File 00 claims, renewal, suspension, appeals, privacy rights, audit and resilience.

== Description ==
File 09 owns doctor applications, professional evidence, reviewer findings, decisions, lifecycle and signed professional-decision claims. File 00 remains identity/membership/authorization owner; File 02 owns professional reauthentication; File 19 owns notification delivery; File 20 owns the application shell; Files 03/07/08/21/23 consume current public-safe professional eligibility; File 26 owns search/discovery/ranking and consumes a verification projection without indexing File 09 private evidence.

Release 1.2.0 RC4 is a source/package/automated-QA candidate. It is not staging-accepted, live-deployed or operationally accepted until the documented external acceptance gates pass.

== Security ==
Credential objects are encrypted outside public uploads. Production requires GDO_KEYRING, GDO_PRIVATE_STORAGE_DIR, GDO_CLAIM_SIGNING_KEY, a fail-closed malware scanner integration and compatible File 00/File 02 contracts. File 09 never indexes private applications/evidence in File 26 and never implements parallel email/SMS/push delivery. Do not store secrets in this repository.

== Installation ==
1. Install compatible File 00 and File 02 providers.
2. Configure private storage, external versioned encryption keyring and claim signing key.
3. Install current File 19/File 20 and companion consumers on staging and complete STAGING-ACCEPTANCE.md.
4. Activate File 09 and validate System Check before enabling real applications.

== Changelog ==
= 1.2.0 RC4 =
* Aligns File 09 with the latest central and File 09 plans while retaining runtime 1.2.0/schema 6.
* Adds File 21/File 23 eligibility contracts, File 26 public-safe verification projection contract and File 20 page-contract declaration.
* Uses File 19 sun.event.v1 as the canonical notification integration with minimized payloads and idempotent outbox delivery.
* Adds latest-plan parity tests and an exact-head deterministic generated SBOM for RC4 packaging.
* Preserves truthful staging/live/operational=false release boundaries.
