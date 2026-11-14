# File09 V12.3 P00/P01 — Exact-source freeze, 53-ID gap matrix and 32-gate acceptance ledger

**Date:** 2026-11-14. **Decision:** REPOSITORY-ONLY / RELEASE HOLD. This is a *new* plan-conformance evidence layer; not a runtime fix or acceptance.
**Governing plan:** `Sabri-Website-Master-Plan-V12.3.docx`, pp.40, 78–80, 157–162, 261–263. Execution sequence P00–P14 in `File09-V12.3-Complete-Execution-Plan-2026-11-14.docx`.
## P00 — Frozen reality (do not substitute one environment for another)
| Truth field | Current evidence |
|---|---|
| GitHub repository | `majidhussainqadri1-dot/09-global-doctor-onboarding-and-verification-completion` |
| Repository main exact SHA | `8f52f87a6d82fc89c6c78b7da80b474dff916d97` |
| Source plugin and package | PHP plugin header 1.3.0; RC6 candidate; core schema 6 / Advanced Trust schema 2; 62-file package allowlist |
| PR #17 source candidate | `e32015b88d8dfc18ab093197095bc41eac26d775`, open Draft, unmerged; exact-head CI run `43553604068` = 5/5 success |
| Other open historical drafts | #1, #2, #4 (not automatically considered merge-ready) |
| Staging state | `STAGING-ACCEPTANCE.md`: NOT EXECUTED / NOT ACCEPTED; 0/32 accepted |
| WordPress site access | WordPress Jetpack connector: `site_disconnected`; WPVibe not connected and `connect_site` got HTTP 530. Neither result identifies failing hop or root cause |
| Deployed Version / artifact checksum | **UNVERIFIED** |
| Live DB Version / tables / `wp_options` | **UNVERIFIED** |
| Migration State / runtime logs | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔** No source QA result changes this statement.

## P01 — Full 53-ID source-level mapping
This faithfully transcribes the specific ID-to-source/test claims in the exact-main `TRACEABILITY.md`; it does **not** independently execute the mapped code. Every row is **MAPPED-SOURCE / STAGING-UNVERIFIED**. A source claim is not independent proof of completeness.
| Requirement | Spec capability / rule | Existing reported source/test trace | Evidence disposition |
|---|---|---|---|
| F09-FR-001 | Eligibility precheck | `GDO_Policy`, File 00 assertions; policy/membership tests | MAPPED-SOURCE; staging unverified |
| F09-FR-002 | Guided wizard | `GDO_Frontend`, onboarding JS | MAPPED-SOURCE; staging unverified |
| F09-FR-003 | Draft save/resume | `GDO_Application`, REST autosave/concurrency | MAPPED-SOURCE; staging unverified |
| F09-FR-004 | Evidence upload | `GDO_Evidence`, private crypto/storage | MAPPED-SOURCE; staging unverified |
| F09-FR-005 | Completeness gate | `GDO_Application::completeness` | MAPPED-SOURCE; staging unverified |
| F09-FR-006 | Submission | owner state machine/outbox | MAPPED-SOURCE; staging unverified |
| F09-FR-007 | Reviewer assignment | `GDO_Admin`, reviewer profiles | MAPPED-SOURCE; staging unverified |
| F09-FR-008 | Evidence review | canonical one-time evidence grants; IDOR/step-up tests | MAPPED-SOURCE; staging unverified |
| F09-FR-009 | More information | versioned replacement evidence | MAPPED-SOURCE; staging unverified |
| F09-FR-010 | Decision | recommendation/finalization/separation of duties | MAPPED-SOURCE; staging unverified |
| F09-FR-011 | Claim issuance | `GDO_Claims`, File 00 acknowledgment | MAPPED-SOURCE; staging unverified |
| F09-FR-012 | Renewal | predecessor continuity + retention lifecycle | MAPPED-SOURCE; staging unverified |
| F09-FR-013 | Suspension/revocation | File 09 state/claims + RC6 derivative-passport invalidation | MAPPED-SOURCE; staging unverified |
| F09-FR-014 | Appeal | independent appeal assignment/resolution | MAPPED-SOURCE; staging unverified |
| F09-FR-015 | Duplicate/fraud detection | `GDO_Risk` + deduplicated credential-reuse clues | MAPPED-SOURCE; staging unverified |
| F09-FR-016 | Applicant data rights | `GDO_Privacy` + Advanced Trust export/erasure/retention | MAPPED-SOURCE; staging unverified |
| F09-FR-017 | Reviewer quality | `GDO_Quality` + completed-sample calibration | MAPPED-SOURCE; staging unverified |
| F09-NFR-001 | Object/field authorization | File 00 + native owner/state/version/purpose + RC6 object-scope REST recheck | MAPPED-SOURCE; staging unverified |
| F09-NFR-002 | Privacy lifecycle | consent/export/erasure/legal hold/retention + trust-table coverage | MAPPED-SOURCE; staging unverified |
| F09-NFR-003 | Reliability | outbox/retry/dead-letter/reconciliation/Safe Mode + monitor backoff | MAPPED-SOURCE; staging unverified |
| F09-NFR-004 | Performance | bounded queries/indexes/background work; Advanced Trust schema 2 indexes | MAPPED-SOURCE; staging unverified |
| F09-NFR-005 | Accessibility | semantic wizard/focus/reflow/RTL/reduced motion; manual staging gate | MAPPED-SOURCE; staging unverified |
| F09-NFR-006 | Observability | audit chain/trace/access/quality/health/trust failure audit | MAPPED-SOURCE; staging unverified |
| F09-NFR-007 | Migration/rollback | core schema 6 + Advanced Trust schema 2 additive migration | MAPPED-SOURCE; staging unverified |
| F09-NFR-008 | Operability | health/Safe Mode/repair/queues/reconciliation/orphan cleanup | MAPPED-SOURCE; staging unverified |
| F09-NFR-009 | Compatibility | WordPress/PHP versioned contracts, PHP 7.4/8.3 exact-head CI | MAPPED-SOURCE; staging unverified |
| F09-NFR-010 | Localization | en-US base, Urdu/Arabic/RTL, locale-safe dates | MAPPED-SOURCE; staging unverified |
| F09-CEN-01 | Canonical owner contract, free/donor-neutral/privacy/safety metadata, Files 21/23 and File 26 public-safe projections; private evidence non-indexed. |  | MAPPED-SOURCE; staging unverified |
| F09-CEN-02 | Owner-only mutations, versioned read/projection/event contracts, action-time authorization recheck, File 19 event and File 20 page contract. |  | MAPPED-SOURCE; staging unverified |
| F09-AT-01 | Primary-Source Verification Hub | base adapter + RC6 request/result minimization, rate/date hardening | MAPPED-SOURCE; staging unverified |
| F09-AT-02 | Trusted Issuer Registry | proposed-first registry + independent issuer review | MAPPED-SOURCE; staging unverified |
| F09-AT-03 | Credential Authenticity Engine | technical/hash reuse facts; human review | MAPPED-SOURCE; staging unverified |
| F09-AT-04 | Jurisdiction Rules Engine | RC6 draft-first/second-review/effective-date/immutable-version REST path | MAPPED-SOURCE; staging unverified |
| F09-AT-05 | Cross-Border Credential Equivalency Map | adapter fact; never legal license | MAPPED-SOURCE; staging unverified |
| F09-AT-06 | Continuous License Monitoring | RC6 scheduled+degraded selection, persisted backoff, single retry wakeup | MAPPED-SOURCE; staging unverified |
| F09-AT-07 | Event-Driven Reverification | exact transition event + explicit `to_state` mapping | MAPPED-SOURCE; staging unverified |
| F09-AT-08 | Professional Verification Passport | serialized signed/revocable passport; underlying truth rechecked | MAPPED-SOURCE; staging unverified |
| F09-AT-09 | Public Verification QR Card | current public-safe URL; GET read-only/non-cacheable | MAPPED-SOURCE; staging unverified |
| F09-AT-10 | Verification Scope Badge | current public verification record + four-state scope semantics | MAPPED-SOURCE; staging unverified |
| F09-AT-11 | Institutional Affiliation Verification | adapter fact | MAPPED-SOURCE; staging unverified |
| F09-AT-12 | Professional History Timeline | append-only rows + public-event allowlist | MAPPED-SOURCE; staging unverified |
| F09-AT-13 | Credential Translation Workspace | derived translation; original authoritative | MAPPED-SOURCE; staging unverified |
| F09-AT-14 | AI-Assisted Evidence Review | bounded hints; decision keys discarded | MAPPED-SOURCE; staging unverified |
| F09-AT-15 | Explainable Risk Intelligence | reasons/factors; no opaque rejection | MAPPED-SOURCE; staging unverified |
| F09-AT-16 | Fraud-Ring & Collusion Detection | deduplicated credential-reuse network signals | MAPPED-SOURCE; staging unverified |
| F09-AT-17 | Reviewer Conflict-of-Interest Engine | authorized declaration, dedupe, resolution, narrowing filter | MAPPED-SOURCE; staging unverified |
| F09-AT-18 | Adaptive Dual Review | current states + monotonic strengthening | MAPPED-SOURCE; staging unverified |
| F09-AT-19 | Smart Reviewer Routing | jurisdiction/language/load/max-open/conflict | MAPPED-SOURCE; staging unverified |
| F09-AT-20 | Reviewer Calibration Laboratory | completed quality-sample metrics | MAPPED-SOURCE; staging unverified |
| F09-AT-21 | Applicant Verification Command Center | private owner state/checks/next action | MAPPED-SOURCE; staging unverified |
| F09-AT-22 | Resumable Secure Evidence Upload | serialized chunks/finalize, exact dimensions/hash, canonical encrypted handoff | MAPPED-SOURCE; staging unverified |
| F09-AT-23 | Secure Evidence Viewing Room | canonical `GDO_Evidence::issue_view_grant`; one-time/session/step-up | MAPPED-SOURCE; staging unverified |
| F09-AT-24 | Professional Trust Transparency Dashboard | fixed public window + minimum-cohort suppression | MAPPED-SOURCE; staging unverified |

**Accounting:** 17 Functional (FR) + 10 Non-functional (NFR) + 2 central ownership (CEN) + 24 Advanced Trust (AT) = **53 source-mapped requirement IDs**. No assertion that 53/53 were independently tested, installed or accepted.

## External 32-gate staging verification matrix
Status derived from `STAGING-ACCEPTANCE.md`: NOT EXECUTED / NOT ACCEPTED. **0 known passes, 0 executed fails, 32 pending/unexecuted**; do not equate absent testing with a passed gate.
| Gate | Requirement title | Acceptance/reproduction scope | Status |
|---|---|---|---|
| S09-01 | Exact artifact / restore point | record RC6 ZIP SHA-256, exact Git commit, 62-entry manifest/SBOM, pre-install database/files backup, isolated restore proof and rollback window. | NOT EXECUTED |
| S09-02 | Fresh install / activation | File 00, File 02, keyring, private storage, claim-signing key and scanner fail closed when absent; successful activation records core schema 6 and Advanced Trust schema 2 only after migrations/index checks/storage health pass. | NOT EXECUTED |
| S09-03 | RC5/schema-1 → RC6/schema-2 upgrade | idempotently reconcile the eight Advanced Trust tables plus `application_status` passport and `application_state` upload indexes; interrupted/concurrent upgrade, downgrade warning and rollback preserve all existing professional records. | NOT EXECUTED |
| S09-04 | AJ-03 doctor journey | save/resume → evidence upload → submit → reviewer assignment → more-info → replacement evidence → independent decision → acknowledged File 00 claim → passport issue → renewal → suspension/reinstatement/revocation/expiry → independent appeal. | NOT EXECUTED |
| S09-05 | Primary-source / issuer governance | issuer starts `proposed`; the creator cannot independently initial-verify it; domain normalization/duplicate issuer rules work; matched/not-matched/revoked/expired/pending/provider-error/unavailable/malformed/timeout paths are recorded without auto-decision. | NOT EXECUTED |
| S09-06 | Jurisdiction-rule governance | draft-first creation, second-reviewer approval of unchanged rules/dates, immutable approved version, retirement and new-version replacement; invalid/effective-date conflicts fail closed. | NOT EXECUTED |
| S09-07 | Provider privacy/robustness | provider secrets are not persisted; requests are minimized; oversized/secret-like provider outputs are stripped/bounded; provider expiry timestamps normalize safely; request rate limits and provider outage behavior are observable. | NOT EXECUTED |
| S09-08 | Continuous/event-driven reverification | `scheduled` and `degraded` monitor rows both execute; bounded backoff and one-time retry wakeups work; adverse/expiry paths schedule targeted reverification and notification without silently changing professional state. | NOT EXECUTED |
| S09-09 | Passport/QR lifecycle | serialized issue/supersession, current File 09 + File 00 recheck, verification expiry, suspension/revocation/expiry invalidation, duplicate/concurrent issuance, invalid UUID/token, public-safe payload, read-only GET, `no-store/no-cache` and no private-field leakage. | NOT EXECUTED |
| S09-10 | Verification Scope Badge / history | current predecessor verification remains visible during renewal; scope distinguishes verified/pending/not-verified/not-applicable; only allowlisted professional-history events are public-safe. | NOT EXECUTED |
| S09-11 | Reviewer conflict / dual review / routing / calibration | self-review, declared conflict, conflict resolution, stale scope, dual-review same-person prevention, cross-border/high-risk/appeal cases, max-open workload and completed-sample calibration. | NOT EXECUTED |
| S09-12 | Fraud/explainable risk | credential-reuse signals deduplicate, false positives can be resolved, factors/reasons are visible only to authorized review, and risk never auto-rejects. | NOT EXECUTED |
| S09-13 | Resumable evidence upload | exclusive session creation; total/chunk geometry; exact order/size; duplicate/replay/out-of-order chunks; DB/file race; symlink/temp-file mismatch; wrong hash; interrupted/finalizing recovery; two concurrent finalizers; canonical malware/MIME/PDF/polyglot/quota/encryption handoff; no `.chunk-*` orphan. | NOT EXECUTED |
| S09-14 | Secure Evidence Viewing Room | wrong reviewer/application/evidence/session, stale step-up, expired/used token, direct-storage attempt and repeated use fail closed; runtime uses the mature canonical one-time evidence grant; watermark/no-download intent is present without impossible copy-proof claims. | NOT EXECUTED |
| S09-15 | Authorization / IDOR | applicant, reviewer, senior reviewer, Founder, support and forged/stale/suspended identities across every persistent state; object/field/state/version/purpose and trust-check object scope revalidated server-side. | NOT EXECUTED |
| S09-16 | Current File 00/File 02 | current membership/identity/contact/2FA/sanction/jurisdiction state and professional reauthentication; deny/unknown/provider-unavailable cases remain fail closed. | NOT EXECUTED |
| S09-17 | File 19 `sun.event.v1` | register `file09-doctor-verification`; ingest minimized versioned events including reverification-required notification; duplicate retry idempotent; evidence/reviewer notes never appear in notification payload; provider outage retains safe outbox/dead-letter/replay behavior. | NOT EXECUTED |
| S09-18 | Files 03/07/08/21/23 projections | current verify/expiry/suspension/revocation/claim changes propagate within approved SLA, never expose File 09 evidence and never grant clinical authority. | NOT EXECUTED |
| S09-19 | File 26 / CEN-SEARCH-001 | private applications/evidence absent from search corpus; public doctor owners consume current verification truth; loss of verification restricts/removes eligible projection; deletion/rights/freshness preserved; no paid/donor rank influence. | NOT EXECUTED |
| S09-20 | File 20 shell | application/command-center surfaces mount through sole shell/declarations; deep links same-origin/current-authorized; no duplicate header/nav/shell; all private surfaces noindex/no-cache. | NOT EXECUTED |
| S09-21 | Privacy export / AJ-35 | Advanced Trust credential checks, professional history, passport metadata and conflicts appear in the authorized personal-data export without secrets/private evidence blobs. | NOT EXECUTED |
| S09-22 | Privacy erasure / legal hold | public verification first becomes non-current; physical evidence deletion proof; Advanced Trust temporary/monitor/passport cleanup; retained checks/history/conflicts anonymized; no `(user_id,version)` collision; legal-held applications are preserved and reported. | NOT EXECUTED |
| S09-23 | Retention | one expired application is anonymized without erasing a reviewer identity from unrelated applications; derivative passports and temp/monitor rows removed; trust accountability rows minimized; `.chunk-*` orphan policy executes. | NOT EXECUTED |
| S09-24 | Reliability / AJ-36 | File 19/scanner/key/storage/provider failure, stale/degraded monitor, queue outage, timeout, duplicate tap, session expiry, race and reconciliation produce no false success, stale public status or duplicate professional decision. | NOT EXECUTED |
| S09-25 | Backup/restore / AJ-37 | restore database + private objects + keys/config in isolation; decrypt representative evidence; restore core schema 6 + Advanced Trust schema 2, passport/revocation, monitor state, history/privacy status; verify documented RPO/RTO objective. | NOT EXECUTED |
| S09-26 | Experience / AJ-31–33 | 320–1920px, desktop/tablet/mobile, keyboard-only, screen reader, 200–400% zoom, focus, reduced motion, RTL/LTR/mixed fields, Urdu/Arabic/English, slow 3G and reconnect/resume. | NOT EXECUTED |
| S09-27 | Security / AJ-34 | CSRF/XSS/SQLi/SSRF/path/race/replay/brute-force/privilege/cache-leak tests, step-up loss and current authorization rechecks; no secret/PII/internal stack leakage. | NOT EXECUTED |
| S09-28 | Donation neutrality / AJ-24–25 | donor/non-donor application, review, verification/passport/badge, directory/search projection and support behavior identical; File 09 contains no fee/paywall/rank advantage. | NOT EXECUTED |
| S09-29 | Medical/ethical gate | verification is professional-eligibility only; no cure guarantee, clinical authorization or autonomous diagnosis/prescription semantics. | NOT EXECUTED |
| S09-30 | Observability | audit hash chain, trace IDs, access logs, credential checks, monitor degradation, conflict/passport/upload failures, quality samples, queue/dead-letter, health/Safe Mode and privacy-safe diagnostics are reviewable; no raw evidence in ordinary logs. | NOT EXECUTED |
| S09-31 | RC6 80-round regression | the exact deployed staging source/package matches the exact-head RC6 candidate that passed `advanced-trust-24.py`, `eighty-round-audit.py` and all historical adversarial suites; no locally edited staging code. | NOT EXECUTED |
| S09-32 | Release evidence / AJ-38–40 | screenshot/evidence corpus recorded; any critical defect blocks rollout unless a dated Founder risk acceptance exists; two fresh staging review→fix→full-retest rounds end with zero known unresolved release blockers. | NOT EXECUTED |

## P02 — Current verified source defect family / proposed remediation boundary
1. **Source root-cause family: 1** applicant-only actor binding in `GDO_Policy::eligibility()`; **3 distinct scenario categories**: anonymous/foreign actor; actor change after `latest_for_user` exposing private application identifier; actor change during final policy filter exposing subject UUID. Historical rounds are not recounted as new defects.
2. **Draft source fix:** PR #17 introduces exact authenticated current applicant guard at entry, after DB application lookup and after eligibility filter; it fails closed. Draft source CI: 5/5 green on exact PR SHA; **main fixes merged=0, Live fixes proven=0**.
3. **Preserve owner boundary:** File00 read-only `SMC_File09_Intake_Contract::assertion($subject)` and File09 `GDO_Membership_Adapter::is_active_doctor_candidate($subject)` are intentional on-behalf reviewer/public prerequisites; never apply applicant-only actor guard to either.
4. **Read-only callsite review:** private eligibility call paths are in `GDO_Application::create_draft/ensure_draft`, `GDO_Frontend::form`, and `GDO_REST::eligibility`. Public/reviewer paths rely on separate `is_active_doctor_candidate` / `GDO_API::latest_decision`. This is a source observation only; installed WP + File02 session switching, real admin finalization/reinstatement and public status are still required.
5. **Hold:** no independent installed-WordPress/DB integration acceptance, no active vendor scanner/issuer proof, no File00 123-ID acceptance, no backup/restore drill, no 2 full fresh independent staging reviews.

## Release-gating order and next real actions
- **P00 unresolved:** obtain authorized read-only deployed plugin directory/ZIP hash, active versions, WP/DB schema, migrations, logs, key configuration and backups. If mismatch with exact HEAD: stop and run deployment-parity audit.
- **P01:** independently execute the 53 IDs with positive/negative cases and record code vs staging vs live evidence separately. Rectify discovered requirement/owner contradictions before new feature code.
- **P02:** independently review and reproduce Draft PR #17 security regression; real File00+File02+File09 joined WordPress/DB test across applicant/foreign reviewer/public sessions; only then consider merge and unique release identity.
- **P03–P10:** only root-cause-proven minimal source changes; complete source/companion/security/privacy/UX/load/rollback work, exact-head PHP 7.4/8.3 CI + deterministic package + version/SBOM.
- **P11:** install exact package in isolated Hostinger-equivalent staging; run S09-01…32 and attach proof.
- **P12:** two fresh distinct review→fix→full-retest rounds on accepted package; any change reopens relevant gates.
- **P13–P14:** explicit Founder release authorization, immutable artifact hash, recoverable limited deployment, then live checks and operational monitoring.
- **Closure rule:** 53/53 accepted, 32/32 PASS, DoD-01…12 met, zero known live-scope release blockers; report Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status. No deployment or completed work claimed here.

## Defect counting
| Item | Count/status |
|---|---|
| Confirmed new applicant actor-binding root-cause families | 1 |
| Related negative scenario categories | 3 |
| Draft source correction | Present in PR #17; 5/5 green |
| Main/Live correction confirmed | 0 / 0 |
| Other unknown Source defects | Unknown; do not write zero |
| Live deployed defect count | Unknown |
| Staging accepted | 0/32 evidenced |
