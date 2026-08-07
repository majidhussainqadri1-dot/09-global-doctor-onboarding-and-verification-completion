<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Notifications {
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
			'context'     => $payload,
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

	private static function deliver_notification( $event_type, $recipient_user_id, array $payload ) {
		$args = self::presentation( $event_type, $payload );
		$args['user_id'] = absint( $recipient_user_id );
		$args['dedupe_key'] = isset( $payload['event_uuid'] ) ? sanitize_text_field( $payload['event_uuid'] ) : '';
		if ( class_exists( 'SUN_Core' ) && method_exists( 'SUN_Core', 'create' ) ) {
			return absint( SUN_Core::create( $args ) ) > 0 ? true : new WP_Error( 'gdo_notification_provider_rejected', 'File 19 rejected the notification.' );
		}
		if ( has_action( 'sabri_notify' ) ) {
			do_action( 'sabri_notify', $args );
			return true;
		}
		return new WP_Error( 'gdo_notification_provider_unavailable', 'File 19 Unified Notifications is unavailable.' );
	}

	public static function process( $limit = 25, $event_uuid = '' ) {
		global $wpdb;
		$table = GDO_Schema::table( 'outbox' );
		$now = current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status='failed', attempts=attempts+1, last_error=%s, available_at=%s WHERE status='processing' AND available_at<=%s",
			'Processing lease expired before completion; safe retry scheduled.', $now, $now
		) );
		$where = "status IN ('pending','failed') AND available_at<=%s";
		$values = array( $now );
		if ( $event_uuid ) {
			$where .= ' AND event_uuid=%s';
			$values[] = sanitize_text_field( $event_uuid );
		}
		$values[] = max( 1, min( 100, absint( $limit ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d", $values ) );
		foreach ( $rows as $row ) {
			$lease_seconds = max( 60, absint( apply_filters( 'gdo_outbox_processing_lease_seconds', 300, $row->event_type ) ) );
			$lease_until = gmdate( 'Y-m-d H:i:s', time() + $lease_seconds );
			$claimed = $wpdb->update( $table, array( 'status'=>'processing', 'available_at'=>$lease_until ), array( 'id'=>absint( $row->id ), 'status'=>$row->status ), array( '%s','%s' ), array( '%d','%s' ) );
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
				$wpdb->update(
					$table,
					array( 'status'=>'delivered', 'attempts'=>$attempts, 'last_error'=>null, 'delivered_at'=>current_time( 'mysql', true ), 'dead_at'=>null ),
					array( 'id'=>absint( $row->id ) ),
					array( '%s','%d','%s','%s','%s' ),
					array( '%d' )
				);
				continue;
			}
			$error = is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : 'Provider returned no explicit success.';
			if ( 'doctor_professional_claim' === $row->event_type && ! empty( $payload['application_id'] ) ) {
				$wpdb->update( GDO_Schema::table( 'applications' ), array( 'claim_status'=>'failed', 'claim_last_error'=>sanitize_textarea_field( $error ), 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>absint( $payload['application_id'] ) ), array( '%s','%s','%s' ), array( '%d' ) );
			}
			$terminal = $attempts >= absint( apply_filters( 'gdo_outbox_max_attempts', 7, $row->event_type ) );
			$delay = min( DAY_IN_SECONDS, (int) pow( 2, min( 10, $attempts ) ) * 60 );
			$wpdb->update(
				$table,
				array(
					'status'=>$terminal ? 'dead' : 'failed', 'attempts'=>$attempts,
					'last_error'=>sanitize_textarea_field( $error ),
					'available_at'=>gmdate( 'Y-m-d H:i:s', time() + $delay ),
					'dead_at'=>$terminal ? current_time( 'mysql', true ) : null,
				),
				array( 'id'=>absint( $row->id ) ),
				array( '%s','%d','%s','%s','%s' ),
				array( '%d' )
			);
			if ( $terminal ) {
				GDO_Membership_Adapter::audit( 'doctor_verification_outbox_dead_letter', array( 'event_uuid'=>$row->event_uuid, 'event_type'=>$row->event_type, 'attempts'=>$attempts ) );
			}
		}
	}

	public static function replay( $event_id, $actor_id, $reason ) {
		global $wpdb;
		$reason = sanitize_textarea_field( $reason );
		if ( strlen( $reason ) < 20 ) {
			return new WP_Error( 'gdo_outbox_replay_reason', __( 'A reason of at least 20 characters is required.', 'global-doctor-onboarding' ) );
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
		GDO_Membership_Adapter::audit( 'doctor_verification_outbox_replayed', array( 'event_id'=>absint( $event_id ), 'actor_id'=>absint( $actor_id ), 'reason'=>$reason ) );
		self::process( 1 );
		return true;
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
