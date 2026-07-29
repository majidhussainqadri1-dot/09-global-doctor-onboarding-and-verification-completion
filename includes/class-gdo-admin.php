<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Admin {
    public function hooks() {
        add_action( 'admin_menu', array( $this, 'menu' ), 30 );
        add_action( 'admin_post_gdo_reviewer_step_up', array( $this, 'step_up' ) );
        add_action( 'admin_post_gdo_assign_application', array( $this, 'assign' ) );
        add_action( 'admin_post_gdo_review_evidence', array( $this, 'review_evidence' ) );
        add_action( 'admin_post_gdo_recommend_decision', array( $this, 'recommend' ) );
        add_action( 'admin_post_gdo_finalize_decision', array( $this, 'finalize' ) );
        add_action( 'admin_post_gdo_download_evidence', array( $this, 'download' ) );
        add_action( 'admin_post_gdo_resolve_appeal', array( $this, 'resolve_appeal' ) );
        add_action( 'admin_post_gdo_change_verification_state', array( $this, 'change_state' ) );
    }

    public function menu() {
        if ( ! GDO_Membership_Adapter::can( 'sabri_verify_doctors' ) && ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification' ) ) {
            return;
        }
        global $admin_page_hooks;
        if ( isset( $admin_page_hooks['smc-membership'] ) ) {
            add_submenu_page( 'smc-membership', __( 'Doctor Verification', 'global-doctor-onboarding' ), __( 'Doctor Verification', 'global-doctor-onboarding' ), 'smc_review_verification', 'global-doctor-verification', array( $this, 'page' ) );
        } else {
            add_menu_page( __( 'Doctor Verification', 'global-doctor-onboarding' ), __( 'Doctor Verification', 'global-doctor-onboarding' ), 'smc_review_verification', 'global-doctor-verification', array( $this, 'page' ), 'dashicons-id-alt', 4 );
        }
    }

    private function guard( $capability ) {
        if ( ! GDO_Membership_Adapter::can( $capability ) || ! GDO_Membership_Adapter::recent_step_up( get_current_user_id() ) ) {
            wp_die( esc_html__( 'Access requires an authorized File 00 reviewer and recent password plus Authenticator verification.', 'global-doctor-onboarding' ), '', array( 'response'=>403 ) );
        }
    }

    private function render_step_up() {
        ?>
        <div class="wrap gdo-admin">
            <h1><?php esc_html_e( 'Doctor Verification Security Check', 'global-doctor-onboarding' ); ?></h1>
            <p><?php esc_html_e( 'Private credentials require a session-bound step-up. Enter your current password and File 00 Authenticator code. Access expires after 15 minutes.', 'global-doctor-onboarding' ); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="gdo_reviewer_step_up">
                <?php wp_nonce_field('gdo_reviewer_step_up'); ?>
                <table class="form-table"><tbody>
                    <tr><th><label for="gdo-password"><?php esc_html_e('Current password','global-doctor-onboarding'); ?></label></th><td><input id="gdo-password" type="password" name="password" autocomplete="current-password" required></td></tr>
                    <tr><th><label for="gdo-otp"><?php esc_html_e('Authenticator code','global-doctor-onboarding'); ?></label></th><td><input id="gdo-otp" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></td></tr>
                </tbody></table>
                <button class="button button-primary"><?php esc_html_e('Verify and continue','global-doctor-onboarding'); ?></button>
            </form>
        </div>
        <?php
    }

    public function step_up() {
        if ( ! GDO_Membership_Adapter::can( 'sabri_verify_doctors' ) && ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification' ) ) {
            wp_die( esc_html__('Reviewer access denied.','global-doctor-onboarding'),'',array('response'=>403) );
        }
        check_admin_referer('gdo_reviewer_step_up');
        $result = GDO_Membership_Adapter::verify_step_up( get_current_user_id(), isset($_POST['password'])?wp_unslash($_POST['password']):'', isset($_POST['otp'])?wp_unslash($_POST['otp']):'' );
        if ( is_wp_error($result) ) {
            wp_die( esc_html($result->get_error_message()),'',array('response'=>403,'back_link'=>true) );
        }
        $this->redirect();
    }

    public function page() {
        if ( ! GDO_Membership_Adapter::can( 'sabri_verify_doctors' ) && ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification' ) ) {
            wp_die( esc_html__('Reviewer access denied.','global-doctor-onboarding'),'',array('response'=>403) );
        }
        if ( ! GDO_Membership_Adapter::recent_step_up( get_current_user_id() ) ) {
            $this->render_step_up();
            return;
        }
        global $wpdb;
        $paged=max(1,absint(isset($_GET['paged'])?$_GET['paged']:1));$per_page=25;$offset=($paged-1)*$per_page;
        $reviewer=get_current_user_id();$manager=GDO_Membership_Adapter::can('sabri_manage_doctor_verification',$reviewer);$table=GDO_Schema::table('applications');
        if($manager){$apps=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d OFFSET %d",$per_page,$offset));$total=absint($wpdb->get_var("SELECT COUNT(*) FROM {$table}"));}
        else{$apps=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE assigned_reviewer_id=%d ORDER BY updated_at DESC LIMIT %d OFFSET %d",$reviewer,$per_page,$offset));$total=absint($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE assigned_reviewer_id=%d",$reviewer)));}
        ?>
        <div class="wrap gdo-admin">
            <h1><?php esc_html_e('Doctor Verification','global-doctor-onboarding'); ?></h1>
            <p><?php esc_html_e('Reviewers must be assigned, independent from the applicant, recently re-authenticated, and within File 00 scope. A separate finalizer is required for the final decision.','global-doctor-onboarding'); ?></p>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Application','global-doctor-onboarding');?></th><th><?php esc_html_e('Evidence','global-doctor-onboarding');?></th><th><?php esc_html_e('Workflow','global-doctor-onboarding');?></th></tr></thead><tbody>
            <?php foreach($apps as $app):$evidence=GDO_Evidence::records($app->id,true);?>
                <tr><td><strong>#<?php echo absint($app->id);?></strong><br><?php echo esc_html($app->application_uuid);?><br><strong><?php echo esc_html($app->state);?></strong><br><?php printf(esc_html__('Applicant ID: %d','global-doctor-onboarding'),absint($app->user_id));?><br><?php printf(esc_html__('Version: %d / Row: %d','global-doctor-onboarding'),absint($app->version),absint($app->row_version));?></td>
                <td><?php foreach($evidence as $record):?><div class="gdo-evidence-row"><strong><?php echo esc_html(isset(GDO_Evidence::types()[$record->document_type])?GDO_Evidence::types()[$record->document_type]:$record->document_type);?></strong> — <?php echo esc_html($record->status);?>
                    <?php if(absint($app->assigned_reviewer_id)===$reviewer||$manager):?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="gdo_download_evidence"><input type="hidden" name="evidence_id" value="<?php echo absint($record->id);?>"><?php wp_nonce_field('gdo_download_evidence_'.$record->id);?><label><?php esc_html_e('Access purpose','global-doctor-onboarding');?><input name="purpose" required minlength="10" maxlength="300"></label><button class="button"><?php esc_html_e('Download securely','global-doctor-onboarding');?></button></form>
                    <?php if(absint($app->assigned_reviewer_id)===$reviewer&&'under_review'===$app->state):?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="gdo_review_evidence"><input type="hidden" name="evidence_id" value="<?php echo absint($record->id);?>"><?php wp_nonce_field('gdo_review_evidence_'.$record->id);?>
                        <label><?php esc_html_e('Decision','global-doctor-onboarding');?><select name="status"><option value="accepted">Accepted</option><option value="more_information">More information</option><option value="rejected">Reject and replace</option></select></label>
                        <?php foreach(array('name_match'=>'Name match','document_legible'=>'Document legible','scope_match'=>'Professional scope match') as $item=>$label):?><label><?php echo esc_html($label);?><select name="checklist[<?php echo esc_attr($item);?>]"><option value="yes">Yes</option><option value="no">No</option><option value="not_applicable">Not applicable</option></select></label><?php endforeach;?>
                        <label><?php esc_html_e('Authenticity method','global-doctor-onboarding');?><input name="checklist[authenticity_method]" required maxlength="300"></label>
                        <label><?php esc_html_e('Registry result','global-doctor-onboarding');?><select name="registry_result"><option value="">Not applicable</option><option value="verified">Verified</option><option value="active">Active</option><option value="matched">Matched</option><option value="not_found">Not found</option><option value="expired">Expired</option></select></label>
                        <label><?php esc_html_e('Valid from','global-doctor-onboarding');?><input type="date" name="validity_from"></label><label><?php esc_html_e('Valid until','global-doctor-onboarding');?><input type="date" name="validity_until"></label>
                        <label><?php esc_html_e('Review note','global-doctor-onboarding');?><textarea name="review_note" maxlength="2000"></textarea></label><button class="button button-primary"><?php esc_html_e('Record evidence review','global-doctor-onboarding');?></button>
                    </form><?php endif;?><?php endif;?></div><?php endforeach;?></td>
                <td>
                    <?php if($manager&&in_array($app->state,array('submitted','resubmitted','legacy_review_required','under_review'),true)):?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="gdo_assign_application"><input type="hidden" name="application_id" value="<?php echo absint($app->id);?>"><input type="hidden" name="row_version" value="<?php echo absint($app->row_version);?>"><?php wp_nonce_field('gdo_assign_application_'.$app->id);?><label><?php esc_html_e('Reviewer user ID','global-doctor-onboarding');?><input type="number" name="reviewer_id" required min="1" value="<?php echo absint($app->assigned_reviewer_id);?>"></label><label><?php esc_html_e('Assignment/reassignment reason','global-doctor-onboarding');?><textarea name="reason" required minlength="20"></textarea></label><button class="button"><?php esc_html_e('Assign independent reviewer','global-doctor-onboarding');?></button></form><?php endif;?>
                    <?php if(absint($app->assigned_reviewer_id)===$reviewer&&'under_review'===$app->state):?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="gdo_recommend_decision"><input type="hidden" name="application_id" value="<?php echo absint($app->id);?>"><input type="hidden" name="row_version" value="<?php echo absint($app->row_version);?>"><?php wp_nonce_field('gdo_recommend_decision_'.$app->id);?><label><?php esc_html_e('Conflict declaration','global-doctor-onboarding');?><select name="conflict"><option value="none">No conflict</option><option value="conflict">Conflict exists</option></select></label><label><?php esc_html_e('Recommendation','global-doctor-onboarding');?><select name="decision"><option value="verified">Recommend verification</option><option value="rejected">Recommend rejection</option></select></label><label><?php esc_html_e('Reason','global-doctor-onboarding');?><textarea name="reason" required minlength="20"></textarea></label><button class="button button-primary"><?php esc_html_e('Record recommendation','global-doctor-onboarding');?></button></form><?php endif;?>
                    <?php if('recommended'===$app->state&&GDO_Membership_Adapter::can('sabri_finalize_doctor_verification')):?><p><strong><?php esc_html_e('Recommended:','global-doctor-onboarding');?> <?php echo esc_html($app->recommended_decision);?></strong></p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="gdo_finalize_decision"><input type="hidden" name="application_id" value="<?php echo absint($app->id);?>"><input type="hidden" name="row_version" value="<?php echo absint($app->row_version);?>"><?php wp_nonce_field('gdo_finalize_decision_'.$app->id);?><input type="hidden" name="decision" value="<?php echo esc_attr($app->recommended_decision);?>"><label><?php esc_html_e('Final reason','global-doctor-onboarding');?><textarea name="reason" required minlength="20"></textarea></label><?php if('verified'===$app->recommended_decision):?><label><?php esc_html_e('Verification valid until','global-doctor-onboarding');?><input type="date" name="verified_until" required></label><?php endif;?><button class="button button-primary"><?php esc_html_e('Finalize independent decision','global-doctor-onboarding');?></button></form><?php endif;?>
                    <?php if(in_array($app->state,array('verified','reinstated','suspended','renewal_due'),true)&&GDO_Membership_Adapter::can('sabri_finalize_doctor_verification')):?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="gdo_change_verification_state"><input type="hidden" name="application_id" value="<?php echo absint($app->id);?>"><input type="hidden" name="row_version" value="<?php echo absint($app->row_version);?>"><?php wp_nonce_field('gdo_change_verification_state_'.$app->id);?><select name="state"><?php foreach(array('suspended','revoked','renewal_due','reinstated') as $state):if(GDO_State::can_transition($app->state,$state)):?><option value="<?php echo esc_attr($state);?>"><?php echo esc_html($state);?></option><?php endif;endforeach;?></select><label><?php esc_html_e('New validity date when reinstating','global-doctor-onboarding');?><input type="date" name="verified_until"></label><textarea name="reason" required minlength="20"></textarea><button class="button"><?php esc_html_e('Record lifecycle decision','global-doctor-onboarding');?></button></form><?php endif;?>
                    <?php if('appeal_pending'===$app->state&&GDO_Membership_Adapter::can('sabri_finalize_doctor_verification')):?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="gdo_resolve_appeal"><input type="hidden" name="application_id" value="<?php echo absint($app->id);?>"><input type="hidden" name="row_version" value="<?php echo absint($app->row_version);?>"><?php wp_nonce_field('gdo_resolve_appeal_'.$app->id);?><select name="decision"><option value="under_review">Reopen independent review</option><option value="reinstated">Reinstate</option><option value="suspended">Keep suspended</option><option value="rejected">Keep rejected</option><option value="revoked">Revoke</option></select><label><?php esc_html_e('Validity date if reinstated','global-doctor-onboarding');?><input type="date" name="verified_until"></label><textarea name="reason" required minlength="20"></textarea><button class="button"><?php esc_html_e('Resolve appeal','global-doctor-onboarding');?></button></form><?php endif;?>
                </td></tr>
            <?php endforeach;?><?php if(!$apps):?><tr><td colspan="3"><?php esc_html_e('No applications found.','global-doctor-onboarding');?></td></tr><?php endif;?></tbody></table>
            <?php echo wp_kses_post(paginate_links(array('total'=>max(1,(int)ceil($total/$per_page)),'current'=>$paged)));?>
        </div><?php
    }

    public function assign() {
        $this->guard('sabri_manage_doctor_verification');global $wpdb;$id=absint($_POST['application_id']??0);$reviewer=absint($_POST['reviewer_id']??0);$reason=sanitize_textarea_field($_POST['reason']??'');check_admin_referer('gdo_assign_application_'.$id);$app=GDO_Application::get($id);
        if(!$app||$reviewer===absint($app->user_id)||strlen($reason)<20||!GDO_Membership_Adapter::reviewer_scope_allows($reviewer,$app->user_id,$id)||!in_array($app->state,array('submitted','resubmitted','legacy_review_required','under_review'),true))wp_die(esc_html__('Invalid reviewer assignment.','global-doctor-onboarding'),'',array('response'=>400));
        $wpdb->query('START TRANSACTION');$updated=$wpdb->query($wpdb->prepare('UPDATE '.GDO_Schema::table('applications').' SET assigned_reviewer_id=%d,recommender_id=NULL,finalizer_id=NULL,recommended_decision=NULL,recommendation_reason=NULL,row_version=row_version+1,updated_at=%s WHERE id=%d AND row_version=%d',$reviewer,current_time('mysql',true),$id,absint($_POST['row_version']??0)));
        if(1!==$updated){$wpdb->query('ROLLBACK');wp_die(esc_html__('The assignment conflicted with a newer change.','global-doctor-onboarding'),'',array('response'=>409));}
        $fresh=GDO_Application::get($id);if('under_review'!==$fresh->state){$result=GDO_State::transition($id,'under_review',get_current_user_id(),'reviewer_assigned',$reason,$fresh->row_version,false);if(is_wp_error($result)){$wpdb->query('ROLLBACK');wp_die(esc_html($result->get_error_message()),'',array('response'=>409));}}
        $wpdb->query('COMMIT');GDO_Membership_Adapter::audit('doctor_application_assigned',array('application_id'=>$id,'reviewer_id'=>$reviewer,'actor_id'=>get_current_user_id()));GDO_Notifications::queue('doctor_application_assigned',$app->user_id,array('application_id'=>$id));$this->redirect();
    }

    public function review_evidence() {
        $this->guard('sabri_verify_doctors');global $wpdb;$evidence_id=absint($_POST['evidence_id']??0);check_admin_referer('gdo_review_evidence_'.$evidence_id);$record=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GDO_Schema::table('evidence').' WHERE id=%d',$evidence_id));$app=$record?GDO_Application::get($record->application_id):null;$reviewer=get_current_user_id();
        if(!$app||absint($app->assigned_reviewer_id)!==$reviewer||absint($app->user_id)===$reviewer||!GDO_Membership_Adapter::reviewer_scope_allows($reviewer,$app->user_id,$app->id))wp_die(esc_html__('This credential is not assigned to you.','global-doctor-onboarding'),'',array('response'=>403));
        $status=sanitize_key($_POST['status']??'');$wpdb->query('START TRANSACTION');$result=GDO_Evidence::review($evidence_id,$reviewer,$status,(array)($_POST['checklist']??array()),sanitize_key($_POST['registry_result']??''),sanitize_text_field($_POST['validity_from']??''),sanitize_text_field($_POST['validity_until']??''),sanitize_textarea_field($_POST['review_note']??''));if(is_wp_error($result)){$wpdb->query('ROLLBACK');wp_die(esc_html($result->get_error_message()),'',array('response'=>400));}
        if(in_array($status,array('more_information','rejected'),true)){$fresh=GDO_Application::get($app->id);$transition=GDO_State::transition($app->id,'more_information',$reviewer,'evidence_replacement_required',sanitize_textarea_field($_POST['review_note']??'Credential replacement is required.'),$fresh->row_version,false);if(is_wp_error($transition)){$wpdb->query('ROLLBACK');wp_die(esc_html($transition->get_error_message()),'',array('response'=>409));}}
        $wpdb->query('COMMIT');if(in_array($status,array('more_information','rejected'),true))GDO_Notifications::queue('doctor_application_more_information',$app->user_id,array('application_id'=>$app->id,'evidence_type'=>$record->document_type));$this->redirect();
    }

    public function recommend() {
        $this->guard('sabri_verify_doctors');global $wpdb;$id=absint($_POST['application_id']??0);check_admin_referer('gdo_recommend_decision_'.$id);$app=GDO_Application::get($id);$reviewer=get_current_user_id();$reason=sanitize_textarea_field($_POST['reason']??'');$decision=sanitize_key($_POST['decision']??'');
        if(!$app||absint($app->assigned_reviewer_id)!==$reviewer||absint($app->user_id)===$reviewer||'none'!==sanitize_key($_POST['conflict']??'')||strlen($reason)<20||!in_array($decision,array('verified','rejected'),true)||('verified'===$decision&&!GDO_Evidence::all_accepted($id)))wp_die(esc_html__('Independent review requirements are incomplete.','global-doctor-onboarding'),'',array('response'=>400));
        $wpdb->query('START TRANSACTION');$updated=$wpdb->update(GDO_Schema::table('applications'),array('recommender_id'=>$reviewer,'recommended_decision'=>$decision,'recommendation_reason'=>$reason,'recommendation_at'=>current_time('mysql',true),'reviewed_at'=>current_time('mysql',true)),array('id'=>$id,'row_version'=>absint($_POST['row_version']??0)),array('%d','%s','%s','%s','%s'),array('%d','%d'));if(1!==$updated){$wpdb->query('ROLLBACK');wp_die(esc_html__('The recommendation conflicted with a newer change.','global-doctor-onboarding'),'',array('response'=>409));}
        $fresh=GDO_Application::get($id);$result=GDO_State::transition($id,'recommended',$reviewer,'verification_'.$decision.'_recommended',$reason,$fresh->row_version,false);if(is_wp_error($result)){$wpdb->query('ROLLBACK');wp_die(esc_html($result->get_error_message()),'',array('response'=>409));}$wpdb->query('COMMIT');$this->redirect();
    }

    public function finalize() {
        $this->guard('sabri_finalize_doctor_verification');global $wpdb;$id=absint($_POST['application_id']??0);check_admin_referer('gdo_finalize_decision_'.$id);$app=GDO_Application::get($id);$finalizer=get_current_user_id();$decision=sanitize_key($_POST['decision']??'');$reason=sanitize_textarea_field($_POST['reason']??'');$until=sanitize_text_field($_POST['verified_until']??'');
        if(!$app||'recommended'!==$app->state||absint($app->user_id)===$finalizer||absint($app->recommender_id)===$finalizer||$decision!==$app->recommended_decision||!in_array($decision,array('verified','rejected'),true)||strlen($reason)<20||!GDO_Membership_Adapter::reviewer_scope_allows($finalizer,$app->user_id,$id))wp_die(esc_html__('Independent finalization requirements are incomplete.','global-doctor-onboarding'),'',array('response'=>400));
        if('verified'===$decision&&(!GDO_Evidence::all_accepted($id)||!$until||strtotime($until.' 23:59:59 UTC')<=time()))wp_die(esc_html__('Accepted evidence and a future verification expiry date are required.','global-doctor-onboarding'),'',array('response'=>400));
        $profile=json_decode($app->profile_json,true);$evidence=array();foreach(GDO_Evidence::records($id,true) as $record){$evidence[$record->document_type]=array('version'=>absint($record->version),'content_hmac'=>$record->content_hmac,'status'=>$record->status,'validity_until'=>$record->validity_until);}
        $snapshot=array('schema'=>3,'application_uuid'=>$app->application_uuid,'application_version'=>absint($app->version),'profile'=>$profile,'evidence'=>$evidence,'verified_until'=>$until,'recommender_id'=>absint($app->recommender_id),'finalizer_id'=>$finalizer,'captured_at'=>current_time('mysql',true));$fingerprint=GDO_Application::fingerprint((array)$profile,$evidence);
        $wpdb->query('START TRANSACTION');$result=GDO_State::transition($id,$decision,$finalizer,'verification_finalized',$reason,absint($_POST['row_version']??0),false);if(is_wp_error($result)){$wpdb->query('ROLLBACK');wp_die(esc_html($result->get_error_message()),'',array('response'=>409));}
        $data=array('finalizer_id'=>$finalizer,'decision_at'=>current_time('mysql',true),'verified_until'=>'verified'===$decision?gmdate('Y-m-d 23:59:59',strtotime($until.' UTC')):null,'approved_snapshot_json'=>'verified'===$decision?wp_json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,'approved_fingerprint'=>'verified'===$decision?$fingerprint:null,'retention_until'=>'rejected'===$decision?GDO_State::retention_deadline('rejected'):null,'updated_at'=>current_time('mysql',true));$updated=$wpdb->update(GDO_Schema::table('applications'),$data,array('id'=>$id),array('%d','%s','%s','%s','%s','%s','%s'),array('%d'));if(false===$updated){$wpdb->query('ROLLBACK');wp_die(esc_html__('The final decision could not be committed.','global-doctor-onboarding'),'',array('response'=>500));}$wpdb->query('COMMIT');
        GDO_Notifications::queue('doctor_verification_'.$decision,$app->user_id,array('application_id'=>$id,'state'=>$decision,'verified_until'=>$until));do_action('gdo_verification_decision_changed',$app->user_id,$decision,$id,'verified'===$decision?$snapshot:array());$this->redirect();
    }

    public function download() {
        $this->guard('sabri_access_doctor_credentials');global $wpdb;$id=absint($_POST['evidence_id']??0);check_admin_referer('gdo_download_evidence_'.$id);$purpose=sanitize_textarea_field($_POST['purpose']??'');$reviewer=get_current_user_id();$record=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GDO_Schema::table('evidence')." WHERE id=%d AND retention_state='active' AND deleted_at IS NULL",$id));$app=$record?GDO_Application::get($record->application_id):null;
        $allowed=$app&&strlen($purpose)>=10&&absint($app->user_id)!==$reviewer&&GDO_Membership_Adapter::reviewer_scope_allows($reviewer,$app->user_id,$app->id)&&(absint($app->assigned_reviewer_id)===$reviewer||GDO_Membership_Adapter::can('sabri_manage_doctor_verification',$reviewer))&&GDO_Rate_Limiter::hit('credential-access:'.$reviewer,30,HOUR_IN_SECONDS);
        if(!$allowed){if($record)GDO_Audit::access($record->application_id,$id,$reviewer,$purpose,'denied');wp_die(esc_html__('Credential access denied.','global-doctor-onboarding'),'',array('response'=>403));}
        $audit=GDO_Audit::access($app->id,$id,$reviewer,$purpose,'allowed');if(is_wp_error($audit))wp_die(esc_html($audit->get_error_message()),'',array('response'=>500));$bytes=GDO_Evidence::decrypt_record($record);if(is_wp_error($bytes)){GDO_Audit::access($app->id,$id,$reviewer,$purpose,'failed');wp_die(esc_html($bytes->get_error_message()),'',array('response'=>500));}
        GDO_Notifications::queue('doctor_credential_accessed',$app->user_id,array('application_id'=>$app->id,'evidence_type'=>$record->document_type));while(ob_get_level())ob_end_clean();nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: sandbox; default-src 'none'");header('Content-Type: '.$record->mime_type);header('Content-Disposition: attachment; filename="'.sanitize_file_name($record->original_name).'"');header('Content-Length: '.strlen($bytes));echo $bytes;exit;
    }

    public function change_state() {
        $this->guard('sabri_finalize_doctor_verification');global $wpdb;$id=absint($_POST['application_id']??0);check_admin_referer('gdo_change_verification_state_'.$id);$app=GDO_Application::get($id);$state=sanitize_key($_POST['state']??'');$reason=sanitize_textarea_field($_POST['reason']??'');$until=sanitize_text_field($_POST['verified_until']??'');$actor=get_current_user_id();
        if(!$app||absint($app->user_id)===$actor||strlen($reason)<20||!in_array($state,array('suspended','revoked','renewal_due','reinstated'),true)||!GDO_State::can_transition($app->state,$state)||!GDO_Membership_Adapter::reviewer_scope_allows($actor,$app->user_id,$id))wp_die(esc_html__('Invalid verification lifecycle decision.','global-doctor-onboarding'),'',array('response'=>400));
        if('reinstated'===$state&&(!GDO_Evidence::all_accepted($id)||!$until||strtotime($until.' 23:59:59 UTC')<=time()))wp_die(esc_html__('Reinstatement requires accepted current evidence and a future validity date.','global-doctor-onboarding'),'',array('response'=>400));
        $wpdb->query('START TRANSACTION');$result=GDO_State::transition($id,$state,$actor,'verification_lifecycle',$reason,absint($_POST['row_version']??0),false);if(is_wp_error($result)){$wpdb->query('ROLLBACK');wp_die(esc_html($result->get_error_message()),'',array('response'=>409));}
        $data=array('updated_at'=>current_time('mysql',true));$formats=array('%s');if('reinstated'===$state){$data['verified_until']=gmdate('Y-m-d 23:59:59',strtotime($until.' UTC'));$data['finalizer_id']=$actor;$formats[]= '%s';$formats[]='%d';}if('revoked'===$state){$data['retention_until']=GDO_State::retention_deadline('revoked');$formats[]='%s';}$updated=$wpdb->update(GDO_Schema::table('applications'),$data,array('id'=>$id),$formats,array('%d'));if(false===$updated){$wpdb->query('ROLLBACK');wp_die(esc_html__('The lifecycle decision could not be committed.','global-doctor-onboarding'),'',array('response'=>500));}$wpdb->query('COMMIT');
        GDO_Notifications::queue('doctor_verification_'.$state,$app->user_id,array('application_id'=>$id,'state'=>$state,'verified_until'=>$until));do_action('gdo_verification_decision_changed',$app->user_id,$state,$id,array());$this->redirect();
    }

    public function resolve_appeal() {
        $this->guard('sabri_finalize_doctor_verification');global $wpdb;$id=absint($_POST['application_id']??0);check_admin_referer('gdo_resolve_appeal_'.$id);$app=GDO_Application::get($id);$decision=sanitize_key($_POST['decision']??'');$reason=sanitize_textarea_field($_POST['reason']??'');$until=sanitize_text_field($_POST['verified_until']??'');$actor=get_current_user_id();$appeal=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GDO_Schema::table('appeals')." WHERE application_id=%d AND status='open' ORDER BY id DESC LIMIT 1",$id));
        $allowed=array('rejected'=>array('under_review','rejected','revoked'),'suspended'=>array('under_review','reinstated','suspended','revoked'),'revoked'=>array('under_review','reinstated','revoked'));
        if(!$app||!$appeal||'appeal_pending'!==$app->state||absint($app->user_id)===$actor||absint($app->recommender_id)===$actor||strlen($reason)<20||empty($allowed[$appeal->source_state])||!in_array($decision,$allowed[$appeal->source_state],true)||!GDO_State::can_transition($app->state,$decision))wp_die(esc_html__('Invalid appeal decision.','global-doctor-onboarding'),'',array('response'=>400));
        if('reinstated'===$decision&&(!GDO_Evidence::all_accepted($id)||!$until||strtotime($until.' 23:59:59 UTC')<=time()))wp_die(esc_html__('Reinstatement requires current accepted evidence and a future validity date.','global-doctor-onboarding'),'',array('response'=>400));
        $wpdb->query('START TRANSACTION');$result=GDO_State::transition($id,$decision,$actor,'appeal_resolved',$reason,absint($_POST['row_version']??0),false);if(is_wp_error($result)){$wpdb->query('ROLLBACK');wp_die(esc_html($result->get_error_message()),'',array('response'=>409));}
        $updated=$wpdb->update(GDO_Schema::table('appeals'),array('status'=>'resolved','resolution'=>$reason,'decision'=>$decision,'resolver_id'=>$actor,'resolved_at'=>current_time('mysql',true)),array('id'=>$appeal->id,'status'=>'open'),array('%s','%s','%s','%d','%s'),array('%d','%s'));if(1!==$updated){$wpdb->query('ROLLBACK');wp_die(esc_html__('The appeal resolution could not be committed.','global-doctor-onboarding'),'',array('response'=>500));}
        $app_data=array('updated_at'=>current_time('mysql',true));$formats=array('%s');if('under_review'===$decision){$app_data['assigned_reviewer_id']=null;$app_data['recommender_id']=null;$app_data['finalizer_id']=null;$app_data['recommended_decision']=null;$formats=array('%s','%s','%s','%s','%s');}elseif('reinstated'===$decision){$app_data['verified_until']=gmdate('Y-m-d 23:59:59',strtotime($until.' UTC'));$app_data['finalizer_id']=$actor;$formats=array('%s','%s','%d');}$wpdb->update(GDO_Schema::table('applications'),$app_data,array('id'=>$id),$formats,array('%d'));$wpdb->query('COMMIT');
        GDO_Notifications::queue('doctor_verification_appeal_resolved',$app->user_id,array('application_id'=>$id,'state'=>$decision));do_action('gdo_verification_decision_changed',$app->user_id,$decision,$id,array());$this->redirect();
    }

    private function redirect(){wp_safe_redirect(add_query_arg(array('page'=>'global-doctor-verification','updated'=>'1'),admin_url('admin.php')));exit;}
}
