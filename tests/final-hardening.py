from pathlib import Path
import sys
root = Path(__file__).resolve().parents[1]
def fail(message):
    print('FAIL:', message, file=sys.stderr)
    raise SystemExit(1)
def text(path):
    return (root / path).read_text(encoding='utf-8')

app = text('includes/class-gdo-application.php')
if "empty( $eligibility['eligible'] ) && ! $renewed_from_id" in app:
    fail('renewal eligibility bypass remains')
if "if ( empty( $eligibility['eligible'] ) )" not in app:
    fail('current membership eligibility is not mandatory for draft/renewal creation')

schema = text('includes/class-gdo-schema.php')
apps = schema[schema.index("dbDelta( \"CREATE TABLE {$apps}"):schema.index("dbDelta( \"CREATE TABLE {$evidence}")]
if 'user_id bigint(20) unsigned NULL' not in apps:
    fail('application erasure subject link is not nullable')

claims = text('includes/class-gdo-claims.php')
for token in ['gdo_claim_state_mismatch', 'gdo_claim_snapshot_missing', "sanitize_key( $app->state ) !== $state"]:
    if token not in claims:
        fail('claim state/snapshot hardening missing: ' + token)

migration = text('includes/class-gdo-migration.php')
if 'LIMIT 5000' in migration:
    fail('migration still truncates backfill at 5000 rows')
for token in ['gdo_legacy_migration_user_checkpoint', 'LIMIT 500', 'LIMIT 25', 'LIMIT 100', 'doctor_legacy_source_cleanup_pending']:
    if token not in migration:
        fail('bounded/resumable migration control missing: ' + token)
store = migration[migration.index('private static function store_legacy_evidence'):]
if store.rindex("$wpdb->query( 'COMMIT' )") > store.rindex('@unlink( $source )'):
    fail('legacy source can be deleted before new evidence commit')

privacy = text('includes/class-gdo-privacy.php')
erase = privacy[privacy.index('public function erase'):privacy.index('public function policy')]
for token in ['legal_hold=0', 'GDO_Evidence::records( $app->id, false )', "'user_id'=>null", "GDO_Claims::issue( $current->id, 'revoked', array(), false )"]:
    if token not in erase:
        fail('privacy erasure hardening missing: ' + token)
if 'event_type<>' not in erase or 'doctor_professional_claim' not in erase:
    fail('privacy erasure can redact a pending professional revocation claim')
if 'OFFSET' in erase:
    fail('mutating privacy erasure still uses offset pagination and can skip records')

retention = text('includes/class-gdo-retention.php')
for token in ['doctor_verification_retention_anonymized', "'profile_json'=>'{}'", "'source_sha256'=>'', 'ciphertext_sha256'=>'', 'content_hmac'=>''"]:
    if token not in retention:
        fail('retention anonymization hardening missing: ' + token)

print('File 09 final hardening invariants passed.')
