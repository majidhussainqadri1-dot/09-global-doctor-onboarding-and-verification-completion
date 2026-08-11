<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy' ) );
	}

	public function exporters( $exporters ) {
		$exporters['global-doctor-onboarding'] = array(
			'exporter_friendly_name' => __( 'Global Doctor Verification', 'global-doctor-onboarding' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data'=>array(), 'done'=>true );
		}
		global $wpdb;
		$per = 20;
		$offset = ( max( 1, absint( $page ) ) - 1 ) * $per;
		$wpdb->last_error = '';
		$apps = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE user_id=%d ORDER BY version ASC LIMIT %d OFFSET %d',
			$user->ID, $per, $offset
		) );
		if ( null === $apps || ! empty( $wpdb->last_error ) ) {
			return array( 'data'=>array(), 'done'=>false );
		}
		$data = array();
		foreach ( $apps as $app ) {
			$rows = array(
				array( 'name'=>'Application UUID', 'value'=>$app->application_uuid ),
				array( 'name'=>'Version', 'value'=>$app->version ),
				array( 'name'=>'State', 'value'=>$app->state ),
				array( 'name'=>'Policy and terms', 'value'=>wp_json_encode( array( 'policy_version'=>$app->policy_version, 'terms_version'=>$app->terms_version, 'consent_version'=>$app->consent_version ) ) ),
				array( 'name'=>'Jurisdiction and language', 'value'=>wp_json_encode( array( 'jurisdiction'=>$app->jurisdiction, 'preferred_language'=>$app->preferred_language ) ) ),
				array( 'name'=>'Submitted at', 'value'=>$app->submitted_at ),
				array( 'name'=>'Decision at', 'value'=>$app->decision_at ),
				array( 'name'=>'Verified until', 'value'=>$app->verified_until ),
				array( 'name'=>'Professional application', 'value'=>$app->profile_json ),
				array( 'name'=>'Recommendation', 'value'=>wp_json_encode( array( 'decision'=>$app->recommended_decision, 'reason'=>$app->recommendation_reason, 'at'=>$app->recommendation_at ) ) ),
				array( 'name'=>'File 00 claim status', 'value'=>wp_json_encode( array( 'claim_version'=>$app->claim_version, 'status'=>$app->claim_status, 'acknowledged_at'=>$app->claim_ack_at ) ) ),
			);
			$evidence_rows = GDO_Evidence::records_checked( $app->id );
			if ( is_wp_error( $evidence_rows ) ) { return array( 'data'=>$data, 'done'=>false ); }
			foreach ( $evidence_rows as $record ) {
				$rows[] = array( 'name'=>'Credential evidence metadata', 'value'=>wp_json_encode( array(
					'type'=>$record->document_type, 'version'=>$record->version, 'status'=>$record->status,
					'review_note'=>$record->review_note, 'registry_result'=>$record->registry_result,
					'validity_until'=>$record->validity_until, 'created_at'=>$record->created_at,
					'deletion_state'=>$record->retention_state,
				) ) );
			}
			$queries = array(
				'Consent' => 'SELECT consent_version,purpose,retention_notice,lawful_basis,accepted_at,withdrawn_at FROM ' . GDO_Schema::table( 'consents' ) . ' WHERE application_id=%d',
				'Credential access event' => 'SELECT evidence_id,purpose_code,result,trace_id,created_at FROM ' . GDO_Schema::table( 'access_log' ) . ' WHERE application_id=%d',
				'Appeal' => 'SELECT appeal_uuid,source_state,status,reason,resolution,decision,created_at,resolved_at FROM ' . GDO_Schema::table( 'appeals' ) . ' WHERE application_id=%d',
				'Risk signal' => 'SELECT signal_type,severity,status,resolution,resolution_reason,created_at,resolved_at FROM ' . GDO_Schema::table( 'risk_signals' ) . ' WHERE application_id=%d',
				'Quality sample' => 'SELECT original_decision,status,outcome,reason,created_at,completed_at FROM ' . GDO_Schema::table( 'quality_samples' ) . ' WHERE application_id=%d',
			);
			foreach ( $queries as $label => $sql ) {
				$wpdb->last_error = '';
				$items = $wpdb->get_results( $wpdb->prepare( $sql, $app->id ), ARRAY_A );
				if ( null === $items || ! empty( $wpdb->last_error ) ) {
					return array( 'data'=>$data, 'done'=>false );
				}
				foreach ( $items as $item ) { $rows[] = array( 'name'=>$label, 'value'=>wp_json_encode( $item ) ); }
			}
			if ( class_exists( 'GDO_Advanced_Trust' ) ) {
				$advanced_rows = GDO_Advanced_Trust::privacy_export_rows( $app->id );
				if ( is_wp_error( $advanced_rows ) ) {
					GDO_Membership_Adapter::audit( 'gdo_advanced_export_failed', array( 'application_id'=>absint( $app->id ), 'error'=>$advanced_rows->get_error_code() ) );
					return array( 'data'=>$data, 'done'=>false );
				}
				$rows = array_merge( $rows, $advanced_rows );
			}
			$data[] = array( 'group_id'=>'global-doctor-verification', 'group_label'=>__( 'Global Doctor Verification', 'global-doctor-onboarding' ), 'item_id'=>'application-' . absint( $app->id ), 'data'=>$rows );
		}
		return array( 'data'=>$data, 'done'=>count( $apps ) < $per );
	}

	public function erasers( $erasers ) {
		$erasers['global-doctor-onboarding'] = array(
			'eraser_friendly_name' => __( 'Global Doctor Verification', 'global-doctor-onboarding' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed'=>false, 'items_retained'=>false, 'messages'=>array(), 'done'=>true );
		}
		global $wpdb;
		$limit = 10;
		$wpdb->last_error = '';
		$apps = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE user_id=%d AND legal_hold=0 ORDER BY id ASC LIMIT %d',
			$user->ID, $limit
		) );
		if ( null === $apps || ! empty( $wpdb->last_error ) ) {
			return array( 'items_removed'=>false, 'items_retained'=>true, 'messages'=>array( 'Erasure is paused because application records could not be read safely.' ), 'done'=>false );
		}
		$wpdb->last_error = '';
		$held_raw = $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE user_id=%d AND legal_hold=1',
			$user->ID
		) );
		if ( null === $held_raw || ! empty( $wpdb->last_error ) ) {
			return array( 'items_removed'=>false, 'items_retained'=>true, 'messages'=>array( 'Erasure is paused because legal-hold status could not be verified safely.' ), 'done'=>false );
		}
		$held = absint( $held_raw );
		$removed = false;
		$retained = $held > 0;
		$messages = $held ? array( 'One or more doctor-verification records remain under a documented legal hold.' ) : array();
		foreach ( $apps as $app ) {
			$wpdb->last_error = '';
			$current = GDO_Application::get( $app->id );
			if ( null === $current && ! empty( $wpdb->last_error ) ) { $retained = true; $messages[] = 'Erasure is paused because the current application state could not be read safely.'; continue; }
			if ( $current && GDO_State::public_verified( $current->state ) ) {
				if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
					$retained = true;
					$messages[] = 'Erasure is paused because the verification revocation transaction could not start safely.';
					continue;
				}
				$result = GDO_State::transition( $current->id, 'revoked', 0, 'privacy_erasure', 'Public verification was revoked before personal-data erasure.', $current->row_version, false );
				$claim = is_wp_error( $result ) ? $result : GDO_Claims::issue( $current->id, 'revoked', array(), false );
				if ( is_wp_error( $result ) || is_wp_error( $claim ) || false === $wpdb->query( 'COMMIT' ) ) {
					$wpdb->query( 'ROLLBACK' );
					$retained = true;
					$messages[] = is_wp_error( $result ) ? $result->get_error_message() : ( is_wp_error( $claim ) ? $claim->get_error_message() : 'Erasure is paused until revocation and claim propagation can commit atomically.' );
					continue;
				}
				GDO_Audit::publish_transition( $result );
				GDO_Claims::publish( $claim );
			} elseif ( $current && ! in_array( $current->state, array( 'withdrawn','revoked' ), true ) ) {
				if ( ! GDO_State::can_transition( $current->state, 'withdrawn' ) ) {
					$retained = true;
					$messages[] = 'An application is in a state that must be resolved before erasure.';
					continue;
				}
				$transition = GDO_State::transition( $current->id, 'withdrawn', 0, 'privacy_erasure', 'Application was withdrawn before personal-data erasure.', $current->row_version );
				if ( is_wp_error( $transition ) ) {
					$retained = true;
					$messages[] = $transition->get_error_message();
					continue;
				}
			}

			$deletion_failed = false;
			$evidence_rows = GDO_Evidence::records_checked( $app->id, false );
			if ( is_wp_error( $evidence_rows ) ) { $retained = true; $messages[] = 'Erasure is paused because credential evidence inventory could not be read safely.'; continue; }
			foreach ( $evidence_rows as $record ) {
				if ( ! empty( $record->deleted_at ) ) {
					$updated = $wpdb->update( GDO_Schema::table( 'evidence' ), array( 'user_id'=>0 ), array( 'id'=>absint( $record->id ) ), array( '%d' ), array( '%d' ) );
					if ( false === $updated ) {
						$deletion_failed = true;
						$retained = true;
						$messages[] = 'A previously deleted credential record could not be detached from the account identity.';
					}
					continue;
				}
				$proof = GDO_Evidence::delete_record_safely( $record, 'deleted', current_time( 'mysql', true ) );
				if ( is_wp_error( $proof ) ) {
					$deletion_failed = true;
					$retained = true;
					$messages[] = $proof->get_error_message();
					continue;
				}
				$removed = true;
			}
			if ( $deletion_failed ) {
				continue;
			}

			if ( class_exists( 'GDO_Advanced_Trust_Hardening' ) ) {
				$advanced = GDO_Advanced_Trust_Hardening::privacy_erase_application( $app->id, $user->ID );
				if ( is_wp_error( $advanced ) ) {
					$retained = true;
					$messages[] = $advanced->get_error_message();
					continue;
				}
			}

			$anonymous = hash( 'sha256', 'erased|' . $app->application_uuid . '|' . wp_salt( 'nonce' ) );
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				$retained = true;
				$messages[] = 'Application anonymization is paused because a database transaction could not be started safely.';
				continue;
			}
			$wpdb->last_error = '';
			$locked_app = $wpdb->get_row( $wpdb->prepare(
				'SELECT id,user_id FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
				absint( $app->id ), $user->ID
			) );
			$now = current_time( 'mysql', true );
			$db_ok = (bool) $locked_app && empty( $wpdb->last_error );
			$db_ok = $db_ok && false !== $wpdb->update( GDO_Schema::table( 'consents' ), array( 'user_id'=>0, 'purpose'=>'retained-accountability-record', 'retention_notice'=>'anonymized', 'withdrawn_at'=>$now ), array( 'application_id'=>$app->id ) );
			$db_ok = $db_ok && false !== $wpdb->update( GDO_Schema::table( 'evidence' ), array( 'user_id'=>0 ), array( 'application_id'=>$app->id ) );
			$db_ok = $db_ok && false !== $wpdb->update( GDO_Schema::table( 'access_log' ), array( 'reviewer_id'=>0, 'purpose_code'=>'anonymized' ), array( 'application_id'=>$app->id, 'reviewer_id'=>$user->ID ) );
			$db_ok = $db_ok && false !== $wpdb->update( GDO_Schema::table( 'appeals' ), array( 'user_id'=>0, 'status'=>'closed', 'reason'=>'anonymized', 'evidence_json'=>null, 'resolution'=>'anonymized', 'decision'=>'withdrawn', 'resolved_at'=>$now ), array( 'application_id'=>$app->id ) );
			$db_ok = $db_ok && false !== $wpdb->update( GDO_Schema::table( 'risk_signals' ), array( 'related_digest'=>null, 'resolution_reason'=>'anonymized' ), array( 'application_id'=>$app->id ) );
			$db_ok = $db_ok && false !== $wpdb->update( GDO_Schema::table( 'quality_samples' ), array( 'reason'=>'anonymized' ), array( 'application_id'=>$app->id ) );
			// Transition rows are hash-chained immutable accountability evidence; actor_id is retained under that integrity purpose.
			$db_ok = $db_ok && false !== $wpdb->delete( GDO_Schema::table( 'access_grants' ), array( 'application_id'=>$app->id ) );
			$payload_like = '%"application_id":' . absint( $app->id ) . '%';
			$db_ok = $db_ok && false !== $wpdb->query( $wpdb->prepare(
				'UPDATE ' . GDO_Schema::table( 'outbox' ) . ' SET recipient_user_id=0,payload_json=%s WHERE recipient_user_id=%d AND event_type<>\'doctor_professional_claim\' AND payload_json LIKE %s',
				'{"redacted":"privacy_erasure"}', $user->ID, $payload_like
			) );
			$updated = $db_ok ? $wpdb->update(
				GDO_Schema::table( 'applications' ),
				array(
					'user_id'=>null, 'profile_json'=>'{}', 'profile_fingerprint'=>$anonymous, 'identity_fingerprint'=>'',
					'approved_snapshot_json'=>null, 'approved_fingerprint'=>null, 'submission_hash'=>null,
					'assigned_reviewer_id'=>null, 'recommender_id'=>null, 'finalizer_id'=>null,
					'recommendation_reason'=>'anonymized', 'claim_last_error'=>null, 'updated_at'=>$now,
				),
				array( 'id'=>absint( $app->id ), 'user_id'=>$user->ID )
			) : false;
			if ( 1 !== $updated || false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				$retained = true;
				$messages[] = 'Application anonymization requires administrator repair; native database identity links were not partially committed.';
				continue;
			}
			do_action( 'gdo_identity_projection_erased', $user->ID, $app->id );
			$removed = true;
			$retained = true;
			$messages[] = 'Personal credential and Advanced Trust data were erased or anonymized; minimal accountability evidence was retained.';
		}
		return array( 'items_removed'=>$removed, 'items_retained'=>$retained, 'messages'=>array_values( array_unique( $messages ) ), 'done'=>count( $apps ) < $limit );
	}

	public function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'Global Doctor Verification', 'global-doctor-onboarding' ),
				'<p class="privacy-policy-tutorial">Doctor applicants provide professional information and private credential evidence. File 09 stores versioned consent, uses encrypted private storage, records professional trust checks and verification-passport lifecycle data, audits purpose-bound credential access, supports export and correction workflows, and applies legal-hold, appeal, retention, anonymization, and verified physical-erasure controls. Public profiles never expose credential documents, private contact details, reviewer notes, license evidence, encryption metadata, or security signals.</p>'
			);
		}
	}
}
