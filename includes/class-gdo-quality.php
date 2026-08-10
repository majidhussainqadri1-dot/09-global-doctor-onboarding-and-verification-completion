<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Quality {
	public static function create_sample( $application_id, $reviewer_id, $decision, $source = 'automatic' ) {
		global $wpdb;
		$rate = max( 1, min( 100, absint( apply_filters( 'gdo_quality_sample_percent', 10 ) ) ) );
		if ( 'automatic' === $source && random_int( 1, 100 ) > $rate ) {
			return 0;
		}
		$ok = $wpdb->insert( GDO_Schema::table( 'quality_samples' ), array(
			'application_id'=>absint( $application_id ), 'reviewer_id'=>absint( $reviewer_id ),
			'original_decision'=>sanitize_key( $decision ), 'status'=>'pending', 'source'=>sanitize_key( $source ),
			'created_at'=>current_time( 'mysql', true ), 'updated_at'=>current_time( 'mysql', true ),
		), array( '%d','%d','%s','%s','%s','%s','%s' ) );
		return 1 === $ok ? absint( $wpdb->insert_id ) : 0;
	}

	public static function complete_sample( $sample_id, $auditor_id, $outcome, $reason ) {
		global $wpdb;
		$outcome = sanitize_key( $outcome );
		$reason = sanitize_textarea_field( $reason );
		$sample = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'quality_samples' ) . " WHERE id=%d AND status='pending'", absint( $sample_id ) ) );
		if ( ! $sample || absint( $sample->reviewer_id ) === absint( $auditor_id ) || ! in_array( $outcome, array( 'agree','minor_error','major_error' ), true ) || strlen( $reason ) < 10 ) {
			return new WP_Error( 'gdo_quality_invalid', __( 'A complete independent quality review is required.', 'global-doctor-onboarding' ) );
		}
		$updated = $wpdb->update( GDO_Schema::table( 'quality_samples' ), array(
			'status'=>'completed', 'outcome'=>$outcome, 'reason'=>$reason, 'auditor_id'=>absint( $auditor_id ),
			'completed_at'=>current_time( 'mysql', true ), 'updated_at'=>current_time( 'mysql', true ),
		), array( 'id'=>absint( $sample_id ), 'status'=>'pending' ), array( '%s','%s','%s','%d','%s','%s' ), array( '%d','%s' ) );
		return 1 === $updated ? true : new WP_Error( 'gdo_quality_conflict', __( 'The quality sample is no longer pending.', 'global-doctor-onboarding' ) );
	}

	public static function metrics( $reviewer_id, $days = 90 ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - max( 1, absint( $days ) ) * DAY_IN_SECONDS );
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT COUNT(*) total, SUM(outcome=\'agree\') agreed, SUM(outcome=\'minor_error\') minor_errors, SUM(outcome=\'major_error\') major_errors FROM ' . GDO_Schema::table( 'quality_samples' ) . ' WHERE reviewer_id=%d AND completed_at>=%s',
			absint( $reviewer_id ), $since
		), ARRAY_A );
		return array_map( 'absint', is_array( $row ) ? $row : array() );
	}
}
