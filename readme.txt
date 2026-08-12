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

Release 1.3.0 RC6 preserves all 24 approved Advanced Professional Trust & Verification Extensions and incorporates the Founder-requested 80-round corrective review: 49 defect-bearing rounds corrected and 31 clean rounds. RC6 hardens issuer/jurisdiction governance, provider minimization, monitor retry/backoff, exact lifecycle events, passport supersession/current-state verification, four-state verification scope, fraud-signal dedupe, serialized resumable uploads, canonical evidence viewing grants, REST object authorization, aggregate transparency, Advanced Trust privacy/retention and additive schema-2 migration.

External issuer/AI/equivalency/translation/affiliation providers are adapters only. They cannot approve, reject, suspend, revoke or otherwise finalize a professional verification decision. Human authorized review remains mandatory.

RC6 is a source/package/automated-QA candidate only after the latest exact-head workflow passes. It is not staging-accepted, live-deployed or operationally accepted until the documented external acceptance gates pass.

== Security ==
Credential objects are encrypted outside public uploads. Production requires GDO_KEYRING, GDO_PRIVATE_STORAGE_DIR, GDO_CLAIM_SIGNING_KEY, a fail-closed malware scanner integration and compatible File 00/File 02 contracts. Private applications/evidence are never indexed by File 26. Public passports expose only current public-safe verification scope, are read-only/no-cache, and do not grant clinical authorization or guarantee treatment outcomes. Resumable uploads finish through the normal File 09 malware/type/quota/encryption path. Viewing-room grants reuse the canonical one-time/session/step-up evidence-grant lifecycle. Provider secrets must remain in deployment secrets/configuration, never this repository.

== Installation ==
1. Install compatible File 00 and File 02 providers.
2. Configure private storage, external versioned encryption keyring and claim signing key.
3. Install current File 19/File 20 and companion consumers on staging.
4. Activate File 09; verify core schema 6 and Advanced Trust schema 2.
5. Configure trusted issuers as proposed and complete independent review; create jurisdiction rules as drafts and approve unchanged versions through a second authorized reviewer.
6. Complete STAGING-ACCEPTANCE.md including primary-source failure modes, monitor retry/adverse behavior, conflicts/dual review, chunk interruption/resume/races, passport issue/supersession/expiry/revocation, privacy/retention and viewing-room tests before real applications.

== Changelog ==
= 1.3.0 RC6 =
* Preserves all 24 approved Advanced Professional Trust & Verification Extensions.
* Completes 80 corrective review rounds: 49 defect-bearing rounds corrected, 31 clean rounds.
* Advances Advanced Trust schema to 2 and contract to 1.1.0.
* Hardens issuer/rule independent approval, provider minimization, monitoring backoff, exact lifecycle events and passport current-state semantics.
* Hardens resumable upload concurrency/finalization and reuses the canonical evidence-view grant.
* Extends privacy export/erasure/retention across Advanced Trust records.
* Adds RC6 exact-head 80-round, deterministic package and generated SPDX 2.3 SBOM gates.
* Staging, live and operational acceptance remain explicitly false until external evidence is completed.
