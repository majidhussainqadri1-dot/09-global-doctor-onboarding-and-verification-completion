from pathlib import Path

root = Path(__file__).resolve().parents[1]


def read(path):
    return (root / path).read_text(encoding="utf-8")


def write(path, value):
    (root / path).write_text(value, encoding="utf-8")


def replace_once(path, old, new):
    value = read(path)
    count = value.count(old)
    if count != 1:
        raise SystemExit(f"{path}: expected one replacement, found {count}: {old[:90]!r}")
    write(path, value.replace(old, new, 1))


# R3-01 — A renewal/expired application must never bypass current File 00 eligibility.
replace_once(
    "includes/class-gdo-application.php",
    "\t\tif ( empty( $eligibility['eligible'] ) && ! $renewed_from_id ) {",
    "\t\tif ( empty( $eligibility['eligible'] ) ) {",
)

# R3-02 — Privacy/retention anonymization needs a nullable subject link so the
# unique (user_id,version) key cannot collide on a shared sentinel user ID.
replace_once(
    "includes/class-gdo-schema.php",
    "\t\t\tuser_id bigint(20) unsigned NOT NULL,\n\t\t\tversion int(10) unsigned NOT NULL DEFAULT 1,",
    "\t\t\tuser_id bigint(20) unsigned NULL,\n\t\t\tversion int(10) unsigned NOT NULL DEFAULT 1,",
)

# R3-03 — Claims are facts about the locked current application state, never a
# caller-supplied alternate state. Public-verified claims also require the
# immutable approved snapshot/fingerprint.
claims_path = "includes/class-gdo-claims.php"
claims = read(claims_path)
claim_anchor = "\t\tif ( ! $app ) {\n\t\t\tif ( $manage_transaction ) {\n\t\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\t}\n\t\t\treturn new WP_Error( 'gdo_claim_application_missing', __( 'The application is unavailable.', 'global-doctor-onboarding' ) );\n\t\t}\n"
claim_insert = claim_anchor + "\t\tif ( sanitize_key( $app->state ) !== $state ) {\n\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n\t\t\treturn new WP_Error( 'gdo_claim_state_mismatch', __( 'The professional claim must match the current locked application state.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\tif ( GDO_State::public_verified( $state ) && ( empty( $app->approved_snapshot_json ) || empty( $app->approved_fingerprint ) ) ) {\n\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n\t\t\treturn new WP_Error( 'gdo_claim_snapshot_missing', __( 'A verified professional claim requires an immutable approved snapshot.', 'global-doctor-onboarding' ) );\n\t\t}\n"
if claims.count(claim_anchor) != 1:
    raise SystemExit("claims: application lock anchor mismatch")
write(claims_path, claims.replace(claim_anchor, claim_insert, 1))

# R3-04/R3-05 — Migration backfill must cover every row in bounded batches;
# legacy migration is resumable and never destroys the old credential before
# the new database record commits.
migration_path = "includes/class-gdo-migration.php"
migration = read(migration_path)
start = migration.index("\t\t$rows = $wpdb->get_results( \"SELECT id,profile_json,identity_fingerprint,terms_version,consent_version FROM {$table} ORDER BY id ASC LIMIT 5000\" );")
end = migration.index("\t\tif ( $from_version < 6 ) {", start)
backfill = """\t\t$last_id = 0;
\t\tdo {
\t\t\t$rows = $wpdb->get_results( $wpdb->prepare(
\t\t\t\t\"SELECT id,profile_json,identity_fingerprint,terms_version,consent_version FROM {$table} WHERE id>%d ORDER BY id ASC LIMIT 500\",
\t\t\t\t$last_id
\t\t\t) );
\t\t\tif ( null === $rows ) {
\t\t\t\tthrow new RuntimeException( 'File 09 application backfill query failed.' );
\t\t\t}
\t\t\tif ( ! $rows ) {
\t\t\t\tbreak;
\t\t\t}
\t\t\tforeach ( $rows as $row ) {
\t\t\t\t$profile = json_decode( $row->profile_json, true );
\t\t\t\t$profile = is_array( $profile ) ? GDO_Application::sanitize_profile( $profile ) : array();
\t\t\t\t$data = array( 'updated_at'=>$now );
\t\t\t\t$formats = array( '%s' );
\t\t\t\tif ( empty( $row->identity_fingerprint ) ) {
\t\t\t\t\t$data['identity_fingerprint'] = GDO_Risk::identity_fingerprint( $profile );
\t\t\t\t\t$formats[] = '%s';
\t\t\t\t}
\t\t\t\tif ( empty( $row->terms_version ) && ! empty( $row->consent_version ) ) {
\t\t\t\t\t$data['terms_version'] = GDO_Policy::TERMS_VERSION;
\t\t\t\t\t$formats[] = '%s';
\t\t\t\t}
\t\t\t\t$changed = $wpdb->update( $table, $data, array( 'id'=>absint( $row->id ) ), $formats, array( '%d' ) );
\t\t\t\tif ( false === $changed ) {
\t\t\t\t\tthrow new RuntimeException( 'File 09 application backfill update failed.' );
\t\t\t\t}
\t\t\t\t$last_id = absint( $row->id );
\t\t\t}
\t\t} while ( 500 === count( $rows ) );
"""
migration = migration[:start] + backfill + migration[end:]

q_start = migration.index("\tprivate static function quarantine_legacy() {")
q_end = migration.index("\n\tprivate static function store_legacy_evidence", q_start)
quarantine = """\tprivate static function quarantine_legacy() {
\t\tglobal $wpdb;
\t\t$legacy = $wpdb->prefix . 'gdo_documents';
\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );
\t\tif ( $exists !== $legacy ) {
\t\t\tdelete_option( 'gdo_legacy_migration_user_checkpoint' );
\t\t\treturn;
\t\t}
\t\t$uploads = wp_upload_dir();
\t\t$checkpoint = absint( get_option( 'gdo_legacy_migration_user_checkpoint', 0 ) );
\t\t$migrated_users = 0;
\t\tdo {
\t\t\t$users = $wpdb->get_col( $wpdb->prepare(
\t\t\t\t\"SELECT DISTINCT user_id FROM {$legacy} WHERE user_id>%d ORDER BY user_id ASC LIMIT 25\",
\t\t\t\t$checkpoint
\t\t\t) );
\t\t\tif ( null === $users ) {
\t\t\t\tthrow new RuntimeException( 'Legacy File 09 user migration query failed.' );
\t\t\t}
\t\t\tif ( ! $users ) {
\t\t\t\tbreak;
\t\t\t}
\t\t\tforeach ( $users as $user_id ) {
\t\t\t\t$user_id = absint( $user_id );
\t\t\t\t$app = GDO_Application::latest_for_user( $user_id );
\t\t\t\tif ( ! $app ) {
\t\t\t\t\t$profile = self::legacy_profile( $user_id );
\t\t\t\t\t$now = current_time( 'mysql', true );
\t\t\t\t\t$data = array(
\t\t\t\t\t\t'application_uuid'=>wp_generate_uuid4(), 'user_id'=>$user_id, 'version'=>1,
\t\t\t\t\t\t'application_type'=>'homeopathic_doctor', 'jurisdiction'=>'', 'preferred_language'=>get_user_locale( $user_id ),
\t\t\t\t\t\t'state'=>'legacy_review_required', 'row_version'=>1,
\t\t\t\t\t\t'profile_json'=>wp_json_encode( $profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
\t\t\t\t\t\t'profile_fingerprint'=>GDO_Application::fingerprint( $profile ), 'identity_fingerprint'=>GDO_Risk::identity_fingerprint( $profile ),
\t\t\t\t\t\t'policy_version'=>GDO_Policy::VERSION, 'terms_version'=>'',
\t\t\t\t\t\t'retention_until'=>gmdate( 'Y-m-d H:i:s', time() + 365 * DAY_IN_SECONDS ),
\t\t\t\t\t\t'created_at'=>$now, 'updated_at'=>$now,
\t\t\t\t\t);
\t\t\t\t\t$formats = array( '%s','%d','%d','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s' );
\t\t\t\t\t$inserted = $wpdb->insert( GDO_Schema::table( 'applications' ), $data, $formats );
\t\t\t\t\tif ( 1 !== $inserted ) {
\t\t\t\t\t\tthrow new RuntimeException( 'Legacy File 09 application quarantine insert failed.' );
\t\t\t\t\t}
\t\t\t\t\t$app = GDO_Application::get( $wpdb->insert_id );
\t\t\t\t\tif ( ! $app ) {
\t\t\t\t\t\tthrow new RuntimeException( 'Legacy File 09 quarantine application could not be reloaded.' );
\t\t\t\t\t}
\t\t\t\t\tGDO_Audit::transition( $app->id, 0, 'legacy', 'legacy_review_required', 'legacy_quarantine', 'Legacy File 09 data requires independent re-review and credential migration.' );
\t\t\t\t}
\t\t\t\t$last_document_id = 0;
\t\t\t\tdo {
\t\t\t\t\t$rows = $wpdb->get_results( $wpdb->prepare(
\t\t\t\t\t\t\"SELECT * FROM {$legacy} WHERE user_id=%d AND id>%d ORDER BY id ASC LIMIT 100\",
\t\t\t\t\t\t$user_id, $last_document_id
\t\t\t\t\t) );
\t\t\t\t\tif ( null === $rows ) {
\t\t\t\t\t\tthrow new RuntimeException( 'Legacy File 09 credential migration query failed.' );
\t\t\t\t\t}
\t\t\t\t\tforeach ( $rows as $row ) {
\t\t\t\t\t\t$type = sanitize_key( $row->document_type );
\t\t\t\t\t\t$last_document_id = absint( $row->id );
\t\t\t\t\t\tif ( ! isset( GDO_Evidence::types()[ $type ] ) ) {
\t\t\t\t\t\t\tcontinue;
\t\t\t\t\t\t}
\t\t\t\t\t\t$source = trailingslashit( $uploads['basedir'] ) . 'gdo-secure/' . basename( $row->storage_name );
\t\t\t\t\t\tif ( ! is_file( $source ) || is_link( $source ) ) {
\t\t\t\t\t\t\tcontinue;
\t\t\t\t\t\t}
\t\t\t\t\t\t$envelope = file_get_contents( $source );
\t\t\t\t\t\t$plain = false === $envelope ? new WP_Error( 'gdo_legacy_read', 'Legacy file could not be read.' ) : self::decrypt_legacy( $envelope );
\t\t\t\t\t\tif ( is_wp_error( $plain ) ) {
\t\t\t\t\t\t\tGDO_Membership_Adapter::audit( 'doctor_legacy_credential_decrypt_failed', array( 'application_id'=>$app->id, 'legacy_document_id'=>absint( $row->id ), 'error'=>$plain->get_error_code() ) );
\t\t\t\t\t\t\tcontinue;
\t\t\t\t\t\t}
\t\t\t\t\t\tself::store_legacy_evidence( $app, $row, $type, $plain, $source );
\t\t\t\t\t}
\t\t\t\t} while ( 100 === count( $rows ) );
\t\t\t\t$checkpoint = $user_id;
\t\t\t\tupdate_option( 'gdo_legacy_migration_user_checkpoint', $checkpoint, false );
\t\t\t\t++$migrated_users;
\t\t\t}
\t\t} while ( 25 === count( $users ) );
\t\tdelete_option( 'gdo_legacy_migration_user_checkpoint' );
\t\tGDO_Membership_Adapter::audit( 'doctor_verification_legacy_quarantined', array( 'users'=>$migrated_users ) );
\t}
"""
migration = migration[:q_start] + quarantine + migration[q_end:]

s_start = migration.index("\tprivate static function store_legacy_evidence")
s_end = migration.rfind("\n}")
store = """\tprivate static function store_legacy_evidence( $app, $row, $type, $plain, $source ) {
\t\tglobal $wpdb;
\t\t$source_sha256 = hash( 'sha256', $plain );
\t\t$existing = absint( $wpdb->get_var( $wpdb->prepare(
\t\t\t'SELECT id FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE application_id=%d AND document_type=%s AND source_sha256=%s AND deleted_at IS NULL LIMIT 1',
\t\t\tabsint( $app->id ), $type, $source_sha256
\t\t) ) );
\t\tif ( $existing ) {
\t\t\tif ( is_file( $source ) && ( ! @unlink( $source ) || is_file( $source ) ) ) {
\t\t\t\tGDO_Membership_Adapter::audit( 'doctor_legacy_source_cleanup_pending', array( 'application_id'=>absint( $app->id ), 'legacy_document_id'=>absint( $row->id ) ) );
\t\t\t}
\t\t\treturn;
\t\t}
\t\t$version = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE application_id=%d AND document_type=%s', $app->id, $type ) ) ) + 1;
\t\t$meta = array( 'application_uuid'=>$app->application_uuid, 'application_version'=>$app->version, 'user_id'=>$app->user_id, 'document_type'=>$type, 'document_version'=>$version );
\t\t$encrypted = GDO_Crypto::encrypt( $plain, $meta );
\t\tif ( is_wp_error( $encrypted ) ) {
\t\t\treturn;
\t\t}
\t\t$storage = wp_generate_uuid4() . '.gdo2';
\t\t$stored = GDO_Storage::atomic_write( $storage, $encrypted['bytes'] );
\t\tif ( is_wp_error( $stored ) ) {
\t\t\treturn;
\t\t}
\t\t$now = current_time( 'mysql', true );
\t\t$data = array(
\t\t\t'application_id'=>$app->id, 'user_id'=>$app->user_id, 'document_type'=>$type, 'purpose_code'=>'legacy_migration_review',
\t\t\t'version'=>$version, 'status'=>'legacy_quarantine', 'original_name'=>sanitize_file_name( $row->original_name ),
\t\t\t'mime_type'=>sanitize_text_field( $row->mime_type ), 'file_size'=>strlen( $plain ), 'source_sha256'=>$source_sha256,
\t\t\t'storage_name'=>$storage, 'ciphertext_sha256'=>$stored['sha256'], 'content_hmac'=>$encrypted['content_hmac'],
\t\t\t'key_id'=>$encrypted['key_id'], 'envelope_version'=>'GDO2', 'malware_status'=>'migration_scan_required',
\t\t\t'retention_state'=>'active', 'created_at'=>$now, 'updated_at'=>$now,
\t\t);
\t\t$formats = array( '%d','%d','%s','%s','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' );
\t\t$wpdb->query( 'START TRANSACTION' );
\t\t$inserted = $wpdb->insert( GDO_Schema::table( 'evidence' ), $data, $formats );
\t\tif ( 1 !== $inserted || ! hash_equals( $stored['sha256'], hash_file( 'sha256', GDO_Storage::path( $storage ) ) ) ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tGDO_Storage::delete_verified( $storage, $stored['sha256'] );
\t\t\treturn;
\t\t}
\t\tif ( false === $wpdb->query( 'COMMIT' ) ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tGDO_Storage::delete_verified( $storage, $stored['sha256'] );
\t\t\treturn;
\t\t}
\t\tif ( ! @unlink( $source ) || is_file( $source ) ) {
\t\t\tGDO_Membership_Adapter::audit( 'doctor_legacy_source_cleanup_pending', array( 'application_id'=>absint( $app->id ), 'legacy_document_id'=>absint( $row->id ), 'evidence_id'=>absint( $wpdb->insert_id ) ) );
\t\t} else {
\t\t\tGDO_Membership_Adapter::audit( 'doctor_legacy_credential_migrated', array( 'application_id'=>absint( $app->id ), 'legacy_document_id'=>absint( $row->id ), 'source_digest'=>$source_sha256 ) );
\t\t}
\t}
"""
migration = migration[:s_start] + store + migration[s_end:]
write(migration_path, migration)

# R3-06/R3-07 — Erasure must be atomic with revocation claim propagation,
# must erase every credential version, must not skip rows as pagination mutates
# ownership, and must not collide on (user_id,version).
privacy_path = "includes/class-gdo-privacy.php"
privacy = read(privacy_path)
e_start = privacy.index("\tpublic function erase( $email, $page = 1 ) {")
e_end = privacy.index("\n\tpublic function policy() {", e_start)
erase = """\tpublic function erase( $email, $page = 1 ) {
\t\t$user = get_user_by( 'email', $email );
\t\tif ( ! $user ) {
\t\t\treturn array( 'items_removed'=>false, 'items_retained'=>false, 'messages'=>array(), 'done'=>true );
\t\t}
\t\tglobal $wpdb;
\t\t$limit = 10;
\t\t$apps = $wpdb->get_results( $wpdb->prepare(
\t\t\t'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE user_id=%d AND legal_hold=0 ORDER BY id ASC LIMIT %d',
\t\t\t$user->ID, $limit
\t\t) );
\t\t$held = absint( $wpdb->get_var( $wpdb->prepare(
\t\t\t'SELECT COUNT(*) FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE user_id=%d AND legal_hold=1',
\t\t\t$user->ID
\t\t) ) );
\t\t$removed = false;
\t\t$retained = $held > 0;
\t\t$messages = $held ? array( 'One or more doctor-verification records remain under a documented legal hold.' ) : array();
\t\tforeach ( $apps as $app ) {
\t\t\t$current = GDO_Application::get( $app->id );
\t\t\tif ( $current && GDO_State::public_verified( $current->state ) ) {
\t\t\t\t$wpdb->query( 'START TRANSACTION' );
\t\t\t\t$result = GDO_State::transition( $current->id, 'revoked', 0, 'privacy_erasure', 'Public verification was revoked before personal-data erasure.', $current->row_version, false );
\t\t\t\t$claim = is_wp_error( $result ) ? $result : GDO_Claims::issue( $current->id, 'revoked', array(), false );
\t\t\t\tif ( is_wp_error( $result ) || is_wp_error( $claim ) || false === $wpdb->query( 'COMMIT' ) ) {
\t\t\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t\t\t$retained = true;
\t\t\t\t\t$messages[] = is_wp_error( $result ) ? $result->get_error_message() : ( is_wp_error( $claim ) ? $claim->get_error_message() : 'Erasure is paused until revocation and claim propagation can commit atomically.' );
\t\t\t\t\tcontinue;
\t\t\t\t}
\t\t\t} elseif ( $current && ! in_array( $current->state, array( 'withdrawn','revoked' ), true ) ) {
\t\t\t\tif ( ! GDO_State::can_transition( $current->state, 'withdrawn' ) ) {
\t\t\t\t\t$retained = true;
\t\t\t\t\t$messages[] = 'An application is in a state that must be resolved before erasure.';
\t\t\t\t\tcontinue;
\t\t\t\t}
\t\t\t\t$transition = GDO_State::transition( $current->id, 'withdrawn', 0, 'privacy_erasure', 'Application was withdrawn before personal-data erasure.', $current->row_version );
\t\t\t\tif ( is_wp_error( $transition ) ) {
\t\t\t\t\t$retained = true;
\t\t\t\t\t$messages[] = $transition->get_error_message();
\t\t\t\t\tcontinue;
\t\t\t\t}
\t\t\t}

\t\t\t$deletion_failed = false;
\t\t\tforeach ( GDO_Evidence::records( $app->id, false ) as $record ) {
\t\t\t\tif ( ! empty( $record->deleted_at ) ) {
\t\t\t\t\t$wpdb->update( GDO_Schema::table( 'evidence' ), array( 'user_id'=>0 ), array( 'id'=>absint( $record->id ) ), array( '%d' ), array( '%d' ) );
\t\t\t\t\tcontinue;
\t\t\t\t}
\t\t\t\t$proof = GDO_Storage::delete_verified( $record->storage_name, $record->ciphertext_sha256 );
\t\t\t\tif ( is_wp_error( $proof ) ) {
\t\t\t\t\t$deletion_failed = true;
\t\t\t\t\t$retained = true;
\t\t\t\t\t$messages[] = $proof->get_error_message();
\t\t\t\t\tcontinue;
\t\t\t\t}
\t\t\t\t$updated = $wpdb->update(
\t\t\t\t\tGDO_Schema::table( 'evidence' ),
\t\t\t\t\tarray( 'user_id'=>0, 'retention_state'=>'deleted', 'deletion_proof'=>$proof, 'deleted_at'=>current_time( 'mysql', true ), 'original_name'=>'erased', 'storage_name'=>'deleted-' . absint( $record->id ), 'source_sha256'=>'', 'ciphertext_sha256'=>'', 'content_hmac'=>'', 'key_id'=>'', 'scan_reference'=>null, 'checklist_json'=>null, 'findings_json'=>null, 'review_note'=>null, 'registry_source'=>null, 'updated_at'=>current_time( 'mysql', true ) ),
\t\t\t\t\tarray( 'id'=>absint( $record->id ) ),
\t\t\t\t\tarray( '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ),
\t\t\t\t\tarray( '%d' )
\t\t\t\t);
\t\t\t\tif ( false === $updated ) {
\t\t\t\t\t$deletion_failed = true;
\t\t\t\t\t$retained = true;
\t\t\t\t\t$messages[] = 'A physical credential deletion succeeded but its proof record requires administrator repair.';
\t\t\t\t} else {
\t\t\t\t\t$removed = true;
\t\t\t\t}
\t\t\t}
\t\t\tif ( $deletion_failed ) {
\t\t\t\tcontinue;
\t\t\t}

\t\t\t$anonymous = hash( 'sha256', 'erased|' . $app->application_uuid . '|' . wp_salt( 'nonce' ) );
\t\t\t$updated = $wpdb->update(
\t\t\t\tGDO_Schema::table( 'applications' ),
\t\t\t\tarray(
\t\t\t\t\t'user_id'=>null, 'profile_json'=>'{}', 'profile_fingerprint'=>$anonymous, 'identity_fingerprint'=>'',
\t\t\t\t\t'approved_snapshot_json'=>null, 'approved_fingerprint'=>null, 'submission_hash'=>null,
\t\t\t\t\t'assigned_reviewer_id'=>null, 'recommender_id'=>null, 'finalizer_id'=>null,
\t\t\t\t\t'recommendation_reason'=>'anonymized', 'claim_last_error'=>null, 'updated_at'=>current_time( 'mysql', true ),
\t\t\t\t),
\t\t\t\tarray( 'id'=>absint( $app->id ), 'user_id'=>$user->ID ),
\t\t\t\tarray( '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ),
\t\t\t\tarray( '%d','%d' )
\t\t\t);
\t\t\tif ( 1 !== $updated ) {
\t\t\t\t$retained = true;
\t\t\t\t$messages[] = 'Application anonymization requires administrator repair.';
\t\t\t\tcontinue;
\t\t\t}
\t\t\t$wpdb->update( GDO_Schema::table( 'consents' ), array( 'user_id'=>0, 'purpose'=>'retained-accountability-record', 'retention_notice'=>'anonymized', 'withdrawn_at'=>current_time( 'mysql', true ) ), array( 'application_id'=>$app->id ), array( '%d','%s','%s','%s' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'evidence' ), array( 'user_id'=>0 ), array( 'application_id'=>$app->id ), array( '%d' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'access_log' ), array( 'reviewer_id'=>0, 'purpose_code'=>'anonymized' ), array( 'application_id'=>$app->id, 'reviewer_id'=>$user->ID ), array( '%d','%s' ), array( '%d','%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'appeals' ), array( 'user_id'=>0, 'status'=>'closed', 'reason'=>'anonymized', 'evidence_json'=>null, 'resolution'=>'anonymized', 'decision'=>'withdrawn', 'resolved_at'=>current_time( 'mysql', true ) ), array( 'application_id'=>$app->id ), array( '%d','%s','%s','%s','%s','%s','%s' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'risk_signals' ), array( 'related_digest'=>null, 'resolution_reason'=>'anonymized' ), array( 'application_id'=>$app->id ), array( '%s','%s' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'quality_samples' ), array( 'reason'=>'anonymized' ), array( 'application_id'=>$app->id ), array( '%s' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'transitions' ), array( 'actor_id'=>null ), array( 'application_id'=>$app->id, 'actor_id'=>$user->ID ), array( '%d' ), array( '%d','%d' ) );
\t\t\t$wpdb->delete( GDO_Schema::table( 'access_grants' ), array( 'application_id'=>$app->id ), array( '%d' ) );
\t\t\t$payload_like = '%\"application_id\":' . absint( $app->id ) . '%';
\t\t\t$wpdb->query( $wpdb->prepare(
\t\t\t\t'UPDATE ' . GDO_Schema::table( 'outbox' ) . ' SET recipient_user_id=0,payload_json=%s WHERE recipient_user_id=%d AND payload_json LIKE %s',
\t\t\t\t'{\"redacted\":\"privacy_erasure\"}', $user->ID, $payload_like
\t\t\t) );
\t\t\tdo_action( 'gdo_identity_projection_erased', $user->ID, $app->id );
\t\t\t$removed = true;
\t\t\t$retained = true;
\t\t\t$messages[] = 'Personal credential data was erased; minimal anonymized decision and audit evidence was retained for accountability.';
\t\t}
\t\treturn array( 'items_removed'=>$removed, 'items_retained'=>$retained, 'messages'=>array_values( array_unique( $messages ) ), 'done'=>count( $apps ) < $limit );
\t}
"""
privacy = privacy[:e_start] + erase + privacy[e_end:]
write(privacy_path, privacy)

# R3-08 — Expired retention must scrub subject data, not only unlink bytes.
retention_path = "includes/class-gdo-retention.php"
retention = read(retention_path)
a_start = retention.index("\tprivate function apply_retention( $now, $apps_table ) {")
a_end = retention.index("\n\tprivate function cleanup_access", a_start)
apply_retention = """\tprivate function apply_retention( $now, $apps_table ) {
\t\tglobal $wpdb;
\t\t$apps = $wpdb->get_results( $wpdb->prepare(
\t\t\t\"SELECT * FROM {$apps_table} WHERE legal_hold=0 AND retention_until IS NOT NULL AND retention_until<%s LIMIT 100\",
\t\t\t$now
\t\t) );
\t\tforeach ( $apps as $app ) {
\t\t\tif ( in_array( $app->state, array( 'verified','reinstated','under_review','recommended','appeal_pending','submitted','resubmitted','renewal_due' ), true ) ) {
\t\t\t\tcontinue;
\t\t\t}
\t\t\t$failed = false;
\t\t\tforeach ( GDO_Evidence::records( $app->id, false ) as $record ) {
\t\t\t\tif ( empty( $record->deleted_at ) && ! self::delete_record( $record, 'retention_deleted', $now ) ) {
\t\t\t\t\t$failed = true;
\t\t\t\t}
\t\t\t}
\t\t\tif ( $failed ) {
\t\t\t\tcontinue;
\t\t\t}
\t\t\t$anonymous = hash( 'sha256', 'retained|' . $app->application_uuid . '|' . wp_salt( 'nonce' ) );
\t\t\t$wpdb->update( $apps_table, array(
\t\t\t\t'user_id'=>null, 'profile_json'=>'{}', 'profile_fingerprint'=>$anonymous, 'identity_fingerprint'=>'',
\t\t\t\t'approved_snapshot_json'=>null, 'approved_fingerprint'=>null, 'submission_hash'=>null,
\t\t\t\t'assigned_reviewer_id'=>null, 'recommender_id'=>null, 'finalizer_id'=>null,
\t\t\t\t'recommendation_reason'=>'anonymized', 'claim_last_error'=>null, 'updated_at'=>$now,
\t\t\t), array( 'id'=>absint( $app->id ) ), array( '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'consents' ), array( 'user_id'=>0, 'purpose'=>'retained-accountability-record', 'retention_notice'=>'anonymized', 'withdrawn_at'=>$now ), array( 'application_id'=>$app->id ), array( '%d','%s','%s','%s' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'evidence' ), array( 'user_id'=>0 ), array( 'application_id'=>$app->id ), array( '%d' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'appeals' ), array( 'user_id'=>0, 'reason'=>'anonymized', 'evidence_json'=>null, 'resolution'=>'anonymized' ), array( 'application_id'=>$app->id ), array( '%d','%s','%s','%s' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'risk_signals' ), array( 'related_digest'=>null, 'resolution_reason'=>'anonymized' ), array( 'application_id'=>$app->id ), array( '%s','%s' ), array( '%d' ) );
\t\t\t$wpdb->update( GDO_Schema::table( 'quality_samples' ), array( 'reason'=>'anonymized' ), array( 'application_id'=>$app->id ), array( '%s' ), array( '%d' ) );
\t\t\t$wpdb->delete( GDO_Schema::table( 'access_grants' ), array( 'application_id'=>$app->id ), array( '%d' ) );
\t\t\tGDO_Membership_Adapter::audit( 'doctor_verification_retention_anonymized', array( 'application_id'=>absint( $app->id ) ) );
\t\t}
\t}
"""
retention = retention[:a_start] + apply_retention + retention[a_end:]

d_start = retention.index("\tprivate static function delete_record( $record, $state, $now ) {")
d_end = retention.index("\n\tprivate static function cleanup_orphans", d_start)
delete_record = """\tprivate static function delete_record( $record, $state, $now ) {
\t\tglobal $wpdb;
\t\t$proof = GDO_Storage::delete_verified( $record->storage_name, $record->ciphertext_sha256 );
\t\tif ( is_wp_error( $proof ) ) {
\t\t\tGDO_Membership_Adapter::audit( 'doctor_credential_retention_delete_failed', array( 'application_id'=>absint( $record->application_id ), 'evidence_id'=>absint( $record->id ), 'error'=>$proof->get_error_code() ) );
\t\t\treturn false;
\t\t}
\t\t$updated = $wpdb->update(
\t\t\tGDO_Schema::table( 'evidence' ),
\t\t\tarray( 'user_id'=>0, 'retention_state'=>$state, 'deletion_proof'=>$proof, 'deleted_at'=>$now, 'storage_name'=>'deleted-' . absint( $record->id ), 'original_name'=>'erased', 'source_sha256'=>'', 'ciphertext_sha256'=>'', 'content_hmac'=>'', 'key_id'=>'', 'scan_reference'=>null, 'checklist_json'=>null, 'findings_json'=>null, 'review_note'=>null, 'registry_source'=>null, 'updated_at'=>$now ),
\t\t\tarray( 'id'=>absint( $record->id ) ),
\t\t\tarray( '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ),
\t\t\tarray( '%d' )
\t\t);
\t\treturn false !== $updated;
\t}
"""
retention = retention[:d_start] + delete_record + retention[d_end:]
write(retention_path, retention)

hardening_test = r'''from pathlib import Path
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
if store.index("$wpdb->query( 'COMMIT' )") > store.index('@unlink( $source )'):
    fail('legacy source can be deleted before new evidence commit')

privacy = text('includes/class-gdo-privacy.php')
erase = privacy[privacy.index('public function erase'):privacy.index('public function policy')]
for token in ['legal_hold=0', 'GDO_Evidence::records( $app->id, false )', "'user_id'=>null", "GDO_Claims::issue( $current->id, 'revoked', array(), false )"]:
    if token not in erase:
        fail('privacy erasure hardening missing: ' + token)
if 'OFFSET' in erase:
    fail('mutating privacy erasure still uses offset pagination and can skip records')

retention = text('includes/class-gdo-retention.php')
for token in ['doctor_verification_retention_anonymized', "'profile_json'=>'{}'", "'source_sha256'=>'', 'ciphertext_sha256'=>'', 'content_hmac'=>''"]:
    if token not in retention:
        fail('retention anonymization hardening missing: ' + token)

print('File 09 final hardening invariants passed.')
'''
write("tests/final-hardening.py", hardening_test)

workflow_path = ".github/workflows/file09-rc2-final.yml"
workflow = read(workflow_path)
needle = "          python3 tests/rc2-adversarial.py\n"
if workflow.count(needle) != 1:
    raise SystemExit("final workflow test insertion anchor mismatch")
workflow = workflow.replace(needle, needle + "          python3 tests/final-hardening.py\n", 1)
write(workflow_path, workflow)

release_path = "RELEASE-FILES.txt"
release = read(release_path)
if "REVIEW-ROUND-3-RC2.md\n" not in release:
    release += "REVIEW-ROUND-3-RC2.md\n"
write(release_path, release)

review3 = """# File 09 — RC2 Fresh Adversarial Review Round 3

Date: 2026-08-07

A fresh post-green review found and corrected defects that earlier static suites did not cover:

1. Renewal/expired draft creation could bypass a newly failed File 00 eligibility decision.
2. Privacy erasure used mutating OFFSET pagination and could skip application versions.
3. Privacy anonymization used `user_id=0`, conflicting with the unique `(user_id, version)` key across different erased users.
4. Public-verification erasure could commit revocation locally before the File 00 professional claim was safely queued.
5. Privacy erasure considered only active evidence and could leave superseded credential files.
6. Retention expiry removed credential bytes but did not anonymize the retained subject/profile linkage.
7. Schema backfill stopped after 5,000 rows while still promoting the schema version.
8. Legacy migration lacked bounded row/user checkpoints and could delete the legacy source before the new evidence transaction committed.
9. Professional claim issuance did not independently require the caller-supplied claim state to equal the current locked application state.

Corrections are fail-closed, migration-safe, privacy-minimized and covered by `tests/final-hardening.py`. The canonical PHP 7.4/8.3 exact-head workflow must pass again after this correction. Hostinger staging remains a separate acceptance gate.
"""
write("REVIEW-ROUND-3-RC2.md", review3)

changelog_path = "CHANGELOG.md"
changelog = read(changelog_path)
marker = "## RC2 Fresh Adversarial Hardening — 2026-08-07"
if marker not in changelog:
    changelog += f"""

{marker}

- Closed renewal eligibility bypass on expired/renewal draft creation.
- Made professional claim issuance state-bound and snapshot-bound.
- Made schema backfill exhaustive and legacy migration bounded/resumable.
- Moved legacy-source deletion after successful new-record commit with dedupe-safe cleanup.
- Corrected privacy erasure pagination, all-version credential deletion, atomic revocation claim, and collision-free nullable subject anonymization.
- Corrected retention expiry to scrub subject/profile and credential metadata after physical deletion.
- Added a dedicated final hardening regression gate and third fresh adversarial review record.
"""
write(changelog_path, changelog)
