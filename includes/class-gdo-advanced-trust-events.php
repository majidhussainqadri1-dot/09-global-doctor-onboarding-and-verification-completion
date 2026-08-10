<?php
defined( 'ABSPATH' ) || exit;

/**
 * Event and hardening bridge for the advanced trust layer.
 * Existing File 09 workflows already emit canonical audit facts. This bridge
 * observes those facts after owner mutations and schedules/derives trust work;
 * it never turns a derivative signal into an owner command.
 */
final class GDO_Advanced_Trust_Events {
    public static function hooks() {
        add_action( 'gdo_canonical_audit_event', array( __CLASS__, 'audit_fact' ), 20, 2 );
        add_action( 'gdo_continuous_verification_adverse_result', array( __CLASS__, 'adverse_monitor_result' ), 10, 3 );
        // Register hardened replacements after the base RC5 routes. The override
        // deliberately reuses the mature File 09 evidence-grant lifecycle.
        add_action( 'rest_api_init', array( __CLASS__, 'register_hardened_routes' ), 30 );
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

    public static function register_hardened_routes() {
        register_rest_route(
            GDO_Advanced_Trust::REST_NAMESPACE,
            '/trust/viewing-room',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'rest_safe_viewing_room' ),
                'permission_callback' => array( __CLASS__, 'rest_reviewer_permission' ),
            ),
            true
        );
        register_rest_route(
            GDO_Advanced_Trust::REST_NAMESPACE,
            '/public/transparency',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'rest_safe_transparency' ),
                'permission_callback' => '__return_true',
            ),
            true
        );
    }

    public static function rest_reviewer_permission() {
        $reviewer_id = get_current_user_id();
        return $reviewer_id
            && GDO_Membership_Adapter::can( 'sabri_verify_doctors', $reviewer_id )
            && GDO_Membership_Adapter::recent_step_up( $reviewer_id );
    }

    /**
     * Issue the secure-room token through the already hardened one-time File 09
     * evidence grant rather than constructing a second, incompatible grant.
     */
    public static function rest_safe_viewing_room( WP_REST_Request $request ) {
        $payload = (array) $request->get_json_params();
        $application_id = isset( $payload['application_id'] ) ? absint( $payload['application_id'] ) : 0;
        $evidence_id = isset( $payload['evidence_id'] ) ? absint( $payload['evidence_id'] ) : 0;
        $purpose = isset( $payload['purpose'] ) ? sanitize_textarea_field( $payload['purpose'] ) : 'credential_review';
        $reviewer_id = get_current_user_id();
        $application = $application_id ? GDO_Application::get( $application_id ) : null;
        if ( ! $application || ! $evidence_id ) {
            return new WP_Error( 'gdo_room_not_found', __( 'The requested credential review room is unavailable.', 'global-doctor-onboarding' ), array( 'status'=>404 ) );
        }

        $matched = false;
        foreach ( GDO_Evidence::records( $application_id, true ) as $record ) {
            if ( absint( $record->id ) === $evidence_id ) {
                $matched = true;
                break;
            }
        }
        if ( ! $matched ) {
            // Do not reveal whether the supplied evidence ID belongs elsewhere.
            return new WP_Error( 'gdo_room_not_found', __( 'The requested credential review room is unavailable.', 'global-doctor-onboarding' ), array( 'status'=>404 ) );
        }

        $grant = GDO_Evidence::issue_view_grant( $evidence_id, $reviewer_id, $purpose, 'view' );
        if ( is_wp_error( $grant ) ) {
            return $grant;
        }
        GDO_Membership_Adapter::audit( 'doctor_secure_viewing_room_issued', array(
            'application_id'=>$application_id,
            'evidence_id'=>$evidence_id,
            'reviewer_id'=>$reviewer_id,
            'mode'=>'view',
        ) );
        return rest_ensure_response( array(
            'token'=>$grant['token'],
            'expires_at'=>$grant['expires_at'],
            'mode'=>'view',
            'download_allowed'=>false,
            'watermark'=>sprintf( 'PRIVATE REVIEW • %d • %s UTC', absint( $reviewer_id ), gmdate( 'Y-m-d H:i:s' ) ),
        ) );
    }

    /**
     * Public transparency uses a fixed 90-day window and cohort suppression.
     * This prevents arbitrary small-window differencing from becoming an
     * applicant-level inference surface.
     */
    public static function rest_safe_transparency() {
        $days = 90;
        $snapshot = GDO_Advanced_Trust::transparency_snapshot( $days );
        $minimum = max( 20, min( 100, absint( apply_filters( 'gdo_public_transparency_minimum_cohort', 20 ) ) ) );
        if ( absint( isset( $snapshot['decisions'] ) ? $snapshot['decisions'] : 0 ) < $minimum ) {
            return rest_ensure_response( array(
                'period_days'=>$days,
                'privacy'=>'aggregate_only',
                'suppressed'=>true,
                'minimum_cohort'=>$minimum,
            ) );
        }
        $snapshot['suppressed'] = false;
        $snapshot['minimum_cohort'] = $minimum;
        return rest_ensure_response( $snapshot );
    }
}
