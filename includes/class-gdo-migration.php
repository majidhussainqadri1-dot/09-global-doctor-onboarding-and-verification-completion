<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Migration {
	const LOCK_OPTION = 'gdo_schema_migration_lock';

	public static function maybe_run() {
		$current = absint( get_option( 'gdo_schema_version', 0 ) );
		if ( $current >= GDO_SCHEMA_VERSION ) {
			return true;
		}
		$token = wp_generate_uuid4();
		if ( ! add_option( self::LOCK_OPTION, array( 'token'=>$token, 'started_at'=>time() ), '', false ) ) {
			$lock = (array) get_option( self::LOCK_OPTION, array() );
			if ( empty( $lock['started_at'] ) || absint( $lock['started_at'] ) > time() - 15 * MINUTE_IN_SECONDS ) {
				return new WP_Error( 'gdo_migration_locked', __( 'Another File 09 schema migration is already running.', 'global-doctor-onboarding' ) );
			}
			delete_option( self::LOCK_OPTION );
			if ( ! add_option( self::LOCK_OPTION, array( 'token'=>$token, 'started_at'=>time() ), '', false ) ) {
				return new WP_Error( 'gdo_migration_locked', __( 'File 09 could not acquire its migration lock.', 'global-doctor-onboarding' ) );
			}
		}
		try {
			GDO_Schema::install();
			self::backfill_current_records( $current );
			self::quarantine_legacy();
			update_option( 'gdo_schema_version', GDO_SCHEMA_VERSION, false );
			update_option( 'gdo_last_migration', array( 'from'=>$current, 'to'=>GDO_SCHEMA_VERSION, 'completed_at'=>gmdate( 'c' ) ), false );
			GDO_Membership_Adapter::audit( 'doctor_verification_schema_migrated', array( 'from'=>$current, 'to'=>GDO_SCHEMA_VERSION ) );
			return true;
		} catch ( Throwable $e ) {
			GDO_Membership_Adapter::audit( 'doctor_verification_schema_migration_failed', array( 'from'=>$current, 'to'=>GDO_SCHEMA_VERSION, 'error'=>sanitize_text_field( $e->getMessage() ) ) );
			return new WP_Error( 'gdo_migration_failed', __( 'File 09 schema migration failed and requires rollback or repair.', 'global-doctor-onboarding' ) );
		} finally {
			$lock = (array) get_option( self::LOCK_OPTION, array() );
			if ( isset( $lock['token'] ) && hash_equals( $token, (string) $lock['token'] ) ) {
				delete_option( self::LOCK_OPTION );
			}
		}
	}

	private static function backfill_current_records( $from_version ) {
		global $wpdb;
		$table = GDO_Schema::table( 'applications' );
		$now = current_time( 'mysql', true );
		$wpdb->query( "UPDATE {$table} SET application_type='homeopathic_doctor' WHERE application_type='' OR application_type IS NULL" );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET preferred_language=%s WHERE preferred_language='' OR preferred_language IS NULL", 'en-US' ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET policy_version=%s WHERE policy_version='' OR policy_version IS NULL", GDO_Policy::VERSION ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET draft_expires_at=%s WHERE state='draft' AND draft_expires_at IS NULL", GDO_Policy::draft_expiry() ) );
		$rows = $wpdb->get_results( "SELECT id,profile_json,identity_fingerprint,terms_version,consent_version FROM {$table} ORDER BY id ASC LIMIT 5000" );
		foreach ( $rows as $row ) {
			$profile = json_decode( $row->profile_json, true );
			$profile = is_array( $profile ) ? GDO_Application::sanitize_profile( $profile ) : array();
			$data = array( 'updated_at'=>$now );
			$formats = array( '%s' );
			if ( empty( $row->identity_fingerprint ) ) {
				$data['identity_fingerprint'] = GDO_Risk::identity_fingerprint( $profile );
				$formats[] = '%s';
			}
			if ( empty( $row->terms_version ) && ! empty( $row->consent_version ) ) {
				$data['terms_version'] = GDO_Policy::TERMS_VERSION;
				$formats[] = '%s';
			}
			$wpdb->update( $table, $data, array( 'id'=>absint( $row->id ) ), $formats, array( '%d' ) );
		}
		if ( $from_version < 6 ) {
			do_action( 'gdo_file00_legacy_capability_cleanup_required', 'manage_global_doctor_verification' );
		}
	}

	private static function legacy_profile( $user_id ) {
		$profile = array_fill_keys( GDO_Application::fields(), '' );
		$user = get_userdata( $user_id );
		$profile['display_name'] = $user ? sanitize_text_field( $user->display_name ) : '';
		foreach ( GDO_Application::fields() as $field ) {
			if ( 'display_name' === $field || 0 === strpos( $field, 'declaration_' ) ) {
				continue;
			}
			$legacy_field = 'license_number' === $field ? 'licence_number' : $field;
			$value = get_user_meta( $user_id, '_spd_' . $legacy_field, true );
			if ( '' === (string) $value && 'license_number' === $field ) {
				$value = get_user_meta( $user_id, '_spd_license_number', true );
			}
			$profile[ $field ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}
		return $profile;
	}

	private static function decrypt_legacy( $envelope ) {
		if ( 0 !== strpos( $envelope, 'GDO1' ) || strlen( $envelope ) < 33 ) {
			return new WP_Error( 'gdo_legacy_envelope', __( 'Legacy credential envelope is invalid.', 'global-doctor-onboarding' ) );
		}
		$key = hash( 'sha256', wp_salt( 'auth' ) . '|gdo-credentials', true );
		$plain = openssl_decrypt( substr( $envelope, 32 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $envelope, 4, 12 ), substr( $envelope, 16, 16 ) );
		return false === $plain ? new WP_Error( 'gdo_legacy_decrypt', __( 'Legacy credential decryption failed. Keep the original WordPress salts and restore from backup.', 'global-doctor-onboarding' ) ) : $plain;
	}

	private static function quarantine_legacy() {
		global $wpdb;
		$legacy = $wpdb->prefix . 'gdo_documents';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );
		if ( $exists !== $legacy ) {
			return;
		}
		$users = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$legacy}" );
		$uploads = wp_upload_dir();
		foreach ( $users as $user_id ) {
			$app = GDO_Application::latest_for_user( $user_id );
			if ( ! $app ) {
				$profile = self::legacy_profile( $user_id );
				$now = current_time( 'mysql', true );
				$data = array(
					'application_uuid'=>wp_generate_uuid4(), 'user_id'=>absint( $user_id ), 'version'=>1,
					'application_type'=>'homeopathic_doctor', 'jurisdiction'=>'', 'preferred_language'=>get_user_locale( $user_id ),
					'state'=>'legacy_review_required', 'row_version'=>1,
					'profile_json'=>wp_json_encode( $profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
					'profile_fingerprint'=>GDO_Application::fingerprint( $profile ), 'identity_fingerprint'=>GDO_Risk::identity_fingerprint( $profile ),
					'policy_version'=>GDO_Policy::VERSION, 'terms_version'=>'',
					'retention_until'=>gmdate( 'Y-m-d H:i:s', time() + 365 * DAY_IN_SECONDS ),
					'created_at'=>$now, 'updated_at'=>$now,
				);
				$formats = array( '%s','%d','%d','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s' );
				$inserted = $wpdb->insert( GDO_Schema::table( 'applications' ), $data, $formats );
				if ( 1 !== $inserted ) {
					continue;
				}
				$app = GDO_Application::get( $wpdb->insert_id );
				if ( ! $app ) {
					continue;
				}
				GDO_Audit::transition( $app->id, 0, 'legacy', 'legacy_review_required', 'legacy_quarantine', 'Legacy File 09 data requires independent re-review and credential migration.' );
			}
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$legacy} WHERE user_id=%d ORDER BY id ASC", $user_id ) );
			foreach ( $rows as $row ) {
				$type = sanitize_key( $row->document_type );
				if ( ! isset( GDO_Evidence::types()[ $type ] ) ) {
					continue;
				}
				$source = trailingslashit( $uploads['basedir'] ) . 'gdo-secure/' . basename( $row->storage_name );
				if ( ! is_file( $source ) || is_link( $source ) ) {
					continue;
				}
				$envelope = file_get_contents( $source );
				$plain = false === $envelope ? new WP_Error( 'gdo_legacy_read', 'Legacy file could not be read.' ) : self::decrypt_legacy( $envelope );
				if ( is_wp_error( $plain ) ) {
					GDO_Membership_Adapter::audit( 'doctor_legacy_credential_decrypt_failed', array( 'application_id'=>$app->id, 'legacy_document_id'=>absint( $row->id ), 'error'=>$plain->get_error_code() ) );
					continue;
				}
				self::store_legacy_evidence( $app, $row, $type, $plain, $source );
			}
		}
		GDO_Membership_Adapter::audit( 'doctor_verification_legacy_quarantined', array( 'users'=>count( $users ) ) );
	}

	private static function store_legacy_evidence( $app, $row, $type, $plain, $source ) {
		global $wpdb;
		$version = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE application_id=%d AND document_type=%s', $app->id, $type ) ) ) + 1;
		$meta = array( 'application_uuid'=>$app->application_uuid, 'application_version'=>$app->version, 'user_id'=>$app->user_id, 'document_type'=>$type, 'document_version'=>$version );
		$encrypted = GDO_Crypto::encrypt( $plain, $meta );
		if ( is_wp_error( $encrypted ) ) {
			return;
		}
		$storage = wp_generate_uuid4() . '.gdo2';
		$stored = GDO_Storage::atomic_write( $storage, $encrypted['bytes'] );
		if ( is_wp_error( $stored ) ) {
			return;
		}
		$now = current_time( 'mysql', true );
		$data = array(
			'application_id'=>$app->id, 'user_id'=>$app->user_id, 'document_type'=>$type, 'purpose_code'=>'legacy_migration_review',
			'version'=>$version, 'status'=>'legacy_quarantine', 'original_name'=>sanitize_file_name( $row->original_name ),
			'mime_type'=>sanitize_text_field( $row->mime_type ), 'file_size'=>strlen( $plain ), 'source_sha256'=>hash( 'sha256', $plain ),
			'storage_name'=>$storage, 'ciphertext_sha256'=>$stored['sha256'], 'content_hmac'=>$encrypted['content_hmac'],
			'key_id'=>$encrypted['key_id'], 'envelope_version'=>'GDO2', 'malware_status'=>'migration_scan_required',
			'retention_state'=>'active', 'created_at'=>$now, 'updated_at'=>$now,
		);
		$formats = array( '%d','%d','%s','%s','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' );
		$wpdb->query( 'START TRANSACTION' );
		$inserted = $wpdb->insert( GDO_Schema::table( 'evidence' ), $data, $formats );
		if ( 1 !== $inserted || ! hash_equals( $stored['sha256'], hash_file( 'sha256', GDO_Storage::path( $storage ) ) ) || ! @unlink( $source ) || is_file( $source ) ) {
			$wpdb->query( 'ROLLBACK' );
			GDO_Storage::delete_verified( $storage, $stored['sha256'] );
			return;
		}
		$wpdb->query( 'COMMIT' );
	}
}
