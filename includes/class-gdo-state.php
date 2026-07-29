<?php
defined( 'ABSPATH' ) || exit;

final class GDO_State {
    public static function transitions() {
        return array(
            'draft'          => array( 'submitted', 'withdrawn' ),
            'submitted'      => array( 'under_review', 'withdrawn' ),
            'under_review'   => array( 'more_information', 'recommended', 'rejected', 'withdrawn' ),
            'more_information'=> array( 'resubmitted', 'withdrawn' ),
            'resubmitted'    => array( 'under_review', 'withdrawn' ),
            'recommended'    => array( 'verified', 'rejected' ),
            'verified'       => array( 'suspended', 'revoked', 'expired', 'renewal_due' ),
            'renewal_due'    => array( 'resubmitted', 'expired', 'revoked' ),
            'suspended'      => array( 'appeal_pending', 'reinstated', 'revoked' ),
            'appeal_pending' => array( 'under_review', 'suspended', 'reinstated', 'revoked' ),
            'reinstated'     => array( 'verified', 'suspended', 'revoked' ),
            'rejected'       => array( 'appeal_pending' ),
            'expired'        => array( 'resubmitted', 'revoked' ),
            'revoked'        => array(),
            'withdrawn'      => array(),
            'legacy_review_required' => array( 'under_review', 'rejected', 'withdrawn' ),
        );
    }

    public static function can_transition( $from, $to ) {
        $map = self::transitions();
        return isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );
    }

    public static function terminal( $state ) {
        return in_array( $state, array( 'revoked', 'withdrawn' ), true );
    }

    public static function public_verified( $state ) {
        return in_array( $state, array( 'verified', 'reinstated' ), true );
    }

    public static function transition( $application_id, $to, $actor_id, $reason_code, $reason_text, $expected_row_version = 0 ) {
        global $wpdb;
        $table = GDO_Schema::table( 'applications' );
        $app = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", absint( $application_id ) ) );
        if ( ! $app || ! self::can_transition( $app->state, $to ) ) {
            return new WP_Error( 'gdo_invalid_transition', __( 'This application state transition is not allowed.', 'global-doctor-onboarding' ) );
        }
        if ( $expected_row_version && absint( $app->row_version ) !== absint( $expected_row_version ) ) {
            return new WP_Error( 'gdo_concurrent_change', __( 'The application changed while it was being reviewed. Reload and try again.', 'global-doctor-onboarding' ) );
        }
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET state=%s,row_version=row_version+1,updated_at=%s WHERE id=%d AND row_version=%d",
            sanitize_key( $to ), current_time( 'mysql', true ), absint( $application_id ), absint( $app->row_version )
        ) );
        if ( 1 !== $updated ) {
            return new WP_Error( 'gdo_concurrent_change', __( 'The application changed while it was being reviewed. Reload and try again.', 'global-doctor-onboarding' ) );
        }
        GDO_Audit::transition( $application_id, absint( $actor_id ), $app->state, $to, $reason_code, $reason_text );
        return true;
    }
}
