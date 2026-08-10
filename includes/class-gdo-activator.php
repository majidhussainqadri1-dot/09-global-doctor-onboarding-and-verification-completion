<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Activator {
	public static function activate() {
		if ( ! GDO_Membership_Adapter::available() ) {
			deactivate_plugins( plugin_basename( GDO_FILE ) );
			wp_die( esc_html__( 'Activate a compatible File 00 Membership Core before File 09.', 'global-doctor-onboarding' ), '', array( 'back_link' => true ) );
		}
		if ( ! GDO_Membership_Adapter::authentication_available() ) {
			deactivate_plugins( plugin_basename( GDO_FILE ) );
			wp_die( esc_html__( 'Activate the compatible File 02 professional reauthentication contract before File 09.', 'global-doctor-onboarding' ), '', array( 'back_link' => true ) );
		}
		if ( ! GDO_Crypto::available() ) {
			deactivate_plugins( plugin_basename( GDO_FILE ) );
			wp_die( esc_html__( 'Configure a valid versioned GDO_KEYRING before activation.', 'global-doctor-onboarding' ), '', array( 'back_link' => true ) );
		}
		if ( ! defined( 'GDO_CLAIM_SIGNING_KEY' ) || strlen( (string) GDO_CLAIM_SIGNING_KEY ) < 32 ) {
			deactivate_plugins( plugin_basename( GDO_FILE ) );
			wp_die( esc_html__( 'Configure a private File 09 professional-claim signing key before activation.', 'global-doctor-onboarding' ), '', array( 'back_link' => true ) );
		}
		$health = GDO_Storage::health();
		if ( is_wp_error( $health ) ) {
			deactivate_plugins( plugin_basename( GDO_FILE ) );
			wp_die( esc_html( $health->get_error_message() ), '', array( 'back_link' => true ) );
		}
		$result = GDO_Migration::maybe_run();
		if ( is_wp_error( $result ) ) {
			deactivate_plugins( plugin_basename( GDO_FILE ) );
			wp_die( esc_html( $result->get_error_message() ), '', array( 'back_link' => true ) );
		}
		$result = GDO_Advanced_Trust_Hardening::maybe_upgrade_schema();
		if ( is_wp_error( $result ) ) {
			deactivate_plugins( plugin_basename( GDO_FILE ) );
			wp_die( esc_html( $result->get_error_message() ), '', array( 'back_link' => true ) );
		}
		self::page();
		$schedules = self::schedules();
		if ( is_wp_error( $schedules ) ) {
			deactivate_plugins( plugin_basename( GDO_FILE ) );
			wp_die( esc_html( $schedules->get_error_message() ), '', array( 'back_link'=>true ) );
		}
		update_option( 'gdo_version', GDO_VERSION, false );
		update_option( 'gdo_activation_evidence', array( 'version'=>GDO_VERSION, 'schema'=>GDO_SCHEMA_VERSION, 'advanced_trust_schema'=>GDO_Advanced_Trust_Hardening::SCHEMA_VERSION, 'advanced_trust_contract'=>GDO_Advanced_Trust_Hardening::CONTRACT_VERSION, 'review80_corrective_layer'=>true, 'activated_at'=>gmdate( 'c' ) ), false );
	}

	private static function schedules() {
		$specs = array(
			array( 'gdo_daily_retention', time() + HOUR_IN_SECONDS, 'daily', 'gdo_activation_retention_schedule' ),
			array( 'gdo_notification_outbox', time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'gdo_activation_outbox_schedule' ),
			array( 'gdo_trust_continuous_monitor', time() + 2 * HOUR_IN_SECONDS, 'daily', 'gdo_activation_trust_schedule' ),
		);
		foreach ( $specs as $spec ) {
			list( $hook, $timestamp, $recurrence, $error_code ) = $spec;
			if ( wp_next_scheduled( $hook ) ) { continue; }
			$scheduled = wp_schedule_event( $timestamp, $recurrence, $hook, array(), true );
			if ( is_wp_error( $scheduled ) || false === $scheduled || ! wp_next_scheduled( $hook ) ) {
				return new WP_Error( $error_code, __( 'A required File 09 maintenance schedule could not be persisted safely.', 'global-doctor-onboarding' ) );
			}
		}
		return true;
	}

	private static function page() {
		$key = 'doctor-application';
		$shortcode = '[gdo_doctor_application]';
		$owned = get_posts( array( 'post_type'=>'page', 'post_status'=>'any', 'meta_key'=>'_gdo_managed_page_key', 'meta_value'=>$key, 'numberposts'=>2 ) );
		if ( count( $owned ) > 1 ) {
			wp_die( esc_html__( 'Multiple File 09-owned application pages exist. Resolve the ownership conflict.', 'global-doctor-onboarding' ) );
		}
		if ( $owned ) {
			$id = $owned[0]->ID;
			if ( trim( $owned[0]->post_content ) !== $shortcode ) {
				wp_update_post( array( 'ID'=>$id, 'post_content'=>$shortcode ) );
			}
		} else {
			$slug = 'doctor-application';
			if ( get_page_by_path( $slug ) ) { $slug = 'doctor-application-file-09'; }
			$id = wp_insert_post( array( 'post_title'=>'Doctor Application and Verification', 'post_name'=>$slug, 'post_content'=>$shortcode, 'post_status'=>'publish', 'post_type'=>'page' ), true );
			if ( is_wp_error( $id ) ) { wp_die( esc_html( $id->get_error_message() ) ); }
			update_post_meta( $id, '_gdo_managed_page_key', $key );
		}
		update_option( 'gdo_page_map', array( 'apply'=>absint( $id ) ), false );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'gdo_daily_retention' );
		wp_clear_scheduled_hook( 'gdo_notification_outbox' );
		wp_clear_scheduled_hook( 'gdo_trust_continuous_monitor' );
		GDO_Membership_Adapter::clear_step_up();
	}
}
