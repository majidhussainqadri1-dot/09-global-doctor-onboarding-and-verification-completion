<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Application {
	public static function fields() {
		return array(
			'display_name','country','city','clinic','professional_title','qualification','license_number',
			'licensing_authority','license_jurisdiction','experience_years','specialty','services','languages',
			'consultation_modes','phone','whatsapp','bio','declaration_accuracy','declaration_no_impersonation',
			'declaration_professional_scope',
		);
	}

	public static function get( $application_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d', absint( $application_id ) ) );
	}

	public static function latest_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE user_id=%d ORDER BY version DESC LIMIT 1', absint( $user_id ) ) );
	}

	public static function verification_record_for_user( $user_id ) {
		$user_id = absint( $user_id );
		$latest = self::latest_for_user( $user_id );
		if ( ! $latest ) {
			return null;
		}
		$renewal_in_progress = array( 'draft','submitted','under_review','more_information','resubmitted','recommended' );
		if ( ! empty( $latest->renewed_from_id ) && in_array( sanitize_key( $latest->state ), $renewal_in_progress, true ) ) {
			$prior = self::get( $latest->renewed_from_id );
			$expires = $prior && ! empty( $prior->verified_until ) ? strtotime( $prior->verified_until . ' UTC' ) : 0;
			if ( $prior && absint( $prior->user_id ) === $user_id && GDO_State::public_verified( $prior->state ) && $expires > time() ) {
				return $prior;
			}
		}
		return $latest;
	}

	public static function sanitize_profile( array $source ) {
		$data = array();
		foreach ( self::fields() as $field ) {
			$value = isset( $source[ $field ] ) ? wp_unslash( $source[ $field ] ) : '';
			if ( 0 === strpos( $field, 'declaration_' ) ) {
				$data[ $field ] = ! empty( $value ) ? '1' : '';
				continue;
			}
			$value = 'bio' === $field ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			if ( in_array( $field, array( 'phone','whatsapp' ), true ) ) {
				$value = preg_replace( '/[^0-9+() .-]/', '', $value );
			}
			$data[ $field ] = trim( (string) $value );
		}
		return $data;
	}

	public static function validate_profile( array $profile, $allow_incomplete = false, $jurisdiction = '', $application_type = 'homeopathic_doctor' ) {
		$required = GDO_Policy::required_fields( $jurisdiction, $application_type );
		if ( ! $allow_incomplete ) {
			foreach ( $required as $field ) {
				if ( ! isset( $profile[ $field ] ) || '' === trim( (string) $profile[ $field ] ) ) {
					return new WP_Error( 'gdo_profile_incomplete', sprintf( __( 'Complete the required field: %s.', 'global-doctor-onboarding' ), $field ) );
				}
			}
		}
		$limits = array(
			'display_name'=>150,'country'=>100,'city'=>120,'clinic'=>240,'professional_title'=>160,
			'qualification'=>240,'license_number'=>120,'licensing_authority'=>240,'license_jurisdiction'=>100,
			'specialty'=>180,'services'=>500,'languages'=>240,'consultation_modes'=>240,
			'phone'=>40,'whatsapp'=>40,'bio'=>4000,
		);
		foreach ( $limits as $field => $maximum ) {
			$value = isset( $profile[ $field ] ) ? (string) $profile[ $field ] : '';
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
			if ( $length > $maximum ) {
				return new WP_Error( 'gdo_profile_length', sprintf( __( 'The field %s is too long.', 'global-doctor-onboarding' ), $field ) );
			}
		}
		if ( ! empty( $profile['experience_years'] ) && ( ! ctype_digit( (string) $profile['experience_years'] ) || absint( $profile['experience_years'] ) > 80 ) ) {
			return new WP_Error( 'gdo_experience', __( 'Professional experience must be a valid number of years.', 'global-doctor-onboarding' ) );
		}
		foreach ( array( 'phone','whatsapp' ) as $field ) {
			$value = isset( $profile[ $field ] ) ? trim( (string) $profile[ $field ] ) : '';
			if ( '' === $value && ( $allow_incomplete || ! in_array( $field, $required, true ) ) ) {
				continue;
			}
			$digits = preg_replace( '/\D+/', '', $value );
			if ( strlen( $digits ) < 7 || strlen( $digits ) > 18 ) {
				return new WP_Error( 'gdo_phone', __( 'Provide a valid professional contact number.', 'global-doctor-onboarding' ) );
			}
		}
		return true;
	}

	public static function fingerprint( array $profile, array $evidence = array() ) {
		ksort( $profile );
		ksort( $evidence );
		return hash( 'sha256', wp_json_encode( array( 'profile'=>$profile, 'evidence'=>$evidence ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	public static function completeness( $application ) {
		if ( ! $application ) {
			return array( 'complete'=>false, 'missing_fields'=>array(), 'missing_evidence'=>array(), 'consent'=>false );
		}
		$profile = json_decode( $application->profile_json, true );
		$profile = is_array( $profile ) ? $profile : array();
		$missing_fields = array();
		foreach ( GDO_Policy::required_fields( $application->jurisdiction, $application->application_type ) as $field ) {
			if ( empty( $profile[ $field ] ) ) {
				$missing_fields[] = $field;
			}
		}
		$missing_evidence = array();
		$query_error = false;
		global $wpdb;
		foreach ( array_keys( GDO_Policy::evidence_types( $application->jurisdiction, $application->application_type ) ) as $type ) {
			$wpdb->last_error = '';
			$record = GDO_Evidence::current( $application->id, $type );
			if ( null === $record && ! empty( $wpdb->last_error ) ) { $query_error = true; }
			if ( ! $record
				|| ! in_array( $record->status, array( 'pending_review','accepted' ), true )
				|| ( ! empty( $record->expires_at ) && strtotime( $record->expires_at . ' UTC' ) <= time() ) ) {
				$missing_evidence[] = $type;
			}
		}
		$wpdb->last_error = '';
		$consent = self::active_consent( $application );
		if ( ! empty( $wpdb->last_error ) ) { $query_error = true; }
		return array(
			'complete'=>! $query_error && ! $missing_fields && ! $missing_evidence && $consent,
			'query_error'=>$query_error,
			'missing_fields'=>$missing_fields,
			'missing_evidence'=>$missing_evidence,
			'consent'=>$consent,
			'policy_version'=>GDO_Policy::VERSION,
			'terms_version'=>GDO_Policy::TERMS_VERSION,
		);
	}

	private static function create_draft( $user_id, $version, array $profile, $renewed_from_id = 0 ) {
		if ( ! GDO_Operations::mutation_allowed() ) { return new WP_Error( 'gdo_safe_mode', __( 'Doctor verification changes are temporarily unavailable.', 'global-doctor-onboarding' ) ); }
		global $wpdb;
		$requested_jurisdiction = ! empty( $profile['license_jurisdiction'] ) ? GDO_Policy::normalize_jurisdiction( $profile['license_jurisdiction'] ) : '';
		$eligibility = GDO_Policy::eligibility( $user_id, array( 'jurisdiction'=>$requested_jurisdiction ) );
		if ( empty( $eligibility['eligible'] ) ) {
			return new WP_Error( 'gdo_not_eligible_' . sanitize_key( $eligibility['reason_code'] ), __( 'This account is not eligible to start a new doctor application.', 'global-doctor-onboarding' ) );
		}
		$now = current_time( 'mysql', true );
		$jurisdiction = $requested_jurisdiction ? $requested_jurisdiction : ( ! empty( $eligibility['jurisdiction'] ) ? $eligibility['jurisdiction'] : '' );
		$data = array(
			'application_uuid'=>wp_generate_uuid4(), 'user_id'=>absint( $user_id ), 'version'=>absint( $version ),
			'application_type'=>'homeopathic_doctor', 'jurisdiction'=>$jurisdiction, 'preferred_language'=>get_user_locale( $user_id ),
			'state'=>'draft', 'row_version'=>1,
			'profile_json'=>wp_json_encode( $profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'profile_fingerprint'=>self::fingerprint( $profile ), 'identity_fingerprint'=>GDO_Risk::identity_fingerprint( $profile ),
			'policy_version'=>GDO_Policy::VERSION, 'terms_version'=>'', 'draft_expires_at'=>GDO_Policy::draft_expiry(),
			'renewed_from_id'=>$renewed_from_id ? absint( $renewed_from_id ) : null,
			'created_at'=>$now, 'updated_at'=>$now,
		);
		$formats = array( '%s','%d','%d','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s' );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'gdo_application_transaction', __( 'The private application transaction could not be started safely.', 'global-doctor-onboarding' ) );
		}
		$inserted = $wpdb->insert( GDO_Schema::table( 'applications' ), $data, $formats );
		if ( 1 !== $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			$wpdb->last_error = '';
			$existing = self::latest_for_user( $user_id );
			if ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_application_create_recovery_query', __( 'The application creation result could not be verified safely.', 'global-doctor-onboarding' ) ); }
			return $existing && absint( $existing->version ) === absint( $version ) ? $existing : new WP_Error( 'gdo_application_create', __( 'A private doctor application could not be created.', 'global-doctor-onboarding' ) );
		}
		$wpdb->last_error = '';
		$app = self::get( $wpdb->insert_id );
		if ( null === $app && ! empty( $wpdb->last_error ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'gdo_application_create_reload_query', __( 'The newly created application could not be reloaded safely.', 'global-doctor-onboarding' ) ); }
		$audit = $app ? GDO_Audit::transition( $app->id, $user_id, 'none', 'draft', 'application_created', 'Applicant created a private doctor application.' ) : new WP_Error( 'gdo_application_create', __( 'The private application could not be loaded.', 'global-doctor-onboarding' ) );
		if ( is_wp_error( $audit ) || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return is_wp_error( $audit ) ? $audit : new WP_Error( 'gdo_application_commit', __( 'The private application could not be committed.', 'global-doctor-onboarding' ) );
		}
		GDO_Audit::publish_transition( $audit );
		return $app;
	}

	public static function ensure_draft( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$wpdb->last_error = '';
		$latest = self::latest_for_user( $user_id );
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_application_latest_query', __( 'The current doctor application state could not be read safely.', 'global-doctor-onboarding' ) );
		}
		if ( $latest && in_array( $latest->state, array( 'draft','more_information' ), true ) ) {
			if ( 'draft' === $latest->state && $latest->draft_expires_at && strtotime( $latest->draft_expires_at . ' UTC' ) < time() ) {
				return new WP_Error( 'gdo_draft_expired', __( 'This draft expired. Start a new application after the expired draft is safely closed.', 'global-doctor-onboarding' ) );
			}
			return $latest;
		}
		if ( $latest && in_array( $latest->state, array( 'expired','renewal_due' ), true ) ) {
			$profile = json_decode( $latest->profile_json, true );
			return self::create_draft( $user_id, absint( $latest->version ) + 1, is_array( $profile ) ? $profile : array_fill_keys( self::fields(), '' ), $latest->id );
		}
		if ( $latest ) {
			return new WP_Error( 'gdo_application_locked', __( 'The current application cannot be edited in its present state. Use the status, appeal, or lifecycle process.', 'global-doctor-onboarding' ) );
		}
		$eligibility = GDO_Policy::eligibility( $user_id );
		if ( empty( $eligibility['eligible'] ) ) {
			return new WP_Error( 'gdo_not_eligible_' . sanitize_key( $eligibility['reason_code'] ), __( 'File 00 has not approved this account for the doctor application workflow.', 'global-doctor-onboarding' ) );
		}
		return self::create_draft( $user_id, 1, array_fill_keys( self::fields(), '' ) );
	}

	public static function save_draft( $application_id, $user_id, array $profile, $expected_row_version, $allow_incomplete = false ) {
		global $wpdb;
		$wpdb->last_error = '';
		$app = self::get( $application_id );
		if ( null === $app && ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_draft_application_query', __( 'The draft application could not be read safely.', 'global-doctor-onboarding' ) );
		}
		if ( ! GDO_Operations::mutation_allowed() ) {
			return new WP_Error( 'gdo_safe_mode', __( 'Doctor verification changes are temporarily unavailable.', 'global-doctor-onboarding' ) );
		}
		$jurisdiction = $app && ! empty( $profile['license_jurisdiction'] ) ? GDO_Policy::normalize_jurisdiction( $profile['license_jurisdiction'] ) : ( $app ? $app->jurisdiction : '' );
		if ( ! $app || absint( $app->user_id ) !== absint( $user_id ) || ! in_array( $app->state, array( 'draft','more_information' ), true ) || ! GDO_Membership_Adapter::is_active_doctor_candidate( $user_id, $jurisdiction ) ) {
			return new WP_Error( 'gdo_draft_access', __( 'This application cannot be edited.', 'global-doctor-onboarding' ) );
		}
		$valid = self::validate_profile( $profile, $allow_incomplete, $jurisdiction, $app->application_type );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$updated = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . GDO_Schema::table( 'applications' ) . ' SET profile_json=%s,profile_fingerprint=%s,identity_fingerprint=%s,jurisdiction=%s,policy_version=%s,row_version=row_version+1,updated_at=%s WHERE id=%d AND row_version=%d',
			wp_json_encode( $profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), self::fingerprint( $profile ), GDO_Risk::identity_fingerprint( $profile ), $jurisdiction, GDO_Policy::VERSION, current_time( 'mysql', true ), absint( $application_id ), absint( $expected_row_version )
		) );
		if ( false === $updated ) { return new WP_Error( 'gdo_draft_store_failed', __( 'The draft application could not be stored safely.', 'global-doctor-onboarding' ) ); }
		return 1 === $updated ? true : new WP_Error( 'gdo_concurrent_change', __( 'The application changed. Reload and try again.', 'global-doctor-onboarding' ) );
	}

	public static function active_consent( $application_or_id ) {
		global $wpdb;
		$app = is_object( $application_or_id ) ? $application_or_id : self::get( $application_or_id );
		if ( ! $app || empty( $app->consent_version ) || ! hash_equals( GDO_Policy::TERMS_VERSION, (string) $app->terms_version ) ) {
			return false;
		}
		$text = self::consent_text();
		if ( ! hash_equals( (string) $text['version'], (string) $app->consent_version ) ) {
			return false;
		}
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT consent_version,wording_hash,accepted_at,withdrawn_at FROM ' . GDO_Schema::table( 'consents' ) . ' WHERE application_id=%d AND user_id=%d AND consent_version=%s LIMIT 1',
			absint( $app->id ),
			absint( $app->user_id ),
			$text['version']
		) );
		return $row
			&& ! empty( $row->accepted_at )
			&& empty( $row->withdrawn_at )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/i', (string) $row->wording_hash )
			&& hash_equals( hash( 'sha256', $text['wording'] ), (string) $row->wording_hash );
	}

	public static function consent_text() {
		return array(
			'version'=>GDO_Policy::TERMS_VERSION,
			'wording'=>'I certify that the professional information and credential evidence are authentic, current, belong to me, and do not impersonate another person. I consent to private processing for professional verification, safety, fraud prevention, audit, appeal, renewal, and legal accountability.',
			'purpose'=>'Doctor identity, qualification, license, professional-scope verification, safety, fraud prevention, audit, appeal, renewal, and platform eligibility.',
			'retention'=>'Credential evidence is retained only for active review, verification, renewal, appeal, legal hold, and configured accountability periods. Verified physical erasure is applied when eligible.',
		);
	}

	public static function record_consent( $application_id, $user_id, $accepted, $manage_transaction = true ) {
		if ( ! GDO_Operations::mutation_allowed() ) { return new WP_Error( 'gdo_safe_mode', __( 'Doctor verification changes are temporarily unavailable.', 'global-doctor-onboarding' ) ); }
		global $wpdb;
		$application_id = absint( $application_id );
		$user_id = absint( $user_id );
		if ( ! $accepted ) {
			return new WP_Error( 'gdo_consent_required', __( 'Credential-processing consent is required.', 'global-doctor-onboarding' ) );
		}
		if ( $manage_transaction && false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'gdo_consent_transaction', __( 'The consent transaction could not be started safely.', 'global-doctor-onboarding' ) );
		}
		$wpdb->last_error = '';
		$app = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
			$application_id,
			$user_id
		) );
		if ( null === $app && ! empty( $wpdb->last_error ) ) {
			if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'gdo_consent_application_query', __( 'The consent application state could not be read safely.', 'global-doctor-onboarding' ) );
		}
		if ( ! $app || ! in_array( $app->state, array( 'draft','more_information' ), true ) || ! GDO_Membership_Adapter::is_active_doctor_candidate( $user_id, $app->jurisdiction ) ) {
			if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'gdo_consent_access', __( 'Consent cannot be recorded for this application.', 'global-doctor-onboarding' ) );
		}

		$text = self::consent_text();
		$wording_hash = hash( 'sha256', $text['wording'] );
		$table = GDO_Schema::table( 'consents' );
		$wpdb->last_error = '';
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE application_id=%d AND user_id=%d AND consent_version=%s FOR UPDATE",
			$application_id,
			$user_id,
			$text['version']
		) );
		if ( null === $existing && ! empty( $wpdb->last_error ) ) {
			if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'gdo_consent_history_query', __( 'Existing consent evidence could not be read safely.', 'global-doctor-onboarding' ) );
		}
		if ( $existing && ( ! empty( $existing->withdrawn_at ) || ! hash_equals( $wording_hash, (string) $existing->wording_hash ) ) ) {
			if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'gdo_consent_history_conflict', __( 'Existing consent evidence cannot be overwritten. Reload or begin a new application if consent terms changed.', 'global-doctor-onboarding' ) );
		}

		if ( ! $existing ) {
			$accepted_at = current_time( 'mysql', true );
			$evidence_hash = hash( 'sha256', $application_id . '|' . $user_id . '|' . $text['version'] . '|' . $accepted_at . '|' . $wording_hash );
			$inserted = $wpdb->insert( $table, array(
				'application_id'=>$application_id, 'user_id'=>$user_id, 'consent_version'=>$text['version'],
				'wording_hash'=>$wording_hash, 'purpose'=>$text['purpose'], 'retention_notice'=>$text['retention'],
				'lawful_basis'=>'consent_and_professional_verification', 'accepted_at'=>$accepted_at, 'withdrawn_at'=>null, 'evidence_hash'=>$evidence_hash,
			), array( '%d','%d','%s','%s','%s','%s','%s','%s','%s','%s' ) );
			if ( 1 !== $inserted ) {
				if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
				return new WP_Error( 'gdo_consent_store', __( 'Consent evidence could not be stored.', 'global-doctor-onboarding' ) );
			}
		}

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE " . GDO_Schema::table( 'applications' ) . " SET consent_version=%s,terms_version=%s,row_version=row_version+1,updated_at=%s WHERE id=%d AND user_id=%d AND state IN ('draft','more_information') AND row_version=%d",
			$text['version'],
			GDO_Policy::TERMS_VERSION,
			current_time( 'mysql', true ),
			$application_id,
			$user_id,
			absint( $app->row_version )
		) );
		if ( false === $updated ) {
			if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'gdo_consent_link_store', __( 'Consent could not be linked because the application write failed.', 'global-doctor-onboarding' ) );
		}
		if ( 1 !== $updated ) {
			if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'gdo_consent_link', __( 'Consent could not be linked to the current application state.', 'global-doctor-onboarding' ) );
		}
		if ( $manage_transaction && false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gdo_consent_commit', __( 'Consent evidence could not be committed.', 'global-doctor-onboarding' ) );
		}
		return true;
	}

	public static function submit( $application_id, $user_id, $expected_row_version ) {
		global $wpdb;
		$application_id = absint( $application_id );
		$user_id = absint( $user_id );
		$expected_row_version = absint( $expected_row_version );
		if ( ! GDO_Operations::mutation_allowed() || ! $application_id || ! $user_id ) {
			return new WP_Error( 'gdo_submit_access', __( 'The application cannot be submitted.', 'global-doctor-onboarding' ) );
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'gdo_submit_transaction', __( 'The application submission transaction could not be started safely.', 'global-doctor-onboarding' ) );
		}
		$wpdb->last_error = '';
		$app = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
			$application_id,
			$user_id
		) );
		if ( null === $app && ! empty( $wpdb->last_error ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gdo_submit_application_query', __( 'The application submission state could not be read safely.', 'global-doctor-onboarding' ) );
		}
		if ( ! $app || ! GDO_Membership_Adapter::is_active_doctor_candidate( $user_id, $app->jurisdiction ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gdo_submit_access', __( 'The application cannot be submitted.', 'global-doctor-onboarding' ) );
		}

		$profile = json_decode( $app->profile_json, true );
		$profile = is_array( $profile ) ? $profile : array();
		$valid = self::validate_profile( $profile, false, $app->jurisdiction, $app->application_type );
		$complete = self::completeness( $app );
		if ( ! empty( $complete['query_error'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gdo_submit_completeness_query', __( 'Application completeness could not be verified safely because a database read failed.', 'global-doctor-onboarding' ) );
		}
		if ( is_wp_error( $valid ) || empty( $complete['complete'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gdo_submit_incomplete', __( 'Complete all profile, declaration, consent, and current credential requirements before submission.', 'global-doctor-onboarding' ) );
		}

		// Evidence is read only after the application row has been locked.
		// stage_upload() now takes the same row lock before replacing evidence, so
		// the immutable submission hash and the credential set cannot diverge.
		$evidence_records = GDO_Evidence::records_checked( $app->id, true );
		if ( is_wp_error( $evidence_records ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gdo_submit_evidence_query', __( 'The immutable credential snapshot could not be read safely for submission.', 'global-doctor-onboarding' ) );
		}
		$evidence = array();
		foreach ( $evidence_records as $record ) {
			$evidence[ $record->document_type ] = array(
				'version'=>absint( $record->version ),
				'source_sha256'=>(string) $record->source_sha256,
				'content_hmac'=>(string) $record->content_hmac,
				'status'=>(string) $record->status,
			);
		}
		$submission = array(
			'application_uuid'=>$app->application_uuid,
			'application_version'=>absint( $app->version ),
			'profile_fingerprint'=>self::fingerprint( $profile ),
			'evidence'=>$evidence,
			'consent_version'=>$app->consent_version,
			'terms_version'=>$app->terms_version,
			'policy_version'=>GDO_Policy::VERSION,
		);
		$submission_hash = hash( 'sha256', GDO_Claims::canonical_json( $submission ) );

		// Preserve request idempotency after a successful submit without allowing
		// a different snapshot to masquerade as the same submission.
		if ( in_array( $app->state, array( 'submitted','resubmitted' ), true )
			&& ! empty( $app->submission_hash )
			&& hash_equals( (string) $app->submission_hash, $submission_hash ) ) {
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'gdo_submit_idempotent_commit', __( 'The existing application submission could not be confirmed safely.', 'global-doctor-onboarding' ) );
			}
			return true;
		}
		if ( ! in_array( $app->state, array( 'draft','more_information' ), true )
			|| absint( $app->row_version ) !== $expected_row_version ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gdo_submit_state', __( 'The application state changed. Reload and try again.', 'global-doctor-onboarding' ) );
		}

		// Risk signals are derived from the exact locked snapshot and are part of
		// the same transaction as the state transition.
		$risk_result = GDO_Risk::evaluate( $app, $profile, $evidence_records );
		if ( is_wp_error( $risk_result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $risk_result;
		}
		$to = 'draft' === $app->state ? 'submitted' : 'resubmitted';
		$result = GDO_State::transition(
			$app->id,
			$to,
			$user_id,
			'application_submitted',
			'Applicant submitted a complete immutable application snapshot.',
			$app->row_version,
			false
		);
		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $result;
		}
		$updated = $wpdb->update(
			GDO_Schema::table( 'applications' ),
			array(
				'submitted_at'=>current_time( 'mysql', true ),
				'submission_hash'=>$submission_hash,
				'policy_version'=>GDO_Policy::VERSION,
				'terms_version'=>GDO_Policy::TERMS_VERSION,
				'recommended_decision'=>null,
				'recommendation_reason'=>null,
				'recommendation_at'=>null,
			),
			array( 'id'=>$app->id, 'state'=>$to ),
			array( '%s','%s','%s','%s','%s','%s','%s' ),
			array( '%d','%s' )
		);
		$event = 1 !== $updated
			? new WP_Error( 'gdo_submit_update', __( 'The application submission could not be stored.', 'global-doctor-onboarding' ) )
			: GDO_Notifications::queue( 'doctor_application_submitted', $user_id, array( 'application_id'=>$app->id, 'version'=>$app->version, 'submission_hash'=>$submission_hash ), false );
		if ( 1 !== $updated || is_wp_error( $event ) || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return is_wp_error( $event ) ? $event : new WP_Error( 'gdo_submit_commit', __( 'The application submission could not be committed.', 'global-doctor-onboarding' ) );
		}
		GDO_Audit::publish_transition( $result );
		GDO_Notifications::process( 1, $event );
		do_action( 'gdo_application_submitted', $app->id, $user_id );
		return true;
	}

	public static function stored_approved_snapshot( $application_or_id ) {
		$app = is_object( $application_or_id ) ? $application_or_id : self::get( $application_or_id );
		if ( ! $app || empty( $app->approved_snapshot_json ) || empty( $app->approved_fingerprint ) ) {
			return array();
		}
		$snapshot = json_decode( $app->approved_snapshot_json, true );
		$schema = is_array( $snapshot ) && isset( $snapshot['schema'] ) ? absint( $snapshot['schema'] ) : 0;
		if ( ! is_array( $snapshot ) || $schema < 3 || ! defined( 'GDO_SCHEMA_VERSION' ) || $schema > absint( GDO_SCHEMA_VERSION )
			|| empty( $snapshot['application_uuid'] ) || ! hash_equals( (string) $app->application_uuid, (string) $snapshot['application_uuid'] )
			|| absint( $app->version ) !== absint( isset( $snapshot['application_version'] ) ? $snapshot['application_version'] : 0 )
			|| ! isset( $snapshot['profile'], $snapshot['evidence'], $snapshot['verified_until'] ) || ! is_array( $snapshot['profile'] ) || ! is_array( $snapshot['evidence'] ) ) {
			return array();
		}
		$computed = self::fingerprint( (array) $snapshot['profile'], (array) $snapshot['evidence'] );
		return 1 === preg_match( '/^[a-f0-9]{64}$/i', (string) $computed ) && hash_equals( (string) $app->approved_fingerprint, $computed ) ? $snapshot : array();
	}

	public static function approved_snapshot( $application_id ) {
		$app = self::get( $application_id );
		return $app && GDO_State::public_verified( $app->state ) ? self::stored_approved_snapshot( $app ) : array();
	}

	public static function refresh_approved_snapshot( $application_or_id, $verified_until, $actor_id ) {
		$app = is_object( $application_or_id ) ? $application_or_id : self::get( $application_or_id );
		$snapshot = self::stored_approved_snapshot( $app );
		$normalized_until = GDO_Policy::normalize_future_date( $verified_until );
		if ( ! $app || ! $snapshot || is_wp_error( $normalized_until ) ) {
			return new WP_Error( 'gdo_snapshot_refresh_invalid', __( 'The approved professional snapshot cannot be refreshed safely.', 'global-doctor-onboarding' ) );
		}
		$timestamp = strtotime( $normalized_until . ' 23:59:59 UTC' );
		$evidence_ceiling = GDO_Evidence::verification_valid_until_ceiling( $app->id );
		if ( is_wp_error( $evidence_ceiling ) || ! $timestamp || $timestamp > $evidence_ceiling ) {
			return new WP_Error( 'gdo_snapshot_validity_ceiling', __( 'The requested verification period exceeds the earliest current supporting credential or evidence expiry.', 'global-doctor-onboarding' ) );
		}
		$snapshot['schema'] = absint( GDO_SCHEMA_VERSION );
		$snapshot['verified_until'] = $normalized_until;
		$snapshot['policy_version'] = GDO_Policy::VERSION;
		$snapshot['finalizer_id'] = absint( $actor_id );
		$snapshot['captured_at'] = current_time( 'mysql', true );
		$fingerprint = self::fingerprint( (array) $snapshot['profile'], (array) $snapshot['evidence'] );
		return array( 'snapshot'=>$snapshot, 'json'=>wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'fingerprint'=>$fingerprint, 'verified_until'=>gmdate( 'Y-m-d 23:59:59', $timestamp ) );
	}
}
