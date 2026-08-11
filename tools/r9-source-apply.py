from pathlib import Path
import subprocess

root = Path(__file__).resolve().parents[1]
BASE = '75ea54ed5113bf7ee16e90443f17cc1b941933a9'
if subprocess.call(['git','merge-base','--is-ancestor',BASE,'HEAD'], cwd=root) != 0:
    raise SystemExit('R9 frozen baseline is not an ancestor of the corrective harness head')

def replace(rel, old, new):
    p = root / rel
    s = p.read_text(encoding='utf-8')
    if s.count(old) != 1:
        raise SystemExit(f'R9 anchor mismatch for {rel}: count={s.count(old)}')
    p.write_text(s.replace(old, new, 1), encoding='utf-8')

# R04/R05 — credential-review owner command must reauthorize at method boundary
# and distinguish database uncertainty from a normal invalid object/state.
rel = 'includes/class-gdo-evidence.php'
replace(rel, """    public static function review( $evidence_id, $reviewer_id, $status, array $checklist, $registry_result, $validity_from, $validity_until, $review_note, $manage_transaction = true ) {
        global $wpdb;
        if ( $manage_transaction && false === $wpdb->query( 'START TRANSACTION' ) ) {
            return new WP_Error( 'gdo_evidence_review_transaction', __( 'Credential review could not start a safe database transaction.', 'global-doctor-onboarding' ) );
        }
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . \" WHERE id=%d AND retention_state='active' AND deleted_at IS NULL FOR UPDATE\", absint( $evidence_id ) ) );
        $app = $record ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', absint( $record->application_id ) ) ) : null;
        $status = sanitize_key( $status );
""", """    public static function review( $evidence_id, $reviewer_id, $status, array $checklist, $registry_result, $validity_from, $validity_until, $review_note, $manage_transaction = true ) {
        global $wpdb;
        $reviewer_id = absint( $reviewer_id );
        if ( ! $reviewer_id || $reviewer_id !== get_current_user_id() || ! GDO_Membership_Adapter::can( 'sabri_verify_doctors', $reviewer_id ) || ! GDO_Membership_Adapter::recent_step_up( $reviewer_id ) ) {
            return new WP_Error( 'gdo_evidence_review_forbidden', __( 'Credential review requires current reviewer authorization and recent step-up.', 'global-doctor-onboarding' ) );
        }
        if ( $manage_transaction && false === $wpdb->query( 'START TRANSACTION' ) ) {
            return new WP_Error( 'gdo_evidence_review_transaction', __( 'Credential review could not start a safe database transaction.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . \" WHERE id=%d AND retention_state='active' AND deleted_at IS NULL FOR UPDATE\", absint( $evidence_id ) ) );
        if ( null === $record && ! empty( $wpdb->last_error ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_review_query', __( 'Credential evidence could not be read safely for review.', 'global-doctor-onboarding' ) );
        }
        $app = null;
        if ( $record ) {
            $wpdb->last_error = '';
            $app = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', absint( $record->application_id ) ) );
            if ( null === $app && ! empty( $wpdb->last_error ) ) {
                if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                return new WP_Error( 'gdo_evidence_review_application_query', __( 'The credential application could not be read safely for review.', 'global-doctor-onboarding' ) );
            }
        }
        $status = sanitize_key( $status );
""")

# R06 — a download grant must recheck the current download capability at use time,
# and grant/evidence/application read failures must not masquerade as ordinary denial.
replace(rel, """        $grant = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE grant_hash=%s FOR UPDATE\", $hash ) );
        $session = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
""", """        $wpdb->last_error = '';
        $grant = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE grant_hash=%s FOR UPDATE\", $hash ) );
        if ( null === $grant && ! empty( $wpdb->last_error ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_grant_query', __( 'Credential access grant state could not be read safely.', 'global-doctor-onboarding' ) );
        }
        $session = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
""")
replace(rel, """        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . \" WHERE id=%d AND retention_state='active' AND deleted_at IS NULL\", absint( $grant->evidence_id ) ) );
        $app = $record ? GDO_Application::get( $record->application_id ) : null;
        if ( ! $record || ! $app || ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer_id, $app->user_id, $app->id ) || ! GDO_Membership_Adapter::reviewer_case_allows( $reviewer_id, $app->user_id, $app->id ) || ! GDO_Membership_Adapter::recent_step_up( $reviewer_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_missing', __( 'The credential evidence is unavailable or no longer within reviewer scope.', 'global-doctor-onboarding' ) );
        }
""", """        $wpdb->last_error = '';
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . \" WHERE id=%d AND retention_state='active' AND deleted_at IS NULL\", absint( $grant->evidence_id ) ) );
        if ( null === $record && ! empty( $wpdb->last_error ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_grant_evidence_query', __( 'Credential evidence could not be read safely for access.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $app = $record ? GDO_Application::get( $record->application_id ) : null;
        if ( $record && ! $app && ! empty( $wpdb->last_error ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_grant_application_query', __( 'The credential application could not be read safely for access.', 'global-doctor-onboarding' ) );
        }
        $download_authorized = 'download' !== sanitize_key( $expected_mode ) || GDO_Membership_Adapter::can( 'sabri_access_doctor_credentials', $reviewer_id );
        if ( ! $record || ! $app || ! $download_authorized || ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer_id, $app->user_id, $app->id ) || ! GDO_Membership_Adapter::reviewer_case_allows( $reviewer_id, $app->user_id, $app->id ) || ! GDO_Membership_Adapter::recent_step_up( $reviewer_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_missing', __( 'The credential evidence is unavailable or no longer within reviewer scope.', 'global-doctor-onboarding' ) );
        }
""")

# R07 — idempotent submit must still prove COMMIT success.
rel = 'includes/class-gdo-application.php'
replace(rel, """\t\tif ( in_array( $app->state, array( 'submitted','resubmitted' ), true )
\t\t\t&& ! empty( $app->submission_hash )
\t\t\t&& hash_equals( (string) $app->submission_hash, $submission_hash ) ) {
\t\t\t$wpdb->query( 'COMMIT' );
\t\t\treturn true;
\t\t}
""", """\t\tif ( in_array( $app->state, array( 'submitted','resubmitted' ), true )
\t\t\t&& ! empty( $app->submission_hash )
\t\t\t&& hash_equals( (string) $app->submission_hash, $submission_hash ) ) {
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) {
\t\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t\treturn new WP_Error( 'gdo_submit_idempotent_commit', __( 'The existing application submission could not be confirmed safely.', 'global-doctor-onboarding' ) );
\t\t\t}
\t\t\treturn true;
\t\t}
""")

# R08/R09 — appeal rendering and bounded outbox processing must surface failures.
rel = 'includes/class-gdo-admin.php'
replace(rel, """\t\tif ( $manager && 'appeal_pending' === $app->state ) {
\t\t\tglobal $wpdb;
\t\t\t$appeal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'appeals' ) . \" WHERE application_id=%d AND status='open' ORDER BY id DESC LIMIT 1\", $app->id ) );
\t\t\tif ( $appeal && empty( $appeal->assigned_reviewer_id ) ) {
""", """\t\tif ( $manager && 'appeal_pending' === $app->state ) {
\t\t\tglobal $wpdb;
\t\t\t$wpdb->last_error = '';
\t\t\t$appeal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'appeals' ) . \" WHERE application_id=%d AND status='open' ORDER BY id DESC LIMIT 1\", $app->id ) );
\t\t\tif ( null === $appeal && ! empty( $wpdb->last_error ) ) {
\t\t\t\techo '<div class=\"notice notice-error\"><p>' . esc_html__( 'Appeal workflow state is temporarily unavailable because the database read failed. No appeal action has been assumed.', 'global-doctor-onboarding' ) . '</p></div>';
\t\t\t\treturn;
\t\t\t}
\t\t\tif ( $appeal && empty( $appeal->assigned_reviewer_id ) ) {
""")
replace(rel, """\tpublic function replay_outbox() {
\t\t$this->guard( 'sabri_manage_doctor_verification', true );
\t\tcheck_admin_referer( 'gdo_replay_outbox' );
\t\tGDO_Notifications::process( 100 );
\t\t$this->redirect();
\t}
""", """\tpublic function replay_outbox() {
\t\t$this->guard( 'sabri_manage_doctor_verification', true );
\t\tcheck_admin_referer( 'gdo_replay_outbox' );
\t\t$result = GDO_Notifications::process( 100 );
\t\tif ( is_wp_error( $result ) ) {
\t\t\twp_die( esc_html( $result->get_error_message() ), '', array( 'response'=>503, 'back_link'=>true ) );
\t\t}
\t\t$this->redirect();
\t}
""")

# R10 — dead-letter replay is an owner mutation and cannot trust an actor-id argument.
rel = 'includes/class-gdo-notifications.php'
replace(rel, """\tpublic static function replay( $event_id, $actor_id, $reason ) {
\t\tglobal $wpdb;
\t\t$reason = sanitize_textarea_field( $reason );
""", """\tpublic static function replay( $event_id, $actor_id, $reason ) {
\t\tglobal $wpdb;
\t\t$actor_id = absint( $actor_id );
\t\tif ( ! $actor_id || $actor_id !== get_current_user_id() || ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $actor_id ) || ! GDO_Membership_Adapter::recent_step_up( $actor_id ) ) {
\t\t\treturn new WP_Error( 'gdo_outbox_replay_forbidden', __( 'Dead-letter replay requires current verification-management authorization and recent step-up.', 'global-doctor-onboarding' ) );
\t\t}
\t\t$reason = sanitize_textarea_field( $reason );
""")

# R11 — continuous monitoring gets an atomic processing lease and stale-lease recovery.
rel = 'includes/class-gdo-advanced-trust-hardening.php'
replace(rel, """    public static function continuous_monitor() {
        global $wpdb;
""", """    private static function release_monitor_claim( $application_id, $last_result, $delay = HOUR_IN_SECONDS ) {
        global $wpdb;
        $application_id = absint( $application_id );
        if ( ! $application_id ) { return false; }
        $next = gmdate( 'Y-m-d H:i:s', time() + max( MINUTE_IN_SECONDS, absint( $delay ) ) );
        $updated = $wpdb->query( $wpdb->prepare(
            \"UPDATE \" . GDO_Advanced_Trust::table( 'monitor_state' ) . \" SET monitor_status='degraded',last_result=%s,failure_count=LEAST(20,failure_count+1),next_check_at=%s,updated_at=%s WHERE application_id=%d AND monitor_status='processing'\",
            substr( sanitize_key( $last_result ), 0, 30 ), $next, current_time( 'mysql', true ), $application_id
        ) );
        return false !== $updated;
    }

    public static function continuous_monitor() {
        global $wpdb;
""")
replace(rel, """        $table = GDO_Advanced_Trust::table( 'monitor_state' );
        $wpdb->last_error = '';
        $rows = $wpdb->get_results( $wpdb->prepare(
            \"SELECT * FROM {$table} WHERE monitor_status IN ('scheduled','degraded') AND next_check_at<=%s ORDER BY next_check_at ASC LIMIT 50\",
            current_time( 'mysql', true )
        ) );
""", """        $table = GDO_Advanced_Trust::table( 'monitor_state' );
        $now = current_time( 'mysql', true );
        $wpdb->last_error = '';
        $recovered = $wpdb->query( $wpdb->prepare(
            \"UPDATE {$table} SET monitor_status='degraded',last_result='processing_lease_expired',failure_count=LEAST(20,failure_count+1),updated_at=%s WHERE monitor_status='processing' AND next_check_at<=%s\",
            $now, $now
        ) );
        if ( false === $recovered || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'gdo_trust_monitor_lease_recovery', __( 'Expired continuous-verification processing leases could not be recovered safely.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $rows = $wpdb->get_results( $wpdb->prepare(
            \"SELECT * FROM {$table} WHERE monitor_status IN ('scheduled','degraded') AND next_check_at<=%s ORDER BY next_check_at ASC LIMIT 50\",
            $now
        ) );
""")
replace(rel, """        foreach ( $rows as $row ) {
            $wpdb->last_error = '';
            $app = GDO_Application::get( $row->application_id );
            if ( ! empty( $wpdb->last_error ) ) {
                return new WP_Error( 'gdo_trust_monitor_application_query', __( 'A monitored application could not be read safely.', 'global-doctor-onboarding' ) );
            }
            if ( ! $app ) {
                if ( false === $wpdb->delete( $table, array( 'application_id'=>$row->application_id ) ) ) {
                    return new WP_Error( 'gdo_trust_monitor_orphan_delete', __( 'An orphaned continuous-verification record could not be removed safely.', 'global-doctor-onboarding' ) );
                }
                continue;
            }
""", """        foreach ( $rows as $row ) {
            $claim_now = current_time( 'mysql', true );
            $lease_until = gmdate( 'Y-m-d H:i:s', time() + 15 * MINUTE_IN_SECONDS );
            $wpdb->last_error = '';
            $claimed = $wpdb->query( $wpdb->prepare(
                \"UPDATE {$table} SET monitor_status='processing',next_check_at=%s,updated_at=%s WHERE application_id=%d AND monitor_status IN ('scheduled','degraded') AND next_check_at<=%s\",
                $lease_until, $claim_now, absint( $row->application_id ), $claim_now
            ) );
            if ( false === $claimed || ! empty( $wpdb->last_error ) ) {
                return new WP_Error( 'gdo_trust_monitor_claim_failed', __( 'Continuous-verification work could not claim an exclusive processing lease.', 'global-doctor-onboarding' ) );
            }
            if ( 1 !== $claimed ) { continue; }

            $wpdb->last_error = '';
            $app = GDO_Application::get( $row->application_id );
            if ( ! empty( $wpdb->last_error ) ) {
                self::release_monitor_claim( $row->application_id, 'application_query_failed' );
                return new WP_Error( 'gdo_trust_monitor_application_query', __( 'A monitored application could not be read safely.', 'global-doctor-onboarding' ) );
            }
            if ( ! $app ) {
                $deleted = $wpdb->delete( $table, array( 'application_id'=>$row->application_id, 'monitor_status'=>'processing' ) );
                if ( false === $deleted ) {
                    self::release_monitor_claim( $row->application_id, 'orphan_delete_failed' );
                    return new WP_Error( 'gdo_trust_monitor_orphan_delete', __( 'An orphaned continuous-verification record could not be removed safely.', 'global-doctor-onboarding' ) );
                }
                continue;
            }
""")
replace(rel, """            if ( null === $evidence_rows || ! empty( $wpdb->last_error ) ) {
                return new WP_Error( 'gdo_trust_monitor_evidence_query', __( 'Credential evidence could not be read safely for continuous verification.', 'global-doctor-onboarding' ) );
            }
""", """            if ( null === $evidence_rows || ! empty( $wpdb->last_error ) ) {
                self::release_monitor_claim( $app->id, 'evidence_query_failed' );
                return new WP_Error( 'gdo_trust_monitor_evidence_query', __( 'Credential evidence could not be read safely for continuous verification.', 'global-doctor-onboarding' ) );
            }
""")
replace(rel, """            $updated = $wpdb->update( $table, array(
                'monitor_status'=>$status, 'last_checked_at'=>current_time( 'mysql', true ),
                'last_result'=>substr( sanitize_key( $result ), 0, 30 ), 'failure_count'=>$failures,
                'next_check_at'=>gmdate( 'Y-m-d H:i:s', $next_check ), 'updated_at'=>current_time( 'mysql', true ),
            ), array( 'application_id'=>$app->id ) );
            if ( false === $updated ) {
                GDO_Membership_Adapter::audit( 'doctor_continuous_verification_monitor_store_failed', array( 'application_id'=>$app->id ) );
                return new WP_Error( 'gdo_trust_monitor_store_failed', __( 'Continuous verification state could not be persisted safely.', 'global-doctor-onboarding' ) );
            }
""", """            $updated = $wpdb->update( $table, array(
                'monitor_status'=>$status, 'last_checked_at'=>current_time( 'mysql', true ),
                'last_result'=>substr( sanitize_key( $result ), 0, 30 ), 'failure_count'=>$failures,
                'next_check_at'=>gmdate( 'Y-m-d H:i:s', $next_check ), 'updated_at'=>current_time( 'mysql', true ),
            ), array( 'application_id'=>$app->id, 'monitor_status'=>'processing' ) );
            if ( false === $updated ) {
                self::release_monitor_claim( $app->id, 'monitor_store_failed' );
                GDO_Membership_Adapter::audit( 'doctor_continuous_verification_monitor_store_failed', array( 'application_id'=>$app->id ) );
                return new WP_Error( 'gdo_trust_monitor_store_failed', __( 'Continuous verification state could not be persisted safely.', 'global-doctor-onboarding' ) );
            }
            if ( 0 === $updated ) {
                GDO_Membership_Adapter::audit( 'doctor_continuous_verification_monitor_superseded', array( 'application_id'=>$app->id ) );
                continue;
            }
""")

# R12 — Advanced Trust DB-side privacy erasure must be atomic.
replace(rel, """        foreach((array)$uploads as $row){$path=trailingslashit($dir).'.chunk-'.basename(sanitize_file_name($row->temp_name));if(self::unsafe_chunk_path($path)){return new WP_Error('gdo_privacy_upload_unsafe_path',__('A private resumable upload has an unsafe path; erasure is paused for operator review.','global-doctor-onboarding'));}if(is_file($path)&&(!@unlink($path)||file_exists($path))){return new WP_Error('gdo_privacy_upload_cleanup',__('A private resumable upload could not be deleted.','global-doctor-onboarding'));}}
        if(false===$wpdb->delete(GDO_Advanced_Trust::table('upload_sessions'),array('application_id'=>$application_id))||false===$wpdb->delete(GDO_Advanced_Trust::table('monitor_state'),array('application_id'=>$application_id))||false===$wpdb->delete(GDO_Advanced_Trust::table('verification_passports'),array('application_id'=>$application_id))){return new WP_Error('gdo_privacy_operational_cleanup',__('Advanced Trust operational records could not be removed.','global-doctor-onboarding'));}
        if(false===$wpdb->update(GDO_Advanced_Trust::table('credential_checks'),array('facts_json'=>'{\"redacted\":\"privacy_erasure\"}','explanation_json'=>'{\"redacted\":\"privacy_erasure\"}','external_reference'=>'','updated_at'=>$now),array('application_id'=>$application_id))||false===$wpdb->update(GDO_Advanced_Trust::table('professional_history'),array('user_id'=>0,'public_safe'=>0,'event_json'=>'{\"redacted\":\"privacy_erasure\"}','source_hash'=>hash('sha256','{\"redacted\":\"privacy_erasure\"}')),array('application_id'=>$application_id))||false===$wpdb->update(GDO_Advanced_Trust::table('reviewer_conflicts'),array('applicant_id'=>0),array('application_id'=>$application_id,'applicant_id'=>$user_id))){return new WP_Error('gdo_privacy_anonymize',__('Advanced Trust accountability records could not be anonymized.','global-doctor-onboarding'));}
        $queries=array('reviewer_id'=>\"UPDATE \".GDO_Advanced_Trust::table('reviewer_conflicts').' SET reviewer_id=0 WHERE reviewer_id=%d','declared_by'=>\"UPDATE \".GDO_Advanced_Trust::table('reviewer_conflicts').' SET declared_by=0 WHERE declared_by=%d','resolved_by'=>\"UPDATE \".GDO_Advanced_Trust::table('reviewer_conflicts').' SET resolved_by=0 WHERE resolved_by=%d');foreach($queries as $sql){if(false===$wpdb->query($wpdb->prepare($sql,$user_id))){return new WP_Error('gdo_privacy_reviewer_anonymize',__('Reviewer conflict identifiers could not be anonymized.','global-doctor-onboarding'));}}
        return true;
""", """        foreach((array)$uploads as $row){$path=trailingslashit($dir).'.chunk-'.basename(sanitize_file_name($row->temp_name));if(self::unsafe_chunk_path($path)){return new WP_Error('gdo_privacy_upload_unsafe_path',__('A private resumable upload has an unsafe path; erasure is paused for operator review.','global-doctor-onboarding'));}if(is_file($path)&&(!@unlink($path)||file_exists($path))){return new WP_Error('gdo_privacy_upload_cleanup',__('A private resumable upload could not be deleted.','global-doctor-onboarding'));}}
        if(false===$wpdb->query('START TRANSACTION')){return new WP_Error('gdo_privacy_advanced_transaction',__('Advanced Trust privacy erasure could not start a safe database transaction.','global-doctor-onboarding'));}
        if(false===$wpdb->delete(GDO_Advanced_Trust::table('upload_sessions'),array('application_id'=>$application_id))||false===$wpdb->delete(GDO_Advanced_Trust::table('monitor_state'),array('application_id'=>$application_id))||false===$wpdb->delete(GDO_Advanced_Trust::table('verification_passports'),array('application_id'=>$application_id))){$wpdb->query('ROLLBACK');return new WP_Error('gdo_privacy_operational_cleanup',__('Advanced Trust operational records could not be removed.','global-doctor-onboarding'));}
        if(false===$wpdb->update(GDO_Advanced_Trust::table('credential_checks'),array('facts_json'=>'{\"redacted\":\"privacy_erasure\"}','explanation_json'=>'{\"redacted\":\"privacy_erasure\"}','external_reference'=>'','updated_at'=>$now),array('application_id'=>$application_id))||false===$wpdb->update(GDO_Advanced_Trust::table('professional_history'),array('user_id'=>0,'public_safe'=>0,'event_json'=>'{\"redacted\":\"privacy_erasure\"}','source_hash'=>hash('sha256','{\"redacted\":\"privacy_erasure\"}')),array('application_id'=>$application_id))||false===$wpdb->update(GDO_Advanced_Trust::table('reviewer_conflicts'),array('applicant_id'=>0),array('application_id'=>$application_id,'applicant_id'=>$user_id))){$wpdb->query('ROLLBACK');return new WP_Error('gdo_privacy_anonymize',__('Advanced Trust accountability records could not be anonymized.','global-doctor-onboarding'));}
        $queries=array('reviewer_id'=>\"UPDATE \".GDO_Advanced_Trust::table('reviewer_conflicts').' SET reviewer_id=0 WHERE reviewer_id=%d','declared_by'=>\"UPDATE \".GDO_Advanced_Trust::table('reviewer_conflicts').' SET declared_by=0 WHERE declared_by=%d','resolved_by'=>\"UPDATE \".GDO_Advanced_Trust::table('reviewer_conflicts').' SET resolved_by=0 WHERE resolved_by=%d');foreach($queries as $sql){if(false===$wpdb->query($wpdb->prepare($sql,$user_id))){$wpdb->query('ROLLBACK');return new WP_Error('gdo_privacy_reviewer_anonymize',__('Reviewer conflict identifiers could not be anonymized.','global-doctor-onboarding'));}}
        if(false===$wpdb->query('COMMIT')){$wpdb->query('ROLLBACK');return new WP_Error('gdo_privacy_advanced_commit',__('Advanced Trust privacy erasure could not be committed safely.','global-doctor-onboarding'));}
        return true;
""")

print('R9 source corrections applied: rounds 04-12')
