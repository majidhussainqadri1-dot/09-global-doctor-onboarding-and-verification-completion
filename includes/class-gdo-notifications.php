<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Notifications {
    public static function queue( $event_type, $recipient_user_id, array $payload = array() ) {
        global $wpdb;
        $event_uuid = wp_generate_uuid4();
        $data = array(
            'event_uuid'        => $event_uuid,
            'event_type'        => sanitize_key( $event_type ),
            'recipient_user_id' => absint( $recipient_user_id ),
            'payload_json'      => wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'status'            => 'pending',
            'attempts'          => 0,
            'available_at'      => current_time( 'mysql', true ),
            'created_at'        => current_time( 'mysql', true ),
        );
        $wpdb->insert( GDO_Schema::table( 'outbox' ), $data, array( '%s','%s','%d','%s','%s','%d','%s','%s' ) );
        do_action( 'sabri_unified_notifications_enqueue', $event_type, absint( $recipient_user_id ), $payload, $event_uuid );
        return $event_uuid;
    }
}
