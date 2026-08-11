# File 09 — Release Manifest 1.3.0 RC6

Canonical plugin: **Global Doctor Onboarding and Verification**  
Package root: `global-doctor-onboarding-09/`  
Runtime: `1.3.0`  
Core schema: `6`  
Advanced Trust schema: `2`  
Advanced Trust contract: `1.1.0`  
Review baseline: `6d5c2850dbf86ce954e0c2fdef8adf36d2dbf1f1`

## Scope

RC6 preserves the complete prior File 09 application/evidence/review/decision/renewal/suspension/revocation/appeal/privacy implementation and all 24 approved **Advanced Professional Trust & Verification Extensions 2026**. It additionally preserves all repository corrective assurance evidence through R12.

The RC6 corrective layer fixes lifecycle-state drift, issuer/rule governance, monitoring retry/backoff, passport validity/supersession/read semantics, resumable-upload concurrency, viewing-room grant duplication, REST object authorization, provider-data minimization, Advanced Trust privacy/retention coverage, schema/index migration and release/plan evidence drift.

## Safety invariants

- File 09 owns professional verification decisions; no external issuer, AI, translation, equivalency or affiliation provider may finalize a decision.
- File 00 remains identity/membership/security authority; File 02 remains professional step-up authority.
- Private evidence remains encrypted and non-searchable.
- Public passport/card responses contain only current public-safe verification scope, expiry and disclaimer data; public passport GET is read-only and non-cacheable.
- Search/ranking remains File 07/File 26 responsibility; donation/payment never affects verification or rank.
- Continuous monitoring raises a reverification requirement on adverse external facts; it does not silently auto-revoke.
- Resumable uploads finish through `GDO_Evidence::stage_upload()` and use serialized chunk/finalize state.
- Secure viewing-room grants reuse the canonical one-time/session-bound evidence-grant path and are no-download by contract.
- Advanced Trust privacy export, erasure and app-scoped retention are part of the lifecycle graph.

## Deterministic package gate

`tools/build-release.py` builds `global-doctor-onboarding-09-1.3.0-RC6.zip` twice using the exact **62-entry** release allowlist. `tools/verify-release.py` requires identical entry order, fixed ZIP timestamps, source/package SHA-256 parity and an exact-head generated SPDX 2.3 SBOM whose package identity is `1.3.0-RC6`.

The exact-head source gate runs all legacy suites, Advanced Trust gates, the preserved R1–R11 executable review gates and `tests/ten-round-audit-r12.py` on PHP 7.4 and PHP 8.3. No earlier run can certify later corrected source. Because this manifest itself is inside the release allowlist, **the authoritative source/package/QA result is always the workflow result for the exact final commit that contains this manifest**; any later commit automatically reopens the gate.

## Acceptance state

- Specified: candidate complete
- Coded: RC6 corrective candidate complete at repository-source level
- Repository review: R1–R11 historical 80-round series preserved; R12 fresh 10-round corrective review complete
- Packaged: determined only by the authoritative exact-final-commit RC6 package job
- Automated QA: determined only by the authoritative exact-final-commit PHP 7.4/8.3 RC6 workflow
- Staging Accepted: false
- Live Deployed: false
- Operational: false

Hostinger staging remains mandatory for real WordPress/MySQL core-schema-6 + Advanced-Trust-schema-2 migration, issuer/provider adapters, monitor retry/adverse behavior, chunk interruption/resume/races, private storage, reviewer conflict/dual review, passport issue/supersession/expiry/revocation, privacy/retention, public-safe projections, accessibility, backup/restore and two fresh staging review/fix/retest rounds.

## Fresh second 80-round corrective assurance

RC6 has undergone a second independent 80-control source review against frozen baseline `c3fbbadbee06d2be13b23822f5f17fce07cdab4e`: **47 defect-bearing rounds corrected; 33 clean rounds**. `REVIEW-80-ROUNDS-RC6-R2.md` records the fresh-second ledger; `tests/eighty-round-audit-r2.py` is mandatory alongside the original RC6 gates on PHP 7.4 and PHP 8.3 before deterministic packaging. The final release artifact must be built from the exact current source HEAD; previous RC6 artifacts are historical and must not be represented as current after this correction series.

## Third fresh 80-round corrective assurance

RC6 has undergone a third independent 80-control repository review against frozen baseline `f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361`: **13 defect-bearing rounds corrected; 67 clean rounds**. `REVIEW-80-ROUNDS-RC6-R3.md` records the ledger and `tests/eighty-round-audit-r3.py` is mandatory alongside both earlier 80-round gates. Historical release allowlist at that stage: **55 entries**.

## Fourth fresh 80-round corrective assurance

RC6 has undergone a fourth independent 80-control repository review against frozen baseline `7875ce20a0fd40c0952960b6e45cf1e869b9c4dd`: **30 defect-bearing rounds corrected; 50 clean rounds**. `REVIEW-80-ROUNDS-RC6-R4.md` records the ledger and `tests/eighty-round-audit-r4.py` is mandatory alongside the three earlier 80-round gates. Historical release allowlist at that stage: **55 entries**.

## Fifth fresh 80-round corrective assurance

RC6 has undergone a fifth independent 80-control repository review against frozen baseline `58313a67e1d21ad17c9a066e9a29c34245a0763e`: **29 defect-bearing rounds corrected; 51 clean rounds**. `REVIEW-80-ROUNDS-RC6-R5.md` records the ledger and `tests/eighty-round-audit-r5.py` is mandatory alongside all prior gates. Historical release allowlist at that stage: **56 entries**.

## Sixth fresh 80-round corrective assurance — R6

RC6 underwent a sixth independent 80-control repository review against frozen exact-head baseline `6fa0a5cb7063b6b821bd50c105c735470f589b80`: **60 defect-bearing rounds corrected; 20 clean rounds**. `REVIEW-80-ROUNDS-RC6-R6.md` records the immutable ledger and `tests/eighty-round-audit-r6.py` is mandatory alongside all five prior 80-round gates. Historical release allowlist at that stage: **57 entries**.

## Seventh fresh 80-round corrective assurance — R7

RC6 underwent a seventh independent 80-control repository review against frozen exact-head baseline `9103310fc93d978b6e70661f024a079fc0971003`: **22 defect-bearing rounds corrected; 58 clean rounds**. `REVIEW-80-ROUNDS-RC6-R7.md` records the immutable ledger and `tests/eighty-round-audit-r7.py` is mandatory alongside all prior gates. Historical release allowlist at that stage: **58 entries**.

## Eighth fresh 80-round assurance — R8

Frozen review baseline: `f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2`; **15 defect-bearing rounds corrected, 65 clean**. Historical release allowlist at that stage: **59 entries**.

## Ninth fresh 80-round corrective assurance — R9

RC6 underwent a ninth independent 80-control repository review against frozen exact-head baseline `75ea54ed5113bf7ee16e90443f17cc1b941933a9`: **10 defect-bearing rounds corrected; 70 clean rounds**. `REVIEW-80-ROUNDS-RC6-R9.md` is the immutable ledger and `tests/eighty-round-audit-r9.py` is mandatory alongside R1–R8. Historical release allowlist at that stage: **60 entries**.

## Tenth fresh 80-round corrective assurance — R10

RC6 underwent a tenth independent 80-control repository review against frozen exact-head baseline `ec3ca2dd715e05b66cf29c42f2f996c80987fcd7`: **19 defect-bearing rounds corrected; 61 clean rounds**. `REVIEW-80-ROUNDS-RC6-R10.md` is the immutable ledger and `tests/eighty-round-audit-r10.py` is mandatory alongside R1–R9. Historical release allowlist at that stage: **61 entries**.

## Eleventh fresh 80-round / RC6 release evidence

- Frozen baseline: `91d9a590e18e02030e27ed558ad2147981332ed3`.
- R11: **80 rounds; 17 defect-bearing; 63 clean; post-correction target 80 PASS / 0 FAIL**.
- Defect-bearing rounds: `04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20`.
- Release allowlist after R11: **62 entries**.

## Twelfth fresh 10-round corrective assurance — R12

- Frozen baseline: `0df40731282a4dc905a12b37e5fa5aa4604dc68a`.
- R12: **10 rounds; 10 defect-bearing; 0 clean; post-correction target 10 PASS / 0 FAIL**.
- Defect-bearing rounds: `01,02,03,04,05,06,07,08,09,10`.
- Permanent ledger: `REVIEW-10-ROUNDS-RC6-R12.md`.
- Executable gate: `tests/ten-round-audit-r12.py`.
- R12 fixes fail-closed reviewer/finalizer snapshot reads, claim-acknowledgement-bound passport issuance, checked submission/front-end evidence reads, draft reload certainty, migration/legacy credential failure visibility, version-bound claim delivery failure and retention/orphan-cleanup failure propagation.
- R12 ledger/test are repository QA evidence and intentionally remain outside the installable allowlist; current installable allowlist remains **62 entries**.
- Because R12 changed package-owned PHP source, every artifact predating the final R12 exact head is historical and a new deterministic ZIP/SBOM must be generated.
- Staging acceptance, live deployment and operational acceptance remain false until externally evidenced.
