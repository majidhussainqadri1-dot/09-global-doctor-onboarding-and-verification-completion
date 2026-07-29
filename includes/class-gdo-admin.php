<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Admin {
    public function hooks() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_post_gdo_assign_application', array( $this, 'assign' ) );
        add_action( 'admin_post_gdo_review_evidence', array( $this, 'review_evidence' ) );
        add_action( 'admin_post_gdo_recommend_decision', array( $this, 'recommend' ) );
        add_action( 'admin_post_gdo_finalize_decision', array( $this, 'finalize' ) );
        add_action( 'admin_post_gdo_download_evidence', array( $this, 'download' ) );
        add_action( 'admin_post_gdo_resolve_appeal', array( $this, 'resolve_appeal' ) );
        add_action( 'admin_post_gdo_change_verification_state', array( $this, 'change_state' ) );
    }

    public function menu() {
        if ( GDO_Membership_Adapter::can( 'sabri_verify_doctors' ) || GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification' ) ) {
            add_submenu_page( 'sabri-platform', __( 'Doctor Verification', 'global-doctor-onboarding' ), __( 'Doctor Verification', 'global-doctor-onboarding' ), 'read', 'global-doctor-verification', array( $this, 'page' ) );
        }
    }

    private function guard( $capability ) {
        if ( ! GDO_Membership_Adapter::can( $capability ) || ! GDO_Membership_Adapter::recent_step_up( get_current_user_id() ) ) {
            wp_die( esc_html__( 'Access requires an authorized File 00 reviewer and recent step-up authentication.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) );
        }
    }

    public function page() {
        $this->guard( 'sabri_verify_doctors' );
        global $wpdb;
        $paged = max( 1, absint( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) );
        $per_page = 50;
        $offset = ( $paged - 1 ) * $per_page;
        $reviewer = get_current_user_id();
        $manager = GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $reviewer );
        $where = $manager ? '1=1' : $wpdb->prepare( '(assigned_reviewer_id=%d OR assigned_reviewer_id IS NULL)', $reviewer );
        $table = GDO_Schema::table( 'applications' );
        $apps = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
        $total = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ) );
        ?>
        <div class="wrap gdo-admin">
            <h1><?php esc_html_e( 'Doctor Verification', 'global-doctor-onboarding' ); ?></h1>
            <p><?php esc_html_e( 'Reviewers must be assigned, independent from the applicant, recently re-authenticated, and within File 00 scope.', 'global-doctor-onboarding' ); ?></p>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Application','global-doctor-onboarding');?></th><th><?php esc_html_e('Evidence','global-doctor-onboarding');?></th><th><?php esc_html_e('Workflow','global-doctor-onboarding');?></th></tr></thead><tbody>
            <?php foreach ( $apps as $app ) : $evidence = GDO_Evidence::records( $app->id, true ); ?>
                <tr>
                    <td><strong>#<?php echo absint($app->id); ?></strong><br><?php echo esc_html($app->application_uuid); ?><br><?php echo esc_html($app->state); ?><br><?php printf( esc_html__('Applicant ID: %d','global-doctor-onboarding'), absint($app->user_id) ); ?></td>
                    <td>
                        <?php foreach ( $evidence as $record ) : ?>
                            <div class="gdo-evidence-row">
                                <strong><?php echo esc_html( GDO_Evidence::types()[ $record->document_type ] ); ?></strong> — <?php echo esc_html($record->status); ?>
                                <?php if ( absint($app->assigned_reviewer_id) === $reviewer || $manager ) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="gdo_download_evidence"><input type="hidden" name="evidence_id" value="<?php echo absint($record->id); ?>">
                                        <?php wp_nonce_field( 'gdo_download_evidence_' . $record->id ); ?>
                                        <label><?php esc_html_e('Access purpose','global-doctor-onboarding'); ?><input name="purpose" required minlength="10"></label>
                                        <button class="button"><?php esc_html_e('Download securely','global-doctor-onboarding'); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="gdo_review_evidence"><input type="hidden" name="evidence_id" value="<?php echo absint($record->id); ?>">
                                        <?php wp_nonce_field( 'gdo_review_evidence_' . $record->id ); ?>
                                        <select name="status"><option value="accepted">Accepted</option><option value="more_information">More information</option><option value="rejected">Rejected</option></select>
                                        <?php foreach(array('name_match','document_legible','authenticity_method','scope_match') as $item): ?><label><input type="checkbox" name="checklist[<?php echo esc_attr($item); ?>]" value="1" required> <?php echo esc_html(str_replace('_',' ',$item)); ?></label><?php endforeach; ?>
                                        <label><?php esc_html_e('Registry result','global-doctor-onboarding'); ?><input name="registry_result"></label>
                                        <label><?php esc_html_e('Valid from','global-doctor-onboarding'); ?><input type="date" name="validity_from"></label>
                                        <label><?php esc_html_e('Valid until','global-doctor-onboarding'); ?><input type="date" name="validity_until"></label>
                                        <button class="button button-primary"><?php esc_html_e('Record evidence review','global-doctor-onboarding'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php if ( $manager && ! $app->assigned_reviewer_id ) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="gdo_assign_application"><input type="hidden" name="application_id" value="<?php echo absint($app->id); ?>"><?php wp_nonce_field('gdo_assign_application_'.$app->id); ?><label><?php esc_html_e('Reviewer user ID','global-doctor-onboarding'); ?><input type="number" name="reviewer_id" required min="1"></label><button class="button"><?php esc_html_e('Assign','global-doctor-onboarding'); ?></button></form>
                        <?php endif; ?>
                        <?php if ( absint($app->assigned_reviewer_id) === $reviewer && 'under_review' === $app->state ) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="gdo_recommend_decision"><input type="hidden" name="application_id" value="<?php echo absint($app->id); ?>"><input type="hidden" name="row_version" value="<?php echo absint($app->row_version); ?>"><?php wp_nonce_field('gdo_recommend_decision_'.$app->id); ?><label><?php esc_html_e('Conflict declaration','global-doctor-onboarding'); ?><select name="conflict"><option value="none">No conflict</option><option value="conflict">Conflict exists</option></select></label><label><?php esc_html_e('Reason','global-doctor-onboarding'); ?><textarea name="reason" required minlength="20"></textarea></label><button class="button button-primary"><?php esc_html_e('Recommend verification','global-doctor-onboarding'); ?></button></form>
                        <?php endif; ?>
                        <?php if ( in_array($app->state,array('verified','reinstated','suspended','renewal_due'),true) && GDO_Membership_Adapter::can('sabri_finalize_doctor_verification') ) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="gdo_change_verification_state"><input type="hidden" name="application_id" value="<?php echo absint($app->id); ?>"><input type="hidden" name="row_version" value="<?php echo absint($app->row_version); ?>"><?php wp_nonce_field('gdo_change_verification_state_'.$app->id); ?><select name="state"><?php foreach(array('suspended','revoked','renewal_due','reinstated') as $state):if(GDO_State::can_transition($app->state,$state)):?><option value="<?php echo esc_attr($state);?>"><?php echo esc_html($state);?></option><?php endif;endforeach;?></select><textarea name="reason" required minlength="20"></textarea><button class="button"><?php esc_html_e('Record lifecycle decision','global-doctor-onboarding');?></button></form><?php endif; ?>
                        <?php if ( 'appeal_pending' === $app->state && GDO_Membership_Adapter::can('sabri_finalize_doctor_verification') ) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="gdo_resolve_appeal"><input type="hidden" name="application_id" value="<?php echo absint($app->id); ?>"><input type="hidden" name="row_version" value="<?php echo absint($app->row_version); ?>"><?php wp_nonce_field('gdo_resolve_appeal_'.$app->id); ?><select name="decision"><option value="under_review">Reopen independent review</option><option value="reinstated">Reinstate</option><option value="suspended">Keep suspended</option><option value="revoked">Revoke</option></select><textarea name="reason" required minlength="20"></textarea><button class="button"><?php esc_html_e('Resolve appeal','global-doctor-onboarding');?></button></form><?php endif; ?>
                        <?php if ( 'recommended' === $app->state && GDO_Membership_Adapter::can('sabri_finalize_doctor_verification') ) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="gdo_finalize_decision"><input type="hidden" name="application_id" value="<?php echo absint($app->id); ?>"><input type="hidden" name="row_version" value="<?php echo absint($app->row_version); ?>"><?php wp_nonce_field('gdo_finalize_decision_'.$app->id); ?><select name="decision"><option value="verified">Verify</option><option value="rejected">Reject</option></select><label><?php esc_html_e('Reason','global-doctor-onboarding'); ?><textarea name="reason" required minlength="20"></textarea></label><label><?php esc_html_e('Verification valid until','global-doctor-onboarding'); ?><input type="date" name="verified_until" required></label><button class="button button-primary"><?php esc_html_e('Finalize independent decision','global-doctor-onboarding'); ?></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ( ! $apps ) : ?><tr><td colspan="3"><?php esc_html_e('No applications found.','global-doctor-onboarding'); ?></td></tr><?php endif; ?>
            </tbody></table>
            <?php echo wp_kses_post( paginate_links( array( 'total'=>max(1,(int)ceil($total/$per_page)),'current'=>$paged ) ) ); ?>
        </div>
        <?php
    }

    public function assign() {
        $this->guard( 'sabri_manage_doctor_verification' );
        global $wpdb;
        $id = absint( $_POST['application_id'] ?? 0 );
        $reviewer = absint( $_POST['reviewer_id'] ?? 0 );
        check_admin_referer( 'gdo_assign_application_' . $id );
        $app = GDO_Application::get( $id );
        if ( ! $app || $reviewer === absint($app->user_id) || ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer, $app->user_id, $id ) ) {
            wp_die( esc_html__('Invalid reviewer assignment.','global-doctor-onboarding'),'',array('response'=>400) );
        }
        $wpdb->update( GDO_Schema::table('applications'), array('assigned_reviewer_id'=>$reviewer,'updated_at'=>current_time('mysql',true)), array('id'=>$id), array('%d','%s'), array('%d') );
        GDO_Membership_Adapter::audit( 'doctor_application_assigned', array('application_id'=>$id,'reviewer_id'=>$reviewer,'actor_id'=>get_current_user_id()) );
        $this->redirect();
    }

    public function review_evidence() {
        $this->guard( 'sabri_verify_doctors' );
        global $wpdb;
        $evidence_id = absint( $_POST['evidence_id'] ?? 0 );
        check_admin_referer( 'gdo_review_evidence_' . $evidence_id );
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table('evidence') . ' WHERE id=%d', $evidence_id ) );
        $app = $record ? GDO_Application::get( $record->application_id ) : null;
        $reviewer = get_current_user_id();
        if ( ! $app || absint($app->assigned_reviewer_id)!==$reviewer || absint($app->user_id)===$reviewer || ! GDO_Membership_Adapter::reviewer_scope_allows($reviewer,$app->user_id,$app->id) ) {
            wp_die( esc_html__('This credential is not assigned to you.','global-doctor-onboarding'),'',array('response'=>403) );
        }
        $result = GDO_Evidence::review( $evidence_id, $reviewer, sanitize_key($_POST['status']??''), (array)($_POST['checklist']??array()), sanitize_key($_POST['registry_result']??''), sanitize_text_field($_POST['validity_from']??''), sanitize_text_field($_POST['validity_until']??'') );
        if ( is_wp_error($result) ) wp_die( esc_html($result->get_error_message()),'',array('response'=>400) );
        $this->redirect();
    }

    public function recommend() {
        $this->guard( 'sabri_verify_doctors' );
        global $wpdb;
        $id=absint($_POST['application_id']??0); check_admin_referer('gdo_recommend_decision_'.$id);
        $app=GDO_Application::get($id); $reviewer=get_current_user_id(); $reason=sanitize_textarea_field($_POST['reason']??'');
        if(!$app||absint($app->assigned_reviewer_id)!==$reviewer||absint($app->user_id)===$reviewer||'none'!==sanitize_key($_POST['conflict']??'')||strlen($reason)<20||!GDO_Evidence::all_accepted($id)) wp_die(esc_html__('Independent review requirements are incomplete.','global-doctor-onboarding'),'',array('response'=>400));
        $result=GDO_State::transition($id,'recommended',$reviewer,'verification_recommended',$reason,absint($_POST['row_version']??0));
        if(is_wp_error($result)) wp_die(esc_html($result->get_error_message()),'',array('response'=>409));
        $wpdb->update(GDO_Schema::table('applications'),array('recommender_id'=>$reviewer,'reviewed_at'=>current_time('mysql',true)),array('id'=>$id),array('%d','%s'),array('%d'));
        $this->redirect();
    }

    public function finalize() {
        $this->guard( 'sabri_finalize_doctor_verification' );
        global $wpdb;
        $id=absint($_POST['application_id']??0); check_admin_referer('gdo_finalize_decision_'.$id);
        $app=GDO_Application::get($id); $finalizer=get_current_user_id(); $decision=sanitize_key($_POST['decision']??''); $reason=sanitize_textarea_field($_POST['reason']??''); $until=sanitize_text_field($_POST['verified_until']??'');
        if(!$app||'recommended'!==$app->state||absint($app->user_id)===$finalizer||absint($app->recommender_id)===$finalizer||!in_array($decision,array('verified','rejected'),true)||strlen($reason)<20) wp_die(esc_html__('Independent finalization requirements are incomplete.','global-doctor-onboarding'),'',array('response'=>400));
        if('verified'===$decision&&(!GDO_Evidence::all_accepted($id)||!$until||strtotime($until.' 23:59:59 UTC')<=time())) wp_die(esc_html__('Accepted evidence and a future verification expiry date are required.','global-doctor-onboarding'),'',array('response'=>400));
        $profile=json_decode($app->profile_json,true); $evidence=array(); foreach(GDO_Evidence::records($id,true) as $record){$evidence[$record->document_type]=array('version'=>absint($record->version),'content_hmac'=>$record->content_hmac,'status'=>$record->status,'validity_until'=>$record->validity_until);}
        $snapshot=array('schema'=>2,'application_uuid'=>$app->application_uuid,'application_version'=>absint($app->version),'profile'=>$profile,'evidence'=>$evidence,'verified_until'=>$until,'recommender_id'=>absint($app->recommender_id),'finalizer_id'=>$finalizer,'captured_at'=>current_time('mysql',true));
        $fingerprint=GDO_Application::fingerprint((array)$profile,$evidence);
        $result=GDO_State::transition($id,$decision,$finalizer,'verification_finalized',$reason,absint($_POST['row_version']??0));
        if(is_wp_error($result)) wp_die(esc_html($result->get_error_message()),'',array('response'=>409));
        $data=array('finalizer_id'=>$finalizer,'decision_at'=>current_time('mysql',true),'verified_until'=>'verified'===$decision?$until:null,'approved_snapshot_json'=>'verified'===$decision?wp_json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,'approved_fingerprint'=>'verified'===$decision?$fingerprint:null,'updated_at'=>current_time('mysql',true));
        $wpdb->update(GDO_Schema::table('applications'),$data,array('id'=>$id),array('%d','%s','%s','%s','%s','%s'),array('%d'));
        GDO_Notifications::queue('doctor_verification_'.$decision,$app->user_id,array('application_id'=>$id,'state'=>$decision,'verified_until'=>$until));
        do_action('gdo_verification_decision_changed',$app->user_id,$decision,$id,$snapshot);
        $this->redirect();
    }

    public function download() {
        $this->guard( 'sabri_access_doctor_credentials' );
        global $wpdb;
        $id=absint($_POST['evidence_id']??0); check_admin_referer('gdo_download_evidence_'.$id);
        $purpose=sanitize_textarea_field($_POST['purpose']??''); $reviewer=get_current_user_id();
        $record=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GDO_Schema::table('evidence').' WHERE id=%d',$id)); $app=$record?GDO_Application::get($record->application_id):null;
        $allowed=$app&&strlen($purpose)>=10&&absint($app->user_id)!==$reviewer&&GDO_Membership_Adapter::reviewer_scope_allows($reviewer,$app->user_id,$app->id)&&(absint($app->assigned_reviewer_id)===$reviewer||GDO_Membership_Adapter::can('sabri_manage_doctor_verification',$reviewer))&&GDO_Rate_Limiter::hit('credential-access:'.$reviewer,30,HOUR_IN_SECONDS);
        if(!$allowed){if($record)GDO_Audit::access($record->application_id,$id,$reviewer,$purpose,'denied');wp_die(esc_html__('Credential access denied.','global-doctor-onboarding'),'',array('response'=>403));}
        $bytes=GDO_Evidence::decrypt_record($record); if(is_wp_error($bytes)){GDO_Audit::access($app->id,$id,$reviewer,$purpose,'failed');wp_die(esc_html($bytes->get_error_message()),'',array('response'=>500));}
        GDO_Audit::access($app->id,$id,$reviewer,$purpose,'allowed'); GDO_Notifications::queue('doctor_credential_accessed',$app->user_id,array('application_id'=>$app->id,'evidence_type'=>$record->document_type));
        while(ob_get_level())ob_end_clean(); nocache_headers(); header('Cache-Control: private, no-store, max-age=0'); header('Pragma: no-cache'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer'); header('Content-Security-Policy: sandbox'); header('Content-Type: '.$record->mime_type); header('Content-Disposition: attachment; filename="'.sanitize_file_name($record->original_name).'"'); header('Content-Length: '.strlen($bytes)); echo $bytes; exit;
    }

    public function change_state(){
        $this->guard('sabri_finalize_doctor_verification');$id=absint($_POST['application_id']??0);check_admin_referer('gdo_change_verification_state_'.$id);$app=GDO_Application::get($id);$state=sanitize_key($_POST['state']??'');$reason=sanitize_textarea_field($_POST['reason']??'');if(!$app||strlen($reason)<20||!in_array($state,array('suspended','revoked','renewal_due','reinstated'),true)||!GDO_State::can_transition($app->state,$state))wp_die(esc_html__('Invalid verification lifecycle decision.','global-doctor-onboarding'),'',array('response'=>400));$result=GDO_State::transition($id,$state,get_current_user_id(),'verification_lifecycle',$reason,absint($_POST['row_version']??0));if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),'',array('response'=>409));GDO_Notifications::queue('doctor_verification_'.$state,$app->user_id,array('application_id'=>$id,'state'=>$state));do_action('gdo_verification_decision_changed',$app->user_id,$state,$id,array());$this->redirect();
    }
    public function resolve_appeal(){
        $this->guard('sabri_finalize_doctor_verification');global $wpdb;$id=absint($_POST['application_id']??0);check_admin_referer('gdo_resolve_appeal_'.$id);$app=GDO_Application::get($id);$decision=sanitize_key($_POST['decision']??'');$reason=sanitize_textarea_field($_POST['reason']??'');if(!$app||'appeal_pending'!==$app->state||absint($app->user_id)===get_current_user_id()||absint($app->recommender_id)===get_current_user_id()||strlen($reason)<20||!in_array($decision,array('under_review','reinstated','suspended','revoked'),true)||!GDO_State::can_transition($app->state,$decision))wp_die(esc_html__('Invalid appeal decision.','global-doctor-onboarding'),'',array('response'=>400));$result=GDO_State::transition($id,$decision,get_current_user_id(),'appeal_resolved',$reason,absint($_POST['row_version']??0));if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),'',array('response'=>409));$wpdb->update(GDO_Schema::table('appeals'),array('status'=>'resolved','resolution'=>$reason,'resolver_id'=>get_current_user_id(),'resolved_at'=>current_time('mysql',true)),array('application_id'=>$id,'status'=>'open'),array('%s','%s','%d','%s'),array('%d','%s'));GDO_Notifications::queue('doctor_verification_appeal_resolved',$app->user_id,array('application_id'=>$id,'state'=>$decision));do_action('gdo_verification_decision_changed',$app->user_id,$decision,$id,array());$this->redirect();
    }
    private function redirect(){wp_safe_redirect(add_query_arg(array('page'=>'global-doctor-verification','updated'=>'1'),admin_url('admin.php')));exit;}
}
