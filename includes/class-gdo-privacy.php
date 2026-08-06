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
		$apps = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE user_id=%d ORDER BY version ASC LIMIT %d OFFSET %d',
			$user->ID, $per, $offset
		) );
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
			foreach ( GDO_Evidence::records( $app->id ) as $record ) {
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
				foreach ( $wpdb->get_results( $wpdb->prepare( $sql, $app->id ), ARRAY_A ) as $item ) {
					$rows[] = array( 'name'=>$label, 'value'=>wp_json_encode( $item ) );
				}
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
		$apps = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE user_id=%d ORDER BY id ASC LIMIT 10 OFFSET %d',
			$user->ID, ( max( 1, absint( $page ) ) - 1 ) * 10
		) );
		$removed = false;
		$retained = false;
		$messages = array();
		foreach ( $apps as $app ) {
			if ( $app->legal_hold ) {
				$retained = true;
				$messages[] = 'Application ' . $app->application_uuid . ' is retained under legal hold.';
				continue;
			}
			$current = GDO_Application::get( $app->id );
			if ( $current && GDO_State::public_verified( $current->state ) ) {
				$result = GDO_State::transition( $current->id, 'revoked', 0, 'privacy_erasure', 'Public verification was revoked before personal-data erasure.', $current->row_version );
				if ( is_wp_error( $result ) ) {
					$retained = true;
					$messages[] = $result->get_error_message();
					continue;
				}
				$claim = GDO_Claims::issue( $current->id, 'revoked' );
				if ( is_wp_error( $claim ) ) {
					$retained = true;
					$messages[] = 'Erasure is paused until the revocation claim is safely queued.';
					continue;
				}
			} elseif ( $current && ! in_array( $current->state, array( 'withdrawn','revoked' ), true ) ) {
				$target = GDO_State::can_transition( $current->state, 'withdrawn' ) ? 'withdrawn' : '';
				if ( $target ) {
					$transition = GDO_State::transition( $current->id, $target, 0, 'privacy_erasure', 'Application was withdrawn before personal-data erasure.', $current->row_version );
					if ( is_wp_error( $transition ) ) {
						$retained = true;
						$messages[] = $transition->get_error_message();
						continue;
					}
				}
			}
			$deletion_failed = false;
			foreach ( GDO_Evidence::records( $app->id, true ) as $record ) {
				$proof = GDO_Storage::delete_verified( $record->storage_name, $record->ciphertext_sha256 );
				if ( is_wp_error( $proof ) ) {
					$deletion_failed = true;
					$retained = true;
					$messages[] = $proof->get_error_message();
					continue;
				}
				$updated = $wpdb->update(
					GDO_Schema::table( 'evidence' ),
					array( 'retention_state'=>'deleted', 'deletion_proof'=>$proof, 'deleted_at'=>current_time( 'mysql', true ), 'original_name'=>'erased', 'storage_name'=>'deleted-' . absint( $record->id ), 'source_sha256'=>'', 'content_hmac'=>'', 'updated_at'=>current_time( 'mysql', true ) ),
					array( 'id'=>absint( $record->id ) ),
					array( '%s','%s','%s','%s','%s','%s','%s','%s' ),
					array( '%d' )
				);
				if ( false === $updated ) {
					$deletion_failed = true;
					$retained = true;
					$messages[] = 'A physical credential deletion succeeded but its proof record requires administrator repair.';
				} else {
					$removed = true;
				}
			}
			if ( $deletion_failed ) {
				continue;
			}
			$anonymous = hash( 'sha256', 'erased|' . $app->application_uuid . '|' . wp_salt( 'nonce' ) );
			$updated = $wpdb->update(
				GDO_Schema::table( 'applications' ),
				array(
					'user_id'=>0, 'profile_json'=>'{}', 'profile_fingerprint'=>$anonymous, 'identity_fingerprint'=>'',
					'approved_snapshot_json'=>null, 'approved_fingerprint'=>null, 'submission_hash'=>null,
					'assigned_reviewer_id'=>null, 'recommender_id'=>null, 'finalizer_id'=>null,
					'recommendation_reason'=>'anonymized', 'claim_last_error'=>null, 'updated_at'=>current_time( 'mysql', true ),
				),
				array( 'id'=>absint( $app->id ) ),
				array( '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				$retained = true;
				$messages[] = 'Application anonymization requires administrator repair.';
				continue;
			}
			$wpdb->update( GDO_Schema::table( 'consents' ), array( 'user_id'=>0, 'purpose'=>'retained-accountability-record', 'retention_notice'=>'anonymized', 'withdrawn_at'=>current_time( 'mysql', true ) ), array( 'application_id'=>$app->id ), array( '%d','%s','%s','%s' ), array( '%d' ) );
			$wpdb->update( GDO_Schema::table( 'access_log' ), array( 'reviewer_id'=>0, 'purpose_code'=>'anonymized' ), array( 'application_id'=>$app->id ), array( '%d','%s' ), array( '%d' ) );
			$wpdb->update( GDO_Schema::table( 'appeals' ), array( 'user_id'=>0, 'status'=>'closed', 'reason'=>'anonymized', 'evidence_json'=>null, 'resolution'=>'anonymized', 'decision'=>'withdrawn', 'resolved_at'=>current_time( 'mysql', true ) ), array( 'application_id'=>$app->id ), array( '%d','%s','%s','%s','%s','%s','%s' ), array( '%d' ) );
			$wpdb->update( GDO_Schema::table( 'risk_signals' ), array( 'related_digest'=>null, 'resolution_reason'=>'anonymized' ), array( 'application_id'=>$app->id ), array( '%s','%s' ), array( '%d' ) );
			do_action( 'gdo_identity_projection_erased', $user->ID, $app->id );
			$removed = true;
			$retained = true;
			$messages[] = 'Personal credential data was erased; minimal anonymized decision and audit evidence was retained for accountability.';
		}
		return array( 'items_removed'=>$removed, 'items_retained'=>$retained, 'messages'=>array_values( array_unique( $messages ) ), 'done'=>count( $apps ) < 10 );
	}

	public function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'Global Doctor Verification', 'global-doctor-onboarding' ),
				'<p class="privacy-policy-tutorial">Doctor applicants provide professional information and private credential evidence. File 09 stores versioned consent, uses encrypted private storage, audits purpose-bound credential access, supports export and correction workflows, and applies legal-hold, appeal, retention, anonymization, and verified physical-erasure controls. Public profiles never expose credential documents, private contact details, reviewer notes, license evidence, encryption metadata, or security signals.</p>'
			);
		}
	}
}
