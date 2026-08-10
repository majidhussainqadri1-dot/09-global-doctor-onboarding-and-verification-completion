<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Retention {
	public function hooks() {
		add_action( 'gdo_daily_retention', array( $this, 'run' ) );
		add_action( 'gdo_notification_outbox', array( $this, 'process_outbox' ) );
	}

	public function process_outbox() {
		GDO_Notifications::process( 50 );
	}

	public function run() {
		global $wpdb;
		GDO_Rate_Limiter::cleanup();
		GDO_Notifications::process( 50 );
		$now = current_time( 'mysql', true );
		$apps_table = GDO_Schema::table( 'applications' );
		$evidence_table = GDO_Schema::table( 'evidence' );

		$this->expire_drafts( $now, $apps_table );
		$this->open_renewals( $now, $apps_table );
		$this->expire_verifications( $now, $apps_table );
		$this->reconcile_pending_claims( $now, $apps_table );
		$this->rotate_keys( $evidence_table );
		$this->delete_superseded( $now, $evidence_table );
		$this->apply_retention( $now, $apps_table );
		$this->cleanup_access( $now );
		if ( class_exists( 'GDO_Advanced_Trust' ) ) {
			GDO_Advanced_Trust::cleanup_upload_sessions();
		}
		self::cleanup_orphans();
		GDO_Membership_Adapter::audit( 'doctor_verification_retention_completed', array( 'completed_at'=>$now ) );
		do_action( 'gdo_retention_completed', $now );
	}

	private function expire_drafts( $now, $apps_table ) {
		global $wpdb;
		$warn_at = gmdate( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS );
		$warning_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,draft_expires_at FROM {$apps_table} WHERE state='draft' AND draft_expires_at>%s AND draft_expires_at<=%s LIMIT 100",
			$now, $warn_at
		) );
		foreach ( $warning_rows as $app ) {
			$dedupe = hash( 'sha256', 'draft-warning|' . absint( $app->id ) . '|' . substr( $app->draft_expires_at, 0, 10 ) );
			$exists = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE event_type=%s AND payload_json LIKE %s LIMIT 1', 'doctor_application_draft_expiring', '%' . $wpdb->esc_like( $dedupe ) . '%' ) ) );
			if ( ! $exists ) {
				GDO_Notifications::queue( 'doctor_application_draft_expiring', $app->user_id, array( 'application_id'=>absint( $app->id ), 'expires_at'=>gmdate( 'c', strtotime( $app->draft_expires_at . ' UTC' ) ), 'dedupe_digest'=>$dedupe ), false );
			}
		}
		$expired = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,row_version FROM {$apps_table} WHERE state='draft' AND draft_expires_at IS NOT NULL AND draft_expires_at<%s LIMIT 100",
			$now
		) );
		foreach ( $expired as $app ) {
			GDO_State::transition( $app->id, 'withdrawn', 0, 'application_withdrawn', 'Private application draft expired under the configured draft-retention policy.', $app->row_version );
		}
	}

	private function open_renewals( $now, $apps_table ) {
		global $wpdb;
		$window = gmdate( 'Y-m-d H:i:s', time() + absint( apply_filters( 'gdo_renewal_window_days', 45 ) ) * DAY_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,row_version,verified_until FROM {$apps_table} WHERE state IN ('verified','reinstated') AND verified_until IS NOT NULL AND verified_until>%s AND verified_until<=%s LIMIT 100",
			$now, $window
		) );
		foreach ( $rows as $app ) {
			$wpdb->query( 'START TRANSACTION' );
			$result = GDO_State::transition( $app->id, 'renewal_due', 0, 'verification_lifecycle', 'Verification entered the configured renewal window.', $app->row_version, false );
			$event = is_wp_error( $result ) ? $result : GDO_Notifications::queue( 'doctor_verification_renewal_due', $app->user_id, array( 'application_id'=>absint( $app->id ), 'verified_until'=>$app->verified_until ), false );
			$claim = is_wp_error( $event ) ? $event : GDO_Claims::issue( $app->id, 'renewal_due', array(), false );
			if ( is_wp_error( $result ) || is_wp_error( $event ) || is_wp_error( $claim ) || false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
			}
		}
	}

	private function expire_verifications( $now, $apps_table ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,row_version FROM {$apps_table} WHERE state IN ('verified','reinstated','renewal_due') AND verified_until IS NOT NULL AND verified_until<%s LIMIT 100",
			$now
		) );
		foreach ( $rows as $app ) {
			$wpdb->query( 'START TRANSACTION' );
			$result = GDO_State::transition( $app->id, 'expired', 0, 'verification_expired', 'Verification validity period ended.', $app->row_version, false );
			$event = is_wp_error( $result ) ? $result : GDO_Notifications::queue( 'doctor_verification_expired', $app->user_id, array( 'application_id'=>absint( $app->id ) ), false );
			$claim = is_wp_error( $event ) ? $event : GDO_Claims::issue( $app->id, 'expired', array(), false );
			if ( is_wp_error( $result ) || is_wp_error( $event ) || is_wp_error( $claim ) || false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				continue;
			}
			do_action( 'gdo_verification_decision_changed', $app->user_id, 'expired', $app->id, array() );
		}
	}

	private function reconcile_pending_claims( $now, $apps_table ) {
		global $wpdb;
		$stale = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,state,claim_version FROM {$apps_table} WHERE claim_status IN ('pending','failed') AND updated_at<%s ORDER BY updated_at ASC LIMIT 50",
			$stale
		) );
		foreach ( $rows as $app ) {
			$pattern = '%"application_id":' . absint( $app->id ) . ',%';
			$event = $wpdb->get_row( $wpdb->prepare(
				"SELECT id,status,event_uuid FROM " . GDO_Schema::table( 'outbox' ) . " WHERE event_type='doctor_professional_claim' AND payload_json LIKE %s ORDER BY id DESC LIMIT 1",
				$pattern
			) );
			if ( $event && in_array( $event->status, array( 'pending','failed' ), true ) ) {
				GDO_Notifications::process( 1, $event->event_uuid );
				continue;
			}
			if ( $event && 'dead' === $event->status ) {
				GDO_Membership_Adapter::audit( 'doctor_professional_claim_requires_operator_replay', array( 'application_id'=>absint( $app->id ), 'claim_version'=>absint( $app->claim_version ), 'outbox_id'=>absint( $event->id ) ) );
				continue;
			}
			if ( ! $event ) {
				GDO_Claims::issue( $app->id, $app->state );
			}
		}
	}

	private function rotate_keys( $evidence_table ) {
		global $wpdb;
		$ring = GDO_Crypto::keyring();
		if ( is_wp_error( $ring ) ) {
			return;
		}
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$evidence_table} WHERE envelope_version='GDO2' AND key_id<>%s AND deleted_at IS NULL LIMIT 25",
			$ring['active']
		) );
		foreach ( $ids as $evidence_id ) {
			GDO_Evidence::rotate_key( $evidence_id );
		}
	}

	private function delete_superseded( $now, $evidence_table ) {
		global $wpdb;
		$before = gmdate( 'Y-m-d H:i:s', time() - absint( apply_filters( 'gdo_superseded_evidence_grace_days', 30 ) ) * DAY_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$evidence_table} WHERE retention_state='superseded' AND deleted_at IS NULL AND updated_at<%s LIMIT 100",
			$before
		) );
		foreach ( $rows as $record ) {
			self::delete_record( $record, 'superseded_deleted', $now );
		}
	}

	private function apply_retention( $now, $apps_table ) {
		global $wpdb;
		$apps = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$apps_table} WHERE legal_hold=0 AND retention_until IS NOT NULL AND retention_until<%s LIMIT 100",
			$now
		) );
		foreach ( $apps as $app ) {
			if ( in_array( $app->state, array( 'verified','reinstated','under_review','recommended','appeal_pending','submitted','resubmitted','renewal_due' ), true ) ) {
				continue;
			}
			$failed = false;
			foreach ( GDO_Evidence::records( $app->id, false ) as $record ) {
				if ( empty( $record->deleted_at ) && ! self::delete_record( $record, 'retention_deleted', $now ) ) {
					$failed = true;
				}
			}
			if ( $failed ) {
				continue;
			}
			if ( class_exists( 'GDO_Advanced_Trust' ) ) {
				$advanced = GDO_Advanced_Trust::retention_anonymize_application( $app->id, absint( $app->user_id ) );
				if ( is_wp_error( $advanced ) ) {
					GDO_Membership_Adapter::audit( 'doctor_advanced_trust_retention_failed', array( 'application_id'=>absint( $app->id ), 'error'=>$advanced->get_error_code() ) );
					continue;
				}
			}
			$anonymous = hash( 'sha256', 'retained|' . $app->application_uuid . '|' . wp_salt( 'nonce' ) );
			$wpdb->update( $apps_table, array(
				'user_id'=>null, 'profile_json'=>'{}', 'profile_fingerprint'=>$anonymous, 'identity_fingerprint'=>'',
				'approved_snapshot_json'=>null, 'approved_fingerprint'=>null, 'submission_hash'=>null,
				'assigned_reviewer_id'=>null, 'recommender_id'=>null, 'finalizer_id'=>null,
				'recommendation_reason'=>'anonymized', 'claim_last_error'=>null, 'updated_at'=>$now,
			), array( 'id'=>absint( $app->id ) ) );
			$wpdb->update( GDO_Schema::table( 'consents' ), array( 'user_id'=>0, 'purpose'=>'retained-accountability-record', 'retention_notice'=>'anonymized', 'withdrawn_at'=>$now ), array( 'application_id'=>$app->id ) );
			$wpdb->update( GDO_Schema::table( 'evidence' ), array( 'user_id'=>0 ), array( 'application_id'=>$app->id ) );
			$wpdb->update( GDO_Schema::table( 'appeals' ), array( 'user_id'=>0, 'reason'=>'anonymized', 'evidence_json'=>null, 'resolution'=>'anonymized' ), array( 'application_id'=>$app->id ) );
			$wpdb->update( GDO_Schema::table( 'risk_signals' ), array( 'related_digest'=>null, 'resolution_reason'=>'anonymized' ), array( 'application_id'=>$app->id ) );
			$wpdb->update( GDO_Schema::table( 'quality_samples' ), array( 'reason'=>'anonymized' ), array( 'application_id'=>$app->id ) );
			$wpdb->delete( GDO_Schema::table( 'access_grants' ), array( 'application_id'=>$app->id ) );
			GDO_Membership_Adapter::audit( 'doctor_verification_retention_anonymized', array( 'application_id'=>absint( $app->id ) ) );
		}
	}

	private function cleanup_access( $now ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . GDO_Schema::table( 'access_grants' ) . ' WHERE expires_at<%s OR used_at IS NOT NULL',
			$now
		) );
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . GDO_Schema::table( 'rate_limits' ) . ' WHERE expires_at<%d',
			time()
		) );
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . GDO_Schema::table( 'access_log' ) . " SET reviewer_id=0,purpose_code='anonymized' WHERE created_at<%s",
			gmdate( 'Y-m-d H:i:s', time() - absint( apply_filters( 'gdo_access_log_identifiable_days', 365 ) ) * DAY_IN_SECONDS )
		) );
	}

	private static function delete_record( $record, $state, $now ) {
		global $wpdb;
		$proof = GDO_Storage::delete_verified( $record->storage_name, $record->ciphertext_sha256 );
		if ( is_wp_error( $proof ) ) {
			GDO_Membership_Adapter::audit( 'doctor_credential_retention_delete_failed', array( 'application_id'=>absint( $record->application_id ), 'evidence_id'=>absint( $record->id ), 'error'=>$proof->get_error_code() ) );
			return false;
		}
		$updated = $wpdb->update(
			GDO_Schema::table( 'evidence' ),
			array( 'user_id'=>0, 'retention_state'=>$state, 'deletion_proof'=>$proof, 'deleted_at'=>$now, 'storage_name'=>'deleted-' . absint( $record->id ), 'original_name'=>'erased', 'source_sha256'=>'', 'ciphertext_sha256'=>'', 'content_hmac'=>'', 'key_id'=>'', 'scan_reference'=>null, 'checklist_json'=>null, 'findings_json'=>null, 'review_note'=>null, 'registry_source'=>null, 'updated_at'=>$now ),
			array( 'id'=>absint( $record->id ) )
		);
		return false !== $updated;
	}

	private static function cleanup_orphans() {
		global $wpdb;
		$health = GDO_Storage::health();
		if ( is_wp_error( $health ) ) {
			return;
		}
		$dir = GDO_Storage::directory();
		$known = $wpdb->get_col( 'SELECT storage_name FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE deleted_at IS NULL' );
		$known = array_fill_keys( array_map( 'strval', $known ), true );
		$cutoff = time() - absint( apply_filters( 'gdo_orphan_grace_hours', 24 ) ) * HOUR_IN_SECONDS;
		foreach ( new DirectoryIterator( $dir ) as $file ) {
			if ( $file->isDot() || ! $file->isFile() || $file->isLink() ) {
				continue;
			}
			$name = $file->getFilename();
			if ( isset( $known[ $name ] ) || $file->getMTime() >= $cutoff ) {
				continue;
			}
			if ( 0 === strpos( $name, '.tmp-' ) || 0 === strpos( $name, '.chunk-' ) || preg_match( '/\.(?:gdo1|gdo2)$/i', $name ) ) {
				if ( @unlink( $file->getPathname() ) ) {
					GDO_Membership_Adapter::audit( 'doctor_credential_orphan_removed', array( 'storage_digest'=>hash( 'sha256', $name ) ) );
				} else {
					GDO_Membership_Adapter::audit( 'doctor_credential_orphan_remove_failed', array( 'storage_digest'=>hash( 'sha256', $name ) ) );
				}
			}
		}
	}
}
