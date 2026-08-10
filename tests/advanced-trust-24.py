from pathlib import Path
import sys
root=Path(__file__).resolve().parents[1]
def read(p): return (root/p).read_text(encoding='utf-8')

def fail(m): print('FAIL:',m,file=sys.stderr); raise SystemExit(1)

main=read('global-doctor-onboarding.php')
plugin=read('includes/class-gdo-plugin.php')
trust=read('includes/class-gdo-advanced-trust.php')
hard=read('includes/class-gdo-advanced-trust-hardening.php')
events=read('includes/class-gdo-advanced-trust-events.php')
schema=read('includes/class-gdo-schema.php')
privacy=read('includes/class-gdo-privacy.php')
retention=read('includes/class-gdo-retention.php')
release=read('RELEASE-FILES.txt')

# Bootstrap + versions.
for token in ["define( 'GDO_ADVANCED_TRUST_SCHEMA_VERSION', 2 )", "define( 'GDO_ADVANCED_TRUST_CONTRACT_VERSION', '1.1.0' )"]:
    assert token in main
for path in ['class-gdo-advanced-trust.php','class-gdo-advanced-trust-hardening.php','class-gdo-advanced-trust-events.php']:
    assert path in main or path in plugin

# Advanced Trust table registry and lifecycle.
for token in ['credential_checks','issuer_registry','jurisdiction_rules','monitor_state','reviewer_conflicts','verification_passports','professional_history','upload_sessions']:
    assert token in trust or token in hard
assert 'maybe_upgrade_schema' in hard
assert 'GDO_ADVANCED_TRUST_SCHEMA_VERSION' in hard

# Primary source / authenticity / equivalency / translation / AI are provider mediated and non-decisional.
for token in ['gdo_primary_source_provider','gdo_authenticity_provider','gdo_equivalency_provider','gdo_translation_provider','gdo_ai_verification_assistance']:
    assert token in trust, token
for token in ['provider_unavailable','provider_invalid','low_confidence','human_final_decision_required']:
    assert token in trust
assert 'credential_check' in trust
assert 'external_reference' in trust

# Jurisdiction policy is versioned/effective and cannot silently grant authority.
for token in ['save_jurisdiction_rule','effective_from','effective_until','rule_version']:
    assert token in trust
assert 'jurisdiction_rule' in trust

# Risk/fraud is bounded and human-final.
for token in ['risk_explanation','fraud_collusion_signals','false_positive','human_review']:
    assert token in trust
assert 'auto_reject' not in trust

# Reviewer conflict, dual review, routing, calibration.
for token in ['reviewer_conflict','requires_dual_review','reviewer_routing','calibration_summary']:
    assert token in trust or token in hard
assert 'same_reviewer' in trust or 'same_person' in trust

# Applicant command centre.
for token in ['applicant_command_center','verification_matrix','more_info_deadline','verified_until']:
    assert token in trust or token in hard

# Resumable secure upload has ordered chunks, aggregate caps, TTL and final staging via mature evidence path.
for token in ['create_upload_session','append_upload_chunk','finalize_upload','chunk_count','total_bytes','expires_at']:
    assert token in trust
for token in ['expected_chunks','expected_bytes','received_bytes','next_chunk_index']:
    assert token in hard
assert 'GDO_Evidence::stage_upload' in hard
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

# REST object authorization is rechecked at exact reviewer/applicant/application relationship scope and individual check failures are structured.
assert 'reviewer_case_allows' in hard
assert "'ok'=>false" in hard
assert "register_rest_route( GDO_Advanced_Trust::REST_NAMESPACE, '/trust/check/" in hard

# Release package must include every RC6 runtime layer.
for path in ['includes/class-gdo-advanced-trust.php','includes/class-gdo-advanced-trust-hardening.php','includes/class-gdo-advanced-trust-events.php']:
    assert path in release, f'missing release runtime: {path}'
print('Advanced Trust 24 + RC6 hardening static contract: PASS')
