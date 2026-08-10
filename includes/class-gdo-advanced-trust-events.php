<?php
defined( 'ABSPATH' ) || exit;

/**
 * Canonical File 09 event bridge for Advanced Trust.
 *
 * The bridge consumes exact past-tense File 09 audit facts. It never turns a
 * derivative provider signal into an owner state mutation.
 */
final class GDO_Advanced_Trust_Events {
    public static function hooks() {
        add_action( 'gdo_canonical_audit_event', array( __CLASS__, 'audit_fact' ), 20, 2 );
        add_action( 'gdo_continuous_verification_adverse_result', array( __CLASS__, 'adverse_monitor_result' ), 10, 3 );
        add_filter( 'rest_post_dispatch', array( __CLASS__, 'public_cache_headers' ), 20, 3 );
    }

    public static function audit_fact( $event, $context = array() ) {
        $event = sanitize_key( $event );
        $context = is_array( $context ) ? $context : array();
        $application_id = isset( $context['application_id'] ) ? absint( $context['application_id'] ) : 0;
        if ( ! $application_id ) {
            return;
        }

        // State changes are represented by one canonical hash-chained transition
        // fact; interpret the explicit to_state rather than substring-matching the
        // event name. This keeps replay behavior deterministic.
        if ( 'doctor_verification_transition' === $event ) {
            $to = isset( $context['to_state'] ) ? sanitize_key( $context['to_state'] ) : '';
            if ( 'submitted' === $to ) {
                GDO_Advanced_Trust::application_submitted( $application_id );
                return;
            }
            if ( in_array( $to, array( 'verified','reinstated','expired' ), true ) ) {
                GDO_Advanced_Trust::application_decided( $application_id, $to );
                return;
            }
            if ( in_array( $to, array( 'suspended','revoked','rejected','withdrawn' ), true ) ) {
                GDO_Advanced_Trust::revoke_passports_for_application( $application_id, $to );
                $app = GDO_Application::get( $application_id );
                if ( $app ) {
                    GDO_Advanced_Trust::add_history( $app->user_id, $app->id, 'professional_status_changed', array( 'decision'=>$to ), false );
                }
                GDO_Advanced_Trust::event_reverification( $application_id, $to, $context );
                return;
            }
            if ( in_array( $to, array( 'renewal_due','appeal_pending','more_information','resubmitted','under_review' ), true ) ) {
                GDO_Advanced_Trust::event_reverification( $application_id, $to, $context );
                return;
            }
        }

        // A direct lifecycle event from a future owner command may use these
        // explicit names. No fuzzy matching is permitted.
        $direct = array(
            'doctor_application_submitted'     => 'submitted',
            'doctor_verification_verified'     => 'verified',
            'doctor_verification_reinstated'   => 'reinstated',
            'doctor_verification_expired'      => 'expired',
        );
        if ( isset( $direct[ $event ] ) ) {
            if ( 'submitted' === $direct[ $event ] ) {
                GDO_Advanced_Trust::application_submitted( $application_id );
            } else {
                GDO_Advanced_Trust::application_decided( $application_id, $direct[ $event ] );
            }
        }
        $adverse = array(
            'doctor_verification_suspended'=>'suspended',
            'doctor_verification_revoked'=>'revoked',
            'doctor_verification_rejected'=>'rejected',
        );
        if ( isset( $adverse[ $event ] ) ) {
            GDO_Advanced_Trust::revoke_passports_for_application( $application_id, $adverse[ $event ] );
            GDO_Advanced_Trust::event_reverification( $application_id, $adverse[ $event ], $context );
        }
    }

    public static function adverse_monitor_result( $application_id, $result, $check ) {
        $application_id = absint( $application_id );
        $result = sanitize_key( $result );
        if ( ! $application_id || ! in_array( $result, array( 'revoked','expired','not_matched' ), true ) ) {
            return;
        }
        $app = GDO_Application::get( $application_id );
        if ( ! $app ) {
            return;
        }
        GDO_Membership_Adapter::audit( 'doctor_continuous_verification_attention_required', array(
            'application_id'=>$application_id,
            'result'=>$result,
            'check_uuid'=>is_array( $check ) && isset( $check['check_uuid'] ) ? sanitize_text_field( $check['check_uuid'] ) : '',
        ) );
        // The applicant is informed, but the professional state is not changed
        // here. An authorized File 09 lifecycle command must decide any action.
        $event = GDO_Notifications::queue(
            'doctor_verification_reverification_required',
            absint( $app->user_id ),
            array( 'application_id'=>$application_id, 'reason_code'=>$result ),
            false
        );
        if ( ! is_wp_error( $event ) ) {
            GDO_Notifications::process( 1, $event );
        }
        GDO_Advanced_Trust::event_reverification( $application_id, 'adverse_' . $result, array( 'check'=>$check ) );
        do_action( 'gdo_professional_reverification_required', $application_id, $result, $check );
    }

    /**
     * Public passport verification is current-state security information; it may
     * not be served from a stale browser/CDN cache. Transparency is a fixed
     * aggregate window and may be cached briefly.
     */
    public static function public_cache_headers( $response, $server, $request ) {
        unset( $server );
        if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) {
            return $response;
        }
        $route = (string) $request->get_route();
        if ( 0 === strpos( $route, '/' . GDO_Advanced_Trust::REST_NAMESPACE . '/public/passport/' ) ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
        } elseif ( '/' . GDO_Advanced_Trust::REST_NAMESPACE . '/public/transparency' === $route ) {
            $response->header( 'Cache-Control', 'public, max-age=300' );
        }
        return $response;
    }
}
