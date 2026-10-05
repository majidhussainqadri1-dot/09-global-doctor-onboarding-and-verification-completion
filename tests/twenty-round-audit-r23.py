#!/usr/bin/env python3
from pathlib import Path
import json
import sys

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def has(source, *tokens):
    return all(token in source for token in tokens)

def require(round_no, condition, description):
    ok = bool(condition)
    print(f"R{round_no:02d} {'PASS' if ok else 'FAIL'} — {description}")
    return ok

plugin = text('global-doctor-onboarding.php')
integration = text('includes/class-gdo-integration-contracts.php')
membership = text('includes/class-gdo-membership-adapter.php')
state = text('includes/class-gdo-state.php')
frontend = text('includes/class-gdo-frontend.php')
application = text('includes/class-gdo-application.php')
evidence = text('includes/class-gdo-evidence.php')
admin = text('includes/class-gdo-admin.php')
claims = text('includes/class-gdo-claims.php')
notifications = text('includes/class-gdo-notifications.php')
privacy = text('includes/class-gdo-privacy.php')
retention = text('includes/class-gdo-retention.php')
migration = text('includes/class-gdo-migration.php')
advanced = text('includes/class-gdo-advanced-trust.php')
hardening = text('includes/class-gdo-advanced-trust-hardening.php')
operations = text('includes/class-gdo-operations.php')
workflow = text('.github/workflows/file09-rc2-final.yml')
file03_test = text('tests/file03-profile-contracts.php')
lock = json.loads(text('RELEASE-LOCK.json'))
ledger = text('REVIEW-20-ROUNDS-RC6-R23.md')
status = text('STATUS.md')
manifest = text('RELEASE-MANIFEST-1.3.0.md')

checks = []
checks.append(require(1,
    has(integration, "'canonical_entity'       => 'doctor_verification'", "'canonical_mutations'    => 'owner_commands_only'", "'direct_table_meta_write'=> false"),
    'Canonical File 09 ownership remains single-owner and companion writes remain contract-only'))
checks.append(require(2,
    has(membership, 'SMC_CONTRACT_VERSION', 'recent_step_up', 'identity_assurance_current_checked', 'reviewer_case_allows'),
    'File 00 identity and File 02 privileged step-up boundaries remain current and fail closed'))
checks.append(require(3,
    has(state, "'draft'", "'submitted'", "'under_review'", "'more_information'", "'verified'", "'renewal_due'", "'suspended'", "'revoked'", "'appeal_pending'", 'row_version=row_version+1'),
    'Application/verification lifecycle keeps explicit state transitions and optimistic concurrency'))
checks.append(require(4,
    has(frontend, 'data-gdo-wizard', 'gdo_submit_application', 'gdo_file_appeal', 'gdo_withdraw_application')
    and has(application, 'save_draft', 'completeness', 'submission_hash', 'doctor_application_create_commit_reconciled'),
    'Applicant wizard, draft/resume, completeness, immutable submission, appeal and withdrawal remain implemented'))
checks.append(require(5,
    has(evidence, 'stage_upload', 'records_checked', 'issue_view_grant', 'malware', 'encrypt', 'deletion_pending'),
    'Professional evidence remains private, validated, encrypted, reviewable and deletion-safe'))
checks.append(require(6,
    has(admin, 'assigned_reviewer_id', 'recent_step_up', 'reviewer_case_allows', 'resolve_appeal', 'commit_or_reconcile')
    and has(hardening, 'reviewer_conflict', 'dual_review'),
    'Reviewer least privilege, conflict/dual-review and appeal independence remain enforced'))
checks.append(require(7,
    has(claims, 'claim_version', 'accepted', 'issue', 'publish')
    and has(notifications, 'sun.event.v1', 'event_uuid', 'dead'),
    'Decision claim issuance and durable File 19 notification transport remain versioned and retryable'))
checks.append(require(8,
    has(state, "'reinstated'", "'renewal_due'", "'expired'", "'suspended'", "'revoked'")
    and has(retention, 'retention_pending', 'legal_hold'),
    'Renewal, expiry, suspension/revocation, reinstatement and retention lifecycle remains explicit'))
checks.append(require(9,
    has(integration, 'file03_public_fields', "$out['licence_number']", "$out['jurisdiction']", "'approved_fields' => $profile")
    and "$profile['phone']" not in integration
    and "$profile['whatsapp']" not in integration,
    'File 03 public verification projection uses an explicit public allowlist and exact field aliases without contact leakage'))
checks.append(require(10,
    has(integration, "$profile['license_number']", "'format' => 'platform_record'", "'verification_url' => $url", "'raw_evidence_exposed' => false")
    and has(file03_test, 'LIC-12345', 'Private clinic address', 'gdo_validate_public_projection', 'registration'),
    'File 03 credential wallet consumes canonical File 09 license_number, avoids VC overclaim and is behaviorally regression-tested'))
checks.append(require(11,
    has(integration, 'FILE07', "gdo_file07_directory_eligibility", "'consumer'    => 'file07'"),
    'File 07 directory receives only a current public verification projection; File 09 does not own directory ranking'))
checks.append(require(12,
    has(integration, 'FILE08', "gdo_file08_clinic_eligibility", "'consumer'    => 'file08'")
    and 'clinical_authorization' in integration,
    'File 08 clinic boundary remains verification-only and cannot turn File 09 into clinical/appointment owner'))
checks.append(require(13,
    has(integration, 'FILE19_EVENT', "'consumer'       => 'file19'", "'payload'        => 'minimized-no-evidence'")
    and 'wp_mail(' not in notifications,
    'File 19 remains notification transport owner and File 09 emits minimized domain facts only'))
checks.append(require(14,
    has(integration, 'file20_page_contracts', "'shell_owner'", "'file20'")
    and "'primary_brand_owner'=> 'file25'" in integration,
    'File 20 remains sole shell owner and File 25 remains visual/brand owner without a duplicate File 09 shell'))
checks.append(require(15,
    has(integration, 'FILE21', 'FILE23', 'gdo_file21_publishing_eligibility', 'gdo_file23_dashboard_eligibility'),
    'Files 21/23 receive verification eligibility without File 09 owning publishing workflow or dashboard truth'))
checks.append(require(16,
    has(operations, 'safe_mode', 'health', 'mutation_allowed')
    and has(privacy, 'export', 'eras')
    and has(retention, 'legal_hold'),
    'File 24 assurance boundary is supported by native File 09 security/privacy/operability controls rather than delegated enforcement'))
checks.append(require(17,
    has(integration, 'FILE26_CONNECTOR', "'status'             => 'contract_tested'", "'privacy_classes'    => array( 'c0_public_verification_projection' )", "'deletion_semantics' => 'restrict_on_verification_loss'"),
    'File 26 relationship remains a governed C0 verification projection and never indexes private applications/evidence'))
checks.append(require(18,
    'cf04' not in integration.lower()
    and 'cf-04' not in plugin.lower(),
    'Conditional CF-04 is not silently activated as File 09 runtime ownership before its separate activation gate'))
checks.append(require(19,
    has(migration, 'GDO_SCHEMA_VERSION', 'migration', 'rollback')
    and has(advanced, 'verification_passports', 'professional_history', 'upload_sessions')
    and has(hardening, 'SCHEMA_VERSION = 2', 'public_get_mutates_owner_state'),
    'Core schema 6 + Advanced Trust schema 2, migration/rollback and Advanced Trust lifecycle remain present'))
checks.append(require(20,
    'php tests/file03-profile-contracts.php' in workflow
    and 'python3 tests/twenty-round-audit-r23.py' in workflow
    and lock.get('twenty_third_review_baseline') == 'd35eb982becdf0224a5b850a0c6fb4ace8bf075b'
    and lock.get('twenty_third_review_rounds') == 20
    and lock.get('twenty_third_defect_rounds') == 3
    and lock.get('twenty_third_clean_rounds') == 17
    and lock.get('twenty_third_pending_rounds', 0) == 0
    and '| R09 | DEFECT |' in ledger and '| R10 | DEFECT |' in ledger and '| R20 | DEFECT |' in ledger
    and 'R23' in status and 'R23' in manifest,
    'Post-R22 File 03 changes are now inside exact-head CI and a permanent R23 20-round evidence chain'))

passed = sum(checks)
failed = len(checks) - passed
print(f'File 09 R23 plan/cross-file twenty-round audit: {passed} PASS / {failed} FAIL')
if failed:
    sys.exit(1)
