<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Notifications {
    private static function presentation( $event_type, array $payload ) {
        $application_id = absint( isset($payload['application_id']) ? $payload['application_id'] : 0 );
        $map = array(
            'doctor_application_submitted'        => array( 'administration','normal','Doctor application submitted','Your doctor application was submitted for independent review.' ),
            'doctor_application_assigned'         => array( 'administration','normal','Doctor application assigned','An independent reviewer has been assigned to the application.' ),
            'doctor_application_more_information' => array( 'administration','high','More information required','The reviewer requires replacement evidence or additional information.' ),
            'doctor_verification_verified'        => array( 'administration','high','Doctor verification approved','Your professional verification has been approved for the stated validity period.' ),
            'doctor_verification_rejected'        => array( 'administration','high','Doctor verification rejected','The application was rejected. Review the decision and appeal options.' ),
            'doctor_verification_suspended'       => array( 'security','critical','Doctor verification suspended','Your public doctor verification has been suspended.' ),
            'doctor_verification_revoked'         => array( 'security','critical','Doctor verification revoked','Your public doctor verification has been revoked.' ),
            'doctor_verification_expired'         => array( 'administration','high','Doctor verification expired','The verification validity period has ended.' ),
            'doctor_verification_renewal_due'     => array( 'administration','high','Doctor verification renewal due','Renewal evidence is required to keep verification current.' ),
            'doctor_verification_reinstated'      => array( 'administration','high','Doctor verification reinstated','Your doctor verification has been reinstated.' ),
            'doctor_verification_appeal'          => array( 'administration','high','Verification appeal filed','A verification appeal has been filed.' ),
            'doctor_verification_appeal_resolved' => array( 'administration','high','Verification appeal resolved','The verification appeal has been resolved.' ),
            'doctor_credential_accessed'          => array( 'security','high','Credential evidence accessed','An authorized reviewer accessed private credential evidence for a recorded purpose.' ),
        );
        $item = isset( $map[$event_type] ) ? $map[$event_type] : array( 'administration','normal','Doctor verification update','Your doctor verification record has been updated.' );
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

    public static function queue( $event_type, $recipient_user_id, array $payload = array() ) {
        global $wpdb;
        $event_type = sanitize_key( $event_type );
        $recipient_user_id = absint( $recipient_user_id );
        if ( ! $event_type || ! $recipient_user_id ) {
            return new WP_Error( 'gdo_notification_invalid', __( 'The notification event is invalid.', 'global-doctor-onboarding' ) );
        }
        $event_uuid = wp_generate_uuid4();
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
            return new WP_Error( 'gdo_notification_outbox', __( 'The notification event could not be queued.', 'global-doctor-onboarding' ) );
        }
        self::process( 1, $event_uuid );
        return $event_uuid;
    }

    public static function process( $limit = 25, $event_uuid = '' ) {
        global $wpdb;
        $table = GDO_Schema::table( 'outbox' );
        $where = "status IN ('pending','failed') AND available_at<=%s";
        $args = array( current_time('mysql',true) );
        if ( $event_uuid ) {
            $where .= ' AND event_uuid=%s';
            $args[] = sanitize_text_field( $event_uuid );
        }
        $args[] = max( 1, min( 100, absint($limit) ) );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d", $args ) );
        foreach ( $rows as $row ) {
            $payload = json_decode( $row->payload_json, true );
            $args = self::presentation( $row->event_type, is_array($payload) ? $payload : array() );
            $args['user_id'] = absint( $row->recipient_user_id );
            $args['dedupe_key'] = $row->event_uuid;
            $delivered = false;
            $error = '';
            try {
                if ( class_exists( 'SUN_Core' ) && method_exists( 'SUN_Core', 'create' ) ) {
                    $delivered = absint( SUN_Core::create( $args ) ) > 0;
                } elseif ( has_action( 'sabri_notify' ) ) {
                    do_action( 'sabri_notify', $args );
                    $delivered = true;
                } else {
                    $error = 'File 19 Unified Notifications is unavailable.';
                }
            } catch ( Throwable $e ) {
                $error = $e->getMessage();
            }
            $attempts = absint($row->attempts) + 1;
            if ( $delivered ) {
                $wpdb->update( $table, array('status'=>'delivered','attempts'=>$attempts,'last_error'=>null,'delivered_at'=>current_time('mysql',true)), array('id'=>$row->id), array('%s','%d','%s','%s'), array('%d') );
            } else {
                $terminal = $attempts >= 5;
                $delay = min( DAY_IN_SECONDS, (int) pow( 2, $attempts ) * 300 );
                $wpdb->update( $table, array('status'=>$terminal?'dead':'failed','attempts'=>$attempts,'last_error'=>sanitize_textarea_field($error),'available_at'=>gmdate('Y-m-d H:i:s',time()+$delay)), array('id'=>$row->id), array('%s','%d','%s','%s'), array('%d') );
            }
        }
    }
}
