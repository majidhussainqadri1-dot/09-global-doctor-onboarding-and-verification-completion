<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Frontend {
	private $form_required_fields = array();
	public function hooks() {
		add_shortcode( 'gdo_doctor_application', array( $this, 'form' ) );
		add_action( 'admin_post_gdo_start_application', array( $this, 'start' ) );
		add_action( 'admin_post_gdo_save_application', array( $this, 'save' ) );
		add_action( 'admin_post_gdo_submit_application', array( $this, 'submit' ) );
		add_action( 'admin_post_gdo_file_appeal', array( $this, 'appeal' ) );
		add_action( 'admin_post_gdo_withdraw_application', array( $this, 'withdraw' ) );
	}

	private function notice( $message, $type = 'info' ) {
		return '<div class="gdo-notice gdo-notice-' . esc_attr( $type ) . '" role="status">' . esc_html( $message ) . '</div>';
	}

	private function status_panel( $app, $error = null ) {
		ob_start();
		?><main class="gdo-application" aria-labelledby="gdo-title"><header class="gdo-head"><span><?php esc_html_e( 'Private professional application', 'global-doctor-onboarding' ); ?></span><h1 id="gdo-title"><?php esc_html_e( 'Doctor Application and Verification', 'global-doctor-onboarding' ); ?></h1></header><section class="gdo-progress" aria-labelledby="gdo-status-title"><h2 id="gdo-status-title"><?php esc_html_e( 'Application status', 'global-doctor-onboarding' ); ?></h2>
		<?php if ( $app ) : ?><p><strong><?php echo esc_html( $app->state ); ?></strong></p><p><?php printf( esc_html__( 'Application version %d', 'global-doctor-onboarding' ), absint( $app->version ) ); ?></p><?php if ( $app->verified_until ) : ?><p><?php printf( esc_html__( 'Verification validity: %s UTC', 'global-doctor-onboarding' ), esc_html( $app->verified_until ) ); ?></p><?php endif; ?><p><?php printf( esc_html__( 'File 00 claim: %1$s, version %2$d', 'global-doctor-onboarding' ), esc_html( $app->claim_status ), absint( $app->claim_version ) ); ?></p><?php else : ?><p><?php echo esc_html( $error instanceof WP_Error ? $error->get_error_message() : __( 'No application is available.', 'global-doctor-onboarding' ) ); ?></p><?php endif; ?></section>
		<?php if ( $app && in_array( $app->state, array( 'rejected','suspended','revoked' ), true ) ) : ?>
		<section class="gdo-consent" aria-labelledby="gdo-appeal-title"><h2 id="gdo-appeal-title"><?php esc_html_e( 'Appeal decision', 'global-doctor-onboarding' ); ?></h2><p><?php esc_html_e( 'Explain the factual or procedural basis. The appeal is assigned to an independent senior reviewer and preserves the original decision history.', 'global-doctor-onboarding' ); ?></p><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="gdo_file_appeal"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><?php wp_nonce_field( 'gdo_file_appeal_' . $app->id ); ?><label><?php esc_html_e( 'Appeal reason', 'global-doctor-onboarding' ); ?><textarea name="reason" required minlength="20" maxlength="4000"></textarea></label><label><?php esc_html_e( 'Supporting facts or evidence summary', 'global-doctor-onboarding' ); ?><textarea name="evidence_summary" maxlength="4000"></textarea></label><button class="gdo-button"><?php esc_html_e( 'Submit appeal', 'global-doctor-onboarding' ); ?></button></form></section>
		<?php endif; ?>
		<?php if ( $app && GDO_State::can_transition( $app->state, 'withdrawn' ) ) : ?><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="gdo_withdraw_application"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $app->row_version ); ?>"><?php wp_nonce_field( 'gdo_withdraw_application_' . $app->id ); ?><label><?php esc_html_e( 'Withdrawal reason', 'global-doctor-onboarding' ); ?><textarea name="reason" required minlength="20"></textarea></label><button class="gdo-button"><?php esc_html_e( 'Withdraw application', 'global-doctor-onboarding' ); ?></button></form><?php endif; ?>
		</main><?php
		return ob_get_clean();
	}

	public function form() {
		if ( ! is_user_logged_in() ) {
			return $this->notice( __( 'Log in with a File 00-approved doctor account to start professional verification.', 'global-doctor-onboarding' ), 'warning' );
		}
		$user = get_current_user_id();
		$latest = GDO_Application::latest_for_user( $user );
		$eligibility = GDO_Policy::eligibility( $user );
		if ( empty( $eligibility['eligible'] ) && ! ( $latest && in_array( $latest->state, array( 'draft','more_information','expired','renewal_due' ), true ) ) ) {
			return $this->status_panel( $latest, new WP_Error( 'gdo_eligibility_' . sanitize_key( $eligibility['reason_code'] ), __( 'This account is not currently eligible to start a new doctor application.', 'global-doctor-onboarding' ) ) );
		}
		if ( ! $latest || in_array( $latest->state, array( 'expired','renewal_due' ), true ) ) {
			if ( ! GDO_Operations::mutation_allowed() ) {
				return $this->status_panel( $latest, new WP_Error( 'gdo_safe_mode', __( 'Doctor verification changes are temporarily unavailable.', 'global-doctor-onboarding' ) ) );
			}
			ob_start(); ?>
			<main class="gdo-application" aria-labelledby="gdo-title"><header class="gdo-head"><h1 id="gdo-title"><?php esc_html_e( 'Doctor Application and Verification', 'global-doctor-onboarding' ); ?></h1></header><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="gdo_start_application"><?php wp_nonce_field( 'gdo_start_application' ); ?><button class="gdo-button" type="submit"><?php echo esc_html( $latest ? __( 'Start renewal application', 'global-doctor-onboarding' ) : __( 'Start professional verification', 'global-doctor-onboarding' ) ); ?></button></form></main><?php
			return ob_get_clean();
		}
		$app = GDO_Application::ensure_draft( $user );
		if ( is_wp_error( $app ) ) {
			return $this->status_panel( $latest, $app );
		}
		$profile = json_decode( $app->profile_json, true );
		$profile = is_array( $profile ) ? array_merge( array_fill_keys( GDO_Application::fields(), '' ), $profile ) : array_fill_keys( GDO_Application::fields(), '' );
		$consent = GDO_Application::consent_text();
		$complete = GDO_Application::completeness( $app );
		$this->form_required_fields = array_flip( GDO_Policy::required_fields( $app->jurisdiction, $app->application_type ) );
		ob_start();
		?><main class="gdo-application" aria-labelledby="gdo-title"><header class="gdo-head"><span><?php esc_html_e( 'Private professional application', 'global-doctor-onboarding' ); ?></span><h1 id="gdo-title"><?php esc_html_e( 'Doctor Application and Verification', 'global-doctor-onboarding' ); ?></h1><p><?php esc_html_e( 'Credential files remain encrypted and private. Verification is a professional eligibility decision, not a guarantee of treatment outcomes.', 'global-doctor-onboarding' ); ?></p></header>
		<section class="gdo-progress" aria-live="polite"><strong><?php echo esc_html( $app->state ); ?></strong><p><?php printf( esc_html__( 'Application version %1$d · draft expires %2$s UTC', 'global-doctor-onboarding' ), absint( $app->version ), esc_html( $app->draft_expires_at ) ); ?></p><p data-gdo-autosave-status><?php esc_html_e( 'Draft ready', 'global-doctor-onboarding' ); ?></p></section>
		<?php if ( 'more_information' === $app->state ) : ?><div class="gdo-notice gdo-notice-warning" role="alert"><strong><?php esc_html_e( 'Replacement or additional information is required.', 'global-doctor-onboarding' ); ?></strong><?php if ( $app->more_info_due_at ) : ?><p><?php printf( esc_html__( 'Respond by %s UTC.', 'global-doctor-onboarding' ), esc_html( $app->more_info_due_at ) ); ?></p><?php endif; ?><?php foreach ( GDO_Evidence::records( $app->id, true ) as $record ) : if ( in_array( $record->status, array( 'more_information','rejected' ), true ) ) : ?><p><?php echo esc_html( $record->document_type . ': ' . $record->review_note ); ?></p><?php endif; endforeach; ?></div><?php endif; ?>
		<form data-gdo-wizard data-application-id="<?php echo absint( $app->id ); ?>" data-row-version="<?php echo absint( $app->row_version ); ?>" class="gdo-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="gdo_save_application"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $app->row_version ); ?>"><?php wp_nonce_field( 'gdo_save_application_' . $app->id ); ?>
			<section data-gdo-step class="gdo-step" aria-labelledby="gdo-step-1"><h2 id="gdo-step-1" tabindex="-1"><?php esc_html_e( '1. Personal and location information', 'global-doctor-onboarding' ); ?></h2><?php $this->input( 'display_name', 'Full professional name', $profile ); $this->input( 'country', 'Country', $profile ); $this->input( 'city', 'City', $profile ); $this->input( 'phone', 'Professional contact phone', $profile ); $this->input( 'whatsapp', 'WhatsApp number', $profile ); ?><button type="button" class="gdo-button" data-gdo-step-next><?php esc_html_e( 'Next', 'global-doctor-onboarding' ); ?></button></section>
			<section data-gdo-step class="gdo-step" hidden aria-labelledby="gdo-step-2"><h2 id="gdo-step-2" tabindex="-1"><?php esc_html_e( '2. Professional identity', 'global-doctor-onboarding' ); ?></h2><?php $this->input( 'professional_title', 'Professional title', $profile ); $this->input( 'qualification', 'Primary qualification', $profile ); $this->input( 'license_number', 'License or registration number', $profile ); $this->input( 'licensing_authority', 'Licensing authority', $profile ); $this->input( 'license_jurisdiction', 'License jurisdiction or country code', $profile ); $this->input( 'experience_years', 'Years of professional experience', $profile, 'number' ); ?><div class="gdo-step-actions"><button type="button" class="gdo-button-secondary" data-gdo-step-back><?php esc_html_e( 'Back', 'global-doctor-onboarding' ); ?></button><button type="button" class="gdo-button" data-gdo-step-next><?php esc_html_e( 'Next', 'global-doctor-onboarding' ); ?></button></div></section>
			<section data-gdo-step class="gdo-step" hidden aria-labelledby="gdo-step-3"><h2 id="gdo-step-3" tabindex="-1"><?php esc_html_e( '3. Professional scope and services', 'global-doctor-onboarding' ); ?></h2><?php $this->input( 'clinic', 'Clinic or professional address', $profile ); $this->input( 'specialty', 'Professional specialty', $profile ); $this->input( 'services', 'Services offered', $profile ); $this->input( 'languages', 'Languages', $profile ); $this->input( 'consultation_modes', 'Consultation methods', $profile ); ?><label class="gdo-wide"><?php esc_html_e( 'Professional biography', 'global-doctor-onboarding' ); ?><textarea data-gdo-profile name="bio" required maxlength="4000"><?php echo esc_textarea( $profile['bio'] ); ?></textarea></label><div class="gdo-step-actions"><button type="button" class="gdo-button-secondary" data-gdo-step-back><?php esc_html_e( 'Back', 'global-doctor-onboarding' ); ?></button><button type="button" class="gdo-button" data-gdo-step-next><?php esc_html_e( 'Next', 'global-doctor-onboarding' ); ?></button></div></section>
			<section data-gdo-step class="gdo-step" hidden aria-labelledby="gdo-step-4"><h2 id="gdo-step-4" tabindex="-1"><?php esc_html_e( '4. Credential evidence', 'global-doctor-onboarding' ); ?></h2><?php foreach ( GDO_Evidence::types( $app->jurisdiction, $app->application_type ) as $key=>$label ) : $current=GDO_Evidence::current( $app->id, $key ); $replacement=$current && in_array( $current->status, array( 'rejected','more_information' ), true ); ?><label class="gdo-file"><?php echo esc_html( $label ); ?><?php if ( $current ) : ?><small><?php echo esc_html( sprintf( __( 'Current evidence version %1$d — %2$s', 'global-doctor-onboarding' ), $current->version, $current->status ) ); ?></small><?php else : ?><small><?php esc_html_e( 'Required. PDF, JPEG, PNG, or WebP; maximum 5 MB. Malware scanning is fail-closed.', 'global-doctor-onboarding' ); ?></small><?php endif; ?><input type="file" name="document_<?php echo esc_attr( $key ); ?>" accept="application/pdf,image/jpeg,image/png,image/webp" <?php echo ( ! $current || $replacement ) ? 'required' : ''; ?>></label><?php endforeach; ?><div class="gdo-step-actions"><button type="button" class="gdo-button-secondary" data-gdo-step-back><?php esc_html_e( 'Back', 'global-doctor-onboarding' ); ?></button><button type="button" class="gdo-button" data-gdo-step-next><?php esc_html_e( 'Next', 'global-doctor-onboarding' ); ?></button></div></section>
			<section data-gdo-step class="gdo-step" hidden aria-labelledby="gdo-step-5"><h2 id="gdo-step-5" tabindex="-1"><?php esc_html_e( '5. Declarations, consent, and review', 'global-doctor-onboarding' ); ?></h2><?php foreach ( array( 'declaration_accuracy'=>'I declare that all information and evidence are accurate and current.', 'declaration_no_impersonation'=>'I declare that I am not impersonating another professional.', 'declaration_professional_scope'=>'I understand that verification does not authorize work outside my lawful professional scope.' ) as $field=>$label ) : ?><label class="gdo-check"><input data-gdo-profile type="checkbox" name="<?php echo esc_attr( $field ); ?>" value="1" <?php checked( '1', $profile[ $field ] ); ?> required> <?php echo esc_html( $label ); ?></label><?php endforeach; ?><div class="gdo-wide gdo-consent"><p><?php echo esc_html( $consent['wording'] ); ?></p><p><strong><?php esc_html_e( 'Purpose:', 'global-doctor-onboarding' ); ?></strong> <?php echo esc_html( $consent['purpose'] ); ?></p><p><strong><?php esc_html_e( 'Retention:', 'global-doctor-onboarding' ); ?></strong> <?php echo esc_html( $consent['retention'] ); ?></p><label><input type="checkbox" name="consent" value="1" required> <?php printf( esc_html__( 'I accept terms and consent version %s.', 'global-doctor-onboarding' ), esc_html( $consent['version'] ) ); ?></label></div><p><?php printf( esc_html__( 'Current completeness: %1$d missing fields, %2$d missing evidence types.', 'global-doctor-onboarding' ), count( $complete['missing_fields'] ), count( $complete['missing_evidence'] ) ); ?></p><div class="gdo-step-actions"><button type="button" class="gdo-button-secondary" data-gdo-step-back><?php esc_html_e( 'Back', 'global-doctor-onboarding' ); ?></button><button class="gdo-button" type="submit"><?php esc_html_e( 'Save private application', 'global-doctor-onboarding' ); ?></button></div></section>
		</form>
		<form class="gdo-submit-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="gdo_submit_application"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $app->row_version ); ?>"><?php wp_nonce_field( 'gdo_submit_application_' . $app->id ); ?><button class="gdo-button" type="submit"><?php esc_html_e( 'Submit immutable snapshot for independent review', 'global-doctor-onboarding' ); ?></button></form></main><?php
		return ob_get_clean();
	}

	private function input( $key, $label, array $profile, $type = 'text' ) {
		$required = isset( $this->form_required_fields[ sanitize_key( $key ) ] );
		?><label><?php echo esc_html( $label ); ?><input data-gdo-profile type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $key ); ?>"<?php echo $required ? ' required' : ''; ?> maxlength="<?php echo 'number' === $type ? '2' : '500'; ?>" value="<?php echo esc_attr( isset( $profile[ $key ] ) ? $profile[ $key ] : '' ); ?>"></label><?php
	}

	public function start() {
		if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'Log in.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) ); }
		check_admin_referer( 'gdo_start_application' );
		if ( ! GDO_Operations::mutation_allowed() ) { wp_die( esc_html__( 'Doctor verification changes are temporarily unavailable.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
		$app = GDO_Application::ensure_draft( get_current_user_id() );
		if ( is_wp_error( $app ) ) { wp_die( esc_html( $app->get_error_message() ), '', array( 'response'=>409, 'back_link'=>true ) ); }
		wp_safe_redirect( GDO_Plugin::application_url( array( 'started'=>'1' ) ) ); exit;
	}

	public function save() {
		if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'Log in.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) ); }
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_save_application_' . $id );
		$user = get_current_user_id();
		$app = GDO_Application::get( $id );
		if ( ! $app || absint( $app->user_id ) !== $user || ! GDO_Membership_Adapter::is_active_doctor_candidate( $user, $app ? $app->jurisdiction : '' ) ) {
			wp_die( esc_html__( 'Application access denied.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) );
		}
		$profile = GDO_Application::sanitize_profile( $_POST );
		$valid = GDO_Application::validate_profile( $profile, false, $app->jurisdiction, $app->application_type );
		if ( is_wp_error( $valid ) ) { wp_die( esc_html( $valid->get_error_message() ), '', array( 'response'=>400 ) ); }
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			wp_die( esc_html__( 'The application save could not start a safe database transaction.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) );
		}
		$new_files = array();
		try {
			$saved = GDO_Application::save_draft( $id, $user, $profile, absint( isset( $_POST['row_version'] ) ? $_POST['row_version'] : 0 ) );
			if ( is_wp_error( $saved ) ) { throw new RuntimeException( $saved->get_error_message() ); }
			$app = GDO_Application::get( $id );
			foreach ( GDO_Evidence::types( $app->jurisdiction, $app->application_type ) as $type=>$label ) {
				$key = 'document_' . $type;
				if ( ! empty( $_FILES[ $key ]['tmp_name'] ) ) {
					$record = GDO_Evidence::stage_upload( $app, $type, $_FILES[ $key ], false );
					if ( is_wp_error( $record ) ) { throw new RuntimeException( $record->get_error_message() ); }
					$new_files[] = $record;
				}
			}
			$consent = GDO_Application::record_consent( $id, $user, ! empty( $_POST['consent'] ), false );
			if ( is_wp_error( $consent ) ) { throw new RuntimeException( $consent->get_error_message() ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( __( 'The application could not be committed.', 'global-doctor-onboarding' ) ); }
			foreach ( $new_files as $record ) {
				GDO_Membership_Adapter::audit( 'doctor_evidence_uploaded', array(
					'application_id'=>absint( $record['application_id'] ), 'evidence_id'=>absint( $record['id'] ),
					'document_type'=>$record['document_type'], 'version'=>absint( $record['version'] ), 'source_digest'=>$record['source_digest'],
				) );
			}
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			foreach ( $new_files as $record ) { GDO_Storage::delete_verified( $record['storage_name'], $record['ciphertext_sha256'] ); }
			GDO_Membership_Adapter::audit( 'doctor_application_save_failed', array( 'application_id'=>$id, 'error_class'=>get_class( $e ), 'error_digest'=>hash( 'sha256', $e->getMessage() ) ) );
			wp_die( esc_html__( 'The private application could not be saved safely. No partial application change was accepted.', 'global-doctor-onboarding' ), '', array( 'response'=>503, 'back_link'=>true ) );
		}
		wp_safe_redirect( GDO_Plugin::application_url( array( 'saved'=>'1' ) ) ); exit;
	}

	public function submit() {
		if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'Log in.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) ); }
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_submit_application_' . $id );
		$result = GDO_Application::submit( $id, get_current_user_id(), absint( isset( $_POST['row_version'] ) ? $_POST['row_version'] : 0 ) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>400, 'back_link'=>true ) ); }
		wp_safe_redirect( GDO_Plugin::application_url( array( 'submitted'=>'1' ) ) ); exit;
	}

	public function appeal() {
		if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'Log in.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) ); }
		if ( ! GDO_Operations::mutation_allowed() ) { wp_die( esc_html__( 'Doctor verification changes are temporarily unavailable.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
		global $wpdb;
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_file_appeal_' . $id );
		$reason = sanitize_textarea_field( isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		$evidence_summary = sanitize_textarea_field( isset( $_POST['evidence_summary'] ) ? $_POST['evidence_summary'] : '' );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			wp_die( esc_html__( 'The appeal could not start a safe database transaction.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) );
		}
		$app = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE',
			$id
		) );
		if ( ! $app || absint( $app->user_id ) !== get_current_user_id() || ! in_array( $app->state, array( 'rejected','suspended','revoked' ), true ) || strlen( $reason ) < 20 || ! GDO_State::can_transition( $app->state, 'appeal_pending' ) ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html__( 'This appeal is not valid.', 'global-doctor-onboarding' ), '', array( 'response'=>400 ) );
		}
		$existing = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . GDO_Schema::table( 'appeals' ) . " WHERE application_id=%d AND status='open' LIMIT 1 FOR UPDATE",
			$id
		) );
		if ( $wpdb->last_error ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html__( 'Appeal status could not be verified safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) );
		}
		if ( $existing ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html__( 'An appeal is already open.', 'global-doctor-onboarding' ), '', array( 'response'=>409 ) );
		}
		$appeal_uuid = wp_generate_uuid4();
		$result = GDO_State::transition( $id, 'appeal_pending', get_current_user_id(), 'appeal_filed', $reason, $app->row_version, false );
		$inserted = is_wp_error( $result ) ? false : $wpdb->insert( GDO_Schema::table( 'appeals' ), array(
			'appeal_uuid'=>$appeal_uuid, 'application_id'=>$id, 'user_id'=>get_current_user_id(),
			'source_state'=>$app->state, 'status'=>'open', 'reason'=>$reason,
			'grounds_json'=>wp_json_encode( array( 'type'=>'factual_or_procedural' ) ),
			'evidence_json'=>wp_json_encode( array( 'summary'=>$evidence_summary ) ),
			'deadline_at'=>gmdate( 'Y-m-d H:i:s', time() + absint( apply_filters( 'gdo_appeal_deadline_days', 30 ) ) * DAY_IN_SECONDS ),
			'created_at'=>current_time( 'mysql', true ),
		), array( '%s','%d','%d','%s','%s','%s','%s','%s','%s','%s' ) );
		$event = is_wp_error( $result ) || 1 !== $inserted ? new WP_Error( 'gdo_appeal_not_ready', __( 'The appeal is not ready for notification.', 'global-doctor-onboarding' ) ) : GDO_Notifications::queue( 'doctor_verification_appeal', $app->user_id, array( 'application_id'=>$id, 'appeal_uuid'=>$appeal_uuid ), false );
		if ( is_wp_error( $result ) || 1 !== $inserted || is_wp_error( $event ) || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$error = is_wp_error( $result ) ? $result : ( is_wp_error( $event ) ? $event : new WP_Error( 'gdo_appeal_commit', __( 'Appeal could not be filed.', 'global-doctor-onboarding' ) ) );
			wp_die( esc_html( $error->get_error_message() ), '', array( 'response'=>409 ) );
		}
		GDO_Audit::publish_transition( $result );
		GDO_Notifications::process( 1, $event );
		wp_safe_redirect( GDO_Plugin::application_url( array( 'appealed'=>'1' ) ) ); exit;
	}

	public function withdraw() {
		if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'Log in.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) ); }
		if ( ! GDO_Operations::mutation_allowed() ) { wp_die( esc_html__( 'Doctor verification changes are temporarily unavailable.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_withdraw_application_' . $id );
		$app = GDO_Application::get( $id );
		$reason = sanitize_textarea_field( isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		if ( ! $app || absint( $app->user_id ) !== get_current_user_id() || strlen( $reason ) < 20 ) { wp_die( esc_html__( 'Invalid withdrawal.', 'global-doctor-onboarding' ), '', array( 'response'=>400 ) ); }
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			wp_die( esc_html__( 'The withdrawal could not start a safe database transaction.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) );
		}
		$result = GDO_State::transition( $id, 'withdrawn', get_current_user_id(), 'application_withdrawn', $reason, absint( isset( $_POST['row_version'] ) ? $_POST['row_version'] : 0 ), false );
		$event = is_wp_error( $result ) ? $result : GDO_Notifications::queue( 'doctor_application_withdrawn', $app->user_id, array( 'application_id'=>$id ), false );
		if ( is_wp_error( $result ) || is_wp_error( $event ) || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			$error = is_wp_error( $result ) ? $result : ( is_wp_error( $event ) ? $event : new WP_Error( 'gdo_withdraw_commit', __( 'The application withdrawal could not be committed.', 'global-doctor-onboarding' ) ) );
			wp_die( esc_html( $error->get_error_message() ), '', array( 'response'=>409 ) );
		}
		GDO_Audit::publish_transition( $result );
		GDO_Notifications::process( 1, $event );
		wp_safe_redirect( GDO_Plugin::application_url( array( 'withdrawn'=>'1' ) ) ); exit;
	}
}
