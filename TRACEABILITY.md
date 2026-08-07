# File 09 Requirements Traceability

Candidate: `1.2.0` / schema `6`

A Must requirement is not accepted merely because code is named below. The exact-head automated result and the required external staging evidence must both exist.

## Functional requirements

| Requirement | Capability | Source owner/implementation | Test/evidence |
|---|---|---|---|
| F09-FR-001 | Eligibility precheck | GDO_Policy::eligibility; GDO_Membership_Adapter | tests/policy-runtime.php; tests/membership-adapter.php |
| F09-FR-002 | Guided wizard | GDO_Frontend::form; assets/js/onboarding.js | tests/completion-security.py; staging UI matrix |
| F09-FR-003 | Draft save/resume | GDO_Application::save_draft; GDO_REST::autosave | tests/policy-runtime.php; staging concurrency |
| F09-FR-004 | Evidence upload | GDO_Evidence::stage_upload; GDO_Crypto; GDO_Storage | tests/security-unit.php; tests/completion-security.py |
| F09-FR-005 | Completeness gate | GDO_Application::completeness | tests/policy-runtime.php |
| F09-FR-006 | Submission | GDO_Application::submit; submission_hash; outbox | tests/completion-security.py; staging idempotency |
| F09-FR-007 | Reviewer assignment | GDO_Admin::assign; reviewer_profiles | tests/completion-security.py; staging role tests |
| F09-FR-008 | Evidence review | GDO_Evidence grants/render/review; GDO_Admin | tests/security-unit.php; staging IDOR |
| F09-FR-009 | More information | GDO_Admin::request_more_info; replacement evidence | tests/completion-security.py; staging journey |
| F09-FR-010 | Decision | recommend/finalize; GDO_State; approved snapshot | tests/completion-security.py; staging separation |
| F09-FR-011 | Claim issuance | GDO_Claims; GDO_Notifications claim delivery/ack | tests/policy-runtime.php; tests/completion-security.py |
| F09-FR-012 | Renewal | GDO_Retention::open_renewals; GDO_State | tests/completion-security.py; staging time travel |
| F09-FR-013 | Suspension/revocation | GDO_Admin::change_state; GDO_Claims | tests/completion-security.py; staging lifecycle |
| F09-FR-014 | Appeal | GDO_Frontend::appeal; GDO_Admin::resolve_appeal | tests/completion-security.py; staging independence |
| F09-FR-015 | Duplicate/fraud detection | GDO_Risk | tests/policy-runtime.php; staging false positive |
| F09-FR-016 | Applicant data rights | GDO_Privacy; withdrawal/erasure/legal hold | tests/completion-security.py; staging privacy |
| F09-FR-017 | Reviewer quality | GDO_Quality; access logs/metrics | tests/policy-runtime.php; staging quality |

## Non-functional requirements

| Requirement | Gate | Implementation | Test/evidence |
|---|---|---|---|
| F09-NFR-001 | Object/field authorization | File 00 claims + native object/state/version checks | tests/membership-adapter.php; tests/completion-security.py |
| F09-NFR-002 | Privacy lifecycle | consent/export/erasure/legal hold/retention | tests/completion-security.py; staging privacy |
| F09-NFR-003 | Reliability | transactional outbox/retry/dead-letter/reconciliation/Safe Mode | tests/policy-runtime.php; tests/completion-security.py |
| F09-NFR-004 | Performance | bounded limits/indexes/background work | tests/completion-security.py; staging load |
| F09-NFR-005 | Accessibility | semantic wizard/labels/focus/reflow/RTL/reduced motion | tests/completion-security.py; manual WCAG matrix |
| F09-NFR-006 | Observability | audit hash chain/trace IDs/metrics/health | tests/policy-runtime.php; staging alerts |
| F09-NFR-007 | Migration/rollback | lock/idempotent schema/quarantine/rollback runbook | tests/completion-security.py; staging migration |
| F09-NFR-008 | Operability | health/Safe Mode/repair/queue/reconciliation | tests/policy-runtime.php; staging operations |
| F09-NFR-009 | Compatibility | WP 7.0.1/PHP 8.3 target; PHP 7.4 floor; contracts | GitHub matrix; staging actual environment |
| F09-NFR-010 | Localization | en-US base; translatable strings; RTL logical CSS/date/time | tests/completion-security.py; staging locale matrix |

## Definition of Done mapping

| DoD | Evidence |
|---|---|
| DoD-01 | This traceability map, canonical boundaries and exact plan reference. |
| DoD-02 | Release manifest, SBOM, deterministic package workflow and SHA-256 artifact. |
| DoD-03 | Migration code plus external fresh/upgrade/deactivate/reactivate/uninstall staging evidence. |
| DoD-04 | Architecture and completion-security tests. |
| DoD-05 | Membership/CF-01/static authorization tests plus staging IDOR matrix. |
| DoD-06 | Privacy/security static tests plus external export/erase/retention/provider-deletion evidence. |
| DoD-07 | Security unit/static tests plus real provider and File 24 assurance review. |
| DoD-08 | CSS/static checks plus manual 320–1920px, 400% zoom, keyboard, screen reader and RTL acceptance. |
| DoD-09 | Queue logic/static checks plus staging load/outage/SLO evidence. |
| DoD-10 | Migration/rollback runbook plus isolated backup-restore/decrypt drill. |
| DoD-11 | REVIEW-ROUND-1.md and REVIEW-ROUND-2.md; exact-tree suites run twice. |
| DoD-12 | STAGING-ACCEPTANCE.md with explicit Founder acceptance; pending external execution. |
| DoD-13 | CI zero blockers; residual external gates listed, not hidden. |

## RC2 corrective traceability

| Corrective requirement | Implementation | Automated evidence |
|---|---|---|
| Adult professional eligibility without irrelevant guardian dependency | `GDO_Membership_Adapter::is_active_doctor_candidate` | `tests/membership-adapter.php` |
| Immutable resubmitted snapshot and synchronized optimistic row version | `GDO_API`, `assets/js/onboarding.js` | `tests/rc2-adversarial.py` |
| Atomic credential review + more-information transition | `GDO_Evidence::review`, `GDO_Admin::review_evidence` | PHP lint + adversarial invariants |
| Independent assigned appeal reviewer | `GDO_Admin::assign_appeal/resolve_appeal` | `tests/rc2-adversarial.py` |
| File 03/07/08 fail-closed public projections | `GDO_Integration_Contracts` | `tests/cross-file-contracts.php` |
| Exact-head deterministic RC2 package | `.github/workflows/file09-rc2-final.yml`, `tools/build-release.py` | two-build byte comparison + package parity |

## Four-plan harmonization addendum — 7 Aug 2026

| Governing concern | Corrected implementation evidence |
| --- | --- |
| Current File 00 contract | `GDO_Membership_Adapter` pins general contract 1.2.0 and consumes high-trust fields. |
| Non-circular File 09 entry | Requires approved doctor membership, current identity evidence, verified contacts, 2FA and adult age; it does not require pre-existing File 09 professional approval. |
| Professional truth | File 09 decision, not File 00 display compatibility, controls doctor verification. |
| CF-01 boundary | Current File 00 professional/identity assurance + File 09 decision/snapshot/evidence; no unconditional adult guardian gate. |
| Snapshot lifecycle | Schema 3..current is integrity-checked; reinstatement refreshes validity metadata. |
| Action-time safety | Verification/reinstatement/claims recheck current File 00 assurance; reinstatement also rechecks unresolved high-risk state. |
| File 20 ownership | Local wrapper is `gdo-application`, never a second platform shell. |
| Required fields | Phone remains required; WhatsApp is optional unless future approved policy changes it. |


## Forty-round RC3 corrective traceability — 7 Aug 2026

| Corrective area | Final source evidence | Permanent regression gate |
| --- | --- | --- |
| File 00 result/jurisdiction and monotonic authorization | `GDO_Membership_Adapter`, `GDO_Policy`, `GDO_Claims`, CF-01 | `tests/review40-adversarial.py` Rounds 01–02 |
| Optional-field/runtime parity | `GDO_Frontend` | Round 03 |
| Crash-safe outbox lease | `GDO_Notifications::process` | Round 04 / Round 21 |
| Strict dates | `GDO_Policy::normalize_date/normalize_future_date`, Admin/Evidence callers | Round 05 |
| Reviewer freshness/workload/concurrency | `GDO_Membership_Adapter::reviewer_scope_allows`, `GDO_Admin::assign/assign_appeal` | Rounds 06–07 |
| Credential view/use-time and expiry | `GDO_Evidence`, approved snapshot, CF-01 | Rounds 08–09 |
| Immutable audit/erasure | `GDO_Privacy`, `GDO_Audit` | Round 10 |
| Renewal projection continuity | `GDO_Application::verification_record_for_user`, `GDO_API` | Round 11 |
| Upload owner/quota serialization | `GDO_Evidence::stage_upload` | Round 12 |
| Independent quality/reviewer configuration | `GDO_Quality`, `GDO_Admin::save_reviewer_profile` | Round 13 |
| Corrected-tree assurance families | security/privacy/state/storage/migration/ownership/release | Rounds 14–40 |

RC3 review count: **13 defect-bearing rounds + 27 clean rounds = 40**. Repository zero-known-defect status remains subordinate to fresh evidence and does not imply staging/live/operational acceptance.
