<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Admin {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_post_gdo_reviewer_step_up', array( $this, 'step_up' ) );
		add_action( 'admin_post_gdo_assign_application', array( $this, 'assign' ) );
		add_action( 'admin_post_gdo_review_evidence', array( $this, 'review_evidence' ) );
		add_action( 'admin_post_gdo_request_more_info', array( $this, 'request_more_info' ) );
		add_action( 'admin_post_gdo_recommend_decision', array( $this, 'recommend' ) );
		add_action( 'admin_post_gdo_finalize_decision', array( $this, 'finalize' ) );
		add_action( 'admin_post_gdo_issue_evidence_grant', array( $this, 'issue_evidence_grant' ) );
		add_action( 'admin_post_gdo_change_verification_state', array( $this, 'change_state' ) );
		add_action( 'admin_post_gdo_assign_appeal', array( $this, 'assign_appeal' ) );
		add_action( 'admin_post_gdo_resolve_appeal', array( $this, 'resolve_appeal' ) );
		add_action( 'admin_post_gdo_resolve_risk', array( $this, 'resolve_risk' ) );
		add_action( 'admin_post_gdo_complete_quality_sample', array( $this, 'complete_quality_sample' ) );
		add_action( 'admin_post_gdo_save_reviewer_profile', array( $this, 'save_reviewer_profile' ) );
		add_action( 'admin_post_gdo_toggle_safe_mode', array( $this, 'toggle_safe_mode' ) );
		add_action( 'admin_post_gdo_replay_outbox', array( $this, 'replay_outbox' ) );
		add_action( 'admin_post_gdo_run_repair', array( $this, 'run_repair' ) );
	}

	public function menu() {
		if ( ! GDO_Membership_Adapter::can( 'sabri_verify_doctors' ) && ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification' ) ) {
			return;
		}
		global $admin_page_hooks;
		$cap = 'read';
		if ( isset( $admin_page_hooks['smc-membership'] ) ) {
			add_submenu_page( 'smc-membership', __( 'Doctor Verification', 'global-doctor-onboarding' ), __( 'Doctor Verification', 'global-doctor-onboarding' ), $cap, 'global-doctor-verification', array( $this, 'page' ) );
		} else {
			add_menu_page( __( 'Doctor Verification', 'global-doctor-onboarding' ), __( 'Doctor Verification', 'global-doctor-onboarding' ), $cap, 'global-doctor-verification', array( $this, 'page' ), 'dashicons-id-alt', 4 );
		}
	}

	private function guard( $capability, $allow_recovery = false ) {
		if ( ! GDO_Membership_Adapter::can( $capability ) || ! GDO_Membership_Adapter::recent_step_up( get_current_user_id() ) ) {
			wp_die( esc_html__( 'Access requires an authorized File 00 reviewer and recent password plus Authenticator verification.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) );
		}
		if ( ! $allow_recovery && ! GDO_Operations::mutation_allowed() ) {
			wp_die( esc_html__( 'File 09 mutations are unavailable until Safe Mode is cleared and all runtime dependencies and schemas are healthy.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) );
		}
	}


	private function lock_application( $application_id ) {
		global $wpdb;
		$wpdb->last_error = '';
		$app = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', absint( $application_id ) ) );
		if ( null === $app && ! empty( $wpdb->last_error ) ) {
			$this->rollback_die( __( 'The doctor application could not be locked/read safely.', 'global-doctor-onboarding' ), 503 );
		}
		return $app;
	}

	private function rollback_die( $message, $response = 400 ) {
		global $wpdb;
		$wpdb->query( 'ROLLBACK' );
		wp_die( esc_html( $message ), '', array( 'response'=>absint( $response ) ) );
	}

	private function begin_transaction_or_die() {
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			wp_die( esc_html__( 'The verification operation could not start a safe database transaction.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) );
		}
	}

	private function commit_or_reconcile( $operation, $message, $verify ) {
		global $wpdb;
		if ( false !== $wpdb->query( 'COMMIT' ) ) { return true; }
		$verified = is_callable( $verify ) ? call_user_func( $verify ) : false;
		if ( is_wp_error( $verified ) ) { return $verified; }
		if ( $verified ) {
			GDO_Membership_Adapter::audit( 'doctor_admin_commit_reconciled', array( 'operation'=>sanitize_key( $operation ) ) );
			return true;
		}
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'gdo_' . sanitize_key( $operation ) . '_commit', $message );
	}

	private function outbox_commit_verified( $event_uuid ) {
		global $wpdb;
		if ( ! $event_uuid ) { return false; }
		$wpdb->last_error = '';
		$event_id = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'outbox' ) . ' WHERE event_uuid=%s LIMIT 1', (string) $event_uuid ) ) );
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_admin_commit_uncertain', __( 'The verification operation commit outcome is uncertain and requires reconciliation.', 'global-doctor-onboarding' ) );
		}
		return $event_id > 0;
	}

	private function render_step_up() {
		?>
		<div class="wrap gdo-admin">
			<h1><?php esc_html_e( 'Doctor Verification Security Check', 'global-doctor-onboarding' ); ?></h1>
			<p><?php esc_html_e( 'Private credentials require a session-bound step-up. Enter your current password and Authenticator code. Access expires after 15 minutes.', 'global-doctor-onboarding' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gdo_reviewer_step_up">
				<?php wp_nonce_field( 'gdo_reviewer_step_up' ); ?>
				<p><label for="gdo-password"><?php esc_html_e( 'Current password', 'global-doctor-onboarding' ); ?></label><br><input id="gdo-password" type="password" name="password" autocomplete="current-password" required></p>
				<p><label for="gdo-otp"><?php esc_html_e( 'Authenticator code', 'global-doctor-onboarding' ); ?></label><br><input id="gdo-otp" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></p>
				<button class="button button-primary"><?php esc_html_e( 'Verify and continue', 'global-doctor-onboarding' ); ?></button>
			</form>
		</div>
		<?php
	}

	public function step_up() {
		if ( ! GDO_Membership_Adapter::can( 'sabri_verify_doctors' ) && ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification' ) ) {
			wp_die( esc_html__( 'Reviewer access denied.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) );
		}
		check_admin_referer( 'gdo_reviewer_step_up' );
		$result = GDO_Membership_Adapter::verify_step_up( get_current_user_id(), isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '', isset( $_POST['otp'] ) ? wp_unslash( $_POST['otp'] ) : '' );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>403, 'back_link'=>true ) );
		}
		$this->redirect();
	}

	public function page() {
		if ( ! GDO_Membership_Adapter::can( 'sabri_verify_doctors' ) && ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification' ) ) {
			wp_die( esc_html__( 'Reviewer access denied.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) );
		}
		if ( ! GDO_Membership_Adapter::recent_step_up( get_current_user_id() ) ) {
			$this->render_step_up();
			return;
		}
		global $wpdb;
		$reviewer = get_current_user_id();
		$manager = GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $reviewer );
		$paged = max( 1, absint( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) );
		$per_page = 20;
		$offset = ( $paged - 1 ) * $per_page;
		$table = GDO_Schema::table( 'applications' );
		if ( $manager ) {
			$wpdb->last_error = '';
			$apps = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY FIELD(state,'submitted','resubmitted','under_review','recommended','appeal_pending','renewal_due','suspended','verified','rejected','revoked','expired','withdrawn','draft'), updated_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
			if ( null === $apps || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The doctor-verification review queue is temporarily unavailable because its database state could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
			$wpdb->last_error = '';
			$total_raw = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			if ( null === $total_raw || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The doctor-verification queue total could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
		} else {
			$wpdb->last_error = '';
			$apps = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE assigned_reviewer_id=%d ORDER BY updated_at DESC LIMIT %d OFFSET %d", $reviewer, $per_page, $offset ) );
			if ( null === $apps || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The assigned doctor-verification review queue is temporarily unavailable because its database state could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
			$wpdb->last_error = '';
			$total_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE assigned_reviewer_id=%d", $reviewer ) );
			if ( null === $total_raw || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The assigned doctor-verification queue total could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
		}
		$total = absint( $total_raw );
		$health = GDO_Operations::health();
		?>
		<div class="wrap gdo-admin">
			<h1><?php esc_html_e( 'Doctor Verification', 'global-doctor-onboarding' ); ?></h1>
			<div class="notice <?php echo 'healthy' === $health['status'] ? 'notice-success' : 'notice-warning'; ?> inline"><p><strong><?php echo esc_html( strtoupper( $health['status'] ) ); ?></strong> — <?php printf( esc_html__( 'Runtime %1$s, schema %2$d, dead letters %3$d.', 'global-doctor-onboarding' ), esc_html( GDO_VERSION ), absint( GDO_SCHEMA_VERSION ), absint( $health['dead_letters'] ) ); ?></p></div>
			<p><?php esc_html_e( 'Assignment, evidence access, recommendation, finalization, lifecycle decisions, appeals, risk resolution, and quality review are independently audited.', 'global-doctor-onboarding' ); ?></p>
			<?php if ( $manager ) : $this->render_operations( $health ); endif; ?>
			<table class="widefat striped gdo-review-table"><thead><tr><th><?php esc_html_e( 'Application', 'global-doctor-onboarding' ); ?></th><th><?php esc_html_e( 'Evidence and risks', 'global-doctor-onboarding' ); ?></th><th><?php esc_html_e( 'Authorized workflow', 'global-doctor-onboarding' ); ?></th></tr></thead><tbody>
			<?php if ( ! $apps ) : ?><tr><td colspan="3"><?php esc_html_e( 'No applications are available in this queue.', 'global-doctor-onboarding' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $apps as $app ) : $this->render_application_row( $app, $reviewer, $manager ); endforeach; ?>
			</tbody></table>
			<?php echo wp_kses_post( paginate_links( array( 'base'=>add_query_arg( 'paged', '%#%' ), 'format'=>'', 'current'=>$paged, 'total'=>max( 1, (int) ceil( $total / $per_page ) ) ) ) ); ?>
		</div>
		<?php
	}

	private function render_operations( array $health ) {
		?>
		<details class="gdo-operations"><summary><?php esc_html_e( 'System Check and controlled operations', 'global-doctor-onboarding' ); ?></summary>
			<ul><?php foreach ( $health['checks'] as $name=>$state ) : ?><li><code><?php echo esc_html( $name ); ?></code>: <strong><?php echo esc_html( $state ); ?></strong></li><?php endforeach; ?></ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_toggle_safe_mode"><?php wp_nonce_field( 'gdo_toggle_safe_mode' ); ?><input type="hidden" name="enabled" value="<?php echo $health['safe_mode'] ? '0' : '1'; ?>"><label><?php esc_html_e( 'Reason', 'global-doctor-onboarding' ); ?><textarea name="reason" required minlength="20"></textarea></label><button class="button"><?php echo $health['safe_mode'] ? esc_html__( 'Disable Safe Mode', 'global-doctor-onboarding' ) : esc_html__( 'Enable Safe Mode', 'global-doctor-onboarding' ); ?></button></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_replay_outbox"><?php wp_nonce_field( 'gdo_replay_outbox' ); ?><button class="button"><?php esc_html_e( 'Process bounded notification/claim outbox', 'global-doctor-onboarding' ); ?></button></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_run_repair"><?php wp_nonce_field( 'gdo_run_repair' ); ?><label><?php esc_html_e( 'Controlled repair', 'global-doctor-onboarding' ); ?><select name="repair_action"><option value="schema">Schema/idempotent migration</option><option value="schedules">Cron schedules</option><option value="outbox">Outbox processing</option><option value="reconcile">Lifecycle reconciliation</option><option value="storage_check">Private-storage check</option></select></label><label><?php esc_html_e( 'Reason', 'global-doctor-onboarding' ); ?><textarea name="reason" required minlength="20"></textarea></label><button class="button"><?php esc_html_e( 'Run bounded repair', 'global-doctor-onboarding' ); ?></button></form>
		</details>
		<?php
	}

	private function render_application_row( $app, $reviewer, $manager ) {
		global $wpdb;
		$evidence = GDO_Evidence::records_checked( $app->id, true );
		if ( is_wp_error( $evidence ) ) { wp_die( esc_html__( 'Credential evidence could not be read safely for this review.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
		$wpdb->last_error = '';
		$risks = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'risk_signals' ) . ' WHERE application_id=%d ORDER BY created_at DESC', $app->id ) );
		if ( null === $risks || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'Professional risk signals could not be read safely for this review.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
		$types = GDO_Evidence::types( $app->jurisdiction, $app->application_type );
		?>
		<tr>
			<td><strong>#<?php echo absint( $app->id ); ?></strong><br><code><?php echo esc_html( $app->application_uuid ); ?></code><br><strong><?php echo esc_html( $app->state ); ?></strong><br><?php printf( esc_html__( 'Applicant %1$d · version %2$d · row %3$d', 'global-doctor-onboarding' ), absint( $app->user_id ), absint( $app->version ), absint( $app->row_version ) ); ?><br><?php printf( esc_html__( 'Jurisdiction: %1$s · language: %2$s', 'global-doctor-onboarding' ), esc_html( $app->jurisdiction ), esc_html( $app->preferred_language ) ); ?><br><?php printf( esc_html__( 'Claim: %1$s v%2$d', 'global-doctor-onboarding' ), esc_html( $app->claim_status ), absint( $app->claim_version ) ); ?></td>
			<td>
			<?php foreach ( $evidence as $record ) : ?><section class="gdo-evidence-row"><strong><?php echo esc_html( isset( $types[ $record->document_type ] ) ? $types[ $record->document_type ] : $record->document_type ); ?></strong> — <?php echo esc_html( $record->status ); ?> — <?php echo esc_html( $record->malware_status ); ?>
				<?php if ( GDO_Membership_Adapter::reviewer_case_allows( $reviewer, $app->user_id, $app->id ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_issue_evidence_grant"><input type="hidden" name="evidence_id" value="<?php echo absint( $record->id ); ?>"><?php wp_nonce_field( 'gdo_issue_evidence_grant_' . $record->id ); ?><label><?php esc_html_e( 'Access purpose', 'global-doctor-onboarding' ); ?><input name="purpose" required minlength="10" maxlength="300"></label><select name="mode"><option value="view"><?php esc_html_e( 'Watermarked view', 'global-doctor-onboarding' ); ?></option><option value="download"><?php esc_html_e( 'Authorized download', 'global-doctor-onboarding' ); ?></option></select><button class="button"><?php esc_html_e( 'Open credential', 'global-doctor-onboarding' ); ?></button></form>
				<?php endif; ?>
				<?php if ( absint( $app->assigned_reviewer_id ) === $reviewer && 'under_review' === $app->state ) : $this->render_evidence_review_form( $record ); endif; ?>
			</section><?php endforeach; ?>
			<?php foreach ( $risks as $risk ) : ?><p><strong><?php echo esc_html( strtoupper( $risk->severity ) . ': ' . $risk->signal_type ); ?></strong> — <?php echo esc_html( $risk->status ); ?><?php if ( $manager && 'open' === $risk->status ) : ?><?php $this->render_risk_form( $risk ); ?><?php endif; ?></p><?php endforeach; ?>
			</td>
			<td><?php $this->render_workflow_forms( $app, $reviewer, $manager ); ?></td>
		</tr>
		<?php
	}

	private function render_evidence_review_form( $record ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gdo-review-form"><input type="hidden" name="action" value="gdo_review_evidence"><input type="hidden" name="evidence_id" value="<?php echo absint( $record->id ); ?>"><?php wp_nonce_field( 'gdo_review_evidence_' . $record->id ); ?>
			<label><?php esc_html_e( 'Decision', 'global-doctor-onboarding' ); ?><select name="status"><option value="accepted">Accepted</option><option value="more_information">More information</option><option value="rejected">Reject and replace</option></select></label>
			<?php foreach ( array( 'name_match'=>'Name match','document_legible'=>'Document legible','scope_match'=>'Professional scope match' ) as $item=>$label ) : ?><label><?php echo esc_html( $label ); ?><select name="checklist[<?php echo esc_attr( $item ); ?>]"><option value="yes">Yes</option><option value="no">No</option><option value="not_applicable">Not applicable</option></select></label><?php endforeach; ?>
			<label><?php esc_html_e( 'Authenticity method/source', 'global-doctor-onboarding' ); ?><input name="checklist[authenticity_method]" required maxlength="300"></label>
			<label><?php esc_html_e( 'Registry result', 'global-doctor-onboarding' ); ?><select name="registry_result"><option value="">Not applicable</option><option value="verified">Verified</option><option value="active">Active</option><option value="matched">Matched</option><option value="not_found">Not found</option><option value="expired">Expired</option></select></label>
			<label><?php esc_html_e( 'Valid from', 'global-doctor-onboarding' ); ?><input type="date" name="validity_from"></label><label><?php esc_html_e( 'Valid until', 'global-doctor-onboarding' ); ?><input type="date" name="validity_until"></label>
			<label><?php esc_html_e( 'Review note / exact information required', 'global-doctor-onboarding' ); ?><textarea name="review_note" maxlength="2000"></textarea></label><button class="button button-primary"><?php esc_html_e( 'Record evidence review', 'global-doctor-onboarding' ); ?></button>
		</form>
		<?php
	}

	private function render_risk_form( $risk ) {
		?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_resolve_risk"><input type="hidden" name="signal_id" value="<?php echo absint( $risk->id ); ?>"><?php wp_nonce_field( 'gdo_resolve_risk_' . $risk->id ); ?><select name="decision"><option value="false_positive">False positive</option><option value="mitigated">Mitigated</option><option value="confirmed">Confirmed</option></select><textarea name="reason" required minlength="20"></textarea><button class="button"><?php esc_html_e( 'Resolve signal', 'global-doctor-onboarding' ); ?></button></form><?php
	}

	private function render_workflow_forms( $app, $reviewer, $manager ) {
		if ( $manager && ( in_array( $app->state, array( 'submitted','resubmitted' ), true ) || ( 'under_review' === $app->state && empty( $app->assigned_reviewer_id ) ) ) ) {
			?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_assign_application"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $app->row_version ); ?>"><?php wp_nonce_field( 'gdo_assign_application_' . $app->id ); ?><label><?php esc_html_e( 'Reviewer user ID', 'global-doctor-onboarding' ); ?><input type="number" min="1" name="reviewer_id" required></label><button class="button button-primary"><?php esc_html_e( 'Assign independently', 'global-doctor-onboarding' ); ?></button></form><?php
		}
		if ( absint( $app->assigned_reviewer_id ) === $reviewer && 'under_review' === $app->state ) {
			?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_request_more_info"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $app->row_version ); ?>"><?php wp_nonce_field( 'gdo_request_more_info_' . $app->id ); ?><label><?php esc_html_e( 'Response deadline', 'global-doctor-onboarding' ); ?><input type="date" name="due_date" required></label><label><?php esc_html_e( 'Exact information or replacement evidence required', 'global-doctor-onboarding' ); ?><textarea name="reason" required minlength="20" maxlength="2000"></textarea></label><button class="button"><?php esc_html_e( 'Request more information', 'global-doctor-onboarding' ); ?></button></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_recommend_decision"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $app->row_version ); ?>"><?php wp_nonce_field( 'gdo_recommend_decision_' . $app->id ); ?><select name="decision"><option value="verified">Recommend verification</option><option value="rejected">Recommend rejection</option></select><textarea name="reason" required minlength="20"></textarea><button class="button button-primary"><?php esc_html_e( 'Record recommendation', 'global-doctor-onboarding' ); ?></button></form><?php
		}
		if ( $manager && 'recommended' === $app->state && absint( $app->recommender_id ) !== $reviewer ) {
			?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_finalize_decision"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $app->row_version ); ?>"><?php wp_nonce_field( 'gdo_finalize_decision_' . $app->id ); ?><select name="decision"><option value="verified">Verify</option><option value="rejected">Reject</option><option value="under_review">Return for review</option></select><label><?php esc_html_e( 'Verification valid until', 'global-doctor-onboarding' ); ?><input type="date" name="verified_until"></label><textarea name="reason" required minlength="20"></textarea><button class="button button-primary"><?php esc_html_e( 'Finalize independently', 'global-doctor-onboarding' ); ?></button></form><?php
		}
		if ( $manager && in_array( $app->state, array( 'verified','reinstated','renewal_due','suspended' ), true ) ) {
			?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_change_verification_state"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $app->row_version ); ?>"><?php wp_nonce_field( 'gdo_change_verification_state_' . $app->id ); ?><select name="state"><option value="renewal_due">Renewal due</option><option value="suspended">Suspend</option><option value="revoked">Revoke</option><option value="reinstated">Reinstate</option></select><input type="date" name="verified_until"><textarea name="reason" required minlength="20"></textarea><button class="button"><?php esc_html_e( 'Apply lifecycle decision', 'global-doctor-onboarding' ); ?></button></form><?php
		}
		if ( $manager && 'appeal_pending' === $app->state ) {
			global $wpdb;
			$wpdb->last_error = '';
			$appeal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'appeals' ) . " WHERE application_id=%d AND status='open' ORDER BY id DESC LIMIT 1", $app->id ) );
			if ( null === $appeal && ! empty( $wpdb->last_error ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Appeal workflow state is temporarily unavailable because the database read failed. No appeal action has been assumed.', 'global-doctor-onboarding' ) . '</p></div>';
				return;
			}
			if ( $appeal && ! empty( $appeal->deadline_at ) ) {
				$appeal_deadline = strtotime( $appeal->deadline_at . ' UTC' );
				$appeal_overdue = $appeal_deadline && $appeal_deadline < time();
				echo '<p class="description">' . esc_html( sprintf( $appeal_overdue ? __( 'Appeal review deadline: %s UTC — OVERDUE; prioritize resolution without prejudicing the applicant.', 'global-doctor-onboarding' ) : __( 'Appeal review deadline: %s UTC.', 'global-doctor-onboarding' ), $appeal->deadline_at ) ) . '</p>';
			}
			if ( $appeal && empty( $appeal->assigned_reviewer_id ) ) {
				?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_assign_appeal"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="appeal_id" value="<?php echo absint( $appeal->id ); ?>"><?php wp_nonce_field( 'gdo_assign_appeal_' . $appeal->id ); ?><label><?php esc_html_e( 'Independent senior reviewer user ID', 'global-doctor-onboarding' ); ?><input type="number" min="1" name="reviewer_id" required></label><button class="button"><?php esc_html_e( 'Assign appeal independently', 'global-doctor-onboarding' ); ?></button></form><?php
			} elseif ( $appeal && absint( $appeal->assigned_reviewer_id ) === absint( $reviewer ) ) {
				?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="gdo_resolve_appeal"><input type="hidden" name="application_id" value="<?php echo absint( $app->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $app->row_version ); ?>"><?php wp_nonce_field( 'gdo_resolve_appeal_' . $app->id ); ?><select name="decision"><option value="under_review">Reopen review</option><option value="reinstated">Reinstate</option><option value="rejected">Uphold rejection</option><option value="suspended">Uphold suspension</option><option value="revoked">Uphold revocation</option></select><input type="date" name="verified_until"><textarea name="reason" required minlength="20"></textarea><button class="button button-primary"><?php esc_html_e( 'Resolve appeal independently', 'global-doctor-onboarding' ); ?></button></form><?php
			}
		}
	}

	private function reviewer_eligible( $reviewer_id, $app ) {
		global $wpdb;
		$reviewer_id = absint( $reviewer_id );
		if ( ! $reviewer_id || $reviewer_id === absint( $app->user_id ) || ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer_id, $app->user_id, $app->id ) ) {
			return new WP_Error( 'gdo_reviewer_scope', __( 'The reviewer is outside the authorized File 00 scope or has a self-review conflict.', 'global-doctor-onboarding' ) );
		}
		$wpdb->last_error = '';
		$profile = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . " WHERE user_id=%d AND status='active'", $reviewer_id ) );
		if ( null === $profile && ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_reviewer_profile_query', __( 'The reviewer qualification profile could not be read safely.', 'global-doctor-onboarding' ) );
		}
		if ( ! $profile ) {
			return new WP_Error( 'gdo_reviewer_profile', __( 'The reviewer does not have an active qualified File 09 reviewer profile.', 'global-doctor-onboarding' ) );
		}
		$jurisdictions = json_decode( $profile->jurisdictions_json, true );
		$languages = json_decode( $profile->languages_json, true );
		if ( $app->jurisdiction && ! in_array( $app->jurisdiction, (array) $jurisdictions, true ) ) {
			return new WP_Error( 'gdo_reviewer_jurisdiction', __( 'The reviewer is not qualified for this jurisdiction.', 'global-doctor-onboarding' ) );
		}
		if ( $app->preferred_language && ! in_array( $app->preferred_language, (array) $languages, true ) && ! in_array( 'en-US', (array) $languages, true ) ) {
			return new WP_Error( 'gdo_reviewer_language', __( 'The reviewer does not cover the application language.', 'global-doctor-onboarding' ) );
		}
		$wpdb->last_error = '';
		$open_apps = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'applications' ) . " WHERE assigned_reviewer_id=%d AND state IN ('under_review','more_information','recommended')", $reviewer_id ) );
		if ( null === $open_apps || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_reviewer_workload_unknown', __( 'Reviewer workload could not be verified safely.', 'global-doctor-onboarding' ) );
		}
		$wpdb->last_error = '';
		$open_appeals = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . GDO_Schema::table( 'appeals' ) . " WHERE assigned_reviewer_id=%d AND status='open'", $reviewer_id ) );
		if ( null === $open_appeals || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'gdo_reviewer_workload_unknown', __( 'Reviewer appeal workload could not be verified safely.', 'global-doctor-onboarding' ) );
		}
		$open = absint( $open_apps ) + absint( $open_appeals );
		if ( $open >= absint( $profile->max_open_cases ) ) {
			return new WP_Error( 'gdo_reviewer_workload', __( 'The reviewer has reached the configured open-case limit.', 'global-doctor-onboarding' ) );
		}
		return true;
	}

	public function assign() {
		$this->guard( 'sabri_manage_doctor_verification' );
		global $wpdb;
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_assign_application_' . $id );
		$reviewer_id = absint( isset( $_POST['reviewer_id'] ) ? $_POST['reviewer_id'] : 0 );
		$expected_version = absint( isset( $_POST['row_version'] ) ? $_POST['row_version'] : 0 );
		$actor = get_current_user_id();
		$this->begin_transaction_or_die();
		$wpdb->last_error = '';
		$wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . ' WHERE user_id=%d FOR UPDATE', $reviewer_id ) );
		if ( ! empty( $wpdb->last_error ) ) { $this->rollback_die( __( 'The reviewer profile could not be locked safely for assignment.', 'global-doctor-onboarding' ), 503 ); }
		$app = $this->lock_application( $id );
		$reopened = $app && 'under_review' === $app->state && empty( $app->assigned_reviewer_id );
		$initial = $app && in_array( $app->state, array( 'submitted','resubmitted' ), true );
		$eligible = $app ? $this->reviewer_eligible( $reviewer_id, $app ) : new WP_Error( 'gdo_application_missing', __( 'Application unavailable.', 'global-doctor-onboarding' ) );
		if ( is_wp_error( $eligible ) || ! $app || $expected_version !== absint( $app->row_version ) || ( ! $initial && ! $reopened ) ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html( is_wp_error( $eligible ) ? $eligible->get_error_message() : __( 'Application cannot be assigned or changed before assignment.', 'global-doctor-onboarding' ) ), '', array( 'response'=>409 ) );
		}
		if ( $initial ) {
			$result = GDO_State::transition( $id, 'under_review', $actor, 'review_started', 'A qualified independent reviewer was assigned.', $expected_version, false );
			$updated = is_wp_error( $result ) ? false : $wpdb->update( GDO_Schema::table( 'applications' ), array( 'assigned_reviewer_id'=>$reviewer_id, 'reviewed_at'=>current_time( 'mysql', true ), 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>$id, 'state'=>'under_review' ), array( '%d','%s','%s' ), array( '%d','%s' ) );
		} else {
			$updated = $wpdb->query( $wpdb->prepare(
				"UPDATE " . GDO_Schema::table( 'applications' ) . " SET assigned_reviewer_id=%d,reviewed_at=%s,row_version=row_version+1,updated_at=%s WHERE id=%d AND state='under_review' AND assigned_reviewer_id IS NULL AND row_version=%d",
				$reviewer_id, current_time( 'mysql', true ), current_time( 'mysql', true ), $id, $expected_version
			) );
			$result = 1 === $updated ? GDO_Audit::transition( $id, $actor, 'under_review', 'under_review', 'review_reassigned', 'A new qualified independent reviewer was assigned after an appeal reopened review.' ) : new WP_Error( 'gdo_assignment_conflict', __( 'The reopened review changed before assignment.', 'global-doctor-onboarding' ) );
		}
		$event = is_wp_error( $result ) || 1 !== $updated ? new WP_Error( 'gdo_assignment_not_ready', __( 'Reviewer assignment is not ready for notification.', 'global-doctor-onboarding' ) ) : GDO_Notifications::queue( 'doctor_application_assigned', $app->user_id, array( 'application_id'=>$id, 'reopened_after_appeal'=>$reopened ? 1 : 0 ), false );
		if ( is_wp_error( $result ) || 1 !== $updated || is_wp_error( $event ) ) {
			$wpdb->query( 'ROLLBACK' );
			$error = is_wp_error( $result ) ? $result : ( is_wp_error( $event ) ? $event : new WP_Error( 'gdo_assignment_commit', __( 'Reviewer assignment failed.', 'global-doctor-onboarding' ) ) );
			wp_die( esc_html( $error->get_error_message() ), '', array( 'response'=>409 ) );
		}
		$commit = $this->commit_or_reconcile( 'assignment', __( 'Reviewer assignment failed.', 'global-doctor-onboarding' ), function() use ( $event ) { return $this->outbox_commit_verified( $event ); } );
		if ( is_wp_error( $commit ) ) { wp_die( esc_html( $commit->get_error_message() ), '', array( 'response'=>503 ) ); }
		GDO_Audit::publish_transition( $result );
		GDO_Notifications::process( 1, $event );
		$this->redirect();
	}

	public function review_evidence() {
		$this->guard( 'sabri_verify_doctors' );
		global $wpdb;
		$id = absint( isset( $_POST['evidence_id'] ) ? $_POST['evidence_id'] : 0 );
		check_admin_referer( 'gdo_review_evidence_' . $id );
		$wpdb->last_error = '';
		$record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE id=%d', $id ) );
		if ( null === $record && ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'Credential evidence could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
		$wpdb->last_error = '';
		$app = $record ? GDO_Application::get( $record->application_id ) : null;
		if ( $record && null === $app && ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The credential application could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
		$reviewer = get_current_user_id();
		if ( ! $app || absint( $app->assigned_reviewer_id ) !== $reviewer || ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer, $app->user_id, $app->id ) ) {
			wp_die( esc_html__( 'Evidence review access denied.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) );
		}
		$status = sanitize_key( isset( $_POST['status'] ) ? $_POST['status'] : '' );
		$reason = sanitize_textarea_field( isset( $_POST['review_note'] ) ? $_POST['review_note'] : '' );
		$this->begin_transaction_or_die();
		$result = GDO_Evidence::review( $id, $reviewer, $status, isset( $_POST['checklist'] ) ? (array) $_POST['checklist'] : array(), isset( $_POST['registry_result'] ) ? $_POST['registry_result'] : '', isset( $_POST['validity_from'] ) ? $_POST['validity_from'] : '', isset( $_POST['validity_until'] ) ? $_POST['validity_until'] : '', $reason, false );
		$event = null;
		$state = null;
		if ( ! is_wp_error( $result ) && in_array( $status, array( 'more_information','rejected' ), true ) ) {
			$due = gmdate( 'Y-m-d H:i:s', time() + absint( apply_filters( 'gdo_more_information_days', 14, $app->id ) ) * DAY_IN_SECONDS );
			$state = GDO_State::transition( $app->id, 'more_information', $reviewer, 'more_information_required', $reason, $app->row_version, false );
			$updated = is_wp_error( $state ) ? false : $wpdb->update( GDO_Schema::table( 'applications' ), array( 'more_info_due_at'=>$due, 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>$app->id ), array( '%s','%s' ), array( '%d' ) );
			$event = is_wp_error( $state ) || false === $updated ? new WP_Error( 'gdo_more_info_not_ready', __( 'The information request is not ready for notification.', 'global-doctor-onboarding' ) ) : GDO_Notifications::queue( 'doctor_application_more_information', $app->user_id, array( 'application_id'=>$app->id, 'due_at'=>gmdate( 'c', strtotime( $due . ' UTC' ) ), 'evidence_type'=>$record->document_type ), false );
			if ( is_wp_error( $state ) ) { $result = $state; }
		}
		if ( is_wp_error( $result ) || is_wp_error( $event ) ) {
			$wpdb->query( 'ROLLBACK' );
			$error = is_wp_error( $result ) ? $result : $event;
			wp_die( esc_html( $error->get_error_message() ), '', array( 'response'=>409 ) );
		}
		$commit = $this->commit_or_reconcile( 'evidence_review', __( 'The credential review could not be committed.', 'global-doctor-onboarding' ), function() use ( $id, $reviewer, $status, $event ) {
			if ( $event ) { return $this->outbox_commit_verified( $event ); }
			global $wpdb;
			$wpdb->last_error = '';
			$current = $wpdb->get_row( $wpdb->prepare( 'SELECT status,reviewer_id,reviewed_at FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE id=%d LIMIT 1', $id ) );
			if ( null === $current && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_evidence_review_commit_uncertain', __( 'The credential review commit outcome is uncertain.', 'global-doctor-onboarding' ) ); }
			return $current && sanitize_key( $current->status ) === $status && absint( $current->reviewer_id ) === $reviewer && ! empty( $current->reviewed_at );
		} );
		if ( is_wp_error( $commit ) ) { wp_die( esc_html( $commit->get_error_message() ), '', array( 'response'=>503 ) ); }
		if ( is_array( $state ) ) { GDO_Audit::publish_transition( $state ); }
		GDO_Membership_Adapter::audit( 'doctor_evidence_reviewed', array( 'application_id'=>absint($app->id),'evidence_id'=>$id,'reviewer_id'=>$reviewer,'status'=>$status ) );
		if ( $event ) { GDO_Notifications::process( 1, $event ); }
		$this->redirect();
	}

	public function request_more_info() {
		$this->guard( 'sabri_verify_doctors' );
		global $wpdb;
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_request_more_info_' . $id );
		$wpdb->last_error = '';
		$app = GDO_Application::get( $id );
		if ( null === $app && ! empty( $wpdb->last_error ) ) {
			wp_die( esc_html__( 'The professional application could not be read safely for the information request.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) );
		}
		$reviewer = get_current_user_id();
		$reason = sanitize_textarea_field( isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		$due_date = sanitize_text_field( isset( $_POST['due_date'] ) ? $_POST['due_date'] : '' );
		$normalized_due = GDO_Policy::normalize_future_date( $due_date );
		$due_time = is_wp_error( $normalized_due ) ? 0 : strtotime( $normalized_due . ' 23:59:59 UTC' );
		if ( ! $app || 'under_review' !== $app->state || absint( $app->assigned_reviewer_id ) !== $reviewer || strlen( $reason ) < 20 || ! $due_time || $due_time > time() + 30 * DAY_IN_SECONDS || ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer, $app->user_id, $id ) ) {
			wp_die( esc_html__( 'The information request is invalid or unauthorized.', 'global-doctor-onboarding' ), '', array( 'response'=>400 ) );
		}
		$due = gmdate( 'Y-m-d H:i:s', $due_time );
		$this->begin_transaction_or_die();
		$result = GDO_State::transition( $id, 'more_information', $reviewer, 'more_information_required', $reason, absint( isset( $_POST['row_version'] ) ? $_POST['row_version'] : 0 ), false );
		$updated = is_wp_error( $result ) ? false : $wpdb->update( GDO_Schema::table( 'applications' ), array( 'more_info_due_at'=>$due, 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>$id ), array( '%s','%s' ), array( '%d' ) );
		$event = is_wp_error( $result ) || false === $updated ? new WP_Error( 'gdo_more_info_not_ready', __( 'The information request is not ready for notification.', 'global-doctor-onboarding' ) ) : GDO_Notifications::queue( 'doctor_application_more_information', $app->user_id, array( 'application_id'=>$id, 'due_at'=>gmdate( 'c', $due_time ), 'request'=>$reason ), false );
		if ( is_wp_error( $result ) || false === $updated || is_wp_error( $event ) ) {
			$wpdb->query( 'ROLLBACK' );
			$error = is_wp_error( $result ) ? $result : ( is_wp_error( $event ) ? $event : new WP_Error( 'gdo_more_info_commit', __( 'The information request could not be committed.', 'global-doctor-onboarding' ) ) );
			wp_die( esc_html( $error->get_error_message() ), '', array( 'response'=>409 ) );
		}
		$commit = $this->commit_or_reconcile( 'more_info', __( 'The information request could not be committed.', 'global-doctor-onboarding' ), function() use ( $event ) { return $this->outbox_commit_verified( $event ); } );
		if ( is_wp_error( $commit ) ) { wp_die( esc_html( $commit->get_error_message() ), '', array( 'response'=>503 ) ); }
		GDO_Audit::publish_transition( $result );
		GDO_Notifications::process( 1, $event );
		$this->redirect();
	}

	public function recommend() {
		$this->guard( 'sabri_verify_doctors' );
		global $wpdb;
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_recommend_decision_' . $id );
		$reviewer = get_current_user_id();
		$decision = sanitize_key( isset( $_POST['decision'] ) ? $_POST['decision'] : '' );
		$reason = sanitize_textarea_field( isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		$expected_version = absint( isset( $_POST['row_version'] ) ? $_POST['row_version'] : 0 );
		$this->begin_transaction_or_die();
		$app = $this->lock_application( $id );
		if ( ! $app || $expected_version !== absint( $app->row_version ) || 'under_review' !== $app->state || absint( $app->assigned_reviewer_id ) !== $reviewer || ! in_array( $decision, array( 'verified','rejected' ), true ) || strlen( $reason ) < 20 || ! GDO_Membership_Adapter::reviewer_case_allows( $reviewer, $app->user_id, $id ) || GDO_Risk::unresolved( $id, 'high' ) || ( 'verified' === $decision && ! GDO_Evidence::all_accepted( $id ) ) ) {
			$this->rollback_die( __( 'The recommendation is incomplete, conflicted, stale, or blocked by evidence/risk requirements.', 'global-doctor-onboarding' ), 409 );
		}
		$result = GDO_State::transition( $id, 'recommended', $reviewer, 'verification_recommended', $reason, $expected_version, false );
		$updated = is_wp_error( $result ) ? false : $wpdb->update( GDO_Schema::table( 'applications' ), array( 'recommender_id'=>$reviewer, 'recommended_decision'=>$decision, 'recommendation_reason'=>$reason, 'recommendation_at'=>current_time( 'mysql', true ), 'updated_at'=>current_time( 'mysql', true ) ), array( 'id'=>$id ), array( '%d','%s','%s','%s','%s' ), array( '%d' ) );
		if ( is_wp_error( $result ) || false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html( is_wp_error( $result ) ? $result->get_error_message() : __( 'Recommendation failed.', 'global-doctor-onboarding' ) ), '', array( 'response'=>409 ) );
		}
		$commit = $this->commit_or_reconcile( 'recommendation', __( 'Recommendation failed.', 'global-doctor-onboarding' ), function() use ( $id, $reviewer, $decision ) {
			global $wpdb;
			$wpdb->last_error = '';
			$current = $wpdb->get_row( $wpdb->prepare( 'SELECT state,recommender_id,recommended_decision FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d LIMIT 1', $id ) );
			if ( null === $current && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_recommendation_commit_uncertain', __( 'Recommendation commit outcome is uncertain.', 'global-doctor-onboarding' ) ); }
			return $current && 'recommended' === sanitize_key( $current->state ) && absint( $current->recommender_id ) === $reviewer && sanitize_key( $current->recommended_decision ) === $decision;
		} );
		if ( is_wp_error( $commit ) ) { wp_die( esc_html( $commit->get_error_message() ), '', array( 'response'=>503 ) ); }
		GDO_Audit::publish_transition( $result );
		$this->redirect();
	}

	public function finalize() {
		$this->guard( 'sabri_finalize_doctor_verification' );
		global $wpdb;
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_finalize_decision_' . $id );
		$finalizer = get_current_user_id();
		$decision = sanitize_key( isset( $_POST['decision'] ) ? $_POST['decision'] : '' );
		$reason = sanitize_textarea_field( isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		$until = sanitize_text_field( isset( $_POST['verified_until'] ) ? $_POST['verified_until'] : '' );
		$expected_version = absint( isset( $_POST['row_version'] ) ? $_POST['row_version'] : 0 );
		if ( ! defined( 'GDO_CLAIM_SIGNING_KEY' ) || strlen( (string) GDO_CLAIM_SIGNING_KEY ) < 32 ) {
			wp_die( esc_html__( 'The File 09 claim signing key must be configured before a final decision.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) );
		}
		$this->begin_transaction_or_die();
		$app = $this->lock_application( $id );
		if ( ! $app || $expected_version !== absint( $app->row_version ) || 'recommended' !== $app->state || absint( $app->user_id ) === $finalizer || absint( $app->recommender_id ) === $finalizer || ! in_array( $decision, array( 'verified','rejected','under_review' ), true ) || strlen( $reason ) < 20 || ! GDO_Membership_Adapter::reviewer_case_allows( $finalizer, $app->user_id, $id ) ) {
			$this->rollback_die( __( 'Invalid, conflicted, stale, or unauthorized final decision.', 'global-doctor-onboarding' ), 409 );
		}
		$normalized_until = 'verified' === $decision ? GDO_Policy::normalize_future_date( $until ) : '';
		$evidence_ceiling = 'verified' === $decision ? GDO_Evidence::verification_valid_until_ceiling( $id ) : 0;
		$requested_until = ! is_wp_error( $normalized_until ) && $normalized_until ? strtotime( $normalized_until . ' 23:59:59 UTC' ) : 0;
		if ( 'verified' === $decision && ( is_wp_error( $normalized_until ) || is_wp_error( $evidence_ceiling ) || ! $requested_until || $requested_until > $evidence_ceiling || ! GDO_Evidence::all_accepted( $id ) || GDO_Risk::unresolved( $id, 'high' ) || ! GDO_Membership_Adapter::is_active_doctor_candidate( $app->user_id, $app->jurisdiction ) ) ) {
			$this->rollback_die( __( 'Verification requires accepted current evidence, a real future expiry date no later than the earliest supporting credential/evidence expiry, and resolved high-risk signals.', 'global-doctor-onboarding' ), 400 );
		}
		if ( 'verified' === $decision ) { $until = $normalized_until; }
		$profile = json_decode( $app->profile_json, true );
		if ( ! is_array( $profile ) ) {
			$this->rollback_die( __( 'The immutable professional profile snapshot is unreadable and cannot be finalized safely.', 'global-doctor-onboarding' ), 503 );
		}
		$evidence_records = GDO_Evidence::records_checked( $id, true );
		if ( is_wp_error( $evidence_records ) ) {
			$this->rollback_die( __( 'The immutable credential snapshot could not be read safely for final decision.', 'global-doctor-onboarding' ), 503 );
		}
		$evidence = array();
		foreach ( $evidence_records as $record ) {
			$evidence[ $record->document_type ] = array( 'version'=>absint( $record->version ), 'content_hmac'=>$record->content_hmac, 'status'=>$record->status, 'validity_until'=>$record->validity_until, 'expires_at'=>$record->expires_at );
		}
		$snapshot = array( 'schema'=>GDO_SCHEMA_VERSION, 'application_uuid'=>$app->application_uuid, 'application_version'=>absint( $app->version ), 'profile'=>$profile, 'evidence'=>$evidence, 'verified_until'=>$until, 'policy_version'=>GDO_Policy::VERSION, 'recommender_id'=>absint( $app->recommender_id ), 'finalizer_id'=>$finalizer, 'captured_at'=>current_time( 'mysql', true ) );
		$fingerprint = GDO_Application::fingerprint( (array) $profile, $evidence );
		$result = GDO_State::transition( $id, $decision, $finalizer, 'verification_finalized', $reason, $expected_version, false );
		$data = array( 'finalizer_id'=>$finalizer, 'decision_at'=>current_time( 'mysql', true ), 'verified_until'=>'verified' === $decision ? gmdate( 'Y-m-d 23:59:59', strtotime( $until . ' UTC' ) ) : null, 'approved_snapshot_json'=>'verified' === $decision ? wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : null, 'approved_fingerprint'=>'verified' === $decision ? $fingerprint : null, 'retention_until'=>'rejected' === $decision ? GDO_State::retention_deadline( 'rejected' ) : null, 'claim_status'=>'pending', 'updated_at'=>current_time( 'mysql', true ) );
		$updated = is_wp_error( $result ) ? false : $wpdb->update( GDO_Schema::table( 'applications' ), $data, array( 'id'=>$id ), array( '%d','%s','%s','%s','%s','%s','%s','%s' ), array( '%d' ) );
		$claim = is_wp_error( $result ) || false === $updated ? new WP_Error( 'gdo_decision_not_ready', __( 'The final decision is not ready for claim issuance.', 'global-doctor-onboarding' ) ) : GDO_Claims::issue( $id, $decision, 'verified' === $decision ? $snapshot : array(), false );
		$notice_event = is_wp_error( $claim ) ? $claim : GDO_Notifications::queue( 'doctor_verification_' . $decision, $app->user_id, array( 'application_id'=>$id, 'state'=>$decision, 'verified_until'=>$until ), false );
		if ( is_wp_error( $result ) || false === $updated || is_wp_error( $claim ) || is_wp_error( $notice_event ) ) {
			$wpdb->query( 'ROLLBACK' );
			$error = is_wp_error( $result ) ? $result : ( is_wp_error( $claim ) ? $claim : ( is_wp_error( $notice_event ) ? $notice_event : new WP_Error( 'gdo_decision_commit', __( 'The final decision could not be committed.', 'global-doctor-onboarding' ) ) ) );
			wp_die( esc_html( $error->get_error_message() ), '', array( 'response'=>409 ) );
		}
		$commit = $this->commit_or_reconcile( 'decision', __( 'The final decision could not be committed.', 'global-doctor-onboarding' ), function() use ( $notice_event ) { return $this->outbox_commit_verified( $notice_event ); } );
		if ( is_wp_error( $commit ) ) { wp_die( esc_html( $commit->get_error_message() ), '', array( 'response'=>503 ) ); }
		GDO_Audit::publish_transition( $result );
		GDO_Claims::publish( $claim );
		$quality_sample = GDO_Quality::create_sample( $id, absint( $app->recommender_id ), $decision );
		if ( is_wp_error( $quality_sample ) ) {
			GDO_Membership_Adapter::audit( 'doctor_verification_quality_sample_failed', array( 'application_id'=>$id, 'reviewer_id'=>absint( $app->recommender_id ), 'error'=>$quality_sample->get_error_code() ) );
		}
		GDO_Notifications::process( 2 );
		do_action( 'gdo_verification_decision_changed', $app->user_id, $decision, $id, 'verified' === $decision ? $snapshot : array() );
		$this->redirect();
	}

	public function issue_evidence_grant() {
		$this->guard( 'sabri_verify_doctors' );
		$id = absint( isset( $_POST['evidence_id'] ) ? $_POST['evidence_id'] : 0 );
		check_admin_referer( 'gdo_issue_evidence_grant_' . $id );
		$mode = sanitize_key( isset( $_POST['mode'] ) ? $_POST['mode'] : 'view' );
		$result = GDO_Evidence::issue_view_grant( $id, get_current_user_id(), isset( $_POST['purpose'] ) ? $_POST['purpose'] : '', $mode );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>403 ) );
		}
		$this->serve_grant( $result['token'], $mode );
	}

	private function serve_grant( $token, $mode ) {
		$result = GDO_Evidence::consume_view_grant( $token, get_current_user_id(), $mode );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>403 ) );
		}
		$record = $result['record'];
		$bytes = 'view' === $mode ? GDO_Evidence::review_bytes( $record, get_current_user_id() ) : GDO_Evidence::decrypt_record( $record );
		if ( is_wp_error( $bytes ) ) {
			GDO_Audit::access( $record->application_id, $record->id, get_current_user_id(), 'render', 'failed' );
			wp_die( esc_html( $bytes->get_error_message() ), '', array( 'response'=>500 ) );
		}
		$served_audit = GDO_Audit::access( $record->application_id, $record->id, get_current_user_id(), 'professional_review', 'served' );
		if ( is_wp_error( $served_audit ) ) {
			// Confidential credential bytes must never leave the server unless the
			// access itself has a durable audit record.
			wp_die( esc_html( $served_audit->get_error_message() ), '', array( 'response'=>503 ) );
		}
		while ( ob_get_level() ) { ob_end_clean(); }
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: no-referrer' );
		header( "Content-Security-Policy: sandbox; default-src 'none'" );
		header( 'Content-Type: ' . $record->mime_type );
		$disposition = 'view' === $mode ? 'inline' : 'attachment';
		header( 'Content-Disposition: ' . $disposition . '; filename="' . sanitize_file_name( $record->original_name ) . '"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		echo $bytes;
		exit;
	}

	public function change_state() {
		$this->guard( 'sabri_finalize_doctor_verification' );
		global $wpdb;
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_change_verification_state_' . $id );
		$state = sanitize_key( isset( $_POST['state'] ) ? $_POST['state'] : '' );
		$reason = sanitize_textarea_field( isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		$until = sanitize_text_field( isset( $_POST['verified_until'] ) ? $_POST['verified_until'] : '' );
		$actor = get_current_user_id();
		$expected_version = absint( isset( $_POST['row_version'] ) ? $_POST['row_version'] : 0 );
		$this->begin_transaction_or_die();
		$app = $this->lock_application( $id );
		if ( ! $app || $expected_version !== absint( $app->row_version ) || absint( $app->user_id ) === $actor || strlen( $reason ) < 20 || ! in_array( $state, array( 'suspended','revoked','renewal_due','reinstated' ), true ) || ! GDO_State::can_transition( $app->state, $state ) || ! GDO_Membership_Adapter::reviewer_case_allows( $actor, $app->user_id, $id ) ) {
			$this->rollback_die( __( 'Invalid, stale, or unauthorized verification lifecycle decision.', 'global-doctor-onboarding' ), 409 );
		}
		$snapshot_refresh = array();
		$normalized_until = 'reinstated' === $state ? GDO_Policy::normalize_future_date( $until ) : '';
		if ( 'reinstated' === $state && ( is_wp_error( $normalized_until ) || ! GDO_Evidence::all_accepted( $id ) || GDO_Risk::unresolved( $id, 'high' ) || ! GDO_Membership_Adapter::is_active_doctor_candidate( $app->user_id, $app->jurisdiction ) ) ) {
			$this->rollback_die( __( 'Reinstatement requires current File 00 assurance, accepted evidence, a real future validity date, and resolved high-risk signals.', 'global-doctor-onboarding' ), 400 );
		}
		if ( 'reinstated' === $state ) { $until = $normalized_until; }
		if ( 'reinstated' === $state ) {
			$snapshot_refresh = GDO_Application::refresh_approved_snapshot( $app, $until, $actor );
			if ( is_wp_error( $snapshot_refresh ) ) { $this->rollback_die( $snapshot_refresh->get_error_message(), 409 ); }
		}
		$result = GDO_State::transition( $id, $state, $actor, 'verification_lifecycle', $reason, $expected_version, false );
		$data = array( 'claim_status'=>'pending', 'updated_at'=>current_time( 'mysql', true ) );
		$formats = array( '%s','%s' );
		if ( 'reinstated' === $state ) { $data['verified_until'] = $snapshot_refresh['verified_until']; $data['finalizer_id'] = $actor; $data['approved_snapshot_json'] = $snapshot_refresh['json']; $data['approved_fingerprint'] = $snapshot_refresh['fingerprint']; $formats[]='%s'; $formats[]='%d'; $formats[]='%s'; $formats[]='%s'; }
		if ( 'revoked' === $state ) { $data['retention_until'] = GDO_State::retention_deadline( 'revoked' ); $formats[]='%s'; }
		$updated = is_wp_error( $result ) ? false : $wpdb->update( GDO_Schema::table( 'applications' ), $data, array( 'id'=>$id ), $formats, array( '%d' ) );
		$claim = is_wp_error( $result ) || false === $updated ? new WP_Error( 'gdo_lifecycle_not_ready', __( 'The lifecycle decision is not ready for claim issuance.', 'global-doctor-onboarding' ) ) : GDO_Claims::issue( $id, $state, 'reinstated' === $state ? $snapshot_refresh['snapshot'] : array(), false );
		$notice_event = is_wp_error( $claim ) ? $claim : GDO_Notifications::queue( 'doctor_verification_' . $state, $app->user_id, array( 'application_id'=>$id, 'state'=>$state, 'verified_until'=>$until ), false );
		if ( is_wp_error( $result ) || false === $updated || is_wp_error( $claim ) || is_wp_error( $notice_event ) ) {
			$wpdb->query( 'ROLLBACK' );
			$error = is_wp_error( $result ) ? $result : ( is_wp_error( $claim ) ? $claim : ( is_wp_error( $notice_event ) ? $notice_event : new WP_Error( 'gdo_lifecycle_commit', __( 'The lifecycle decision could not be committed.', 'global-doctor-onboarding' ) ) ) );
			wp_die( esc_html( $error->get_error_message() ), '', array( 'response'=>409 ) );
		}
		$commit = $this->commit_or_reconcile( 'lifecycle', __( 'The lifecycle decision could not be committed.', 'global-doctor-onboarding' ), function() use ( $notice_event ) { return $this->outbox_commit_verified( $notice_event ); } );
		if ( is_wp_error( $commit ) ) { wp_die( esc_html( $commit->get_error_message() ), '', array( 'response'=>503 ) ); }
		GDO_Audit::publish_transition( $result );
		GDO_Claims::publish( $claim );
		GDO_Notifications::process( 2 );
		do_action( 'gdo_verification_decision_changed', $app->user_id, $state, $id, 'reinstated' === $state ? $snapshot_refresh['snapshot'] : array() );
		$this->redirect();
	}

	public function assign_appeal() {
		$this->guard( 'sabri_manage_doctor_verification' );
		global $wpdb;
		$application_id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		$appeal_id = absint( isset( $_POST['appeal_id'] ) ? $_POST['appeal_id'] : 0 );
		$reviewer_id = absint( isset( $_POST['reviewer_id'] ) ? $_POST['reviewer_id'] : 0 );
		check_admin_referer( 'gdo_assign_appeal_' . $appeal_id );
		$this->begin_transaction_or_die();
		$wpdb->last_error = '';
		$wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . ' WHERE user_id=%d FOR UPDATE', $reviewer_id ) );
		if ( ! empty( $wpdb->last_error ) ) { $this->rollback_die( __( 'The appeal reviewer profile could not be locked safely.', 'global-doctor-onboarding' ), 503 ); }
		$app = $this->lock_application( $application_id );
		$wpdb->last_error = '';
		$appeal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'appeals' ) . " WHERE id=%d AND application_id=%d AND status='open' FOR UPDATE", $appeal_id, $application_id ) );
		if ( null === $appeal && ! empty( $wpdb->last_error ) ) { $this->rollback_die( __( 'The appeal assignment state could not be read safely.', 'global-doctor-onboarding' ), 503 ); }
		$conflicts = $app ? array_filter( array( absint( $app->user_id ), absint( $app->assigned_reviewer_id ), absint( $app->recommender_id ), absint( $app->finalizer_id ) ) ) : array();
		$eligible = $app ? $this->reviewer_eligible( $reviewer_id, $app ) : new WP_Error( 'gdo_application_missing', __( 'Application unavailable.', 'global-doctor-onboarding' ) );
		if ( ! $app || ! $appeal || 'appeal_pending' !== $app->state || ! empty( $appeal->assigned_reviewer_id ) || in_array( $reviewer_id, $conflicts, true ) || is_wp_error( $eligible ) || ! GDO_Membership_Adapter::can( 'sabri_finalize_doctor_verification', $reviewer_id ) ) {
			$wpdb->query( 'ROLLBACK' );
			$message = is_wp_error( $eligible ) ? $eligible->get_error_message() : __( 'The appeal cannot be assigned to that reviewer.', 'global-doctor-onboarding' );
			wp_die( esc_html( $message ), '', array( 'response'=>400 ) );
		}
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . GDO_Schema::table( 'appeals' ) . " SET assigned_reviewer_id=%d WHERE id=%d AND application_id=%d AND status='open' AND assigned_reviewer_id IS NULL", $reviewer_id, $appeal_id, $application_id ) );
		$event = 1 === $updated ? GDO_Notifications::queue( 'doctor_verification_appeal_assigned', $reviewer_id, array( 'application_id'=>$application_id, 'appeal_id'=>$appeal_id ), false ) : new WP_Error( 'gdo_appeal_assignment_conflict', __( 'The appeal assignment changed. Reload and try again.', 'global-doctor-onboarding' ) );
		if ( 1 !== $updated || is_wp_error( $event ) ) {
			$wpdb->query( 'ROLLBACK' );
			$error = is_wp_error( $event ) ? $event : new WP_Error( 'gdo_appeal_assignment_commit', __( 'The appeal assignment could not be committed.', 'global-doctor-onboarding' ) );
			wp_die( esc_html( $error->get_error_message() ), '', array( 'response'=>409 ) );
		}
		$commit = $this->commit_or_reconcile( 'appeal_assignment', __( 'The appeal assignment could not be committed.', 'global-doctor-onboarding' ), function() use ( $event ) { return $this->outbox_commit_verified( $event ); } );
		if ( is_wp_error( $commit ) ) { wp_die( esc_html( $commit->get_error_message() ), '', array( 'response'=>503 ) ); }
		GDO_Membership_Adapter::audit( 'doctor_verification_appeal_assigned', array( 'application_id'=>$application_id, 'appeal_id'=>$appeal_id, 'reviewer_id'=>$reviewer_id, 'actor_id'=>get_current_user_id() ) );
		GDO_Notifications::process( 1, $event );
		$this->redirect();
	}

	public function resolve_appeal() {
		$this->guard( 'sabri_finalize_doctor_verification' );
		global $wpdb;
		$id = absint( isset( $_POST['application_id'] ) ? $_POST['application_id'] : 0 );
		check_admin_referer( 'gdo_resolve_appeal_' . $id );
		$decision = sanitize_key( isset( $_POST['decision'] ) ? $_POST['decision'] : '' );
		$reason = sanitize_textarea_field( isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		$until = sanitize_text_field( isset( $_POST['verified_until'] ) ? $_POST['verified_until'] : '' );
		$actor = get_current_user_id();
		$expected_version = absint( isset( $_POST['row_version'] ) ? $_POST['row_version'] : 0 );
		$allowed = array( 'rejected'=>array( 'under_review','rejected','revoked' ), 'suspended'=>array( 'under_review','reinstated','suspended','revoked' ), 'revoked'=>array( 'under_review','reinstated','revoked' ) );
		$this->begin_transaction_or_die();
		$app = $this->lock_application( $id );
		$wpdb->last_error = '';
		$appeal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'appeals' ) . " WHERE application_id=%d AND status='open' ORDER BY id DESC LIMIT 1 FOR UPDATE", $id ) );
		if ( null === $appeal && ! empty( $wpdb->last_error ) ) { $this->rollback_die( __( 'The appeal resolution state could not be read safely.', 'global-doctor-onboarding' ), 503 ); }
		$conflict = $app && in_array( $actor, array( absint( $app->assigned_reviewer_id ), absint( $app->recommender_id ), absint( $app->finalizer_id ), absint( $app->user_id ) ), true );
		$assigned_to_actor = $appeal && absint( $appeal->assigned_reviewer_id ) === $actor;
		if ( ! $app || $expected_version !== absint( $app->row_version ) || ! $appeal || 'appeal_pending' !== $app->state || ! $assigned_to_actor || $conflict || strlen( $reason ) < 20 || empty( $allowed[ $appeal->source_state ] ) || ! in_array( $decision, $allowed[ $appeal->source_state ], true ) || ! GDO_State::can_transition( $app->state, $decision ) || ! GDO_Membership_Adapter::reviewer_case_allows( $actor, $app->user_id, $id ) ) {
			$this->rollback_die( __( 'Invalid, conflicted, stale, or unauthorized appeal decision.', 'global-doctor-onboarding' ), 409 );
		}
		$snapshot_refresh = array();
		$normalized_until = 'reinstated' === $decision ? GDO_Policy::normalize_future_date( $until ) : '';
		if ( 'reinstated' === $decision && ( is_wp_error( $normalized_until ) || ! GDO_Evidence::all_accepted( $id ) || GDO_Risk::unresolved( $id, 'high' ) || ! GDO_Membership_Adapter::is_active_doctor_candidate( $app->user_id, $app->jurisdiction ) ) ) {
			$this->rollback_die( __( 'Reinstatement requires current File 00 assurance, accepted evidence, a real future validity date, and resolved high-risk signals.', 'global-doctor-onboarding' ), 400 );
		}
		if ( 'reinstated' === $decision ) { $until = $normalized_until; }
		if ( 'reinstated' === $decision ) {
			$snapshot_refresh = GDO_Application::refresh_approved_snapshot( $app, $until, $actor );
			if ( is_wp_error( $snapshot_refresh ) ) { $this->rollback_die( $snapshot_refresh->get_error_message(), 409 ); }
		}
		$result = GDO_State::transition( $id, $decision, $actor, 'appeal_resolved', $reason, $expected_version, false );
		$appeal_updated = is_wp_error( $result ) ? false : $wpdb->update( GDO_Schema::table( 'appeals' ), array( 'status'=>'resolved', 'resolution'=>$reason, 'decision'=>$decision, 'resolver_id'=>$actor, 'resolved_at'=>current_time( 'mysql', true ) ), array( 'id'=>$appeal->id, 'status'=>'open', 'assigned_reviewer_id'=>$actor ), array( '%s','%s','%s','%d','%s' ), array( '%d','%s','%d' ) );
		$app_data = array( 'claim_status'=>'pending', 'updated_at'=>current_time( 'mysql', true ) );
		$formats = array( '%s','%s' );
		if ( 'under_review' === $decision ) { $app_data['assigned_reviewer_id']=null; $app_data['recommender_id']=null; $app_data['finalizer_id']=null; $app_data['recommended_decision']=null; $formats=array( '%s','%s','%s','%s','%s','%s' ); }
		if ( 'reinstated' === $decision ) { $app_data['verified_until']=$snapshot_refresh['verified_until']; $app_data['finalizer_id']=$actor; $app_data['approved_snapshot_json']=$snapshot_refresh['json']; $app_data['approved_fingerprint']=$snapshot_refresh['fingerprint']; $formats[]='%s'; $formats[]='%d'; $formats[]='%s'; $formats[]='%s'; }
		$app_updated = is_wp_error( $result ) ? false : $wpdb->update( GDO_Schema::table( 'applications' ), $app_data, array( 'id'=>$id ), $formats, array( '%d' ) );
		$claim = is_wp_error( $result ) || 1 !== $appeal_updated || false === $app_updated ? new WP_Error( 'gdo_appeal_not_ready', __( 'The appeal decision is not ready for claim issuance.', 'global-doctor-onboarding' ) ) : GDO_Claims::issue( $id, $decision, 'reinstated' === $decision ? $snapshot_refresh['snapshot'] : array(), false );
		$notice_event = is_wp_error( $claim ) ? $claim : GDO_Notifications::queue( 'doctor_verification_appeal_resolved', $app->user_id, array( 'application_id'=>$id, 'state'=>$decision ), false );
		if ( is_wp_error( $result ) || 1 !== $appeal_updated || false === $app_updated || is_wp_error( $claim ) || is_wp_error( $notice_event ) ) {
			$wpdb->query( 'ROLLBACK' );
			$error = is_wp_error( $result ) ? $result : ( is_wp_error( $claim ) ? $claim : ( is_wp_error( $notice_event ) ? $notice_event : new WP_Error( 'gdo_appeal_commit', __( 'The appeal resolution could not be committed.', 'global-doctor-onboarding' ) ) ) );
			wp_die( esc_html( $error->get_error_message() ), '', array( 'response'=>409 ) );
		}
		$commit = $this->commit_or_reconcile( 'appeal_resolution', __( 'The appeal resolution could not be committed.', 'global-doctor-onboarding' ), function() use ( $notice_event ) { return $this->outbox_commit_verified( $notice_event ); } );
		if ( is_wp_error( $commit ) ) { wp_die( esc_html( $commit->get_error_message() ), '', array( 'response'=>503 ) ); }
		GDO_Audit::publish_transition( $result );
		GDO_Claims::publish( $claim );
		GDO_Notifications::process( 2 );
		$this->redirect();
	}

	public function resolve_risk() {
		$this->guard( 'sabri_manage_doctor_verification' );
		$id = absint( isset( $_POST['signal_id'] ) ? $_POST['signal_id'] : 0 );
		check_admin_referer( 'gdo_resolve_risk_' . $id );
		$result = GDO_Risk::resolve( $id, get_current_user_id(), isset( $_POST['decision'] ) ? $_POST['decision'] : '', isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>400 ) ); }
		$this->redirect();
	}

	public function complete_quality_sample() {
		$this->guard( 'sabri_manage_doctor_verification' );
		$id = absint( isset( $_POST['sample_id'] ) ? $_POST['sample_id'] : 0 );
		check_admin_referer( 'gdo_complete_quality_sample_' . $id );
		$result = GDO_Quality::complete_sample( $id, get_current_user_id(), isset( $_POST['outcome'] ) ? $_POST['outcome'] : '', isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>400 ) ); }
		$this->redirect();
	}

	public function save_reviewer_profile() {
		$this->guard( 'sabri_manage_doctor_verification' );
		global $wpdb;
		$user_id = absint( isset( $_POST['reviewer_id'] ) ? $_POST['reviewer_id'] : 0 );
		check_admin_referer( 'gdo_save_reviewer_profile_' . $user_id );
		if ( ! GDO_Membership_Adapter::can( 'sabri_verify_doctors', $user_id ) ) {
			wp_die( esc_html__( 'The selected user is not an authorized File 00 reviewer.', 'global-doctor-onboarding' ), '', array( 'response'=>400 ) );
		}
		$jurisdictions = array_filter( array_map( array( 'GDO_Policy','normalize_jurisdiction' ), preg_split( '/[,\s]+/', sanitize_text_field( isset( $_POST['jurisdictions'] ) ? $_POST['jurisdictions'] : '' ) ) ) );
		$languages = array_filter( array_map( 'sanitize_text_field', preg_split( '/[,\s]+/', sanitize_text_field( isset( $_POST['languages'] ) ? $_POST['languages'] : '' ) ) ) );
		$saved = $wpdb->replace( GDO_Schema::table( 'reviewer_profiles' ), array( 'user_id'=>$user_id, 'jurisdictions_json'=>wp_json_encode( array_values( array_unique( $jurisdictions ) ) ), 'languages_json'=>wp_json_encode( array_values( array_unique( $languages ) ) ), 'max_open_cases'=>max( 1, min( 100, absint( isset( $_POST['max_open_cases'] ) ? $_POST['max_open_cases'] : 25 ) ) ), 'status'=>'active', 'qualification_checked_at'=>current_time( 'mysql', true ), 'access_reviewed_at'=>current_time( 'mysql', true ), 'updated_at'=>current_time( 'mysql', true ) ), array( '%d','%s','%s','%d','%s','%s','%s','%s' ) );
		if ( false === $saved ) { wp_die( esc_html__( 'The reviewer profile could not be stored.', 'global-doctor-onboarding' ), '', array( 'response'=>500 ) ); }
		GDO_Membership_Adapter::audit( 'doctor_verification_reviewer_profile_saved', array( 'reviewer_id'=>$user_id, 'actor_id'=>get_current_user_id() ) );
		$this->redirect();
	}

	public function toggle_safe_mode() {
		$this->guard( 'sabri_manage_doctor_verification', true );
		check_admin_referer( 'gdo_toggle_safe_mode' );
		$result = GDO_Operations::set_safe_mode( ! empty( $_POST['enabled'] ), get_current_user_id(), isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>400 ) ); }
		$this->redirect();
	}

	public function run_repair() {
		$this->guard( 'sabri_manage_doctor_verification', true );
		check_admin_referer( 'gdo_run_repair' );
		$result = GDO_Operations::repair( isset( $_POST['repair_action'] ) ? $_POST['repair_action'] : '', get_current_user_id(), isset( $_POST['reason'] ) ? $_POST['reason'] : '' );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>400 ) );
		}
		$this->redirect();
	}

	public function replay_outbox() {
		$this->guard( 'sabri_manage_doctor_verification', true );
		check_admin_referer( 'gdo_replay_outbox' );
		$result = GDO_Notifications::process( 100 );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>503, 'back_link'=>true ) );
		}
		$this->redirect();
	}

	private function redirect() {
		wp_safe_redirect( add_query_arg( array( 'page'=>'global-doctor-verification', 'updated'=>'1' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
