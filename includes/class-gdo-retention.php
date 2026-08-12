<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Retention {
	public function hooks() {
		add_action( 'gdo_daily_retention', array( $this, 'run' ) );
		add_action( 'gdo_notification_outbox', array( $this, 'process_outbox' ) );
	}

	public function process_outbox() {
		return GDO_Notifications::process( 50 );
	}

	public function run() {
		if ( ! GDO_Operations::mutation_allowed() ) {
			$error = new WP_Error( 'gdo_retention_runtime_not_ready', __( 'File 09 retention is paused until dependencies and schemas are healthy.', 'global-doctor-onboarding' ) );
			GDO_Membership_Adapter::audit( 'doctor_verification_retention_failed', array( 'reason'=>$error->get_error_code() ) );
			return $error;
		}
		$rate = GDO_Rate_Limiter::cleanup();
		if ( is_wp_error( $rate ) ) { return self::retention_failure( $rate ); }
		$outbox = GDO_Notifications::process( 50 );
		if ( is_wp_error( $outbox ) ) { return self::retention_failure( $outbox ); }
		$now = current_time( 'mysql', true );
		$apps_table = GDO_Schema::table( 'applications' );
		$evidence_table = GDO_Schema::table( 'evidence' );
		$steps = array(
			array( $this, 'expire_drafts', array( $now, $apps_table ) ),
			array( $this, 'open_renewals', array( $now, $apps_table ) ),
			array( $this, 'expire_verifications', array( $now, $apps_table ) ),
			array( $this, 'reconcile_pending_claims', array( $now, $apps_table ) ),
			array( $this, 'rotate_keys', array( $evidence_table ) ),
			array( $this, 'delete_superseded', array( $now, $evidence_table ) ),
			array( $this, 'apply_retention', array( $now, $apps_table ) ),
			array( $this, 'cleanup_access', array( $now ) ),
		);
		foreach ( $steps as $step ) {
			$result = call_user_func_array( array( $step[0], $step[1] ), $step[2] );
			if ( is_wp_error( $result ) ) { return self::retention_failure( $result ); }
		}
		if ( class_exists( 'GDO_Advanced_Trust_Hardening' ) ) {
			$advanced = GDO_Advanced_Trust_Hardening::cleanup_upload_sessions();
			if ( is_wp_error( $advanced ) ) { return self::retention_failure( $advanced ); }
		}
		$orphans = self::cleanup_orphans();
		if ( false === $orphans ) { return self::retention_failure( new WP_Error( 'gdo_retention_orphan_cleanup', __( 'Credential orphan cleanup could not be verified safely.', 'global-doctor-onboarding' ) ) ); }
		GDO_Membership_Adapter::audit( 'doctor_verification_retention_completed', array( 'completed_at'=>$now ) );
		do_action( 'gdo_retention_completed', $now );
		return true;
	}

	private static function retention_failure( $error ) {
		$error = is_wp_error( $error ) ? $error : new WP_Error( 'gdo_retention_failed', __( 'File 09 retention could not complete safely.', 'global-doctor-onboarding' ) );
		GDO_Membership_Adapter::audit( 'doctor_verification_retention_failed', array( 'reason'=>$error->get_error_code() ) );
		return $error;
	}

	private function commit_lifecycle_or_reconcile( $operation, $application_id, $target_state, $notice_event, $claim ) {
		global $wpdb;
		if ( false !== $wpdb->query( 'COMMIT' ) ) { return true; }
		$wpdb->query( 'ROLLBACK' );
		$wpdb->last_error = '';
		$current = $wpdb->get_row( $wpdb->prepare( 'SELECT state,claim_version FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d LIMIT 1', absint( $application_id ) ) );
		$app_error = ! empty( $wpdb->last_error );
		$wpdb->last_error = '';
		$notice_id = $notice_event ? absint( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE event_uuid=%s LIMIT 1', (string) $notice_event ) ) ) : 0;
		$notice_error = ! empty( $wpdb->last_error );
		$claim_event = is_array( $claim ) && ! empty( $claim['event_id'] ) ? (string) $claim['event_id'] : '';
		$wpdb->last_error = '';
		$claim_outbox_id = $claim_event ? absint( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE event_uuid=%s LIMIT 1', $claim_event ) ) ) : 0;
		$claim_error = ! empty( $wpdb->last_error );
		if ( $app_error || $notice_error || $claim_error ) {
			return new WP_Error( 'gdo_retention_' . sanitize_key( $operation ) . '_commit_uncertain', __( 'Retention lifecycle commit outcome is uncertain and requires reconciliation.', 'global-doctor-onboarding' ) );
		}
		$committed = $current && sanitize_key( $current->state ) === sanitize_key( $target_state )
			&& is_array( $claim ) && absint( $current->claim_version ) === absint( $claim['claim_version'] )
			&& $notice_id > 0 && $claim_outbox_id > 0;
		if ( ! $committed ) {
			return new WP_Error( 'gdo_retention_' . sanitize_key( $operation ) . '_commit', __( 'Retention lifecycle changes could not be committed safely.', 'global-doctor-onboarding' ) );
		}
		GDO_Membership_Adapter::audit( 'doctor_retention_lifecycle_commit_reconciled', array( 'application_id'=>absint($application_id), 'operation'=>sanitize_key($operation), 'state'=>sanitize_key($target_state) ) );
		return true;
	}

	private function expire_drafts( $now, $apps_table ) {
		global $wpdb;
		$warn_at = gmdate( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS );
		$wpdb->last_error = '';
		$warning_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,draft_expires_at FROM {$apps_table} WHERE state='draft' AND draft_expires_at>%s AND draft_expires_at<=%s LIMIT 100",
			$now, $warn_at
		) );
		if ( null === $warning_rows || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_draft_warning_query', __( 'Draft expiry warnings could not be read safely.', 'global-doctor-onboarding' ) ); }
		foreach ( $warning_rows as $app ) {
			$dedupe = hash( 'sha256', 'draft-warning|' . absint( $app->id ) . '|' . substr( $app->draft_expires_at, 0, 10 ) );
			$wpdb->last_error = '';
			$exists_raw = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE event_type=%s AND payload_json LIKE %s LIMIT 1', 'doctor_application_draft_expiring', '%' . $wpdb->esc_like( $dedupe ) . '%' ) );
			if ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_draft_warning_dedupe', __( 'Draft expiry notification state could not be verified safely.', 'global-doctor-onboarding' ) ); }
			if ( ! absint( $exists_raw ) ) {
				$queued = GDO_Notifications::queue( 'doctor_application_draft_expiring', $app->user_id, array( 'application_id'=>absint( $app->id ), 'expires_at'=>gmdate( 'c', strtotime( $app->draft_expires_at . ' UTC' ) ), 'dedupe_digest'=>$dedupe ), false );
				if ( is_wp_error( $queued ) ) { return $queued; }
			}
		}
		$wpdb->last_error = '';
		$expired = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,row_version FROM {$apps_table} WHERE state='draft' AND draft_expires_at IS NOT NULL AND draft_expires_at<%s LIMIT 100",
			$now
		) );
		if ( null === $expired || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_draft_expiry_query', __( 'Expired drafts could not be read safely.', 'global-doctor-onboarding' ) ); }
		foreach ( $expired as $app ) {
			$result = GDO_State::transition( $app->id, 'withdrawn', 0, 'application_withdrawn', 'Private application draft expired under the configured draft-retention policy.', $app->row_version );
			if ( is_wp_error( $result ) ) { return $result; }
		}
		return true;
	}

	private function open_renewals( $now, $apps_table ) {
		global $wpdb;
		$window = gmdate( 'Y-m-d H:i:s', time() + absint( apply_filters( 'gdo_renewal_window_days', 45 ) ) * DAY_IN_SECONDS );
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,row_version,verified_until FROM {$apps_table} WHERE state IN ('verified','reinstated') AND verified_until IS NOT NULL AND verified_until>%s AND verified_until<=%s LIMIT 100",
			$now, $window
		) );
		if ( null === $rows || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_renewal_query', __( 'Renewal-due records could not be read safely.', 'global-doctor-onboarding' ) ); }
		foreach ( $rows as $app ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return new WP_Error( 'gdo_retention_renewal_transaction', __( 'Renewal processing could not start a safe transaction.', 'global-doctor-onboarding' ) );
			}
			$result = GDO_State::transition( $app->id, 'renewal_due', 0, 'verification_lifecycle', 'Verification entered the configured renewal window.', $app->row_version, false );
			$event = is_wp_error( $result ) ? $result : GDO_Notifications::queue( 'doctor_verification_renewal_due', $app->user_id, array( 'application_id'=>absint( $app->id ), 'verified_until'=>$app->verified_until ), false );
			$claim = is_wp_error( $event ) ? $event : GDO_Claims::issue( $app->id, 'renewal_due', array(), false );
			if ( is_wp_error( $result ) || is_wp_error( $event ) || is_wp_error( $claim ) ) {
				$wpdb->query( 'ROLLBACK' );
				return is_wp_error( $result ) ? $result : ( is_wp_error( $event ) ? $event : $claim );
			}
			$commit = $this->commit_lifecycle_or_reconcile( 'renewal', $app->id, 'renewal_due', $event, $claim );
			if ( is_wp_error( $commit ) ) { return $commit; }
			GDO_Audit::publish_transition( $result );
			GDO_Claims::publish( $claim );
		}
		return true;
	}

	private function expire_verifications( $now, $apps_table ) {
		global $wpdb;
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,user_id,row_version FROM {$apps_table} WHERE state IN ('verified','reinstated','renewal_due') AND verified_until IS NOT NULL AND verified_until<%s LIMIT 100",
			$now
		) );
		if ( null === $rows || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_expiry_query', __( 'Expired verification records could not be read safely.', 'global-doctor-onboarding' ) ); }
		foreach ( $rows as $app ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return new WP_Error( 'gdo_retention_expiry_transaction', __( 'Verification expiry could not start a safe transaction.', 'global-doctor-onboarding' ) );
			}
			$result = GDO_State::transition( $app->id, 'expired', 0, 'verification_expired', 'Verification validity period ended.', $app->row_version, false );
			$event = is_wp_error( $result ) ? $result : GDO_Notifications::queue( 'doctor_verification_expired', $app->user_id, array( 'application_id'=>absint( $app->id ) ), false );
			$claim = is_wp_error( $event ) ? $event : GDO_Claims::issue( $app->id, 'expired', array(), false );
			if ( is_wp_error( $result ) || is_wp_error( $event ) || is_wp_error( $claim ) ) {
				$wpdb->query( 'ROLLBACK' );
				return is_wp_error( $result ) ? $result : ( is_wp_error( $event ) ? $event : $claim );
			}
			$commit = $this->commit_lifecycle_or_reconcile( 'expiry', $app->id, 'expired', $event, $claim );
			if ( is_wp_error( $commit ) ) { return $commit; }
			GDO_Audit::publish_transition( $result );
			GDO_Claims::publish( $claim );
			do_action( 'gdo_verification_decision_changed', $app->user_id, 'expired', $app->id, array() );
		}
		return true;
	}

	private function reconcile_pending_claims( $now, $apps_table ) {
		global $wpdb;
		$stale = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,state,claim_version FROM {$apps_table} WHERE claim_status IN ('pending','failed') AND updated_at<%s ORDER BY updated_at ASC LIMIT 50",
			$stale
		) );
		if ( null === $rows || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_claim_query', __( 'Pending professional claims could not be read safely.', 'global-doctor-onboarding' ) ); }
		foreach ( $rows as $app ) {
			$pattern = '%"application_id":' . absint( $app->id ) . ',%';
			$wpdb->last_error = '';
			$event = $wpdb->get_row( $wpdb->prepare(
				"SELECT id,status,event_uuid FROM " . GDO_Schema::table( 'outbox' ) . " WHERE event_type='doctor_professional_claim' AND payload_json LIKE %s ORDER BY id DESC LIMIT 1",
				$pattern
			) );
			if ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_claim_outbox_query', __( 'Professional claim outbox state could not be verified safely.', 'global-doctor-onboarding' ) ); }
			if ( $event && in_array( $event->status, array( 'pending','failed' ), true ) ) {
				$processed = GDO_Notifications::process( 1, $event->event_uuid );
				if ( is_wp_error( $processed ) ) { return $processed; }
				continue;
			}
			if ( $event && 'dead' === $event->status ) {
				GDO_Membership_Adapter::audit( 'doctor_professional_claim_requires_operator_replay', array( 'application_id'=>absint( $app->id ), 'claim_version'=>absint( $app->claim_version ), 'outbox_id'=>absint( $event->id ) ) );
				continue;
			}
			if ( ! $event ) {
				$issued = GDO_Claims::issue( $app->id, $app->state );
				if ( is_wp_error( $issued ) ) { return $issued; }
			}
		}
		return true;
	}

	private function rotate_keys( $evidence_table ) {
		global $wpdb;
		$ring = GDO_Crypto::keyring();
		if ( is_wp_error( $ring ) ) { return $ring; }
		$wpdb->last_error = '';
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$evidence_table} WHERE envelope_version='GDO2' AND key_id<>%s AND deleted_at IS NULL LIMIT 25",
			$ring['active']
		) );
		if ( null === $ids || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_rotation_query', __( 'Credential key-rotation inventory could not be read safely.', 'global-doctor-onboarding' ) ); }
		foreach ( $ids as $evidence_id ) {
			$rotated = GDO_Evidence::rotate_key( $evidence_id );
			if ( is_wp_error( $rotated ) ) { return $rotated; }
		}
		return true;
	}

	private function delete_superseded( $now, $evidence_table ) {
		global $wpdb;
		$before = gmdate( 'Y-m-d H:i:s', time() - absint( apply_filters( 'gdo_superseded_evidence_grace_days', 30 ) ) * DAY_IN_SECONDS );
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$evidence_table} WHERE deleted_at IS NULL AND ((retention_state='superseded' AND updated_at<%s) OR retention_state='deletion_pending_superseded') LIMIT 100",
			$before
		) );
		if ( null === $rows || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_superseded_query', __( 'Superseded credential records could not be read safely.', 'global-doctor-onboarding' ) ); }
		foreach ( $rows as $record ) {
			if ( ! self::delete_record( $record, 'superseded_deleted', $now ) ) { return new WP_Error( 'gdo_retention_superseded_delete', __( 'A superseded credential could not be deleted safely.', 'global-doctor-onboarding' ) ); }
		}
		return true;
	}

	private function apply_retention( $now, $apps_table ) {
		global $wpdb;
		$wpdb->last_error = '';
		$apps = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$apps_table} WHERE legal_hold=0 AND retention_until IS NOT NULL AND retention_until<%s LIMIT 100",
			$now
		) );
		if ( null === $apps || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_application_query', __( 'Retention-eligible applications could not be read safely.', 'global-doctor-onboarding' ) ); }
		foreach ( $apps as $app ) {
			if ( in_array( $app->state, array( 'verified','reinstated','under_review','recommended','appeal_pending','submitted','resubmitted','renewal_due' ), true ) ) {
				continue;
			}

			// Serialize the destructive-retention authorization point before any
			// credential file or Advanced Trust state is irreversibly removed.
			// A legal hold or state change committed before this lock wins and
			// retention is skipped; later changes are ordered after retention began.
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return new WP_Error( 'gdo_retention_predelete_transaction', __( 'Retention eligibility could not be serialized safely before deletion.', 'global-doctor-onboarding' ) );
			}
			$wpdb->last_error = '';
			$predelete = $wpdb->get_row( $wpdb->prepare( "SELECT id,legal_hold,retention_until,state FROM {$apps_table} WHERE id=%d FOR UPDATE", absint( $app->id ) ) );
			if ( null === $predelete && ! empty( $wpdb->last_error ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'gdo_retention_predelete_query', __( 'Retention eligibility could not be revalidated safely before deletion.', 'global-doctor-onboarding' ) );
			}
			$unsafe_states = array( 'verified','reinstated','under_review','recommended','appeal_pending','submitted','resubmitted','renewal_due' );
			$still_due = $predelete && ! empty( $predelete->retention_until ) && strtotime( $predelete->retention_until . ' UTC' ) < strtotime( $now . ' UTC' );
			if ( ! $predelete || absint( $predelete->legal_hold ) || ! $still_due || in_array( sanitize_key( $predelete->state ), $unsafe_states, true ) ) {
				$wpdb->query( 'ROLLBACK' );
				continue;
			}
			// Keep the eligibility row lock through irreversible native evidence
			// deletion so a legal-hold write and retention have one serialized order.
			$failed = false;
			$evidence_rows = GDO_Evidence::records_checked( $app->id, false );
			if ( is_wp_error( $evidence_rows ) ) { $wpdb->query( 'ROLLBACK' ); return $evidence_rows; }
			foreach ( $evidence_rows as $record ) {
				if ( empty( $record->deleted_at ) && ! self::delete_record( $record, 'retention_deleted', $now ) ) {
					$failed = true;
				}
			}
			// Preserve database truth for any credential already physically unlinked.
			// A later record failure must never roll successful deletions back to
			// active rows that reference missing ciphertext.
			$native_commit = $wpdb->query( 'COMMIT' );
			if ( false === $native_commit ) {
				$wpdb->query( 'ROLLBACK' );
				$all_final = true;
				foreach ( $evidence_rows as $record ) {
					if ( ! empty( $record->deleted_at ) ) { continue; }
					$recovered = GDO_Evidence::reconcile_authorized_missing_deletion( $record, 'retention_deleted', $now );
					if ( false === $recovered || is_wp_error( $recovered ) ) { $all_final = false; }
				}
				if ( ! $all_final ) {
					return new WP_Error( 'gdo_retention_predelete_commit', __( 'Retention evidence-deletion commit required recovery and remaining records must be retried safely.', 'global-doctor-onboarding' ) );
				}
				GDO_Membership_Adapter::audit( 'doctor_retention_native_deletion_commit_reconciled', array( 'application_id'=>absint($app->id) ) );
			}
			if ( $failed ) { return new WP_Error( 'gdo_retention_evidence_delete', __( 'Credential evidence retention could not complete safely; successful deletions were preserved and failed records remain retryable.', 'global-doctor-onboarding' ) ); }
			if ( class_exists( 'GDO_Advanced_Trust' ) && ! $this->retire_advanced_trust_for_application( $app, $now ) ) {
				return new WP_Error( 'gdo_retention_advanced_trust', __( 'Advanced Trust retention could not complete safely.', 'global-doctor-onboarding' ) );
			}
			$anonymous = hash( 'sha256', 'retained|' . $app->application_uuid . '|' . wp_salt( 'nonce' ) );
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return new WP_Error( 'gdo_retention_anonymize_transaction', __( 'Application retention anonymization could not start a safe transaction.', 'global-doctor-onboarding' ) );
			}
			$wpdb->last_error = '';
			$locked_app = $wpdb->get_row( $wpdb->prepare( "SELECT id,legal_hold,retention_until FROM {$apps_table} WHERE id=%d FOR UPDATE", absint( $app->id ) ) );
			if ( ! $locked_app || ! empty( $wpdb->last_error ) || absint( $locked_app->legal_hold ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'gdo_retention_application_recheck', __( 'Application retention eligibility changed or could not be revalidated safely.', 'global-doctor-onboarding' ) );
			}
			$updated = $wpdb->update( $apps_table, array(
				'user_id'=>null, 'profile_json'=>'{}', 'profile_fingerprint'=>$anonymous, 'identity_fingerprint'=>'',
				'approved_snapshot_json'=>null, 'approved_fingerprint'=>null, 'submission_hash'=>null,
				'assigned_reviewer_id'=>null, 'recommender_id'=>null, 'finalizer_id'=>null,
				'recommendation_reason'=>'anonymized', 'claim_last_error'=>null, 'retention_until'=>null, 'updated_at'=>$now,
			), array( 'id'=>absint( $app->id ), 'legal_hold'=>0 ) );
			$native_ok = 1 === $updated;
			$native_ok = $native_ok && false !== $wpdb->update( GDO_Schema::table( 'consents' ), array( 'user_id'=>0, 'purpose'=>'retained-accountability-record', 'retention_notice'=>'anonymized', 'withdrawn_at'=>$now ), array( 'application_id'=>$app->id ) );
			$native_ok = $native_ok && false !== $wpdb->update( GDO_Schema::table( 'evidence' ), array( 'user_id'=>0 ), array( 'application_id'=>$app->id ) );
			$native_ok = $native_ok && false !== $wpdb->update( GDO_Schema::table( 'appeals' ), array( 'user_id'=>0, 'reason'=>'anonymized', 'evidence_json'=>null, 'resolution'=>'anonymized' ), array( 'application_id'=>$app->id ) );
			$native_ok = $native_ok && false !== $wpdb->update( GDO_Schema::table( 'risk_signals' ), array( 'related_digest'=>null, 'resolution_reason'=>'anonymized' ), array( 'application_id'=>$app->id ) );
			$native_ok = $native_ok && false !== $wpdb->update( GDO_Schema::table( 'quality_samples' ), array( 'reason'=>'anonymized' ), array( 'application_id'=>$app->id ) );
			$native_ok = $native_ok && false !== $wpdb->delete( GDO_Schema::table( 'access_grants' ), array( 'application_id'=>$app->id ) );
			if ( ! $native_ok ) {
				$wpdb->query( 'ROLLBACK' );
				GDO_Membership_Adapter::audit( 'doctor_verification_retention_native_anonymize_failed', array( 'application_id'=>absint( $app->id ) ) );
				return new WP_Error( 'gdo_retention_native_anonymize', __( 'Related retention records could not be anonymized atomically.', 'global-doctor-onboarding' ) );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				$wpdb->last_error = '';
				$anon = $wpdb->get_row( $wpdb->prepare( "SELECT user_id,profile_json,retention_until FROM {$apps_table} WHERE id=%d LIMIT 1", absint( $app->id ) ) );
				if ( null === $anon && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_retention_native_anonymize_uncertain', __( 'Retention anonymization commit outcome is uncertain and requires reconciliation.', 'global-doctor-onboarding' ) ); }
				if ( ! $anon || null !== $anon->user_id || '{}' !== (string) $anon->profile_json || null !== $anon->retention_until ) { return new WP_Error( 'gdo_retention_native_anonymize', __( 'Retention anonymization was not committed and will be retried.', 'global-doctor-onboarding' ) ); }
				GDO_Membership_Adapter::audit( 'doctor_retention_anonymization_commit_reconciled', array( 'application_id'=>absint($app->id) ) );
			}
			GDO_Membership_Adapter::audit( 'doctor_verification_retention_anonymized', array( 'application_id'=>absint( $app->id ) ) );
		}
		return true;
	}

	private function retire_advanced_trust_for_application( $app, $now ) {
		global $wpdb;
		$app_id = absint( $app->id );
		$health = GDO_Storage::health();
		$dir = GDO_Storage::directory();
		if ( is_wp_error( $health ) || ! $dir ) {
			GDO_Membership_Adapter::audit( 'doctor_advanced_trust_retention_failed', array( 'application_id'=>$app_id, 'error'=>'storage_unavailable' ) );
			return false;
		}
		$upload_table = GDO_Advanced_Trust::table( 'upload_sessions' );
		$wpdb->last_error = '';
		$uploads = $wpdb->get_results( $wpdb->prepare( 'SELECT upload_uuid,temp_name,state FROM ' . $upload_table . ' WHERE application_id=%d', $app_id ) );
		if ( null === $uploads || ! empty( $wpdb->last_error ) ) {
			GDO_Membership_Adapter::audit( 'doctor_advanced_trust_retention_failed', array( 'application_id'=>$app_id, 'error'=>'upload_inventory_failed' ) );
			return false;
		}
		$base = wp_normalize_path( realpath( $dir ) ?: $dir );
		$prefix = trailingslashit( $base );
		foreach ( (array) $uploads as $row ) {
			$path = wp_normalize_path( trailingslashit( $dir ) . '.chunk-' . basename( sanitize_file_name( $row->temp_name ) ) );
			if ( 0 !== strpos( $path, $prefix ) || is_link( $path ) ) {
				GDO_Membership_Adapter::audit( 'doctor_advanced_trust_retention_failed', array( 'application_id'=>$app_id, 'error'=>'upload_path_unsafe' ) );
				return false;
			}
		}
		if ( $uploads ) {
			$wpdb->last_error = '';
			$checkpoint = $wpdb->update( $upload_table, array( 'state'=>'retention_pending', 'updated_at'=>$now ), array( 'application_id'=>$app_id ), array( '%s','%s' ), array( '%d' ) );
			if ( false === $checkpoint || ! empty( $wpdb->last_error ) ) { return false; }
			$wpdb->last_error = '';
			$not_pending = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$upload_table} WHERE application_id=%d AND state<>'retention_pending'", $app_id ) );
			if ( null === $not_pending || ! empty( $wpdb->last_error ) || absint( $not_pending ) > 0 ) { return false; }
		}
		foreach ( (array) $uploads as $row ) {
			$path = wp_normalize_path( trailingslashit( $dir ) . '.chunk-' . basename( sanitize_file_name( $row->temp_name ) ) );
			if ( is_file( $path ) && ( ! @unlink( $path ) || file_exists( $path ) ) ) {
				GDO_Membership_Adapter::audit( 'doctor_advanced_trust_retention_failed', array( 'application_id'=>$app_id, 'error'=>'upload_cleanup_failed' ) );
				return false;
			}
		}
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return false; }
		$ok = true;
		$ok = $ok && false !== $wpdb->delete( $upload_table, array( 'application_id'=>$app_id ) );
		$ok = $ok && false !== $wpdb->delete( GDO_Advanced_Trust::table( 'monitor_state' ), array( 'application_id'=>$app_id ) );
		// Passports are derivative credentials; after retention they must no longer
		// be resolvable and are deleted rather than mapped to a shared user_id=0.
		$ok = $ok && false !== $wpdb->delete( GDO_Advanced_Trust::table( 'verification_passports' ), array( 'application_id'=>$app_id ) );
		$ok = $ok && false !== $wpdb->update( GDO_Advanced_Trust::table( 'credential_checks' ), array( 'facts_json'=>'{"redacted":"retention"}', 'explanation_json'=>'{"redacted":"retention"}', 'external_reference'=>'', 'updated_at'=>$now ), array( 'application_id'=>$app_id ) );
		$ok = $ok && false !== $wpdb->update( GDO_Advanced_Trust::table( 'professional_history' ), array( 'user_id'=>0, 'public_safe'=>0, 'event_json'=>'{"redacted":"retention"}', 'source_hash'=>hash( 'sha256', '{"redacted":"retention"}' ) ), array( 'application_id'=>$app_id ) );
		$ok = $ok && false !== $wpdb->update( GDO_Advanced_Trust::table( 'reviewer_conflicts' ), array( 'applicant_id'=>0 ), array( 'application_id'=>$app_id ) );
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			GDO_Membership_Adapter::audit( 'doctor_advanced_trust_retention_failed', array( 'application_id'=>$app_id, 'error'=>'database_anonymization_failed' ) );
			return false;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$tables = array( $upload_table, GDO_Advanced_Trust::table('monitor_state'), GDO_Advanced_Trust::table('verification_passports') );
			foreach ( $tables as $verify_table ) {
				$wpdb->last_error = '';
				$remaining = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$verify_table} WHERE application_id=%d", $app_id ) );
				if ( null === $remaining || ! empty( $wpdb->last_error ) ) { GDO_Membership_Adapter::audit( 'doctor_advanced_trust_retention_failed', array( 'application_id'=>$app_id, 'error'=>'commit_uncertain' ) ); return false; }
				if ( absint( $remaining ) > 0 ) { return false; }
			}
			$wpdb->last_error = '';
			$unredacted_checks = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . GDO_Advanced_Trust::table('credential_checks') . " WHERE application_id=%d AND facts_json<>'{\"redacted\":\"retention\"}'", $app_id ) );
			$checks_error = ! empty( $wpdb->last_error );
			$wpdb->last_error = '';
			$unredacted_history = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . GDO_Advanced_Trust::table('professional_history') . " WHERE application_id=%d AND (user_id<>0 OR public_safe<>0 OR event_json<>'{\"redacted\":\"retention\"}')", $app_id ) );
			if ( $checks_error || null === $unredacted_checks || null === $unredacted_history || ! empty( $wpdb->last_error ) || absint( $unredacted_checks ) > 0 || absint( $unredacted_history ) > 0 ) { return false; }
			GDO_Membership_Adapter::audit( 'doctor_advanced_trust_retention_commit_reconciled', array( 'application_id'=>$app_id ) );
		}
		return true;
	}

	private function cleanup_access( $now ) {
		global $wpdb;
		$queries = array(
			$wpdb->prepare( 'DELETE FROM ' . GDO_Schema::table( 'access_grants' ) . ' WHERE expires_at<%s OR used_at IS NOT NULL', $now ),
			$wpdb->prepare( 'DELETE FROM ' . GDO_Schema::table( 'rate_limits' ) . ' WHERE expires_at<%d', time() ),
			$wpdb->prepare( 'UPDATE ' . GDO_Schema::table( 'access_log' ) . " SET reviewer_id=0,purpose_code='anonymized' WHERE created_at<%s", gmdate( 'Y-m-d H:i:s', time() - absint( apply_filters( 'gdo_access_log_identifiable_days', 365 ) ) * DAY_IN_SECONDS ) ),
		);
		foreach ( $queries as $sql ) { if ( false === $wpdb->query( $sql ) ) { return new WP_Error( 'gdo_retention_access_cleanup', __( 'Expired access records could not be cleaned safely.', 'global-doctor-onboarding' ) ); } } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return true;
	}

	private static function delete_record( $record, $state, $now ) {
		$result = GDO_Evidence::delete_record_safely( $record, $state, $now );
		if ( is_wp_error( $result ) ) {
			GDO_Membership_Adapter::audit( 'doctor_credential_retention_delete_failed', array( 'application_id'=>absint( $record->application_id ), 'evidence_id'=>absint( $record->id ), 'error'=>$result->get_error_code() ) );
			return false;
		}
		return true;
	}

	private static function cleanup_orphans() {
		global $wpdb;
		$health = GDO_Storage::health();
		if ( is_wp_error( $health ) ) { return false; }
		$dir = GDO_Storage::directory();
		$wpdb->last_error = '';
		$known_rows = $wpdb->get_col( 'SELECT storage_name FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE deleted_at IS NULL' );
		if ( null === $known_rows || ! empty( $wpdb->last_error ) ) {
			GDO_Membership_Adapter::audit( 'doctor_credential_orphan_inventory_failed', array( 'reason'=>'database_inventory_unavailable' ) );
			return false;
		}
		$known = array_fill_keys( array_map( 'strval', $known_rows ), true );
		$cutoff = time() - absint( apply_filters( 'gdo_orphan_grace_hours', 24 ) ) * HOUR_IN_SECONDS;
		$cleanup_ok = true;
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
					$cleanup_ok = false;
					GDO_Membership_Adapter::audit( 'doctor_credential_orphan_remove_failed', array( 'storage_digest'=>hash( 'sha256', $name ) ) );
				}
			}
		}
		return $cleanup_ok;
	}
}
