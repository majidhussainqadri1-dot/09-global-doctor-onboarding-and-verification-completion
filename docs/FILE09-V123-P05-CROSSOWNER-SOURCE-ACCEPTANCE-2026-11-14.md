# File09 V12.3 — P05 Cross-Owner Exact-HEAD Dependency and Acceptance Matrix

**Date:** 2026-11-14. **Scope:** GitHub main-branch source reference only. This is not deployment parity, installed integration, or Staging acceptance.
**File09 main baseline:** `8f52f87a6d82fc89c6c78b7da80b474dff916d97`. **Source plugin:** 1.3.0 RC6; core schema 6/Advanced Trust 2.

## Source freeze from currently accessible companion GitHub main branches
The HEAD values below were fetched from each named GitHub repository's `main` directly. They are **not** live deployment measurements and **not** runtime compatibility tests.

| File | Repo | Exact source main SHA | Canonical ownership | File09 interface in-scope | Staging gate | Required positive/negative evidence |
|---|---|---|---|---|---|---|
| 00 | `majidhussainqadri1-dot/00-sabri-membership-core` | `1fad051c86d530d558be3eb1890d3c6e67bce6e8` | identity/role/membership | SMC_File09_Intake_Contract::assertion($subject_id); signed claim acknowledgment | S09-04,15,16,18 | Independent current role/sanction/age/jurisdiction/claimed approval; no clinical grant |
| 02 | `majidhussainqadri1-dot/02-sabri-authentication` | `224c39bcb8c28f77504c7348dbad41226753c7e8` | session and step-up | SA_Professional_Reauthentication v1.0.0 / SA_PROFESSIONAL_REAUTH_VERSION=1.0.0 | S09-14,15,16,27 | Current actor/session/AAL2 and lost-session denial; installed roles and reauth |
| 03 | `majidhussainqadri1-dot/03-sabri-profiles-and-doctors` | `bf317e5521ec79f9f16d21353f402571fcd4f84c` | public profile/wallet | File09 public projection and credential-wallet adapter | S09-10,18,26 | No private fields, live expiration/revocation, status mapping, public allowed scope |
| 07 | `majidhussainqadri1-dot/07-doctors-directory-and-discovery` | `8f9b567c08e9456daf31ebadf17b13bb307571f8` | doctor directory/ranking | gdo_file07_directory_eligibility($user_id) | S09-18,19,28 | No donor bias; status eligibility and directory removal on revoke |
| 08 | `majidhussainqadri1-dot/08-worldwide-clinic-and-appointments-foundation` | `d086840f7e2ac0ed328006385f064e0e2fb58cb1` | clinic/appointment | gdo_file08_clinic_eligibility($user_id) | S09-18,29 | Professional trust prerequisite only; never native clinical authorization |
| 14 | `majidhussainqadri1-dot/14-global-clinic-usp-and-conversion-sections` | `d1cd343392d07607c60f0688362cee450e31774a` | global-clinic onboarding entry | gdo_file14_onboarding_destination() | S09-04,20,26 | No auto-enrollment, invalid account cannot bypass File00/File09 |
| 19 | `majidhussainqadri1-dot/19-sabri-unified-notifications` | `3cd8f01a4614e96c0104d0ff82faa4ad3f02f4e4` | notification delivery | File09 event/outbox producer → File19 sun.event.v1 | S09-17,24,30 | Idempotent minimized events, deliver/retry/dead-letter, no private evidence |
| 20 | `majidhussainqadri1-dot/20-sabri-unified-application-shell` | `8a4dbcaf4fef8e926b9b834ecfde16c21a0f00ca` | canonical app shell | File09 page contracts gdo_page_map/apply | S09-20,26 | Single shell, same-origin deep link, authorized route, no duplicate navigation |
| 23 | `majidhussainqadri1-dot/23-sabri-doctor-founder-publishing-dashboard` | `dcae138e6073f4d0ff596623deb05b9940b8271b` | authorized doctor dashboard | File09 public professional verification projection | S09-18,26 | Owner status only, no private evidence or unsanctioned clinical operation |
| 24 | `majidhussainqadri1-dot/24-sabri-platform-security-privacy-compliance-and-resilience-center` | `a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb` | independent security and compliance | Native File09 controls + File24 assurance coordination | S09-15,21,22,25,27,32 | Do not outsource File09 native auth/privacy; independent pen/restore proofs |
| 25 | `majidhussainqadri1-dot/25-sabri-public-ui-profile-timeline-visual-experience` | `347a4ff4d4c233c5ea6cd82c7786ee5398ea9d1e` | public verification UI | Public scope badge/QR/presentation contract | S09-09,10,18,26 | Expiry/timezone and four-state scope, no misleading verified badge |
| 26 | `majidhussainqadri1-dot/26-search-discovery-recommendations-knowledge-graph-classification` | `1b064c6ea9f81195611ed001d5457e8ced65460a` | search/ranking index | gdo_file26_search_eligibility; CEN-SEARCH-001 | S09-19,28 | Never index private applications/evidence, remove stale trust ranking |

## Concrete source observations verified for V12.3
- File00 main SHA `1fad051c86d530d558be3eb1890d3c6e67bce6e8` exactly equals the source owner pinned by File09's cross-owner QA workflow. Source-only paired owner/consumer tests are green; an installed WordPress+DB+File02 test is **not** established.
- File02 main `224c39bcb8c28f77504c7348dbad41226753c7e8`: `sabri-authentication.php` defines `SAUTH_PROFESSIONAL_REAUTH_VERSION='1.0.0'` and alias `SA_PROFESSIONAL_REAUTH_VERSION`; `SA_Professional_Reauthentication` provides the same 1.0.0 producer and current-session requirement. This is **source signature/version parity**, not session/DB proof.
- File03, File07, File08, File14, File19, File20, File23, File24, File25 and File26 were frozen by repository HEAD only. Their entire native client runtime under WordPress was **not** proven here.
- **File21:** no separately named accessible File21 canonical repository was returned by this GitHub connection. Its ownership and implementation in the current system remain **UNVERIFIED**, not declared absent or defective.
- **CF04:** optional media adapter is outside File09's canonical professional-evidence owner contract unless formally activated by cross-file change control.

## Interaction and privacy invariants
1. File00 identity, roles, age, guardian and suspension always govern; File09 cannot extend their grant from a returned boolean or inferred profile label.
2. File02 owns session authentication and step-up, whereas File09 owns final review/recommendation and professional verification decisions.
3. Authorized reviewers may read an applicant's *on-behalf* File00 prerequisite; private File09 applicant-only entrypoints must bind to the exact authenticated applicant.
4. File09 emits public-safe status and scope only to profile, search, clinics, dashboards; no raw evidence, reviewer notes, unapproved claims, fingerprints as tracking identifiers, automatic clinical authorization or donor advantage.
5. Verified until/expiry/reinstatement/claim acknowledgment must propagate to each consumer at use time; WordPress stale cache or delayed event cannot grant revoked authority.
6. File19 events are minimized, versioned, idempotent and transmitted by the canonical notification service; no File09 transport duplicate.
7. File20 owns only one shell/navigation. File09's internal Command Center remains owner-authorized and noindex/no-cache.

## Required installed behavior fixtures (not yet run)
- Valid requester with separately pending doctor application but current pre-verified ordinary membership should access professional intake, without clinical rights. Denied/stale/suspended/sanctioned File00 state is rejected.
- Different reviewer/finalizer acts on authorized case with current File02 step-up and no conflict: File09 decisions remain human-only; applicant cannot self-finalize.
- Revoke/suspend/expire an existing verified doctor: QR/passport, File03 badge, File07 directory, File08 clinic, File25 UI and File26 search must fail closed or show current nonverified status under approved SLA; events/degraded mode observable.
- File19 down/retry: outbox records exactly one event and later recovers without duplicate decisions, while no private data escapes.
- Donor versus non-donor: identical eligibility/badge/review/support outcome.

## Blockers and truth table
| Field | Current status |
|---|---|
| Companion source HEADs | **12 fetched; GitHub source only** |
| File00 dedicated intake owner/source version parity | Matched to File09 CI pin |
| File02 professional reauthentication API/version parity | Matched as source signature |
| Installed File00/File02/File09 WordPress/DB acceptance | NOT PERFORMED |
| Installed File03/07/08/14/19/20/23/24/25/26 end-to-end | NOT PERFORMED |
| Staging 32 acceptance gates | 0/32 proven PASS |
| Deployed Version / DB Version / Migration State | UNVERIFIED / UNVERIFIED / UNVERIFIED |
| Live Verification Status | NOT PERFORMED |

**Release Hold:** No cross-file delivery acceptance or deployment completion claimed. The correct next action is authorized read-only deployed reality freeze, then installed staging tests. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
