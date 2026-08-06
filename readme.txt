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
File 09 owns doctor applications, professional evidence, reviewer findings, decisions, lifecycle and signed professional-decision claims. File 00 remains identity/membership/authorization owner; File 02 owns professional reauthentication; File 19 owns notification delivery; Files 03/07/08 consume acknowledged public-safe claims.

Release 1.2.0 is a source/package/automated-QA candidate. It is not staging-accepted, live-deployed or operationally accepted until the documented external acceptance gates pass.

== Security ==
Credential objects are encrypted outside public uploads. Production requires GDO_KEYRING, GDO_PRIVATE_STORAGE_DIR, GDO_CLAIM_SIGNING_KEY, a fail-closed malware scanner integration and compatible File 00/File 02 contracts. Do not store secrets in this repository.

== Installation ==
1. Install compatible File 00 and File 02 providers.
2. Configure private storage, external versioned encryption keyring and claim signing key.
3. Install on staging and complete STAGING-ACCEPTANCE.md.
4. Activate File 09 and validate System Check before enabling real applications.

== Changelog ==
= 1.2.0 =
* Implements the complete File 09 master-plan source candidate and schema 6.
* Adds guided wizard, autosave/resume, encrypted evidence, structured review, signed claims, lifecycle, appeals, fraud signals, data rights, quality sampling and operational repair.
* Adds deterministic packaging, SBOM, requirement traceability and two fresh review/fix records.
