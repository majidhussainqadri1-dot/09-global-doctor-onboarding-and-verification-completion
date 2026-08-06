<?php
defined( 'ABSPATH' ) || exit;

final class GDO_State {
    public static function transitions() {
        return array(
            'draft'                  => array( 'submitted', 'withdrawn' ),
            'submitted'              => array( 'under_review', 'withdrawn' ),
            'under_review'           => array( 'more_information', 'recommended', 'rejected', 'withdrawn' ),
            'more_information'       => array( 'resubmitted', 'withdrawn' ),
            'resubmitted'            => array( 'under_review', 'withdrawn' ),
            'recommended'            => array( 'verified', 'rejected', 'under_review', 'withdrawn' ),
            'verified'               => array( 'suspended', 'revoked', 'expired', 'renewal_due' ),
            'renewal_due'            => array( 'resubmitted', 'expired', 'revoked', 'withdrawn' ),
            'suspended'              => array( 'appeal_pending', 'reinstated', 'revoked', 'expired', 'withdrawn' ),
            'appeal_pending'         => array( 'under_review', 'suspended', 'reinstated', 'rejected', 'revoked', 'withdrawn' ),
            'reinstated'             => array( 'verified', 'suspended', 'revoked', 'expired', 'renewal_due' ),
            'rejected'               => array( 'appeal_pending', 'withdrawn' ),
            'expired'                => array( 'resubmitted', 'revoked', 'withdrawn' ),
            'revoked'                => array( 'appeal_pending', 'withdrawn' ),
            'withdrawn'              => array(),
            'legacy_review_required' => array( 'under_review', 'rejected', 'withdrawn' ),
        );
    }

    public static function can_transition( $from, $to ) {
        $map = self::transitions();
        return isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );
    }

    public static function terminal( $state ) {
        return in_array( $state, array( 'withdrawn' ), true );
    }

    public static function public_verified( $state ) {
        return in_array( $state, array( 'verified', 'reinstated', 'renewal_due' ), true );
    }

    public static function retention_deadline( $state ) {
        $days = 0;
        if ( in_array( $state, array( 'rejected', 'revoked', 'withdrawn' ), true ) ) {
            $days = absint( apply_filters( 'gdo_accountability_retention_days', 365, $state ) );
        } elseif ( 'expired' === $state ) {
            $days = absint( apply_filters( 'gdo_expired_retention_days', 180, $state ) );
        }
        return $days ? gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) ) : null;
    }

    public static function transition( $application_id, $to, $actor_id, $reason_code, $reason_text, $expected_row_version = 0, $manage_transaction = true ) {
        global $wpdb;
        $table = GDO_Schema::table( 'applications' );
        $application_id = absint( $application_id );
        $to = sanitize_key( $to );
        if ( $manage_transaction ) {
            $wpdb->query( 'START TRANSACTION' );
        }
        $app = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d FOR UPDATE", $application_id ) );
        if ( ! $app || ! self::can_transition( $app->state, $to ) ) {
            if ( $manage_transaction ) {
                $wpdb->query( 'ROLLBACK' );
            }
            return new WP_Error( 'gdo_invalid_transition', __( 'This application state transition is not allowed.', 'global-doctor-onboarding' ) );
        }
        if ( $expected_row_version && absint( $app->row_version ) !== absint( $expected_row_version ) ) {
            if ( $manage_transaction ) {
                $wpdb->query( 'ROLLBACK' );
            }
            return new WP_Error( 'gdo_concurrent_change', __( 'The application changed while it was being reviewed. Reload and try again.', 'global-doctor-onboarding' ) );
        }
        $sets = array( 'state=%s', 'row_version=row_version+1', 'updated_at=%s' );
        $values = array( $to, current_time( 'mysql', true ) );
        $retention = self::retention_deadline( $to );
        if ( $retention ) {
            $sets[] = 'retention_until=%s';
            $values[] = $retention;
        }
        if ( 'revoked' === $to ) {
            $sets[] = 'revoked_at=%s';
            $values[] = current_time( 'mysql', true );
        }
        if ( 'withdrawn' === $to ) {
            $sets[] = 'withdrawn_at=%s';
            $values[] = current_time( 'mysql', true );
        }
        $values[] = $application_id;
        $values[] = absint( $app->row_version );
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET " . implode( ',', $sets ) . ' WHERE id=%d AND row_version=%d',
            $values
        ) );
        if ( 1 !== $updated ) {
            if ( $manage_transaction ) {
                $wpdb->query( 'ROLLBACK' );
            }
            return new WP_Error( 'gdo_concurrent_change', __( 'The application changed while it was being reviewed. Reload and try again.', 'global-doctor-onboarding' ) );
        }
        $audit = GDO_Audit::transition( $application_id, absint( $actor_id ), $app->state, $to, $reason_code, $reason_text );
        if ( is_wp_error( $audit ) ) {
            if ( $manage_transaction ) {
                $wpdb->query( 'ROLLBACK' );
            }
            return $audit;
        }
        if ( $manage_transaction && false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_transition_commit_failed', __( 'The verification transition could not be committed.', 'global-doctor-onboarding' ) );
        }
        return true;
    }
}
