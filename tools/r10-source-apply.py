from pathlib import Path
import subprocess

root=Path(__file__).resolve().parents[1]
BASE='ec3ca2dd715e05b66cf29c42f2f996c80987fcd7'
if subprocess.call(['git','merge-base','--is-ancestor',BASE,'HEAD'],cwd=root)!=0:
    raise SystemExit('R10 frozen baseline is not an ancestor of this corrective harness head')

def rep(rel, old, new):
    p=root/rel
    s=p.read_text(encoding='utf-8')
    c=s.count(old)
    if c!=1:
        raise SystemExit(f'R10 anchor mismatch {rel}: {c}')
    p.write_text(s.replace(old,new,1),encoding='utf-8')

# R04 — public verification projection must distinguish DB uncertainty from not-applied.
rel='includes/class-gdo-api.php'
rep(rel,"""\tpublic static function latest_decision( $user_id ) {
\t\t$user_id = absint( $user_id );
\t\t$app = GDO_Application::verification_record_for_user( $user_id );
\t\t$checked_at = gmdate( 'c' );
\t\tif ( ! $app ) {
""","""\tpublic static function latest_decision( $user_id ) {
\t\tglobal $wpdb;
\t\t$user_id = absint( $user_id );
\t\t$checked_at = gmdate( 'c' );
\t\t$wpdb->last_error = '';
\t\t$app = GDO_Application::verification_record_for_user( $user_id );
\t\tif ( ! empty( $wpdb->last_error ) ) {
\t\t\treturn array(
\t\t\t\t'state'       => 'unavailable',
\t\t\t\t'verified'    => false,
\t\t\t\t'reason_code' => 'database_unavailable',
\t\t\t\t'checked_at'  => $checked_at,
\t\t\t);
\t\t}
\t\tif ( ! $app ) {
""")
rep(rel,"""\t\t$snapshot = GDO_Application::approved_snapshot( $app->id );
""","""\t\t// Reuse the already-read application row. A second database read here would
\t\t// create another avoidable uncertainty window in a public trust projection.
\t\t$snapshot = GDO_Application::stored_approved_snapshot( $app );
""")

# R05/R06 — own application REST reads fail visibly; privileged health requires step-up.
rel='includes/class-gdo-rest.php'
rep(rel,"""\tpublic function operator() {
\t\treturn GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification' );
\t}
""","""\tpublic function operator() {
\t\t$user_id = get_current_user_id();
\t\treturn $user_id && GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $user_id ) && GDO_Membership_Adapter::recent_step_up( $user_id );
\t}
""")
rep(rel,"""\tpublic function application() {
\t\t$app = GDO_Application::latest_for_user( get_current_user_id() );
\t\treturn rest_ensure_response( GDO_API::application_edit_model( $app, get_current_user_id() ) );
\t}
""","""\tpublic function application() {
\t\tglobal $wpdb;
\t\t$wpdb->last_error = '';
\t\t$app = GDO_Application::latest_for_user( get_current_user_id() );
\t\tif ( ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'gdo_application_read_failed', __( 'The private doctor application could not be read safely.', 'global-doctor-onboarding' ), array( 'status'=>503 ) );
\t\t}
\t\treturn rest_ensure_response( GDO_API::application_edit_model( $app, get_current_user_id() ) );
\t}
""")
rep(rel,"""\t\t$app = GDO_Application::get( absint( $request->get_param( 'application_id' ) ) );
\t\tif ( ! $app || absint( $app->user_id ) !== get_current_user_id() ) {
""","""\t\tglobal $wpdb;
\t\t$wpdb->last_error = '';
\t\t$app = GDO_Application::get( absint( $request->get_param( 'application_id' ) ) );
\t\tif ( ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'gdo_application_autosave_read_failed', __( 'The private doctor application could not be read safely for autosave.', 'global-doctor-onboarding' ), array( 'status'=>503 ) );
\t\t}
\t\tif ( ! $app || absint( $app->user_id ) !== get_current_user_id() ) {
""")

# R07/R08/R09 — draft lookup, consent and submit row-lock reads must fail visibly.
rel='includes/class-gdo-application.php'
rep(rel,"""\tpublic static function ensure_draft( $user_id ) {
\t\t$user_id = absint( $user_id );
\t\t$latest = self::latest_for_user( $user_id );
""","""\tpublic static function ensure_draft( $user_id ) {
\t\tglobal $wpdb;
\t\t$user_id = absint( $user_id );
\t\t$wpdb->last_error = '';
\t\t$latest = self::latest_for_user( $user_id );
\t\tif ( ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'gdo_application_latest_query', __( 'The current doctor application state could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
""")
rep(rel,"""\t\t$app = $wpdb->get_row( $wpdb->prepare(
\t\t\t'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
\t\t\t$application_id,
\t\t\t$user_id
\t\t) );
\t\tif ( ! $app || ! in_array( $app->state, array( 'draft','more_information' ), true ) || ! GDO_Membership_Adapter::is_active_doctor_candidate( $user_id, $app->jurisdiction ) ) {
""","""\t\t$wpdb->last_error = '';
\t\t$app = $wpdb->get_row( $wpdb->prepare(
\t\t\t'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
\t\t\t$application_id,
\t\t\t$user_id
\t\t) );
\t\tif ( null === $app && ! empty( $wpdb->last_error ) ) {
\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
\t\t\treturn new WP_Error( 'gdo_consent_application_query', __( 'The consent application state could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\tif ( ! $app || ! in_array( $app->state, array( 'draft','more_information' ), true ) || ! GDO_Membership_Adapter::is_active_doctor_candidate( $user_id, $app->jurisdiction ) ) {
""")
rep(rel,"""\t\t$existing = $wpdb->get_row( $wpdb->prepare(
\t\t\t\"SELECT * FROM {$table} WHERE application_id=%d AND user_id=%d AND consent_version=%s FOR UPDATE\",
\t\t\t$application_id,
\t\t\t$user_id,
\t\t\t$text['version']
\t\t) );
\t\tif ( $existing && ( ! empty( $existing->withdrawn_at ) || ! hash_equals( $wording_hash, (string) $existing->wording_hash ) ) ) {
""","""\t\t$wpdb->last_error = '';
\t\t$existing = $wpdb->get_row( $wpdb->prepare(
\t\t\t\"SELECT * FROM {$table} WHERE application_id=%d AND user_id=%d AND consent_version=%s FOR UPDATE\",
\t\t\t$application_id,
\t\t\t$user_id,
\t\t\t$text['version']
\t\t) );
\t\tif ( null === $existing && ! empty( $wpdb->last_error ) ) {
\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
\t\t\treturn new WP_Error( 'gdo_consent_history_query', __( 'Existing consent evidence could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\tif ( $existing && ( ! empty( $existing->withdrawn_at ) || ! hash_equals( $wording_hash, (string) $existing->wording_hash ) ) ) {
""")
rep(rel,"""\t\t$app = $wpdb->get_row( $wpdb->prepare(
\t\t\t'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
\t\t\t$application_id,
\t\t\t$user_id
\t\t) );
\t\tif ( ! $app || ! GDO_Membership_Adapter::is_active_doctor_candidate( $user_id, $app->jurisdiction ) ) {
""","""\t\t$wpdb->last_error = '';
\t\t$app = $wpdb->get_row( $wpdb->prepare(
\t\t\t'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
\t\t\t$application_id,
\t\t\t$user_id
\t\t) );
\t\tif ( null === $app && ! empty( $wpdb->last_error ) ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\treturn new WP_Error( 'gdo_submit_application_query', __( 'The application submission state could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\tif ( ! $app || ! GDO_Membership_Adapter::is_active_doctor_candidate( $user_id, $app->jurisdiction ) ) {
""")

# R10/R11/R12/R13 — privileged admin lock/read paths must not translate DB failures into ordinary conflicts.
rel='includes/class-gdo-admin.php'
rep(rel,"""\tprivate function lock_application( $application_id ) {
\t\tglobal $wpdb;
\t\treturn $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', absint( $application_id ) ) );
\t}
""","""\tprivate function lock_application( $application_id ) {
\t\tglobal $wpdb;
\t\t$wpdb->last_error = '';
\t\t$app = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', absint( $application_id ) ) );
\t\tif ( null === $app && ! empty( $wpdb->last_error ) ) {
\t\t\t$this->rollback_die( __( 'The doctor application could not be locked/read safely.', 'global-doctor-onboarding' ), 503 );
\t\t}
\t\treturn $app;
\t}
""")
rep(rel,"""\t\t$profile = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . \" WHERE user_id=%d AND status='active'\", $reviewer_id ) );
\t\tif ( ! $profile ) {
""","""\t\t$wpdb->last_error = '';
\t\t$profile = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . \" WHERE user_id=%d AND status='active'\", $reviewer_id ) );
\t\tif ( null === $profile && ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'gdo_reviewer_profile_query', __( 'The reviewer qualification profile could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\tif ( ! $profile ) {
""")
rep(rel,"""\t\t$open_apps = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM \" . GDO_Schema::table( 'applications' ) . \" WHERE assigned_reviewer_id=%d AND state IN ('under_review','more_information','recommended')\", $reviewer_id ) );
""","""\t\t$wpdb->last_error = '';
\t\t$open_apps = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM \" . GDO_Schema::table( 'applications' ) . \" WHERE assigned_reviewer_id=%d AND state IN ('under_review','more_information','recommended')\", $reviewer_id ) );
""")
rep(rel,"""\t\t$open_appeals = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM \" . GDO_Schema::table( 'appeals' ) . \" WHERE assigned_reviewer_id=%d AND status='open'\", $reviewer_id ) );
""","""\t\t$wpdb->last_error = '';
\t\t$open_appeals = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM \" . GDO_Schema::table( 'appeals' ) . \" WHERE assigned_reviewer_id=%d AND status='open'\", $reviewer_id ) );
""")
rep(rel,"""\t\t$this->begin_transaction_or_die();
\t\t$wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . ' WHERE user_id=%d FOR UPDATE', $reviewer_id ) );
\t\t$app = $this->lock_application( $id );
""","""\t\t$this->begin_transaction_or_die();
\t\t$wpdb->last_error = '';
\t\t$wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . ' WHERE user_id=%d FOR UPDATE', $reviewer_id ) );
\t\tif ( ! empty( $wpdb->last_error ) ) { $this->rollback_die( __( 'The reviewer profile could not be locked safely for assignment.', 'global-doctor-onboarding' ), 503 ); }
\t\t$app = $this->lock_application( $id );
""")
rep(rel,"""\t\t$record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE id=%d', $id ) );
\t\t$app = $record ? GDO_Application::get( $record->application_id ) : null;
""","""\t\t$wpdb->last_error = '';
\t\t$record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE id=%d', $id ) );
\t\tif ( null === $record && ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'Credential evidence could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
\t\t$wpdb->last_error = '';
\t\t$app = $record ? GDO_Application::get( $record->application_id ) : null;
\t\tif ( $record && null === $app && ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The credential application could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }
""")
rep(rel,"""\t\t$this->begin_transaction_or_die();
\t\t$wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . ' WHERE user_id=%d FOR UPDATE', $reviewer_id ) );
\t\t$locked_app = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', $application_id ) );
\t\t$app = $locked_app ? GDO_Application::get( $application_id ) : null;
\t\t$appeal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'appeals' ) . \" WHERE id=%d AND application_id=%d AND status='open' FOR UPDATE\", $appeal_id, $application_id ) );
""","""\t\t$this->begin_transaction_or_die();
\t\t$wpdb->last_error = '';
\t\t$wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . GDO_Schema::table( 'reviewer_profiles' ) . ' WHERE user_id=%d FOR UPDATE', $reviewer_id ) );
\t\tif ( ! empty( $wpdb->last_error ) ) { $this->rollback_die( __( 'The appeal reviewer profile could not be locked safely.', 'global-doctor-onboarding' ), 503 ); }
\t\t$app = $this->lock_application( $application_id );
\t\t$wpdb->last_error = '';
\t\t$appeal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'appeals' ) . \" WHERE id=%d AND application_id=%d AND status='open' FOR UPDATE\", $appeal_id, $application_id ) );
\t\tif ( null === $appeal && ! empty( $wpdb->last_error ) ) { $this->rollback_die( __( 'The appeal assignment state could not be read safely.', 'global-doctor-onboarding' ), 503 ); }
""")
rep(rel,"""\t\t$app = $this->lock_application( $id );
\t\t$appeal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'appeals' ) . \" WHERE application_id=%d AND status='open' ORDER BY id DESC LIMIT 1 FOR UPDATE\", $id ) );
""","""\t\t$app = $this->lock_application( $id );
\t\t$wpdb->last_error = '';
\t\t$appeal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'appeals' ) . \" WHERE application_id=%d AND status='open' ORDER BY id DESC LIMIT 1 FOR UPDATE\", $id ) );
\t\tif ( null === $appeal && ! empty( $wpdb->last_error ) ) { $this->rollback_die( __( 'The appeal resolution state could not be read safely.', 'global-doctor-onboarding' ), 503 ); }
""")

# R14/R15 — evidence mutation/rotation paths must distinguish DB failures.
rel='includes/class-gdo-evidence.php'
rep(rel,"""        $locked_app = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
            absint( $application->id ),
            $actor_id
        ) );
        $locked_types = $locked_app ? self::types( $locked_app->jurisdiction, $locked_app->application_type ) : array();
""","""        $wpdb->last_error = '';
        $locked_app = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
            absint( $application->id ),
            $actor_id
        ) );
        if ( null === $locked_app && ! empty( $wpdb->last_error ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_application_query', __( 'The credential application could not be locked/read safely.', 'global-doctor-onboarding' ) );
        }
        $locked_types = $locked_app ? self::types( $locked_app->jurisdiction, $locked_app->application_type ) : array();
""")
rep(rel,"""        $current = self::current( $locked_app->id, $type );
        if ( ! self::quota_allows( $locked_app->user_id, $normalized['size'], $current ? $current->file_size : 0 ) ) {
""","""        $wpdb->last_error = '';
        $current = self::current( $locked_app->id, $type );
        if ( null === $current && ! empty( $wpdb->last_error ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_current_query', __( 'Existing credential evidence could not be read safely before replacement.', 'global-doctor-onboarding' ) );
        }
        if ( ! self::quota_allows( $locked_app->user_id, $normalized['size'], $current ? $current->file_size : 0 ) ) {
""")
rep(rel,"""    public static function decrypt_record( $record ) {
        $app = GDO_Application::get( $record->application_id );
        if ( ! $app ) {
""","""    public static function decrypt_record( $record ) {
        global $wpdb;
        $wpdb->last_error = '';
        $app = GDO_Application::get( $record->application_id );
        if ( null === $app && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_decrypt_application_query', __( 'The credential application could not be read safely.', 'global-doctor-onboarding' ) );
        }
        if ( ! $app ) {
""")
rep(rel,"""    public static function rotate_key( $evidence_id ) {
        global $wpdb;
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE id=%d', absint( $evidence_id ) ) );
        $ring = GDO_Crypto::keyring();
        if ( ! $record || is_wp_error( $ring ) || $record->key_id === $ring['active'] || 'GDO2' !== $record->envelope_version ) {
            return true;
        }
""","""    public static function rotate_key( $evidence_id ) {
        global $wpdb;
        $wpdb->last_error = '';
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE id=%d', absint( $evidence_id ) ) );
        if ( null === $record && ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_rotation_evidence_query', __( 'Credential key-rotation state could not be read safely.', 'global-doctor-onboarding' ) );
        }
        $ring = GDO_Crypto::keyring();
        if ( is_wp_error( $ring ) ) { return $ring; }
        if ( ! $record || $record->key_id === $ring['active'] || 'GDO2' !== $record->envelope_version ) {
            return true;
        }
""")

# R16 — quality-sample storage failure must be observable after a committed decision.
rel='includes/class-gdo-quality.php'
rep(rel,"""\tpublic static function create_sample( $application_id, $reviewer_id, $decision, $source = 'automatic' ) {
\t\tif ( ! GDO_Operations::mutation_allowed() ) { return 0; }
""","""\tpublic static function create_sample( $application_id, $reviewer_id, $decision, $source = 'automatic' ) {
\t\tif ( ! GDO_Operations::mutation_allowed() ) {
\t\t\treturn new WP_Error( 'gdo_quality_runtime_not_ready', __( 'Quality sampling is unavailable until the File 09 runtime is healthy.', 'global-doctor-onboarding' ) );
\t\t}
""")
rep(rel,"""\t\t$ok = $wpdb->insert( GDO_Schema::table( 'quality_samples' ), array(
\t\t\t'application_id'=>absint( $application_id ), 'reviewer_id'=>absint( $reviewer_id ),
\t\t\t'original_decision'=>sanitize_key( $decision ), 'status'=>'pending', 'source'=>sanitize_key( $source ),
\t\t\t'created_at'=>current_time( 'mysql', true ), 'updated_at'=>current_time( 'mysql', true ),
\t\t), array( '%d','%d','%s','%s','%s','%s','%s' ) );
\t\treturn 1 === $ok ? absint( $wpdb->insert_id ) : 0;
""","""\t\t$ok = $wpdb->insert( GDO_Schema::table( 'quality_samples' ), array(
\t\t\t'application_id'=>absint( $application_id ), 'reviewer_id'=>absint( $reviewer_id ),
\t\t\t'original_decision'=>sanitize_key( $decision ), 'status'=>'pending', 'source'=>sanitize_key( $source ),
\t\t\t'created_at'=>current_time( 'mysql', true ), 'updated_at'=>current_time( 'mysql', true ),
\t\t), array( '%d','%d','%s','%s','%s','%s','%s' ) );
\t\treturn 1 === $ok ? absint( $wpdb->insert_id ) : new WP_Error( 'gdo_quality_sample_store', __( 'The quality sample could not be stored safely.', 'global-doctor-onboarding' ) );
""")
rel='includes/class-gdo-admin.php'
rep(rel,"""\t\tGDO_Claims::publish( $claim );
\t\tGDO_Quality::create_sample( $id, absint( $app->recommender_id ), $decision );
\t\tGDO_Notifications::process( 2 );
""","""\t\tGDO_Claims::publish( $claim );
\t\t$quality_sample = GDO_Quality::create_sample( $id, absint( $app->recommender_id ), $decision );
\t\tif ( is_wp_error( $quality_sample ) ) {
\t\t\tGDO_Membership_Adapter::audit( 'doctor_verification_quality_sample_failed', array( 'application_id'=>$id, 'reviewer_id'=>absint( $app->recommender_id ), 'error'=>$quality_sample->get_error_code() ) );
\t\t}
\t\tGDO_Notifications::process( 2 );
""")

# R17/R18 — front-end state reads must distinguish DB uncertainty before mutation decisions.
rel='includes/class-gdo-frontend.php'
rep(rel,"""\t\t$user = get_current_user_id();
\t\t$latest = GDO_Application::latest_for_user( $user );
\t\t$eligibility = GDO_Policy::eligibility( $user );
""","""\t\t$user = get_current_user_id();
\t\tglobal $wpdb;
\t\t$wpdb->last_error = '';
\t\t$latest = GDO_Application::latest_for_user( $user );
\t\tif ( ! empty( $wpdb->last_error ) ) {
\t\t\treturn $this->status_panel( null, new WP_Error( 'gdo_frontend_application_query', __( 'The private doctor application state could not be read safely.', 'global-doctor-onboarding' ) ) );
\t\t}
\t\t$eligibility = GDO_Policy::eligibility( $user );
""")
rep(rel,"""\t\t$app = $wpdb->get_row( $wpdb->prepare(
\t\t\t'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE',
\t\t\t$id
\t\t) );
\t\tif ( ! $app || absint( $app->user_id ) !== get_current_user_id() || ! in_array( $app->state, array( 'rejected','suspended','revoked' ), true ) || strlen( $reason ) < 20 || ! GDO_State::can_transition( $app->state, 'appeal_pending' ) ) {
""","""\t\t$wpdb->last_error = '';
\t\t$app = $wpdb->get_row( $wpdb->prepare(
\t\t\t'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE',
\t\t\t$id
\t\t) );
\t\tif ( null === $app && ! empty( $wpdb->last_error ) ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\twp_die( esc_html__( 'The appeal application state could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) );
\t\t}
\t\tif ( ! $app || absint( $app->user_id ) !== get_current_user_id() || ! in_array( $app->state, array( 'rejected','suspended','revoked' ), true ) || strlen( $reason ) < 20 || ! GDO_State::can_transition( $app->state, 'appeal_pending' ) ) {
""")

print('R10 source corrections applied: product defect rounds 04-18')
