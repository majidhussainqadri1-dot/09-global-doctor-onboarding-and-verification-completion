<?php
defined( 'ABSPATH' ) || exit;

final class GDO_REST {
	const NAMESPACE_VERSION = 'sabri/file09/v1';

	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route( self::NAMESPACE_VERSION, '/eligibility', array(
			'methods'=>'GET', 'callback'=>array( $this, 'eligibility' ), 'permission_callback'=>array( $this, 'logged_in' ),
		) );
		register_rest_route( self::NAMESPACE_VERSION, '/application', array(
			array( 'methods'=>'GET', 'callback'=>array( $this, 'application' ), 'permission_callback'=>array( $this, 'logged_in' ) ),
			array( 'methods'=>'POST', 'callback'=>array( $this, 'autosave' ), 'permission_callback'=>array( $this, 'logged_in' ) ),
		) );
		register_rest_route( self::NAMESPACE_VERSION, '/health', array(
			'methods'=>'GET', 'callback'=>array( $this, 'health' ), 'permission_callback'=>array( $this, 'operator' ),
		) );
	}

	public function logged_in() {
		return is_user_logged_in();
	}

	public function operator() {
		$user_id = get_current_user_id();
		return $user_id && GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $user_id ) && GDO_Membership_Adapter::recent_step_up( $user_id );
	}

	public function eligibility( WP_REST_Request $request ) {
		return rest_ensure_response( GDO_Policy::eligibility( get_current_user_id(), array( 'jurisdiction'=>$request->get_param( 'jurisdiction' ) ) ) );
	}

	public function application() {
		global $wpdb;
		$wpdb->last_error = '';
		$app = GDO_Application::latest_for_user( get_current_user_id() );
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_application_read_failed', __( 'The private doctor application could not be read safely.', 'global-doctor-onboarding' ), array( 'status'=>503 ) );
		}
		return rest_ensure_response( GDO_API::application_edit_model( $app, get_current_user_id() ) );
	}

	public function autosave( WP_REST_Request $request ) {
		if ( ! GDO_Operations::mutation_allowed() ) {
			return new WP_Error( 'gdo_safe_mode', __( 'Doctor verification changes are temporarily unavailable.', 'global-doctor-onboarding' ), array( 'status'=>503 ) );
		}
		global $wpdb;
		$wpdb->last_error = '';
		$app = GDO_Application::get( absint( $request->get_param( 'application_id' ) ) );
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_application_autosave_read_failed', __( 'The private doctor application could not be read safely for autosave.', 'global-doctor-onboarding' ), array( 'status'=>503 ) );
		}
		if ( ! $app || absint( $app->user_id ) !== get_current_user_id() ) {
			return new WP_Error( 'gdo_application_denied', __( 'Application access denied.', 'global-doctor-onboarding' ), array( 'status'=>403 ) );
		}
		$profile = GDO_Application::sanitize_profile( (array) $request->get_param( 'profile' ) );
		$result = GDO_Application::save_draft( $app->id, get_current_user_id(), $profile, absint( $request->get_param( 'row_version' ) ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( GDO_API::application_edit_model( GDO_Application::get( $app->id ), get_current_user_id() ) );
	}

	public function health() {
		return rest_ensure_response( GDO_Operations::health() );
	}
}
