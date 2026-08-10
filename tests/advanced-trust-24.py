#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
main = (root / 'global-doctor-onboarding.php').read_text(encoding='utf-8')
plugin = (root / 'includes/class-gdo-plugin.php').read_text(encoding='utf-8')
trust = (root / 'includes/class-gdo-advanced-trust.php').read_text(encoding='utf-8')
events = (root / 'includes/class-gdo-advanced-trust-events.php').read_text(encoding='utf-8')
release = (root / 'RELEASE-FILES.txt').read_text(encoding='utf-8')

assert "Version: 1.3.0" in main and "define( 'GDO_VERSION', '1.3.0' )" in main
assert "class-gdo-advanced-trust.php" in main
assert "class-gdo-advanced-trust-events.php" in main
assert "GDO_Advanced_Trust_Events::hooks" in plugin
assert "advanced_trust_schema" in plugin

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

required_methods = [
    'register_issuer','trusted_issuer','save_jurisdiction_rule','jurisdiction_rule','primary_source_verify',
    'authenticity_assessment','equivalency_assessment','verify_affiliation','translation_assistance','ai_assistance',
    'fraud_ring_scan','risk_explanation','declare_conflict','has_conflict','requires_dual_review',
    'smart_reviewer_candidates','reviewer_calibration','add_history','public_history','issue_passport',
    'verify_passport_uuid','schedule_reverification','continuous_monitor','command_center','create_upload_session',
    'append_upload_chunk','finalize_upload_session','issue_viewing_room_grant','transparency_snapshot'
]
for method in required_methods:
    assert re.search(r'function\s+' + re.escape(method) + r'\s*\(', trust), f'missing method {method}'

# Safety invariants: external services advise/verify facts but never own final professional decisions.
assert "human_final_decision_required" in trust
assert "automated_decision_forbidden" in trust
assert "unset( $result['decision'], $result['approve'], $result['reject'] )" in trust
assert "private_evidence_searchable'] = false" in trust
assert "donor_rank_advantage'] = false" in trust
assert "professional verification does not guarantee treatment outcomes" in trust.lower()
assert "outside the public" in (root / 'includes/class-gdo-storage.php').read_text(encoding='utf-8').lower()

# Reviewer conflict is a narrowing filter, never a broadening authorization shortcut.
assert "return $allowed && ! self::has_conflict" in trust
assert "GDO_Membership_Adapter::reviewer_scope_allows" in trust
assert "GDO_Membership_Adapter::recent_step_up" in trust

# Primary-source and AI providers are adapter filters and default fail-safe/unavailable.
assert "gdo_primary_source_verification" in trust
assert "provider_adapter_unavailable" in trust
assert "gdo_ai_evidence_assistance" in trust
assert "status'=>'unavailable'" in trust

# Continuous monitoring only raises adverse attention/reverification; it does not auto-revoke.
assert "gdo_continuous_verification_adverse_result" in trust
assert "gdo_professional_reverification_required" in events
assert "change_state" not in events

# Resumable upload must preserve the existing evidence validation/encryption path.
assert "GDO_Evidence::stage_upload" in trust
assert "gdo_is_uploaded_file" in trust
assert "expected_sha256" in trust and "hash_mismatch" in trust
assert "Upload chunks must arrive exactly once and in order" in trust

# Public passport exposes only scope matrix, not raw evidence/profile payload.
passport_fn = trust[trust.index('function verify_passport_uuid'):trust.index('function schedule_reverification')]
for forbidden in ['profile_json','storage_name','source_sha256','review_note']:
    assert forbidden not in passport_fn, f'public passport leaks {forbidden}'

# Secure-room public route is hardened after base registration: evidence must belong to
# the requested application and the mature one-time view grant is reused.
for token in [
    "register_hardened_routes", "'/trust/viewing-room'", 'rest_safe_viewing_room',
    'GDO_Evidence::records( $application_id, true )',
    "GDO_Evidence::issue_view_grant( $evidence_id, $reviewer_id, $purpose, 'view' )",
    "'download_allowed'=>false", "true\n        );"
]:
    assert token in events, f'missing secure-room hardening: {token}'

# Public transparency is fixed-window and cohort-suppressed; arbitrary tiny date
# windows cannot expose applicant-level count differences.
for token in [
    'rest_safe_transparency', '$days = 90;', 'gdo_public_transparency_minimum_cohort',
    "'suppressed'=>true", "'minimum_cohort'=>$minimum"
]:
    assert token in events, f'missing transparency privacy hardening: {token}'

# Release package must contain both new runtime files.
assert 'includes/class-gdo-advanced-trust.php' in release
assert 'includes/class-gdo-advanced-trust-events.php' in release
print('Advanced Trust 24 static contract: PASS')
