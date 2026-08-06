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
