<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Migration {
	const LOCK_OPTION = 'gdo_schema_migration_lock';

	public static function maybe_run() {
		global $wpdb;
		$current = absint( get_option( 'gdo_schema_version', 0 ) );
		if ( $current > GDO_SCHEMA_VERSION ) {
			return new WP_Error( 'gdo_schema_future_version', __( 'The File 09 database schema is newer than this plugin and cannot be mutated safely.', 'global-doctor-onboarding' ) );
		}
		if ( $current === GDO_SCHEMA_VERSION ) {
			return GDO_Schema::verify_installation();
		}
		$token = wp_generate_uuid4();
		$new_lock = array( 'token'=>$token, 'started_at'=>time() );
		if ( ! add_option( self::LOCK_OPTION, $new_lock, '', false ) ) {
			$lock = (array) get_option( self::LOCK_OPTION, array() );
			if ( empty( $lock['started_at'] ) || absint( $lock['started_at'] ) > time() - 15 * MINUTE_IN_SECONDS ) {
				return new WP_Error( 'gdo_migration_locked', __( 'Another File 09 schema migration is already running.', 'global-doctor-onboarding' ) );
			}

			// Stale takeover is an atomic compare-and-swap. Never delete the
			// option after a stale read because another worker may already have
			// replaced it with a fresh lock in that interval.
			$wpdb->last_error = '';
			$swapped = $wpdb->update(
				$wpdb->options,
				array( 'option_value'=>maybe_serialize( $new_lock ) ),
				array( 'option_name'=>self::LOCK_OPTION, 'option_value'=>maybe_serialize( $lock ) ),
				array( '%s' ),
				array( '%s','%s' )
			);
			if ( 1 !== $swapped || ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'gdo_migration_locked', __( 'File 09 could not acquire the stale migration lock safely.', 'global-doctor-onboarding' ) );
			}
			wp_cache_delete( self::LOCK_OPTION, 'options' );
		}
		try {
			$schema = GDO_Schema::install();
			if ( is_wp_error( $schema ) ) { throw new RuntimeException( $schema->get_error_code() ); }
			self::backfill_current_records( $current );
			self::quarantine_legacy();
			if ( ! update_option( 'gdo_schema_version', GDO_SCHEMA_VERSION, false ) && absint( get_option( 'gdo_schema_version', 0 ) ) !== GDO_SCHEMA_VERSION ) {
				throw new RuntimeException( 'File 09 schema version could not be persisted.' );
			}
			$migration_evidence = array( 'from'=>$current, 'to'=>GDO_SCHEMA_VERSION, 'completed_at'=>gmdate( 'c' ) );
			if ( ! update_option( 'gdo_last_migration', $migration_evidence, false ) && $migration_evidence !== (array) get_option( 'gdo_last_migration', array() ) ) {
				throw new RuntimeException( 'File 09 last-migration evidence could not be persisted.' );
			}
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
		$writes = array(
			"UPDATE {$table} SET application_type='homeopathic_doctor' WHERE application_type='' OR application_type IS NULL",
			$wpdb->prepare( "UPDATE {$table} SET preferred_language=%s WHERE preferred_language='' OR preferred_language IS NULL", 'en-US' ),
			$wpdb->prepare( "UPDATE {$table} SET policy_version=%s WHERE policy_version='' OR policy_version IS NULL", GDO_Policy::VERSION ),
			$wpdb->prepare( "UPDATE {$table} SET draft_expires_at=%s WHERE state='draft' AND draft_expires_at IS NULL", GDO_Policy::draft_expiry() ),
		);
		foreach ( $writes as $sql ) {
			if ( false === $wpdb->query( $sql ) ) { throw new RuntimeException( 'File 09 application backfill write failed.' ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$last_id = 0;
		do {
			$wpdb->last_error = '';
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id,profile_json,identity_fingerprint,terms_version,consent_version FROM {$table} WHERE id>%d ORDER BY id ASC LIMIT 500",
				$last_id
			) );
			if ( null === $rows || ! empty( $wpdb->last_error ) ) {
				throw new RuntimeException( 'File 09 application backfill query failed.' );
			}
			if ( ! $rows ) {
				break;
			}
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
				$changed = $wpdb->update( $table, $data, array( 'id'=>absint( $row->id ) ), $formats, array( '%d' ) );
				if ( false === $changed ) {
					throw new RuntimeException( 'File 09 application backfill update failed.' );
				}
				$last_id = absint( $row->id );
			}
		} while ( 500 === count( $rows ) );
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
		$wpdb->last_error = '';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );
		if ( ! empty( $wpdb->last_error ) ) {
			throw new RuntimeException( 'Legacy File 09 table inventory could not be verified safely.' );
		}
		if ( $exists !== $legacy ) {
			delete_option( 'gdo_legacy_migration_user_checkpoint' );
			return;
		}
		$uploads = wp_upload_dir();
		$checkpoint = absint( get_option( 'gdo_legacy_migration_user_checkpoint', 0 ) );
		$migrated_users = 0;
		do {
			$wpdb->last_error = '';
			$users = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$legacy} WHERE user_id>%d ORDER BY user_id ASC LIMIT 25",
				$checkpoint
			) );
			if ( null === $users || ! empty( $wpdb->last_error ) ) {
				throw new RuntimeException( 'Legacy File 09 user migration query failed.' );
			}
			if ( ! $users ) {
				break;
			}
			foreach ( $users as $user_id ) {
				$user_id = absint( $user_id );
				$wpdb->last_error = '';
				$app = GDO_Application::latest_for_user( $user_id );
				if ( null === $app && ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Legacy File 09 application inventory could not be read safely.' ); }
				if ( ! $app ) {
					$profile = self::legacy_profile( $user_id );
					$now = current_time( 'mysql', true );
					$data = array(
						'application_uuid'=>wp_generate_uuid4(), 'user_id'=>$user_id, 'version'=>1,
						'application_type'=>'homeopathic_doctor', 'jurisdiction'=>'', 'preferred_language'=>get_user_locale( $user_id ),
						'state'=>'legacy_review_required', 'row_version'=>1,
						'profile_json'=>wp_json_encode( $profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
						'profile_fingerprint'=>GDO_Application::fingerprint( $profile ), 'identity_fingerprint'=>GDO_Risk::identity_fingerprint( $profile ),
						'policy_version'=>GDO_Policy::VERSION, 'terms_version'=>'',
						'retention_until'=>gmdate( 'Y-m-d H:i:s', time() + 365 * DAY_IN_SECONDS ),
						'created_at'=>$now, 'updated_at'=>$now,
					);
					$formats = array( '%s','%d','%d','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s' );
					if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
						throw new RuntimeException( 'Legacy File 09 application quarantine transaction could not start.' );
					}
					$inserted = $wpdb->insert( GDO_Schema::table( 'applications' ), $data, $formats );
					if ( 1 !== $inserted ) {
						$wpdb->query( 'ROLLBACK' );
						throw new RuntimeException( 'Legacy File 09 application quarantine insert failed.' );
					}
					$wpdb->last_error = '';
					$app = GDO_Application::get( $wpdb->insert_id );
					if ( ! empty( $wpdb->last_error ) || ! $app ) {
						$wpdb->query( 'ROLLBACK' );
						throw new RuntimeException( 'Legacy File 09 quarantine application could not be reloaded.' );
					}
					$audit = GDO_Audit::transition( $app->id, 0, 'legacy', 'legacy_review_required', 'legacy_quarantine', 'Legacy File 09 data requires independent re-review and credential migration.' );
					if ( is_wp_error( $audit ) ) { $wpdb->query( 'ROLLBACK' ); throw new RuntimeException( $audit->get_error_message() ); }
					if ( false === $wpdb->query( 'COMMIT' ) ) {
						$wpdb->last_error = '';
						$committed_app = $wpdb->get_row( $wpdb->prepare(
							'SELECT id,user_id,version,state FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE application_uuid=%s LIMIT 1',
							$data['application_uuid']
						) );
						$app_read_error = ! empty( $wpdb->last_error );
						$wpdb->last_error = '';
						$committed_hash = $wpdb->get_var( $wpdb->prepare(
							'SELECT event_hash FROM ' . GDO_Schema::table( 'transitions' ) . ' WHERE application_id=%d AND trace_id=%s LIMIT 1',
							$app->id, $audit['trace_id']
						) );
						$audit_read_error = ! empty( $wpdb->last_error );
						if ( $app_read_error || $audit_read_error ) {
							throw new RuntimeException( 'Legacy File 09 quarantine commit outcome is uncertain and requires reconciliation.' );
						}
						$committed = $committed_app
							&& absint( $committed_app->id ) === absint( $app->id )
							&& absint( $committed_app->user_id ) === $user_id
							&& 1 === absint( $committed_app->version )
							&& 'legacy_review_required' === sanitize_key( $committed_app->state )
							&& is_string( $committed_hash )
							&& hash_equals( (string) $audit['event_hash'], $committed_hash );
						if ( ! $committed ) {
							$wpdb->query( 'ROLLBACK' );
							throw new RuntimeException( 'Legacy File 09 quarantine application and audit were not committed atomically.' );
						}
						GDO_Membership_Adapter::audit( 'doctor_legacy_quarantine_commit_reconciled', array( 'application_id'=>$app->id, 'user_id'=>$user_id, 'trace_id'=>$audit['trace_id'] ) );
					}
					GDO_Audit::publish_transition( $audit );
				}
				$last_document_id = 0;
				do {
					$wpdb->last_error = '';
					$rows = $wpdb->get_results( $wpdb->prepare(
						"SELECT * FROM {$legacy} WHERE user_id=%d AND id>%d ORDER BY id ASC LIMIT 100",
						$user_id, $last_document_id
					) );
					if ( null === $rows || ! empty( $wpdb->last_error ) ) {
						throw new RuntimeException( 'Legacy File 09 credential migration query failed.' );
					}
					foreach ( $rows as $row ) {
						$type = sanitize_key( $row->document_type );
						$last_document_id = absint( $row->id );
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
				} while ( 100 === count( $rows ) );
				$checkpoint = $user_id;
				if ( ! update_option( 'gdo_legacy_migration_user_checkpoint', $checkpoint, false ) && absint( get_option( 'gdo_legacy_migration_user_checkpoint', 0 ) ) !== $checkpoint ) {
					throw new RuntimeException( 'Legacy File 09 migration checkpoint could not be persisted.' );
				}
				++$migrated_users;
			}
		} while ( 25 === count( $users ) );
		delete_option( 'gdo_legacy_migration_user_checkpoint' );
		GDO_Membership_Adapter::audit( 'doctor_verification_legacy_quarantined', array( 'users'=>$migrated_users ) );
	}

	private static function store_legacy_evidence( $app, $row, $type, $plain, $source ) {
		global $wpdb;
		$source_sha256 = hash( 'sha256', $plain );
		$wpdb->last_error = '';
		$existing = absint( $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE application_id=%d AND document_type=%s AND source_sha256=%s AND deleted_at IS NULL LIMIT 1',
			absint( $app->id ), $type, $source_sha256
		) ) );
		if ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Legacy credential duplicate state could not be read safely.' ); }
		if ( $existing ) {
			if ( is_file( $source ) && ( ! @unlink( $source ) || is_file( $source ) ) ) {
				GDO_Membership_Adapter::audit( 'doctor_legacy_source_cleanup_pending', array( 'application_id'=>absint( $app->id ), 'legacy_document_id'=>absint( $row->id ) ) );
				throw new RuntimeException( 'Legacy credential source cleanup is still pending.' );
			}
			return true;
		}
		$wpdb->last_error = '';
		$version_raw = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE application_id=%d AND document_type=%s', $app->id, $type ) );
		if ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Legacy credential version state could not be read safely.' ); }
		$version = absint( $version_raw ) + 1;
		$meta = array( 'application_uuid'=>$app->application_uuid, 'application_version'=>$app->version, 'user_id'=>$app->user_id, 'document_type'=>$type, 'document_version'=>$version );
		$encrypted = GDO_Crypto::encrypt( $plain, $meta );
		if ( is_wp_error( $encrypted ) ) {
			throw new RuntimeException( 'Legacy credential encryption failed: ' . $encrypted->get_error_code() );
		}
		$storage = wp_generate_uuid4() . '.gdo2';
		$stored = GDO_Storage::atomic_write( $storage, $encrypted['bytes'] );
		if ( is_wp_error( $stored ) ) {
			throw new RuntimeException( 'Legacy credential private storage failed: ' . $stored->get_error_code() );
		}
		$now = current_time( 'mysql', true );
		$data = array(
			'application_id'=>$app->id, 'user_id'=>$app->user_id, 'document_type'=>$type, 'purpose_code'=>'legacy_migration_review',
			'version'=>$version, 'status'=>'legacy_quarantine', 'original_name'=>sanitize_file_name( $row->original_name ),
			'mime_type'=>sanitize_text_field( $row->mime_type ), 'file_size'=>strlen( $plain ), 'source_sha256'=>$source_sha256,
			'storage_name'=>$storage, 'ciphertext_sha256'=>$stored['sha256'], 'content_hmac'=>$encrypted['content_hmac'],
			'key_id'=>$encrypted['key_id'], 'envelope_version'=>'GDO2', 'malware_status'=>'migration_scan_required',
			'retention_state'=>'active', 'created_at'=>$now, 'updated_at'=>$now,
		);
		$formats = array( '%d','%d','%s','%s','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			GDO_Storage::delete_verified( $storage, $stored['sha256'] );
			throw new RuntimeException( 'Legacy credential transaction could not be started.' );
		}
		$inserted = $wpdb->insert( GDO_Schema::table( 'evidence' ), $data, $formats );
		$stored_hash = hash_file( 'sha256', GDO_Storage::path( $storage ) );
		if ( 1 !== $inserted || ! is_string( $stored_hash ) || 64 !== strlen( $stored_hash ) || ! hash_equals( (string) $stored['sha256'], $stored_hash ) ) {
			$wpdb->query( 'ROLLBACK' );
			GDO_Storage::delete_verified( $storage, $stored['sha256'] );
			throw new RuntimeException( 'Legacy credential database/hash commit preparation failed.' );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			// COMMIT failure is outcome-ambiguous. Preserve both the newly written
			// private object and the legacy source so the next migration run can
			// reconcile by source digest instead of risking an orphaned DB row.
			throw new RuntimeException( 'Legacy credential commit outcome is uncertain and requires safe retry/reconciliation.' );
		}
		if ( ! @unlink( $source ) || is_file( $source ) ) {
			GDO_Membership_Adapter::audit( 'doctor_legacy_source_cleanup_pending', array( 'application_id'=>absint( $app->id ), 'legacy_document_id'=>absint( $row->id ), 'evidence_id'=>absint( $wpdb->insert_id ) ) );
			throw new RuntimeException( 'Legacy credential migrated but source cleanup is pending; checkpoint advancement was stopped.' );
		}
		GDO_Membership_Adapter::audit( 'doctor_legacy_credential_migrated', array( 'application_id'=>absint( $app->id ), 'legacy_document_id'=>absint( $row->id ), 'source_digest'=>$source_sha256 ) );
		return true;
	}

}
