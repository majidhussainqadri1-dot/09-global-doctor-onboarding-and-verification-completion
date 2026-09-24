<?php
defined( 'ABSPATH' ) || exit;

/**
 * Canonical File 09 event bridge for Advanced Trust.
 *
 * The bridge consumes exact past-tense File 09 audit facts. It never turns a
 * derivative provider signal into an owner state mutation.
 */
final class GDO_Advanced_Trust_Events {
    private static function observe_result( $result, $application_id, $operation ) {
        if ( ! is_wp_error( $result ) ) { return true; }
        GDO_Membership_Adapter::audit( 'doctor_advanced_trust_lifecycle_side_effect_failed', array(
            'application_id'=>absint( $application_id ),
            'operation'=>substr( sanitize_key( $operation ), 0, 80 ),
            'error_code'=>substr( sanitize_key( $result->get_error_code() ), 0, 100 ),
        ) );
        do_action( 'gdo_advanced_trust_lifecycle_attention', absint( $application_id ), sanitize_key( $operation ), $result->get_error_code() );
        return false;
    }

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

        if ( 'doctor_verification_transition' === $event ) {
            $to = isset( $context['to_state'] ) ? sanitize_key( $context['to_state'] ) : '';
            if ( 'submitted' === $to ) {
                // GDO_Application::submit() emits the canonical post-commit
                // gdo_application_submitted owner hook immediately after this
                // transition publication. Do not execute the same Advanced Trust
                // submission side effects twice in one successful submit.
                return;
            }
            if ( in_array( $to, array( 'verified','reinstated','expired','suspended','revoked','rejected','withdrawn' ), true ) ) {
                self::observe_result( GDO_Advanced_Trust_Hardening::application_decided( $application_id, $to ), $application_id, 'decision_' . $to );
                if ( 'expired' === $to ) {
                    self::observe_result( GDO_Advanced_Trust_Hardening::event_reverification( $application_id, 'expired', $context ), $application_id, 'reverification_expired' );
                }
                return;
            }
            if ( in_array( $to, array( 'renewal_due','appeal_pending','more_information','resubmitted','under_review' ), true ) ) {
                self::observe_result( GDO_Advanced_Trust_Hardening::event_reverification( $application_id, $to, $context ), $application_id, 'reverification_' . $to );
                return;
            }
        }

        $direct = array(
            'doctor_application_submitted'     => 'submitted',
            'doctor_verification_verified'     => 'verified',
            'doctor_verification_reinstated'   => 'reinstated',
            'doctor_verification_expired'      => 'expired',
            'doctor_verification_suspended'    => 'suspended',
            'doctor_verification_revoked'      => 'revoked',
            'doctor_verification_rejected'     => 'rejected',
        );
        if ( isset( $direct[ $event ] ) ) {
            if ( 'submitted' === $direct[ $event ] ) {
                self::observe_result( GDO_Advanced_Trust_Hardening::application_submitted( $application_id ), $application_id, 'submission' );
            } else {
                self::observe_result( GDO_Advanced_Trust_Hardening::application_decided( $application_id, $direct[ $event ] ), $application_id, 'decision_' . $direct[ $event ] );
                if ( 'expired' === $direct[ $event ] ) {
                    self::observe_result( GDO_Advanced_Trust_Hardening::event_reverification( $application_id, 'expired', $context ), $application_id, 'reverification_expired' );
                }
            }
        }
    }

    public static function adverse_monitor_result( $application_id, $result, $check ) {
        $application_id = absint( $application_id );
        $result = sanitize_key( $result );
        if ( ! $application_id || ! in_array( $result, array( 'revoked','expired','not_matched' ), true ) ) {
            return;
        }
        global $wpdb;
        $wpdb->last_error = '';
        $app = GDO_Application::get( $application_id );
        if ( null === $app && ! empty( $wpdb->last_error ) ) { GDO_Membership_Adapter::audit( 'doctor_continuous_verification_application_read_failed', array( 'application_id'=>$application_id, 'result'=>$result ) ); return; }
        if ( ! $app ) { return; }
        GDO_Membership_Adapter::audit( 'doctor_continuous_verification_attention_required', array(
            'application_id'=>$application_id,
            'result'=>$result,
            'check_uuid'=>is_array( $check ) && isset( $check['check_uuid'] ) ? sanitize_text_field( $check['check_uuid'] ) : '',
        ) );
        $event = GDO_Notifications::queue(
            'doctor_verification_reverification_required',
            absint( $app->user_id ),
            array( 'application_id'=>$application_id, 'reason_code'=>$result ),
            false
        );
        if ( ! is_wp_error( $event ) ) {
            GDO_Notifications::process( 1, $event );
        }
        GDO_Advanced_Trust_Hardening::event_reverification( $application_id, 'adverse_' . $result, array( 'check'=>$check ) );
        do_action( 'gdo_professional_reverification_required', $application_id, $result, $check );
    }

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
