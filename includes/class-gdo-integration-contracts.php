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
	const FILE14 = 'gdo.file14.onboarding-destination';
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
		add_filter( 'sabri_file09_onboarding_destination_v1', array( __CLASS__, 'file14_onboarding_destination' ), 10, 1 );
		add_filter( 'sabri_doctor_verification_public_projection_v1', array( __CLASS__, 'file03_public_projection' ), 10, 3 );
		add_filter( 'sabri_file09_verifiable_credentials_v1', array( __CLASS__, 'file03_verifiable_credentials' ), 10, 4 );
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

		$contracts[ self::FILE14 ] = array(
			'owner'                  => 'file09',
			'consumer'               => 'file14',
			'version'                => self::VERSION,
			'direction'              => 'read',
			'query'                  => 'gdo_file14_onboarding_destination',
			'availability_event'     => 'DoctorOnboardingAvailable.v1',
			'fail_closed'            => true,
			'authorization_recheck'  => 'owner_runtime_health',
			'writes_data'            => false,
			'automatic_enrollment'   => false,
			'automatic_verification' => false,
		);

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
	/**
	 * Public verification contracts use a calendar date for verification validity.
	 * File 09 stores the same owner truth in a DATETIME column, so normalize only
	 * at the public contract edge without mutating canonical storage.
	 *
	 * @param mixed $value Owner-stored validity value.
	 * @return string
	 */
	private static function public_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) { return ''; }
		if ( ! preg_match( '/^(\\d{4}-\\d{2}-\\d{2})(?:[ T]\\d{2}:\\d{2}:\\d{2})?$/', $value, $matches ) ) {
			return '';
		}
		$date = GDO_Policy::normalize_date( $matches[1] );
		return is_wp_error( $date ) ? '' : $date;
	}

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
			'verified_until'          => self::public_date( $decision['verified_until'] ?? '' ),
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
	 * Map the immutable File 09 approved snapshot to File 03's public professional
	 * contract. Never pass the raw application profile: phone/WhatsApp, declarations,
	 * clinic/address text and other application-only fields are not public verification
	 * facts and remain owned/private in File 09 or their canonical companion.
	 *
	 * File 03's current contract intentionally uses `licence_number` and
	 * `jurisdiction`; File 09 stores the canonical source fields as
	 * `license_number` and `license_jurisdiction`.
	 *
	 * @param array $profile Immutable approved File 09 profile snapshot.
	 * @return array
	 */
	private static function file03_public_fields( array $profile ) {
		$out = array();
		foreach ( array(
			'professional_title',
			'qualification',
			'licensing_authority',
			'experience_years',
			'specialty',
			'languages',
			'consultation_modes',
			'country',
			'city',
			'bio',
		) as $key ) {
			if ( ! array_key_exists( $key, $profile ) || ! is_scalar( $profile[ $key ] ) ) {
				continue;
			}
			if ( 'experience_years' === $key ) {
				$out[ $key ] = absint( $profile[ $key ] );
				continue;
			}
			$value = 'bio' === $key ? sanitize_textarea_field( (string) $profile[ $key ] ) : sanitize_text_field( (string) $profile[ $key ] );
			if ( '' !== $value ) {
				$out[ $key ] = $value;
			}
		}

		$licence_number = isset( $profile['license_number'] ) ? $profile['license_number'] : ( $profile['licence_number'] ?? '' );
		if ( is_scalar( $licence_number ) && '' !== trim( (string) $licence_number ) ) {
			$out['licence_number'] = sanitize_text_field( (string) $licence_number );
		}
		$jurisdiction = isset( $profile['license_jurisdiction'] ) ? $profile['license_jurisdiction'] : ( $profile['jurisdiction'] ?? '' );
		if ( is_scalar( $jurisdiction ) && '' !== trim( (string) $jurisdiction ) ) {
			$out['jurisdiction'] = sanitize_text_field( (string) $jurisdiction );
		}
		return $out;
	}

	/**
	 * Exact File 03 verification projection adapter.
	 * Raw application/evidence data never leaves File 09; only a contract-specific
	 * public allowlist from the immutable approved snapshot is projected.
	 */
	public static function file03_public_projection( $claim, $user_id, $consumer_contract = '' ) {
		unset( $claim, $consumer_contract );
		$user_id = absint( $user_id );
		$decision = self::projection( $user_id, 'file03' );
		if ( is_wp_error( $decision ) || ! is_array( $decision ) ) { return array(); }
		$snapshot = ! empty( $decision['verified'] ) ? GDO_API::snapshot( $user_id ) : array();
		$profile = is_array( $snapshot['profile'] ?? null ) ? self::file03_public_fields( $snapshot['profile'] ) : array();
		$status_map = array(
			'verified' => 'verified', 'reinstated' => 'verified', 'renewal_due' => 'verified',
			'under_review' => 'under_review', 'submitted' => 'under_review', 'resubmitted' => 'under_review',
			'more_information' => 'more_info', 'rejected' => 'rejected', 'suspended' => 'suspended',
			'revoked' => 'suspended', 'expired' => 'expired',
		);
		$state = sanitize_key( (string) ( $decision['state'] ?? 'pending' ) );
		$status = $status_map[ $state ] ?? 'pending';
		$reviewer_id = absint( $snapshot['finalizer_id'] ?? 0 );
		$reviewed_at = sanitize_text_field( (string) ( $snapshot['captured_at'] ?? '' ) );
		if ( 'verified' === $status && ( ! $reviewer_id || ! $reviewed_at || empty( $profile ) ) ) { return array(); }
		$now = time();
		return array(
			'user_id' => $user_id,
			'status' => $status,
			'approved_fields' => $profile,
			'reviewer_id' => $reviewer_id,
			'reviewed_at' => $reviewed_at,
			'generated_at' => gmdate( 'c', $now ),
			'valid_until' => gmdate( 'c', $now + 300 ),
			'claim_version' => '1.0.' . max( 1, absint( $decision['claim_version'] ?? 1 ) ),
			'contract_version' => self::VERSION,
			'issuer' => 'file09',
		);
	}

	/**
	 * Public-safe credential-wallet projection for File 03 Future Superset.
	 * This read path never issues a passport. If a current File 09 passport already
	 * exists, its tracking-free public verification URL is attached.
	 */
	public static function file03_verifiable_credentials( $claim, $user_id, $viewer_id = 0, $consumer_contract = '' ) {
		unset( $claim, $viewer_id, $consumer_contract );
		$user_id = absint( $user_id );
		$owner_decision = GDO_API::latest_decision( $user_id );
		if ( ! is_array( $owner_decision ) || empty( $owner_decision['verified'] ) || empty( $owner_decision['application_id'] ) ) { return array(); }
		$snapshot = GDO_Application::approved_snapshot( absint( $owner_decision['application_id'] ) );
		$profile = is_array( $snapshot['profile'] ?? null ) ? $snapshot['profile'] : array();
		if ( empty( $profile ) ) { return array(); }

		$verification_url = '';
		if ( class_exists( 'GDO_Advanced_Trust' ) && is_callable( array( 'GDO_Advanced_Trust', 'active_passport_for_application' ) ) ) {
			$passport = GDO_Advanced_Trust::active_passport_for_application( absint( $owner_decision['application_id'] ) );
			if ( ! is_wp_error( $passport ) && $passport && ! empty( $passport->passport_uuid ) ) {
				$verified_passport = class_exists( 'GDO_Advanced_Trust_Hardening' ) && is_callable( array( 'GDO_Advanced_Trust_Hardening', 'verify_passport_uuid' ) )
					? GDO_Advanced_Trust_Hardening::verify_passport_uuid( $passport->passport_uuid )
					: GDO_Advanced_Trust::verify_passport_uuid( $passport->passport_uuid );
				if ( ! is_wp_error( $verified_passport ) ) {
					$verification_url = rest_url( GDO_Advanced_Trust::REST_NAMESPACE . '/public/passport/' . rawurlencode( (string) $passport->passport_uuid ) );
				}
			}
		}

		$items = array();
		$push = static function ( $type, $name, $issuer, $issued_at, $expires_at, $verification_url = '' ) use ( &$items ) {
			$name = sanitize_text_field( (string) $name );
			if ( '' === $name ) { return; }
			$issuer = sanitize_text_field( (string) $issuer );
			$url = $verification_url ? esc_url_raw( (string) $verification_url, array( 'https' ) ) : '';
			$items[] = array(
				'id' => substr( hash( 'sha256', sanitize_key( (string) $type ) . '|' . $name . '|' . $issuer ), 0, 32 ),
				'type' => sanitize_key( (string) $type ),
				'name' => $name,
				'issuer' => $issuer,
				'issued_at' => sanitize_text_field( (string) $issued_at ),
				'expires_at' => sanitize_text_field( (string) $expires_at ),
				'format' => 'platform_record',
				'verification_url' => $url,
				'verified' => true,
				'status' => 'current',
			);
		};
		$expires_at = (string) ( $owner_decision['verified_until'] ?? '' );
		$push( 'qualification', $profile['qualification'] ?? '', $profile['institution'] ?? '', $profile['credential_issued_at'] ?? '', $profile['credential_expires_at'] ?? $expires_at, $verification_url );
		$registration_number = $profile['license_number'] ?? ( $profile['licence_number'] ?? '' );
		$push( 'registration', $registration_number, $profile['licensing_authority'] ?? '', $profile['credential_issued_at'] ?? '', $profile['credential_expires_at'] ?? $expires_at, $verification_url );
		$now = time();
		return array(
			'contract_version' => self::VERSION,
			'user_id' => $user_id,
			'generated_at' => gmdate( 'c', $now ),
			'valid_until' => gmdate( 'c', $now + 300 ),
			'items' => array_slice( $items, 0, 25 ),
			'raw_evidence_exposed' => false,
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
	/**
	 * File 14 consumes only a stable destination/readiness contract. This endpoint
	 * never creates an application and never grants verification; those actions
	 * remain File 09 owner commands after the user reaches the canonical route.
	 *
	 * @param mixed $projection Existing filter value.
	 * @return array
	 */
	public static function file14_onboarding_destination( $projection = null ) {
		unset( $projection );
		$page_id = GDO_Plugin::page_id();
		$route_ready = $page_id > 0;
		$membership_ready = GDO_Membership_Adapter::available();
		$reauth_ready = GDO_Membership_Adapter::authentication_available();
		$core_schema_ready = absint( get_option( 'gdo_schema_version', 0 ) ) === GDO_SCHEMA_VERSION;
		$advanced_schema_ready = ! class_exists( 'GDO_Advanced_Trust_Hardening' )
			|| absint( get_option( 'gdo_advanced_trust_schema', 0 ) ) === GDO_Advanced_Trust_Hardening::SCHEMA_VERSION;
		$safe_mode = GDO_Operations::safe_mode();
		$accepting = $route_ready && $membership_ready && $reauth_ready && $core_schema_ready && $advanced_schema_ready && ! $safe_mode && GDO_Operations::mutation_allowed();

		$reason = 'available';
		if ( ! $route_ready ) {
			$reason = 'application_route_unavailable';
		} elseif ( ! $membership_ready ) {
			$reason = 'identity_dependency_unavailable';
		} elseif ( ! $reauth_ready ) {
			$reason = 'reauthentication_unavailable';
		} elseif ( ! $core_schema_ready || ! $advanced_schema_ready ) {
			$reason = 'schema_unavailable';
		} elseif ( $safe_mode ) {
			$reason = 'safe_mode';
		} elseif ( ! $accepting ) {
			$reason = 'verification_temporarily_unavailable';
		}

		return array(
			'contract_version'       => self::VERSION,
			'owner'                  => 'file09',
			'consumer'               => 'file14',
			'canonical_url'          => $route_ready ? GDO_Plugin::application_url() : '',
			'available'              => (bool) $accepting,
			'accepting_applications' => (bool) $accepting,
			'reason_code'            => $reason,
			'checked_at'             => gmdate( 'c' ),
			'writes_data'            => false,
			'automatic_enrollment'   => false,
			'automatic_verification' => false,
		);
	}

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
function gdo_file14_onboarding_destination() { return GDO_Integration_Contracts::file14_onboarding_destination(); }
function gdo_file21_publishing_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file21' ); }
function gdo_file23_dashboard_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file23' ); }
function gdo_file26_search_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file26' ); }


/** Validate the exact File 03 public verification projection without trusting caller input. */
function gdo_validate_public_projection( $projection, $user_id, $consumer_contract = '' ) {
	unset( $consumer_contract );
	if ( ! is_array( $projection ) || absint( $projection['user_id'] ?? 0 ) !== absint( $user_id ) ) { return false; }
	if ( 'file09' !== sanitize_key( (string) ( $projection['issuer'] ?? '' ) ) ) { return false; }
	if ( empty( $projection['contract_version'] ) || version_compare( (string) $projection['contract_version'], GDO_Integration_Contracts::VERSION, '<' ) ) { return false; }
	$generated = strtotime( (string) ( $projection['generated_at'] ?? '' ) );
	$valid_until = strtotime( (string) ( $projection['valid_until'] ?? '' ) );
	if ( false === $generated || false === $valid_until || $generated > time() + 300 || $valid_until <= time() || $valid_until <= $generated ) { return false; }
	$current = GDO_Integration_Contracts::file03_public_projection( null, absint( $user_id ), (string) $consumer_contract );
	if ( ! is_array( $current ) || sanitize_key( (string) ( $current['status'] ?? '' ) ) !== sanitize_key( (string) ( $projection['status'] ?? '' ) ) ) { return false; }
	return hash_equals(
		hash( 'sha256', wp_json_encode( (array) ( $current['approved_fields'] ?? array() ) ) ),
		hash( 'sha256', wp_json_encode( (array) ( $projection['approved_fields'] ?? array() ) ) )
	);
}
