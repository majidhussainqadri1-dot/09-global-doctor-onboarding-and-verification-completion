# File 09 — RC6 Eighty-Round Corrective Review

Date: 2026-08-10  
Repository: `majidhussainqadri1-dot/09-global-doctor-onboarding-and-verification-completion`  
Frozen review baseline: `6d5c2850dbf86ce954e0c2fdef8adf36d2dbf1f1` (RC5 exact-head candidate)  
Corrective branch: `codex/file09-1.3.0-rc6-80-round-review`

## Governing method

Each round followed the same law: **review one independent control → if a defect is found, stop that control → correct its root cause immediately → re-review the same control → only then proceed to the next round**. A later round was allowed to expose a defect introduced by an earlier correction; that defect was corrected before continuing. Repository evidence is not staging/live evidence.

Final round count: **80 total review rounds; 49 defect-bearing rounds; 31 clean rounds.** Every defect-bearing round below was corrected in the RC6 corrective tree and is locked by `tests/eighty-round-audit.py` plus the existing source/security/plan suites.

**Defect-bearing rounds:** 03, 05, 06, 09, 15, 16, 17, 18, 22, 23, 24, 29, 30, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 46, 48, 49, 50, 51, 52, 54, 55, 56, 58, 59, 63, 64, 65, 66, 69, 70, 71, 72, 73, 74, 79, 80.

**Clean rounds:** 01, 02, 04, 07, 08, 10, 11, 12, 13, 14, 19, 20, 21, 25, 26, 27, 28, 31, 45, 47, 53, 57, 60, 61, 62, 67, 68, 75, 76, 77, 78.

## Round-by-round ledger

| Round | Independent control | Initial result | Immediate correction / final result |
|---:|---|---|---|
| 01 | Canonical File 09 ownership; no cross-file direct writes | Clean | File 09 owner/read/event/index boundaries already fail-closed; no change. |
| 02 | Specified/Coded/Packaged/Staging/Live/Operational truth separation | Clean | Status law already separated evidence levels; preserved. |
| 03 | Runtime/release/status identity | **Defect** | RC5 evidence became stale after corrective source changes; release line reopened as **RC6** and all release metadata is synchronized before packaging. |
| 04 | Bootstrap/load-order architecture | Clean | Base Advanced Trust → RC6 corrective layer → event bridge ordering is explicit; no duplicate class ownership. |
| 05 | Advanced Trust schema version | **Defect** | RC5 schema option still said 1 although corrective indexes/migration semantics changed; additive Advanced Trust schema raised to **2** through an idempotent hardening migration. |
| 06 | Advanced Trust indexes/data access | **Defect** | Passport application/status and resumable application/state indexes lacked versioned migration evidence; schema-2 index migration added. |
| 07 | Uninstall/rollback non-destructive law | Clean | Destructive purge remains separate; no automatic trust-data drop added. |
| 08 | Cron activation/deactivation hygiene | Clean | Daily monitor hook was already installed/cleared; preserved while adding safe single retry wakeups. |
| 09 | Release allowlist/package closure | **Defect** | New RC6 runtime hardening and 80-round evidence were initially outside `RELEASE-FILES.txt`; both added before package build. |
| 10 | Safe Mode/health/fail-closed dependency gate | Clean | Existing operations gate preserved. |
| 11 | File 00 membership/identity dependency | Clean | Hard fail-closed dependency retained. |
| 12 | File 02 professional step-up | Clean | Reviewer/manage surfaces continue to require recent step-up. |
| 13 | Reviewer object-scope/IDOR | Clean | Mature core evidence path already rechecked current reviewer scope; RC6 REST path keeps it. |
| 14 | Self-review prohibition | Clean | Existing evidence/reviewer controls already blocked self-review; preserved. |
| 15 | Reviewer conflict-of-interest lifecycle | **Defect** | Conflict declaration lacked complete actor/application validation, dedupe, resolution and audit semantics; authorization, meaningful reason, app/applicant match, dedupe, privileged resolution and audit added. |
| 16 | Adaptive dual review | **Defect** | RC5 used a non-existent appeal state and allowed the extension filter to weaken the baseline requirement; corrected to current states and monotonic strengthening. |
| 17 | Smart reviewer routing/workload | **Defect** | Candidate scoring did not enforce each reviewer’s `max_open_cases`; overloaded reviewers are now excluded. |
| 18 | Reviewer calibration denominator | **Defect** | Non-completed quality samples could affect outcome ratios; calibration now counts outcomes only among completed samples. |
| 19 | Existing quality-sampling owner | Clean | File 09 quality ledger remains canonical; no duplicate calibration database created. |
| 20 | Appeal independence | Clean | Existing appeal assignment/separation architecture remained intact. |
| 21 | Primary-source fail-safe unavailable path | Clean | Missing/unavailable issuer/provider already fails to reviewer-required state; preserved. |
| 22 | Trusted Issuer governance | **Defect** | A privileged creator could register an issuer as already verified; issuer creation is now always `proposed`, with an independent second reviewer required for initial verification. |
| 23 | Issuer/provider metadata security | **Defect** | Domain normalization, duplicate active issuer prevention and secret-like metadata filtering were incomplete; canonical host normalization, duplicate rejection, bounded metadata and secret-key stripping added. |
| 24 | Jurisdiction Rules governance | **Defect** | Rules lacked complete effective-date and independent-approval lifecycle; RC6 REST path is draft-first, second-reviewer approved, approved versions immutable, and date-bounded. |
| 25 | Cross-border equivalency legal boundary | Clean | Equivalency remains reviewer evidence and never grants a legal license. |
| 26 | Institutional affiliation ownership | Clean | Affiliation remains adapter fact; File 03 profile ownership is not duplicated. |
| 27 | Translation/original-authority boundary | Clean | Original credential remains authoritative; translation is derivative assistance. |
| 28 | AI final-decision prohibition | Clean | AI decision/approve/reject fields are discarded; human final decision remains mandatory. |
| 29 | Provider data minimization | **Defect** | Default primary-source request included an unnecessary document hash and provider outputs lacked a uniform late sanitization layer; request minimized and result normalization added. |
| 30 | Provider payload/rate/date robustness | **Defect** | Unbounded provider payloads, inconsistent provider expiry formats and repeated calls could create persistence/abuse problems; bounded sanitization, expiry normalization and rate limits added. |
| 31 | Continuous monitor no auto-revoke | Clean | Monitor still raises facts/reverification only; owner state mutation remains outside monitor. |
| 32 | Monitor degraded retry/backoff | **Defect** | RC5 wrote `degraded` rows but selected only `scheduled` rows, stranding failed checks; degraded rows are now selected, exponential backoff is persisted and a single retry wakeup is scheduled. |
| 33 | Event-driven reverification mapping | **Defect** | Fuzzy event-name matching could misclassify lifecycle facts; exact `doctor_verification_transition` + explicit `to_state` mapping replaces regex/fuzzy matching. |
| 34 | Adverse monitor follow-up | **Defect** | Adverse reverification scheduling could be overwritten by the monitor’s later 30-day update and applicant notification was incomplete; adverse cases now retain one-hour follow-up and queue a minimized File 19 event without state mutation. |
| 35 | Passport eligibility | **Defect** | RC5 mixed non-existent `approved` state semantics and did not consistently require current identity assurance; passport issuance now uses `GDO_State::public_verified`, File 00 current identity, and verification expiry. |
| 36 | Passport cryptographic lifecycle | **Defect** | Signing existed but token-validation coverage was incomplete; signed token/hash lifecycle is retained and verified by RC6 contract tests. |
| 37 | Passport revocation propagation | **Defect** | Suspension/revocation/expiry/rejection/withdrawal did not consistently invalidate derivative passports; explicit lifecycle revocation added. |
| 38 | Passport issuance concurrency/supersession | **Defect** | Old active-passport supersession write failure was unchecked and issuance could race; application row locking, transaction, version serialization and fail-closed supersession added. |
| 39 | Passport underlying-state recheck | **Defect** | Public passport lookup relied primarily on passport row status/expiry; it now rechecks the current application and File 00 identity at every public read. |
| 40 | Public passport read/cache semantics | **Defect** | Current-state verification could be served stale and a public read path risked derivative mutation in interim correction; public GET is read-only, `no-store/no-cache`, `noindex`, and returns inactive on stale owner state. |
| 41 | Verification-matrix source record | **Defect** | Matrix used the latest application, incorrectly losing current predecessor verification during renewal; it now uses `verification_record_for_user()`. |
| 42 | Renewal continuity | **Defect** | A renewal draft could cause the public trust matrix/passport context to appear unverified; predecessor verification continuity now follows the mature File 09 decision API rule. |
| 43 | Verification Scope Badge semantics | **Defect** | RC5 exposed mostly booleans despite the approved four-state requirement; `verified/pending/not_verified/not_applicable` scope semantics added while retaining compatibility booleans. |
| 44 | Public professional history | **Defect** | Arbitrary callers could mark overly broad history payloads public-safe; public history event types are now allowlisted and provider payloads are minimized. |
| 45 | Explainable risk | Clean | Reasons/factors and human-review requirement already existed; preserved. |
| 46 | Fraud-ring signal idempotency | **Defect** | Repeated scans could create duplicate `credential_reuse_network` signals; active related-digest dedupe added. |
| 47 | Fraud/risk human resolution | Clean | Risk remains a clue for human review, not auto-rejection. |
| 48 | Resumable upload session creation | **Defect** | Temporary file creation and DB insert failure handling could leave collisions/orphans; exclusive `x+b`, rate limit and checked insert with cleanup added. |
| 49 | Resumable chunk concurrency | **Defect** | File lock alone did not serialize DB state and DB update result was unchecked; row `FOR UPDATE`, file lock, optimistic update and rollback compensation added. |
| 50 | Chunk replay/order/exact size | **Defect** | Declared chunk geometry permitted inconsistent intermediate/final chunk sizes; exact order and expected non-final/final sizes enforced. |
| 51 | Resumable temp-file integrity | **Defect** | Existing-file size/symlink consistency was insufficient before append; private path format, symlink rejection and file-size invariant added. |
| 52 | Session dimension consistency | **Defect** | `bytes/chunks/chunk_bytes` could be internally inconsistent; declared chunk count must now equal `ceil(total/chunk_size)`. |
| 53 | Final content hash | Clean | SHA-256 mismatch already failed closed; preserved. |
| 54 | Finalize race/idempotency | **Defect** | Two concurrent finalize calls could both stage evidence; atomic `open → finalizing` claim added before canonical evidence staging. |
| 55 | Finalize compensation/marker | **Defect** | A session-marker failure after evidence storage was not explicit; it now emits an audit/repair-required error rather than pretending full success. |
| 56 | Stale `finalizing` cleanup | **Defect** | Abandoned finalization sessions were excluded from cleanup; expired `finalizing` temp objects are now covered. |
| 57 | Canonical malware/type/encryption handoff | Clean | Resumable finalize still terminates in `GDO_Evidence::stage_upload()`; no security bypass. |
| 58 | Secure Evidence Viewing Room owner path | **Defect** | RC5 introduced a second access-grant creation path over the core evidence grant ledger; runtime now delegates to the mature canonical `GDO_Evidence::issue_view_grant()`. |
| 59 | Duplicate viewing grant backend | **Defect** | Custom `secure_room` grant semantics risked diverging from one-time/session-bound evidence access; no parallel corrective backend remains active. |
| 60 | One-time/session/step-up consumption | Clean | Mature evidence grant already consumes once, binds session, and rechecks reviewer step-up/scope. |
| 61 | Viewing-room no-download/watermark contract | Clean | No-download intent and reviewer watermark remain explicit; no impossible browser-copy guarantee is claimed. |
| 62 | Evidence/application association | Clean | Requested evidence is resolved under the requested application before grant issuance. |
| 63 | Advanced REST object authorization | **Defect** | Top-level reviewer capability was insufficient for `/trust/check/{app}/{evidence}`; current object-scope authorization recheck added. |
| 64 | Advanced REST failure serialization | **Defect** | Individual check `WP_Error` objects could be embedded in a successful aggregate response; failures are now normalized to explicit `ok/code/message` entries. |
| 65 | Public transparency privacy | **Defect** | Arbitrary `days` windows could expose small-count differences; public endpoint uses a fixed 90-day window and configurable minimum-cohort suppression. |
| 66 | Public verification cache staleness | **Defect** | Passport status lacked explicit anti-cache response headers; `no-store/no-cache/must-revalidate` and robots protection added. |
| 67 | SQL injection/prepare review | Clean | User-supplied values remain parameterized; structural table/index names come only from internal allowlisted constants. |
| 68 | Output escaping/i18n | Clean | Existing shortcode/public outputs escape user-visible values and use localization functions; preserved. |
| 69 | Secrets/logging/provider references | **Defect** | Provider metadata/request data could retain unnecessary sensitive material; secret-like keys are stripped, provider payloads bounded and default primary-source request minimized. |
| 70 | Privacy export coverage | **Defect** | New credential checks/history/passports/conflicts were absent from the core WordPress privacy exporter; Advanced Trust export rows are now included. |
| 71 | Privacy erasure coverage/collision | **Defect** | New trust tables were absent from erasure, and an interim `user_id=0` passport anonymization could collide with unique `(user_id,version)`; RC6 erasure deletes derivative passports and anonymizes retained trust accountability records. |
| 72 | Legal-hold/app-scoped retention | **Defect** | An interim generic Advanced Trust erasure reused during one application’s retention could over-anonymize reviewer identity across unrelated cases; retention is now application-scoped and legal-hold behavior remains in the parent eraser/retention flow. |
| 73 | Retention/orphan coverage | **Defect** | Advanced monitor/upload/passport rows and `.chunk-*` temporary files were outside the old retention/orphan graph; app-scoped cleanup and chunk-orphan handling added. |
| 74 | Additive migration/rollback documentation | **Defect** | Corrective index/schema changes made RC5 schema-1 documentation stale; Advanced Trust schema 2 migration/rollback evidence is synchronized for RC6. |
| 75 | Backup/restore truth | Clean | Repository still does not claim real Hostinger restore proof; external acceptance remains pending. |
| 76 | File 19 notification ownership | Clean | File 09 emits minimized domain facts/outbox only; no parallel `wp_mail` transport. |
| 77 | File 03/File 07/File 26 public-safe projection | Clean | Private application/evidence remains non-indexed; public-safe verification projection only. |
| 78 | Accessibility/RTL/weak-network gate | Clean | These remain explicit staging acceptance requirements; repository source does not falsely claim manual evidence. |
| 79 | Exact-head CI/package/SBOM gate | **Defect** | RC5 workflow did not execute the new 80-round gate or package RC6 identity; workflow/build/verifier/SBOM/release-lock are advanced to RC6 and exact-head evidence must rerun. |
| 80 | Word plan/repository trace/status parity | **Defect** | File 09 plan still recorded RC5/schema-1/contract-1.0 snapshot after corrective work; Word plan and repository documentation are updated to RC6/schema-2/contract-1.1 with this 80-round ledger, without claiming staging/live completion. |

## Corrective architecture summary

The 80 rounds did **not** authorize a second professional-verification backend. The RC6 layer removes/replaces only the affected RC5 callbacks and REST surfaces while preserving File 09 as canonical owner. File 00 remains identity/membership/security assertion authority; File 02 remains professional step-up authority; File 03 profile owner; File 07/File 26 directory/search/ranking owners; File 08 clinic owner; File 19 notification-delivery owner; File 20 shell owner; File 24 security/privacy/compliance assurance owner.

The principal root causes corrected were: state-name drift from the core File 09 state machine; derivative passport lifecycle not being tied tightly enough to owner truth; cron retry state being written but not reselected; resumable-upload DB/file state not being one atomic protocol; duplicated evidence-grant semantics; Advanced Trust tables being omitted from privacy/retention graphs; and release/plan evidence becoming stale after new code.

## Truthful completion state

This report supports **repository review/correction only**. RC6 must still pass the exact-current-head PHP 7.4/8.3 workflow, deterministic double build, 52-entry package parity and exact-head SPDX SBOM. Hostinger staging migration, real issuer/provider failure modes, browser/accessibility, low-bandwidth, backup/restore, two fresh staging review/fix/retest cycles, Founder acceptance, deployment parity and live retest remain external gates.
