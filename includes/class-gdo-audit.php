<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Audit {
    public static function transition( $application_id, $actor_id, $from, $to, $reason_code, $reason_text ) {
        global $wpdb;
        $table = GDO_Schema::table( 'transitions' );
        $previous = (string) $wpdb->get_var( $wpdb->prepare( "SELECT event_hash FROM {$table} WHERE application_id=%d ORDER BY id DESC LIMIT 1", absint( $application_id ) ) );
        $created = current_time( 'mysql', true );
        $data = array(
            'application_id' => absint( $application_id ),
            'actor_id'       => absint( $actor_id ) ?: null,
            'from_state'     => sanitize_key( $from ),
            'to_state'       => sanitize_key( $to ),
            'reason_code'    => sanitize_key( $reason_code ),
            'reason_text'    => sanitize_textarea_field( $reason_text ),
            'previous_hash'  => $previous ?: str_repeat( '0', 64 ),
            'created_at'     => $created,
        );
        $data['event_hash'] = hash( 'sha256', wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        $wpdb->insert( $table, $data, array( '%d','%d','%s','%s','%s','%s','%s','%s','%s' ) );
        GDO_Membership_Adapter::audit( 'doctor_verification_transition', $data );
    }

    public static function access( $application_id, $evidence_id, $reviewer_id, $purpose, $result ) {
        global $wpdb;
        $purpose = sanitize_textarea_field( $purpose );
        $data = array(
            'application_id' => absint( $application_id ),
            'evidence_id'    => absint( $evidence_id ),
            'reviewer_id'    => absint( $reviewer_id ),
            'purpose_code'   => sanitize_key( wp_trim_words( $purpose, 8, '' ) ),
            'purpose_hash'   => hash( 'sha256', $purpose ),
            'result'         => sanitize_key( $result ),
            'actor_digest'   => hash( 'sha256', absint( $reviewer_id ) . '|' . wp_salt( 'nonce' ) ),
            'created_at'     => current_time( 'mysql', true ),
        );
        $wpdb->insert( GDO_Schema::table( 'access_log' ), $data, array( '%d','%d','%d','%s','%s','%s','%s','%s' ) );
        GDO_Membership_Adapter::audit( 'doctor_credential_access', $data );
    }
}
