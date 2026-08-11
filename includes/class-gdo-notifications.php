<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Notifications {
	const FILE19_PRODUCER = 'file09-doctor-verification';
	const FILE19_OWNER = 'File 09';
	const FILE19_SCHEMA = '1.0';

	private static function presentation( $event_type, array $payload ) {
		$application_id = absint( isset( $payload['application_id'] ) ? $payload['application_id'] : 0 );
		$map = array(
			'doctor_application_submitted'        => array( 'administration','normal','Doctor application submitted','Your doctor application was submitted for independent review.' ),
			'doctor_application_assigned'         => array( 'administration','normal','Doctor application assigned','An independent reviewer has been assigned to the application.' ),
			'doctor_application_more_information' => array( 'administration','high','More information required','The reviewer requires replacement evidence or additional information before the stated deadline.' ),
			'doctor_verification_verified'        => array( 'administration','high','Doctor verification approved','Your professional verification has been approved for the stated validity period.' ),
			'doctor_verification_rejected'        => array( 'administration','high','Doctor verification rejected','The application was rejected. Review the reason and available appeal route.' ),
			'doctor_verification_suspended'       => array( 'security','critical','Doctor verification suspended','Your public doctor verification has been suspended. Review the reason and appeal route.' ),
			'doctor_verification_revoked'         => array( 'security','critical','Doctor verification revoked','Your public doctor verification has been revoked. Review the reason and appeal route.' ),
			'doctor_verification_expired'         => array( 'administration','high','Doctor verification expired','The verification validity period has ended.' ),
			'doctor_verification_renewal_due'     => array( 'administration','high','Doctor verification renewal due','Updated professional evidence is required to keep verification current.' ),
			'doctor_verification_reinstated'      => array( 'administration','high','Doctor verification reinstated','Your doctor verification has been reinstated.' ),
			'doctor_verification_appeal'          => array( 'administration','high','Verification appeal filed','A verification appeal has been filed and will be independently reviewed.' ),
			'doctor_verification_appeal_assigned' => array( 'administration','high','Verification appeal assigned','An independent verification appeal has been assigned to you for review.' ),
			'doctor_verification_appeal_resolved' => array( 'administration','high','Verification appeal resolved','The verification appeal has been resolved.' ),
			'doctor_credential_accessed'          => array( 'security','high','Credential evidence accessed','An authorized reviewer accessed private credential evidence for a recorded purpose.' ),
			'doctor_application_draft_expiring'   => array( 'administration','normal','Doctor application draft expiring','Your private doctor application draft will expire soon unless it is completed.' ),
		);
		$item = isset( $map[ $event_type ] ) ? $map[ $event_type ] : array( 'administration','normal','Doctor verification update','Your doctor verification record has been updated.' );
		return array(
			'user_id'     => 0,
			'category'    => $item[0],
			'type'        => sanitize_key( $event_type ),
			'priority'    => $item[1],
			'title'       => $item[2],
			'body'        => $item[3],
			'link'        => GDO_Plugin::application_url(),
			'entity_type' => 'doctor_application',
			'entity_id'   => $application_id,
			'source'      => 'file09',
			'source_id'   => $application_id,
			// Never forward the raw event payload to a presentation provider.
			'context'     => array(
				'application_id' => $application_id,
				'event_uuid'     => isset( $payload['event_uuid'] ) ? sanitize_text_field( $payload['event_uuid'] ) : '',
				'source_version' => defined( 'GDO_VERSION' ) ? GDO_VERSION : '',
			),
		);
	}

	/**
	 * Register File 09 as a versioned File 19 producer when the current provider
	 * API is available. Re-registration is request-local and idempotent.
	 *
	 * @return bool
	 */
	public static function register_file19_producer() {
		if ( ! function_exists( 'sun_register_notification_producer' ) ) {
			return false;
		}
		return (bool) sun_register_notification_producer(
			self::FILE19_PRODUCER,
			array(
				'owner'           => self::FILE19_OWNER,
				'event_types'     => array( 'DoctorApplication.*', 'DoctorVerification.*', 'DoctorCredential.*' ),
				'schema_versions' => array( self::FILE19_SCHEMA ),
				'internal'        => true,
			)
		);
	}

	public static function queue( $event_type, $recipient_user_id, array $payload = array(), $process_now = true ) {
		global $wpdb;
		$event_type = sanitize_key( $event_type );
		$recipient_user_id = absint( $recipient_user_id );
		if ( ! $event_type || ! $recipient_user_id ) {
			return new WP_Error( 'gdo_notification_invalid', __( 'The notification event is invalid.', 'global-doctor-onboarding' ) );
		}
		$event_uuid = isset( $payload['event_uuid'] ) && self::valid_uuid( $payload['event_uuid'] ) ? (string) $payload['event_uuid'] : wp_generate_uuid4();
		$payload['event_uuid'] = $event_uuid;
		if ( empty( $payload['occurred_at'] ) ) {
			$payload['occurred_at'] = gmdate( 'c' );
		}
		$data = array(
			'event_uuid'        => $event_uuid,
			'event_type'        => $event_type,
			'recipient_user_id' => $recipient_user_id,
			'payload_json'      => wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'status'            => 'pending',
			'attempts'          => 0,
			'available_at'      => current_time( 'mysql', true ),
			'created_at'        => current_time( 'mysql', true ),
		);
		$inserted = $wpdb->insert( GDO_Schema::table( 'outbox' ), $data, array( '%s','%s','%d','%s','%s','%d','%s','%s' ) );
		if ( 1 !== $inserted ) {
			$existing = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE event_uuid=%s', $event_uuid ) ) );
			if ( ! $existing ) {
				return new WP_Error( 'gdo_notification_outbox', __( 'The notification event could not be queued.', 'global-doctor-onboarding' ) );
			}
		}
		if ( $process_now ) {
			self::process( 1, $event_uuid );
		}
		return $event_uuid;
	}

	private static function deliver_claim( array $payload ) {
		if ( empty( $payload['claim'] ) || ! is_array( $payload['claim'] ) ) {
			return new WP_Error( 'gdo_claim_payload_invalid', 'Professional claim payload is invalid.' );
		}
		$claim = $payload['claim'];
		$result = null;
		$candidates = array(
			array( 'SMC_Professional_Verification_Claims', 'consume' ),
			array( 'SMC_Professional_Claims', 'consume' ),
			array( 'SMC_Contracts', 'consume_professional_claim' ),
		);
		foreach ( $candidates as $candidate ) {
			if ( is_callable( $candidate ) ) {
				$result = call_user_func( $candidate, $claim );
				break;
			}
		}
		$result = apply_filters( 'gdo_deliver_professional_claim', $result, $claim, $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$accepted = true === $result || 'accepted' === $result || ( is_array( $result ) && 'accepted' === ( isset( $result['status'] ) ? $result['status'] : '' ) );
		if ( ! $accepted ) {
			return new WP_Error( 'gdo_claim_provider_unavailable', 'File 00 professional-claim consumer did not explicitly accept the claim.' );
		}
		$application_id = absint( isset( $payload['application_id'] ) ? $payload['application_id'] : 0 );
		$claim_version = absint( isset( $claim['claim_version'] ) ? $claim['claim_version'] : 0 );
		if ( ! GDO_Claims::acknowledge( $application_id, $claim_version, 'accepted', '' ) ) {
			return new WP_Error( 'gdo_claim_ack_failed', 'File 09 could not record the File 00 claim acknowledgment.' );
		}
		return true;
	}

	/** @return string */
	private static function file19_event_type( $event_type ) {
		$map = array(
			'doctor_application_submitted'        => 'DoctorApplication.Submitted',
			'doctor_application_assigned'         => 'DoctorApplication.Assigned',
			'doctor_application_more_information' => 'DoctorApplication.MoreInformationRequested',
			'doctor_application_draft_expiring'   => 'DoctorApplication.DraftExpiring',
			'doctor_verification_verified'        => 'DoctorVerification.Verified',
			'doctor_verification_rejected'        => 'DoctorVerification.Rejected',
			'doctor_verification_suspended'       => 'DoctorVerification.Suspended',
			'doctor_verification_revoked'         => 'DoctorVerification.Revoked',
			'doctor_verification_expired'         => 'DoctorVerification.Expired',
			'doctor_verification_renewal_due'     => 'DoctorVerification.RenewalDue',
			'doctor_verification_reinstated'      => 'DoctorVerification.Reinstated',
			'doctor_verification_appeal'          => 'DoctorVerification.AppealFiled',
			'doctor_verification_appeal_assigned' => 'DoctorVerification.AppealAssigned',
			'doctor_verification_appeal_resolved' => 'DoctorVerification.AppealResolved',
			'doctor_credential_accessed'          => 'DoctorCredential.Accessed',
		);
		return isset( $map[ $event_type ] ) ? $map[ $event_type ] : 'DoctorVerification.Updated';
	}

	/**
	 * Build the current `sun.event.v1` envelope. Only minimized presentation data
	 * is passed to File 19; credential/evidence objects and reviewer notes remain
	 * exclusively in File 09.
	 *
	 * @return array
	 */
	private static function file19_event( $event_type, $recipient_user_id, array $payload, array $args ) {
		$application_id = absint( isset( $payload['application_id'] ) ? $payload['application_id'] : 0 );
		$event_uuid = isset( $payload['event_uuid'] ) && self::valid_uuid( $payload['event_uuid'] ) ? (string) $payload['event_uuid'] : wp_generate_uuid4();
		$sensitivity = 'doctor_credential_accessed' === $event_type ? 'restricted' : 'standard';
		return array(
			'producer'        => self::FILE19_PRODUCER,
			'owner'           => self::FILE19_OWNER,
			'event_id'        => $event_uuid,
			'event_type'      => self::file19_event_type( $event_type ),
			'schema_version'  => self::FILE19_SCHEMA,
			'occurred_at'     => isset( $payload['occurred_at'] ) ? (string) $payload['occurred_at'] : gmdate( 'c' ),
			'recipients'      => array( array( 'user_id' => absint( $recipient_user_id ) ) ),
			'subject'         => array( 'type' => 'doctor_application', 'id' => (string) $application_id ),
			'trace_id'        => $event_uuid,
			'category'        => sanitize_key( $args['category'] ),
			'priority'        => sanitize_key( $args['priority'] ),
			'sensitivity'     => $sensitivity,
			'deep_link'       => $args['link'],
			'deep_context'    => 'file09-doctor-application',
			'data'            => array(
				'action_name'   => (string) $args['title'],
				'summary'       => (string) $args['body'],
				'application_id'=> $application_id,
			),
			'source_version'  => defined( 'GDO_VERSION' ) ? GDO_VERSION : 'unknown',
			'idempotency_key' => $event_uuid,
		);
	}

	private static function deliver_notification( $event_type, $recipient_user_id, array $payload ) {
		$args = self::presentation( $event_type, $payload );
		$args['user_id'] = absint( $recipient_user_id );
		$args['dedupe_key'] = isset( $payload['event_uuid'] ) ? sanitize_text_field( $payload['event_uuid'] ) : '';

		// Current File 19 contract: versioned producer registration + sun.event.v1.
		if ( function_exists( 'sun_ingest_domain_event' ) && self::register_file19_producer() ) {
			$result = sun_ingest_domain_event( self::file19_event( $event_type, $recipient_user_id, $payload, $args ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$status = is_array( $result ) && isset( $result['status'] ) ? sanitize_key( $result['status'] ) : '';
			if ( in_array( $status, array( 'processed', 'duplicate' ), true ) ) {
				return true;
			}
			return new WP_Error( 'gdo_notification_provider_rejected', 'File 19 did not explicitly process or deduplicate the event.' );
		}

		// Compatibility only for older File 19 releases; never implement parallel transport here.
		if ( class_exists( 'SUN_Core' ) && method_exists( 'SUN_Core', 'create' ) ) {
			return absint( SUN_Core::create( $args ) ) > 0 ? true : new WP_Error( 'gdo_notification_provider_rejected', 'File 19 rejected the notification.' );
		}
		if ( has_action( 'sabri_notify' ) ) {
			// A fire-and-forget WordPress action has no delivery acknowledgement.
			// Never mark the durable File 09 outbox row delivered unless a legacy
			// adapter explicitly opts into and returns an acknowledgement contract.
			$supported = (bool) apply_filters( 'gdo_legacy_notification_ack_supported', false, $args );
			if ( ! $supported ) {
				return new WP_Error( 'gdo_notification_legacy_unacknowledged', 'The legacy File 19 notification action does not provide a delivery acknowledgement.' );
			}
			do_action( 'sabri_notify', $args );
			$acknowledged = (bool) apply_filters( 'gdo_legacy_notification_acknowledged', false, $args );
			return $acknowledged ? true : new WP_Error( 'gdo_notification_legacy_unacknowledged', 'The legacy File 19 notification action did not acknowledge the event.' );
		}
		return new WP_Error( 'gdo_notification_provider_unavailable', 'File 19 Unified Notifications is unavailable.' );
	}

	public static function process( $limit = 25, $event_uuid = '' ) {
		global $wpdb;
		$table = GDO_Schema::table( 'outbox' );
		$now = current_time( 'mysql', true );
		$lease_recovered = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status='failed', attempts=attempts+1, last_error=%s, available_at=%s WHERE status='processing' AND available_at<=%s",
			'Processing lease expired before completion; safe retry scheduled.', $now, $now
		) );
		if ( false === $lease_recovered ) {
			return new WP_Error( 'gdo_outbox_lease_recovery_failed', __( 'Notification lease recovery could not be persisted safely.', 'global-doctor-onboarding' ) );
		}
		$where = "status IN ('pending','failed') AND available_at<=%s";
		$values = array( $now );
		if ( $event_uuid ) {
			$where .= ' AND event_uuid=%s';
			$values[] = sanitize_text_field( $event_uuid );
		}
		$values[] = max( 1, min( 100, absint( $limit ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d", $values ) );
		if ( null === $rows || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_outbox_query_failed', __( 'Pending notification events could not be read safely.', 'global-doctor-onboarding' ) );
		}
		$summary = array( 'processed'=>0, 'delivered'=>0, 'failed'=>0, 'dead'=>0 );
		foreach ( $rows as $row ) {
			$lease_seconds = max( 60, absint( apply_filters( 'gdo_outbox_processing_lease_seconds', 300, $row->event_type ) ) );
			$lease_until = gmdate( 'Y-m-d H:i:s', time() + $lease_seconds );
			$claimed = $wpdb->update( $table, array( 'status'=>'processing', 'available_at'=>$lease_until ), array( 'id'=>absint( $row->id ), 'status'=>$row->status ), array( '%s','%s' ), array( '%d','%s' ) );
			if ( false === $claimed ) {
				return new WP_Error( 'gdo_outbox_claim_failed', __( 'A notification event processing lease could not be claimed safely.', 'global-doctor-onboarding' ) );
			}
			if ( 1 !== $claimed ) {
				continue;
			}
			$payload = json_decode( $row->payload_json, true );
			$payload = is_array( $payload ) ? $payload : array();
			$payload['event_uuid'] = (string) $row->event_uuid;
			try {
				$result = 'doctor_professional_claim' === $row->event_type
					? self::deliver_claim( $payload )
					: self::deliver_notification( $row->event_type, $row->recipient_user_id, $payload );
			} catch ( Throwable $e ) {
				$result = new WP_Error( 'gdo_outbox_exception', $e->getMessage() );
			}
			$attempts = absint( $row->attempts ) + 1;
			if ( true === $result ) {
				$persisted = $wpdb->update(
					$table,
					array( 'status'=>'delivered', 'attempts'=>$attempts, 'last_error'=>null, 'delivered_at'=>current_time( 'mysql', true ), 'dead_at'=>null ),
					array( 'id'=>absint( $row->id ), 'status'=>'processing' ),
					array( '%s','%d','%s','%s','%s' ),
					array( '%d','%s' )
				);
				if ( 1 !== $persisted ) {
					GDO_Membership_Adapter::audit( 'doctor_verification_outbox_delivery_persistence_uncertain', array( 'event_uuid'=>$row->event_uuid, 'event_type'=>$row->event_type ) );
					return new WP_Error( 'gdo_outbox_delivery_persist_failed', __( 'Provider delivery succeeded but the durable outbox receipt could not be persisted safely.', 'global-doctor-onboarding' ) );
				}
				++$summary['processed'];
				++$summary['delivered'];
				continue;
			}
			$error = is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : 'Provider returned no explicit success.';
			if ( 'doctor_professional_claim' === $row->event_type && ! empty( $payload['application_id'] ) ) {
				$claim_marked = $wpdb->update( GDO_Schema::table( 'applications' ), array( 'claim_status'=>'failed', 'claim_last_error'=>sanitize_textarea_field( $error ), 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>absint( $payload['application_id'] ) ), array( '%s','%s','%s' ), array( '%d' ) );
				if ( false === $claim_marked ) {
					return new WP_Error( 'gdo_claim_failure_persist_failed', __( 'Claim delivery failed but its application status could not be persisted safely.', 'global-doctor-onboarding' ) );
				}
			}
			$terminal = $attempts >= absint( apply_filters( 'gdo_outbox_max_attempts', 7, $row->event_type ) );
			$delay = min( DAY_IN_SECONDS, (int) pow( 2, min( 10, $attempts ) ) * 60 );
			$persisted = $wpdb->update(
				$table,
				array(
					'status'=>$terminal ? 'dead' : 'failed', 'attempts'=>$attempts,
					'last_error'=>sanitize_textarea_field( $error ),
					'available_at'=>gmdate( 'Y-m-d H:i:s', time() + $delay ),
					'dead_at'=>$terminal ? current_time( 'mysql', true ) : null,
				),
				array( 'id'=>absint( $row->id ), 'status'=>'processing' ),
				array( '%s','%d','%s','%s','%s' ),
				array( '%d','%s' )
			);
			if ( 1 !== $persisted ) {
				return new WP_Error( 'gdo_outbox_failure_persist_failed', __( 'Notification failure state could not be persisted safely.', 'global-doctor-onboarding' ) );
			}
			++$summary['processed'];
			++$summary['failed'];
			if ( $terminal ) {
				++$summary['dead'];
				GDO_Membership_Adapter::audit( 'doctor_verification_outbox_dead_letter', array( 'event_uuid'=>$row->event_uuid, 'event_type'=>$row->event_type, 'attempts'=>$attempts ) );
			}
		}
		return $summary;
	}

	public static function replay( $event_id, $actor_id, $reason ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		if ( ! $actor_id || $actor_id !== get_current_user_id() || ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $actor_id ) || ! GDO_Membership_Adapter::recent_step_up( $actor_id ) ) {
			return new WP_Error( 'gdo_outbox_replay_forbidden', __( 'Dead-letter replay requires current verification-management authorization and recent step-up.', 'global-doctor-onboarding' ) );
		}
		$reason = sanitize_textarea_field( $reason );
		if ( strlen( $reason ) < 20 ) {
			return new WP_Error( 'gdo_outbox_replay_reason', __( 'A reason of at least 20 characters is required.', 'global-doctor-onboarding' ) );
		}
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT id,event_uuid,status FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE id=%d LIMIT 1',
			absint( $event_id )
		) );
		if ( null === $row && ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_outbox_replay_query', __( 'The dead-letter event could not be read safely.', 'global-doctor-onboarding' ) );
		}
		if ( ! $row || 'dead' !== sanitize_key( $row->status ) || ! self::valid_uuid( $row->event_uuid ) ) {
			return new WP_Error( 'gdo_outbox_replay_conflict', __( 'The dead-letter event is no longer available for replay.', 'global-doctor-onboarding' ) );
		}
		$updated = $wpdb->update(
			GDO_Schema::table( 'outbox' ),
			array( 'status'=>'pending', 'available_at'=>current_time( 'mysql', true ), 'dead_at'=>null, 'last_error'=>null ),
			array( 'id'=>absint( $event_id ), 'status'=>'dead' ),
			array( '%s','%s','%s','%s' ),
			array( '%d','%s' )
		);
		if ( 1 !== $updated ) {
			return new WP_Error( 'gdo_outbox_replay_conflict', __( 'The dead-letter event is no longer available for replay.', 'global-doctor-onboarding' ) );
		}
		GDO_Membership_Adapter::audit( 'doctor_verification_outbox_replayed', array( 'event_id'=>absint( $event_id ), 'event_uuid'=>(string) $row->event_uuid, 'actor_id'=>absint( $actor_id ), 'reason'=>$reason ) );
		$result = self::process( 1, (string) $row->event_uuid );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
