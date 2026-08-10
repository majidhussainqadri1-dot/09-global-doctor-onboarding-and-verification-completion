from pathlib import Path
root=Path(__file__).resolve().parents[1]
def read(p): return (root/p).read_text(encoding='utf-8')

main=read('global-doctor-onboarding.php')
trust=read('includes/class-gdo-advanced-trust.php')
hard=read('includes/class-gdo-advanced-trust-hardening.php')
privacy=read('includes/class-gdo-privacy.php')
retention=read('includes/class-gdo-retention.php')
release=read('RELEASE-FILES.txt')

# RC6 Advanced Trust version truth lives in the hardening owner class.
assert "const SCHEMA_VERSION = 2;" in hard
assert "const CONTRACT_VERSION = '1.1.0';" in hard
for path in ['class-gdo-advanced-trust.php','class-gdo-advanced-trust-hardening.php','class-gdo-advanced-trust-events.php']:
    assert path in main

# Canonical Advanced Trust data/lifecycle surfaces remain present.
for token in ['credential_checks','trusted_issuers','jurisdiction_rules','monitor_state','reviewer_conflicts','verification_passports','professional_history','upload_sessions']:
    assert token in trust or token in hard
assert 'maybe_upgrade_schema' in hard
assert "get_option( 'gdo_advanced_trust_schema'" in hard

# Provider assistance is typed, bounded, and human-final.
for method in ['primary_source_verify','authenticity_assessment','equivalency_assessment','verify_affiliation','translation_assistance','ai_assistance']:
    assert ('function ' + method) in trust
for token in ['gdo_primary_source_verification','gdo_credential_equivalency_assessment','gdo_institutional_affiliation_verification','gdo_credential_translation_assistance','gdo_ai_evidence_assistance']:
    assert token in trust
for token in ['provider_unavailable','manual_review_required','human_final_decision_required','automated_decision_forbidden']:
    assert token in trust
assert 'safe_external_reference' in trust
assert 'sanitize_provider_array' in trust

# Jurisdiction, risk, conflict, dual-review, routing and calibration controls.
for method in ['save_jurisdiction_rule','jurisdiction_rule','fraud_ring_scan','risk_explanation','declare_conflict','resolve_conflict','has_conflict','requires_dual_review','smart_reviewer_candidates','reviewer_calibration']:
    assert ('function ' + method) in trust
assert 'opaque_rejection_forbidden' in trust
assert 'human_review_required' in trust
assert 'gdo_reviewer_conflict_detected' in trust
assert 'gdo_requires_dual_review' in trust

# Applicant command centre and public verification matrix remain owner projections.
for token in ['command_center_shortcode','verification_matrix','verified_until','public_verified']:
    assert token in trust or token in hard

# Resumable secure upload is owned by the base Advanced Trust class; schema hardening adds migration safeguards.
for method in ['create_upload_session','append_upload_chunk','finalize_upload_session']:
    assert ('function ' + method) in trust
for token in ['expected_chunks','expected_bytes','received_bytes','received_chunks','GDO_Evidence::stage_upload']:
    assert token in trust
assert '.chunk-' in retention

# Secure evidence viewing reuses the one-time File 09 grant and forbids download.
assert 'GDO_Evidence::issue_view_grant' in trust
assert "'download_allowed'=>false" in trust

# Public transparency is aggregate/cohort protected.
assert 'PUBLIC_WINDOW_DAYS' in trust
assert 'gdo_public_transparency_minimum_cohort' in trust
assert "'suppressed'=>true" in trust

# Privacy/retention cover Advanced Trust derivatives.
assert 'privacy_export_rows' in privacy
assert 'GDO_Advanced_Trust_Hardening::privacy_erase_application' in privacy
privacy_erase = hard[hard.index('public static function privacy_erase_application'):hard.index('private static function rest_value')]
assert 'verification_passports' in privacy_erase
assert ('checked_delete' in privacy_erase or '$wpdb->delete' in privacy_erase)
assert 'gdo_privacy_operational_cleanup' in privacy_erase
assert 'retire_advanced_trust_for_application' in retention

# REST object authorization is exact-case scoped; individual provider failures are structured.
assert 'reviewer_case_allows' in hard
assert "'ok'=>false" in hard
assert "register_rest_route( GDO_Advanced_Trust::REST_NAMESPACE, '/trust/check/" in hard

# Release package must include every runtime layer.
for path in ['includes/class-gdo-advanced-trust.php','includes/class-gdo-advanced-trust-hardening.php','includes/class-gdo-advanced-trust-events.php']:
    assert path in release, f'missing release runtime: {path}'
print('Advanced Trust 24 + RC6 hardening static contract: PASS')
