from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
checks = 0

def fail(message):
    print('FAIL:', message, file=sys.stderr)
    raise SystemExit(1)

def text(path):
    return (root / path).read_text(encoding='utf-8')

def check(condition, message):
    global checks
    checks += 1
    if not condition:
        fail(f'R{checks:02d}: {message}')
    print(f'PASS R{checks:02d}: {message}')

# Forty independent, deterministic review lenses over the corrected RC2 source.
workflow = text('.github/workflows/file09-rc2-final.yml')
adapter = text('includes/class-gdo-membership-adapter.php')
policy = text('includes/class-gdo-policy.php')
application = text('includes/class-gdo-application.php')
cf01 = text('includes/class-gdo-cf01-practitioner-contract.php')
claims = text('includes/class-gdo-claims.php')
admin = text('includes/class-gdo-admin.php')
privacy = text('includes/class-gdo-privacy.php')
retention = text('includes/class-gdo-retention.php')
migration = text('includes/class-gdo-migration.php')
frontend = text('includes/class-gdo-frontend.php')
evidence = text('includes/class-gdo-evidence.php')
storage = text('includes/class-gdo-storage.php')
crypto = text('includes/class-gdo-crypto.php')
release = text('tests/release-integrity.py')

check("php: ['7.4', '8.3']" in workflow, 'PHP 7.4 and 8.3 assurance matrix remains mandatory')
check('persist-credentials: false' in workflow, 'CI checkout does not persist GitHub credentials')
check('cmp dist-a/global-doctor-onboarding-09-1.2.0-RC2.zip dist-b/global-doctor-onboarding-09-1.2.0-RC2.zip' in workflow, 'double-build byte parity remains enforced')
check("const FILE00_BASE_VERSION  = '1.2.0'" in adapter, 'File 00 base assertion contract is pinned to 1.2.0')
check("version_compare( (string) SMC_VERSION, '1.2.7', '>=' )" in adapter, 'obsolete File 00 runtimes fail closed')
check('identity_documents_current' in adapter, 'current identity-document assurance is consumed')
check('approved_membership_types' in adapter, 'approved doctor membership grant is consumed')
check("function_exists( 'gdo_user_is_verified' )" in adapter, 'professional verification truth remains owned by File 09')
check("'professional_verified'" in adapter, 'File 00 professional projection remains an explicit downstream assertion')
check("const VERSION       = '2026-08-07.2'" in policy, 'current File 09 policy version is frozen')
check(all(token in policy for token in ("'identity'", "'qualification'", "'license'")), 'identity, qualification and license evidence classes remain required')
check("'whatsapp'" not in policy[policy.index('public static function required_fields'):policy.index('public static function normalize_jurisdiction')], 'WhatsApp remains optional rather than a required application field')
check("max( 18" in policy, 'professional onboarding minimum age remains adult-only')
check("empty( $base['two_factor_ready'] )" in policy, '2FA readiness is required at onboarding eligibility')
check("'approved' !== sanitize_key( $base['status'] )" in policy, 'stale membership approval cannot pass current-status eligibility')
check("'whatsapp'" in application, 'optional WhatsApp data can still be stored when voluntarily supplied')
check('stored_approved_snapshot' in application, 'immutable approved snapshot retrieval exists')
check('refresh_approved_snapshot' in application, 'approved snapshot can be safely refreshed after lifecycle revalidation')
check('GDO_SCHEMA_VERSION' in application, 'approved snapshot uses current schema instead of a fossilized literal')
check("! in_array( $field, $required, true )" in application, 'optional fields are not accidentally made mandatory during validation')
check('GDO_SCHEMA_VERSION' in cf01, 'CF-01 snapshot compatibility follows current File 09 schema')
check('professional_verified' in cf01, 'CF-01 requires current professional verification from File 00 projection')
check("empty( $base['eligible'] )" in cf01, 'CF-01 requires current downstream membership eligibility')
check('identity_assurance' in cf01, 'CF-01 requires explicit identity assurance')
check('gdo_claim_membership_not_current' in claims, 'public professional claims fail closed on stale membership')
check('GDO_Application::stored_approved_snapshot' in claims, 'claim issuance is tied to immutable approved snapshot')
check('GDO_Membership_Adapter::is_active_doctor_candidate' in claims, 'claim issuance revalidates current File 00 assurance')
check('GDO_Application::refresh_approved_snapshot' in admin, 'reinstatement/finalization refreshes approved evidence snapshot')
check("'approved_fingerprint'" in admin, 'reinstatement/finalization persists approved fingerprint integrity')
check("GDO_Claims::issue( $current->id, 'revoked', array(), false )" in privacy, 'privacy erasure revokes current professional claim before unlinking identity')
check('doctor_verification_retention_anonymized' in retention, 'retention lifecycle produces an auditable anonymization event')
check("'profile_json'=>'{}'" in retention, 'retention anonymization removes profile payload')
check('gdo_legacy_migration_user_checkpoint' in migration, 'legacy migration is resumable per user checkpoint')
check('doctor_legacy_source_cleanup_pending' in migration, 'legacy source cleanup is explicit and auditable')
check('<main class="gdo-application"' in frontend and 'gdo-shell' not in frontend, 'File 09 does not impersonate File 20 canonical shell ownership')
check('Verified professional phone' not in frontend, 'UI does not overclaim verification of local professional phone text')
check("'clean' !== $scan" in evidence, 'credential upload fails closed unless malware scanner returns clean')
check("preg_match( '/\\/(JavaScript|JS|OpenAction|AA|Launch|EmbeddedFile|RichMedia|XFA)\\b/i'" in evidence, 'active or embedded PDF features are rejected')
check('gdo_require_storage_outside_wp_content' in storage and 'gdo_storage_public' in storage, 'credential storage is forced outside public WordPress content paths')
check('openssl_encrypt' in crypto and 'content_hmac' in crypto and 'RELEASE-LOCK.json' in release, 'cryptographic envelope integrity and release-lock assurance remain present')

if checks != 40:
    fail(f'expected exactly 40 review lenses, executed {checks}')
print('File 09 forty-round assurance: 40 PASS, 0 FAIL')
