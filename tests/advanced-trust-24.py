#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
main = (root / 'global-doctor-onboarding.php').read_text(encoding='utf-8')
plugin = (root / 'includes/class-gdo-plugin.php').read_text(encoding='utf-8')
trust = (root / 'includes/class-gdo-advanced-trust.php').read_text(encoding='utf-8')
hard = (root / 'includes/class-gdo-advanced-trust-hardening.php').read_text(encoding='utf-8')
events = (root / 'includes/class-gdo-advanced-trust-events.php').read_text(encoding='utf-8')
privacy = (root / 'includes/class-gdo-privacy.php').read_text(encoding='utf-8')
retention = (root / 'includes/class-gdo-retention.php').read_text(encoding='utf-8')
release = (root / 'RELEASE-FILES.txt').read_text(encoding='utf-8')

assert "Version: 1.3.0" in main and "define( 'GDO_VERSION', '1.3.0' )" in main
for path in ['class-gdo-advanced-trust.php','class-gdo-advanced-trust-hardening.php','class-gdo-advanced-trust-events.php']:
    assert path in main
assert 'GDO_Advanced_Trust_Hardening::hooks' in plugin

capabilities = [
    'primary_source_verification','trusted_issuer_registry','credential_authenticity','jurisdiction_rules',
    'cross_border_equivalency','continuous_license_monitoring','event_driven_reverification','professional_verification_passport',
    'public_verification_qr_payload','verification_scope_badge','institutional_affiliation_verification','professional_history_timeline',
    'credential_translation_workspace','ai_assisted_evidence_review','explainable_risk_intelligence','fraud_ring_detection',
    'reviewer_conflict_of_interest','adaptive_dual_review','smart_reviewer_routing','reviewer_calibration',
    'applicant_command_center','resumable_secure_upload','secure_evidence_viewing_room','trust_transparency_dashboard',
]
for capability in capabilities:
    assert capability in trust, f'missing advanced capability: {capability}'

for method in [
    'register_issuer','trusted_issuer','primary_source_verify','authenticity_assessment','equivalency_assessment',
    'verify_affiliation','translation_assistance','ai_assistance','fraud_ring_scan','risk_explanation','declare_conflict',
    'has_conflict','requires_dual_review','smart_reviewer_candidates','reviewer_calibration','add_history','public_history',
    'command_center','create_upload_session','append_upload_chunk','finalize_upload_session','issue_viewing_room_grant','transparency_snapshot'
]:
    assert re.search(r'function\s+' + re.escape(method) + r'\s*\(', trust), f'missing base method {method}'

# External services are facts/hints only; no final professional decision is delegated.
for token in ['human_final_decision_required','automated_decision_forbidden',"private_evidence_searchable'] = false","donor_rank_advantage'] = false"]:
    assert token in trust
assert 'clinical_authorization' in hard and 'cure_guarantee' in hard
assert 'professional verification does not guarantee treatment outcomes' in trust.lower()

# RC6 replaces the affected RC5 callback surfaces rather than stacking duplicate actions.
for token in [
    "remove_action( 'gdo_trust_continuous_monitor'", "remove_action( 'gdo_application_submitted'",
    "remove_action( 'gdo_application_decided'", "remove_action( 'gdo_professional_status_changed'"
]:
    assert token in hard, f'missing callback replacement: {token}'

# Advanced Trust schema 2 is additive and migration-aware.
assert "const SCHEMA_VERSION = 2" in hard
assert 'application_status' in hard and 'application_state' in hard
assert "update_option( 'gdo_advanced_trust_schema', self::SCHEMA_VERSION" in hard

# Trusted issuer/rule governance requires independent approval and immutable approved versions.
assert 'gdo_issuer_review_separation' in trust
for token in ['gdo_jurisdiction_rule_separation','gdo_jurisdiction_rule_immutable','gdo_jurisdiction_rule_changed']:
    assert token in hard

# Provider output is normalized/minimized and provider errors never authorize state changes.
assert 'normalize_primary_source_result' in hard
assert 'expires_at' in hard and "gmdate( 'Y-m-d H:i:s'" in hard
assert 'provider_unavailable' in trust
assert 'gdo_professional_reverification_required' in events
assert 'GDO_State::transition' not in events

# Continuous monitor retries degraded/adverse cases and does not strand degraded rows.
assert "monitor_status IN ('scheduled','degraded')" in hard
assert 'wp_schedule_single_event' in hard
assert "'degraded'" in hard and 'failure_count' in hard

# Passport issuance is serialized, supersession write failures fail closed, and public GET is read-only.
for token in ['FOR UPDATE','gdo_passport_supersede','token_hash','verification_matrix','GDO_State::public_verified']:
    assert token in hard
verify = hard[hard.index('function verify_passport_uuid'):hard.index('function continuous_monitor')]
assert 'revoke_passports_for_application' not in verify
for forbidden in ['profile_json','storage_name','source_sha256','review_note']:
    assert forbidden not in verify

# Public passport verification must never be stale-cached.
assert 'no-store, no-cache, must-revalidate' in events
assert 'X-Robots-Tag' in events

# Reviewer conflict remains a narrowing authorization constraint.
assert 'return $allowed && ! self::has_conflict' in trust
assert 'GDO_Membership_Adapter::reviewer_scope_allows' in trust
assert 'GDO_Membership_Adapter::recent_step_up' in trust

# Resumable upload preserves canonical evidence validation/encryption and stale-finalizing cleanup.
for token in ['GDO_Evidence::stage_upload','gdo_is_uploaded_file','expected_sha256','hash_mismatch','Upload chunks must arrive exactly once and in order']:
    assert token in trust
assert "state IN ('open','failed','finalizing')" in hard
assert '.chunk-' in retention

# Secure room reuses the mature one-time File 09 evidence grant, no parallel raw-file path.
for token in ["GDO_Evidence::issue_view_grant", "'download_allowed'=>false"]:
    assert token in trust
assert 'secure_room' not in hard

# Public transparency is a fixed aggregate window with minimum-cohort suppression.
for token in ['PUBLIC_WINDOW_DAYS','gdo_public_transparency_minimum_cohort',"'suppressed'=>true"]:
    assert token in trust

# Advanced Trust is included in privacy export/erasure and retention without user_version collision.
assert 'privacy_export_rows' in privacy
assert 'GDO_Advanced_Trust_Hardening::privacy_erase_application' in privacy
assert "delete( GDO_Advanced_Trust::table( 'verification_passports' )" in hard
assert 'retire_advanced_trust_for_application' in retention

# REST object authorization is rechecked and individual check failures are structured.
assert 'reviewer_scope_allows' in hard
assert "'ok'=>false" in hard
assert "register_rest_route( GDO_Advanced_Trust::REST_NAMESPACE, '/trust/check/" in hard

# Release package must include every RC6 runtime layer.
for path in ['includes/class-gdo-advanced-trust.php','includes/class-gdo-advanced-trust-hardening.php','includes/class-gdo-advanced-trust-events.php']:
    assert path in release, f'missing release runtime: {path}'
print('Advanced Trust 24 + RC6 hardening static contract: PASS')
