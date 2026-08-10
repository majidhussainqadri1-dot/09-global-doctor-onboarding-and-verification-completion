<?php
defined( 'ABSPATH' ) || exit;

/**
 * Event bridge for the advanced trust layer.
 * Existing File 09 workflows already emit canonical audit facts. This bridge
 * observes those facts after owner mutations and schedules/derives trust work;
 * it never turns a derivative signal into an owner command.
 */
final class GDO_Advanced_Trust_Events {
    public static function hooks() {
        add_action( 'gdo_canonical_audit_event', array( __CLASS__, 'audit_fact' ), 20, 2 );
        add_action( 'gdo_continuous_verification_adverse_result', array( __CLASS__, 'adverse_monitor_result' ), 10, 3 );
    }

    public static function audit_fact( $event, $context = array() ) {
        $event = sanitize_key( $event );
        $context = is_array( $context ) ? $context : array();
        $application_id = isset( $context['application_id'] ) ? absint( $context['application_id'] ) : 0;
        $evidence_id = isset( $context['evidence_id'] ) ? absint( $context['evidence_id'] ) : 0;
        if ( ! $application_id ) {
            return;
        }

        if ( false !== strpos( $event, 'submitted' ) ) {
            GDO_Advanced_Trust::application_submitted( $application_id );
        }
        if ( $evidence_id && false !== strpos( $event, 'evidence_uploaded' ) ) {
            GDO_Advanced_Trust::authenticity_assessment( $application_id, $evidence_id );
            GDO_Advanced_Trust::ai_assistance( $application_id, $evidence_id );
        }
        if ( preg_match( '/(?:approved|verified|finalized)/', $event ) ) {
            GDO_Advanced_Trust::application_decided( $application_id, 'approved' );
        }
        if ( preg_match( '/(?:rejected|suspended|revoked|expired|renewal|more_information|appeal)/', $event ) ) {
            GDO_Advanced_Trust::event_reverification( $application_id, $event, $context );
        }
    }

    public static function adverse_monitor_result( $application_id, $result, $check ) {
        $application_id = absint( $application_id );
        $result = sanitize_key( $result );
        if ( ! $application_id || ! in_array( $result, array( 'revoked','expired','not_matched' ), true ) ) {
            return;
        }
        GDO_Membership_Adapter::audit( 'doctor_continuous_verification_attention_required', array(
            'application_id'=>$application_id,
            'result'=>$result,
            'check_uuid'=>is_array( $check ) && isset( $check['check_uuid'] ) ? sanitize_text_field( $check['check_uuid'] ) : '',
        ) );
        do_action( 'gdo_professional_reverification_required', $application_id, $result, $check );
    }
}
