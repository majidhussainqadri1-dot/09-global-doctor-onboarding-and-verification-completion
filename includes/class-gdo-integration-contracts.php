<?php
defined( 'ABSPATH' ) || exit;

/**
 * Versioned public-safe integration contracts for File 09.
 *
 * File 09 remains the sole owner of doctor-verification application/evidence/
 * decision truth. Consumers receive minimized projections only and must recheck
 * the projection at action/click/index-delivery time.
 */
final class GDO_Integration_Contracts {
	const VERSION = '1.1.0';
	const OWNER = 'gdo.file09.owner-contract';
	const FILE03 = 'gdo.file03.doctor-profile-eligibility';
	const FILE07 = 'gdo.file07.directory-eligibility';
	const FILE08 = 'gdo.file08.clinic-eligibility';
	const FILE21 = 'gdo.file21.publishing-eligibility';
	const FILE23 = 'gdo.file23.publishing-dashboard-eligibility';
	const FILE26 = 'gdo.file26.doctor-verification-projection';
	const FILE19_EVENT = 'sun.event.v1';
	const FILE26_CONNECTOR = 'file09-doctor-verification';

	public function hooks() {
		add_filter( 'sabri_platform_contracts', array( __CLASS__, 'register' ) );
		add_filter( 'sabri_file26_connector_manifests', array( __CLASS__, 'file26_connector_manifests' ) );
		add_filter( 'sabri_file26_doctor_verification_projection', array( __CLASS__, 'file26_projection_filter' ), 10, 2 );
		add_filter( 'sabri_shell_page_contracts', array( __CLASS__, 'file20_page_contracts' ) );
	}

	/**
	 * Publish File 09 owner/read/event/index/notification boundaries.
	 *
	 * @param array $contracts Existing platform contracts.
	 * @return array
	 */
	public static function register( $contracts ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		$contracts[ self::OWNER ] = array(
			'owner'                  => 'file09',
			'version'                => self::VERSION,
			'canonical_entity'       => 'doctor_verification',
			'canonical_mutations'    => 'owner_commands_only',
			'direct_table_meta_write'=> false,
			'read_contracts'         => 'versioned_public_safe_projection',
			'event_contracts'        => 'versioned_past_tense_facts',
			'index_contract'         => self::FILE26,
			'notification_contract'  => self::FILE19_EVENT,
			'authorization_recheck'  => 'required_at_use_time',
			'fail_closed'            => true,
			'evidence_privacy_class' => 'C3-highly-restricted',
			'public_projection_class'=> 'C0-public-verification-only',
			'clinical_authorization' => false,
			'central_laws'           => array(
				'free_access'        => true,
				'donor_neutral'      => true,
				'paid_rank_advantage'=> false,
				'primary_brand_owner'=> 'file25',
				'primary_brand_green'=> true,
				'no_cure_guarantee'  => true,
			),
		);

		foreach ( self::identities() as $consumer => $identity ) {
			$contracts[ $identity ] = array(
				'owner'       => 'file09',
				'consumer'    => $consumer,
				'version'     => self::VERSION,
				'direction'   => 'read',
				'fail_closed' => true,
				'recheck'     => 'current_file00_and_file09_state',
				'fields'      => self::projection_fields(),
			);
		}

		$contracts['gdo.file19.notification-event'] = array(
			'owner'          => 'file09',
			'consumer'       => 'file19',
			'version'        => self::FILE19_EVENT,
			'direction'      => 'event',
			'producer'       => 'file09-doctor-verification',
			'canonical_owner'=> 'File 09',
			'fail_closed'    => true,
			'payload'        => 'minimized-no-evidence',
		);
		return $contracts;
	}

	/**
	 * Return a use-time-authorized public-safe verification projection.
	 *
	 * GDO_API::latest_decision() rechecks current File 00 membership/sanction,
	 * current claim acknowledgement, verification expiry and approved snapshot.
	 *
	 * @param int    $user_id User ID.
	 * @param string $consumer Consumer key.
	 * @return array|WP_Error
	 */
	public static function projection( $user_id, $consumer ) {
		$consumer = sanitize_key( $consumer );
		$identities = self::identities();
		if ( ! isset( $identities[ $consumer ] ) ) {
			return new WP_Error( 'gdo_contract_consumer', __( 'Unknown doctor-verification contract consumer.', 'global-doctor-onboarding' ) );
		}
		$decision = GDO_API::latest_decision( absint( $user_id ) );
		$verified = ! empty( $decision['verified'] );
		$state = isset( $decision['state'] ) ? sanitize_key( $decision['state'] ) : 'unavailable';
		$reason = $verified ? 'verified_current' : self::reason_code( $state, $decision );
		return array(
			'contract'                => $identities[ $consumer ],
			'version'                 => self::VERSION,
			'consumer'                => $consumer,
			'source_of_truth'          => 'file09',
			'user_id'                 => absint( $user_id ),
			'application_uuid'        => isset( $decision['application_uuid'] ) ? (string) $decision['application_uuid'] : '',
			'application_version'     => isset( $decision['version'] ) ? absint( $decision['version'] ) : 0,
			'state'                   => $state,
			'verified'                => $verified,
			'limited'                 => ! empty( $decision['limited'] ),
			'eligible'                => $verified,
			'reason_code'             => $reason,
			'verified_until'          => isset( $decision['verified_until'] ) ? (string) $decision['verified_until'] : '',
			'fingerprint'             => $verified && isset( $decision['fingerprint'] ) ? (string) $decision['fingerprint'] : '',
			'claim_version'           => isset( $decision['claim_version'] ) ? absint( $decision['claim_version'] ) : 0,
			'claim_status'            => isset( $decision['claim_status'] ) ? sanitize_key( $decision['claim_status'] ) : '',
			'authorization_rechecked' => true,
			'privacy_class'           => 'c0_public_verification_projection',
			'evidence_exposed'        => false,
			'clinical_authorization'  => false,
			'donor_rank_advantage'    => false,
			'checked_at'              => isset( $decision['checked_at'] ) ? (string) $decision['checked_at'] : gmdate( 'c' ),
		);
	}

	/**
	 * Register a File 26 compatibility manifest without making private File 09
	 * applications searchable. The connector remains contract-tested until a
	 * public doctor owner (File 03/07) composes a canonical doctor document.
	 *
	 * @param array $manifests Existing manifests.
	 * @return array
	 */
	public static function file26_connector_manifests( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests[] = array(
			'slug'               => self::FILE26_CONNECTOR,
			'owner_file'         => '09',
			'contract_version'   => self::VERSION,
			'entity_types'       => array( 'doctor_verification' ),
			'privacy_classes'    => array( 'c0_public_verification_projection' ),
			'visibility_fields'  => array( 'verified', 'limited', 'state', 'verified_until', 'claim_version' ),
			'deletion_semantics' => 'restrict_on_verification_loss',
			'status'             => 'contract_tested',
			'can_view'           => array( __CLASS__, 'file26_can_view' ),
			'health'             => array( __CLASS__, 'file26_health' ),
		);
		return $manifests;
	}

	/**
	 * Optional File 26 enrichment hook: return only current verification truth.
	 *
	 * @param mixed $projection Existing projection.
	 * @param int   $user_id User ID.
	 * @return array|WP_Error
	 */
	public static function file26_projection_filter( $projection, $user_id ) {
		$current = self::projection( absint( $user_id ), 'file26' );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		return is_array( $projection ) ? array_merge( $projection, $current ) : $current;
	}

	/**
	 * File 26 must fail closed if it ever asks File 09 to authorize an indexed
	 * verification document. Private application/evidence data are never exposed.
	 *
	 * @param array $document Indexed document candidate.
	 * @param array $audience Audience context.
	 * @return bool
	 */
	public static function file26_can_view( array $document, array $audience ) {
		unset( $audience );
		$user_id = 0;
		if ( isset( $document['payload'] ) && is_array( $document['payload'] ) && isset( $document['payload']['user_id'] ) ) {
			$user_id = absint( $document['payload']['user_id'] );
		} elseif ( isset( $document['user_id'] ) ) {
			$user_id = absint( $document['user_id'] );
		}
		if ( ! $user_id ) {
			return false;
		}
		$projection = self::projection( $user_id, 'file26' );
		return is_array( $projection ) && ! empty( $projection['eligible'] );
	}

	/** @return array */
	public static function file26_health() {
		return array(
			'state'            => GDO_Membership_Adapter::available() ? 'healthy' : 'degraded',
			'contract_version' => self::VERSION,
			'owner'            => 'file09',
			'private_indexing' => false,
		);
	}

	/**
	 * Declare File 09's managed page to the sole File 20 application shell.
	 *
	 * @param array $contracts File 20 page contracts.
	 * @return array
	 */
	public static function file20_page_contracts( $contracts ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		$contracts['doctor_application'] = array( array( 'gdo_page_map', 'apply' ) );
		return $contracts;
	}

	private static function identities() {
		return array(
			'file03' => self::FILE03,
			'file07' => self::FILE07,
			'file08' => self::FILE08,
			'file21' => self::FILE21,
			'file23' => self::FILE23,
			'file26' => self::FILE26,
		);
	}

	private static function projection_fields() {
		return array(
			'contract', 'version', 'consumer', 'source_of_truth', 'user_id', 'application_uuid',
			'application_version', 'state', 'verified', 'limited', 'eligible', 'reason_code',
			'verified_until', 'fingerprint', 'claim_version', 'claim_status',
			'authorization_rechecked', 'privacy_class', 'evidence_exposed',
			'clinical_authorization', 'donor_rank_advantage', 'checked_at',
		);
	}

	private static function reason_code( $state, array $decision ) {
		if ( isset( $decision['claim_status'] ) && 'accepted' !== sanitize_key( $decision['claim_status'] ) && in_array( $state, array( 'verified', 'reinstated', 'renewal_due' ), true ) ) {
			return 'file00_claim_not_current';
		}
		$map = array(
			'not_applied'      => 'not_applied',
			'draft'            => 'application_incomplete',
			'submitted'        => 'verification_pending',
			'resubmitted'      => 'verification_pending',
			'under_review'     => 'verification_under_review',
			'more_information' => 'more_information_required',
			'recommended'      => 'decision_pending',
			'rejected'         => 'verification_rejected',
			'suspended'        => 'verification_suspended',
			'revoked'          => 'verification_revoked',
			'expired'          => 'verification_expired',
			'renewal_due'      => 'renewal_due',
			'appeal_pending'   => 'appeal_pending',
			'withdrawn'        => 'application_withdrawn',
		);
		return isset( $map[ $state ] ) ? $map[ $state ] : 'verification_unavailable';
	}
}

function gdo_file03_doctor_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file03' ); }
function gdo_file07_directory_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file07' ); }
function gdo_file08_clinic_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file08' ); }
function gdo_file21_publishing_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file21' ); }
function gdo_file23_dashboard_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file23' ); }
function gdo_file26_search_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file26' ); }
