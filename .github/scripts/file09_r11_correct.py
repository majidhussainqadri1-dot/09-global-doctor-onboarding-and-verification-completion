from pathlib import Path

def rep(path, old, new, count=1):
    p=Path(path); s=p.read_text()
    if s.count(old) < count:
        raise SystemExit(f'missing replacement in {path}: {old[:120]!r}')
    p.write_text(s.replace(old,new,count))

rep('includes/class-gdo-audit.php',
    "        global $wpdb;\n        $previous = $wpdb->get_var",
    "        global $wpdb;\n        $wpdb->last_error = '';\n        $previous = $wpdb->get_var")
rep('includes/class-gdo-rate-limiter.php',
    "\t\t$raw_hits = $wpdb->get_var",
    "\t\t$wpdb->last_error = '';\n\t\t$raw_hits = $wpdb->get_var")

r=Path('includes/class-gdo-risk.php'); s=r.read_text()
s=s.replace("\t\tif ( $identity ) {\n\t\t\t$duplicate = $wpdb->get_var", "\t\tif ( $identity ) {\n\t\t\t$wpdb->last_error = '';\n\t\t\t$duplicate = $wpdb->get_var",1)
s=s.replace("\t\t\t$duplicate = $wpdb->get_var( $wpdb->prepare(\n\t\t\t\t'SELECT id FROM ' . GDO_Schema::table( 'evidence' )", "\t\t\t$wpdb->last_error = '';\n\t\t\t$duplicate = $wpdb->get_var( $wpdb->prepare(\n\t\t\t\t'SELECT id FROM ' . GDO_Schema::table( 'evidence' )",1)
s=s.replace("\t\t$existing = $wpdb->get_var( $wpdb->prepare(", "\t\t$wpdb->last_error = '';\n\t\t$existing = $wpdb->get_var( $wpdb->prepare(",1)
s=s.replace("\t\t$rows = $wpdb->get_results( $wpdb->prepare(", "\t\t$wpdb->last_error = '';\n\t\t$rows = $wpdb->get_results( $wpdb->prepare(",1)
s=s.replace("\t\treturn 1 === $updated ? true : new WP_Error( 'gdo_risk_resolution_conflict', __( 'The risk signal changed before it could be resolved.', 'global-doctor-onboarding' ) );", "\t\tif ( false === $updated ) {\n\t\t\treturn new WP_Error( 'gdo_risk_resolution_store', __( 'The risk resolution could not be stored safely.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\treturn 1 === $updated ? true : new WP_Error( 'gdo_risk_resolution_conflict', __( 'The risk signal changed before it could be resolved.', 'global-doctor-onboarding' ) );")
r.write_text(s)

rep('includes/class-gdo-quality.php',
    "\t\treturn 1 === $updated ? true : new WP_Error( 'gdo_quality_conflict', __( 'The quality sample changed before completion.', 'global-doctor-onboarding' ) );",
    "\t\tif ( false === $updated ) {\n\t\t\treturn new WP_Error( 'gdo_quality_store_failed', __( 'The quality sample completion could not be stored safely.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\treturn 1 === $updated ? true : new WP_Error( 'gdo_quality_conflict', __( 'The quality sample changed before completion.', 'global-doctor-onboarding' ) );")

rep('includes/class-gdo-state.php',
    "        if ( 1 !== $updated ) {\n            if ( $manage_transaction ) {\n                $wpdb->query( 'ROLLBACK' );\n            }\n            return new WP_Error( 'gdo_concurrent_change', __( 'The application changed while it was being reviewed. Reload and try again.', 'global-doctor-onboarding' ) );\n        }",
    "        if ( false === $updated ) {\n            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n            return new WP_Error( 'gdo_transition_store_failed', __( 'The application state could not be stored safely.', 'global-doctor-onboarding' ) );\n        }\n        if ( 1 !== $updated ) {\n            if ( $manage_transaction ) {\n                $wpdb->query( 'ROLLBACK' );\n            }\n            return new WP_Error( 'gdo_concurrent_change', __( 'The application changed while it was being reviewed. Reload and try again.', 'global-doctor-onboarding' ) );\n        }")

c=Path('includes/class-gdo-claims.php'); s=c.read_text()
s=s.replace("\t\tif ( 1 !== $updated ) {\n\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\treturn new WP_Error( 'gdo_claim_concurrent_change'", "\t\tif ( false === $updated ) {\n\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\treturn new WP_Error( 'gdo_claim_store_failed', __( 'The professional verification claim could not be stored safely.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\tif ( 1 !== $updated ) {\n\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\treturn new WP_Error( 'gdo_claim_concurrent_change'",1)
s=s.replace("\t\tglobal $wpdb;\n\t\t$application_id = absint( $wpdb->get_var", "\t\tglobal $wpdb;\n\t\t$wpdb->last_error = '';\n\t\t$application_id = absint( $wpdb->get_var",1)
s=s.replace("\t\tGDO_Membership_Adapter::audit( 'doctor_professional_claim_issued', array(", "\t\tif ( ! empty( $wpdb->last_error ) || ! $application_id ) { return false; }\n\t\tGDO_Membership_Adapter::audit( 'doctor_professional_claim_issued', array(",1)
c.write_text(s)

rep('includes/class-gdo-rest.php',
    "\t\treturn rest_ensure_response( GDO_API::application_edit_model( GDO_Application::get( $app->id ), get_current_user_id() ) );",
    "\t\t$wpdb->last_error = '';\n\t\t$fresh = GDO_Application::get( $app->id );\n\t\tif ( null === $fresh && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_application_autosave_reload_failed', __( 'The saved application could not be reloaded safely.', 'global-doctor-onboarding' ), array( 'status'=>503 ) ); }\n\t\tif ( ! $fresh ) { return new WP_Error( 'gdo_application_autosave_reload_missing', __( 'The saved application is temporarily unavailable.', 'global-doctor-onboarding' ), array( 'status'=>503 ) ); }\n\t\treturn rest_ensure_response( GDO_API::application_edit_model( $fresh, get_current_user_id() ) );")

a=Path('includes/class-gdo-application.php'); s=a.read_text()
s=s.replace("\t\tif ( 1 !== $inserted ) {\n\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\t$existing = self::latest_for_user( $user_id );", "\t\tif ( 1 !== $inserted ) {\n\t\t\t$wpdb->query( 'ROLLBACK' );\n\t\t\t$wpdb->last_error = '';\n\t\t\t$existing = self::latest_for_user( $user_id );\n\t\t\tif ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_application_create_recovery_query', __( 'The application creation result could not be verified safely.', 'global-doctor-onboarding' ) ); }",1)
s=s.replace("\t\t$app = self::get( $wpdb->insert_id );\n\t\t$audit = $app ?", "\t\t$wpdb->last_error = '';\n\t\t$app = self::get( $wpdb->insert_id );\n\t\tif ( null === $app && ! empty( $wpdb->last_error ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'gdo_application_create_reload_query', __( 'The newly created application could not be reloaded safely.', 'global-doctor-onboarding' ) ); }\n\t\t$audit = $app ?",1)
s=s.replace("\t\treturn 1 === $updated ? true : new WP_Error( 'gdo_concurrent_change', __( 'The application changed. Reload and try again.', 'global-doctor-onboarding' ) );", "\t\tif ( false === $updated ) { return new WP_Error( 'gdo_draft_store_failed', __( 'The draft application could not be stored safely.', 'global-doctor-onboarding' ) ); }\n\t\treturn 1 === $updated ? true : new WP_Error( 'gdo_concurrent_change', __( 'The application changed. Reload and try again.', 'global-doctor-onboarding' ) );",1)
s=s.replace("\t\t$row = $wpdb->get_row( $wpdb->prepare(\n\t\t\t'SELECT consent_version", "\t\t$wpdb->last_error = '';\n\t\t$row = $wpdb->get_row( $wpdb->prepare(\n\t\t\t'SELECT consent_version",1)
s=s.replace("\t\tif ( 1 !== $updated ) {\n\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n\t\t\treturn new WP_Error( 'gdo_consent_link'", "\t\tif ( false === $updated ) {\n\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n\t\t\treturn new WP_Error( 'gdo_consent_link_store', __( 'Consent could not be linked because the application write failed.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\tif ( 1 !== $updated ) {\n\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n\t\t\treturn new WP_Error( 'gdo_consent_link'",1)
a.write_text(s)

e=Path('includes/class-gdo-evidence.php'); s=e.read_text()
needle="""    public static function current( $application_id, $type ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . \" WHERE application_id=%d AND document_type=%s AND retention_state='active' AND deleted_at IS NULL ORDER BY version DESC LIMIT 1\",
            absint( $application_id ), sanitize_key( $type )
        ) );
    }
"""
insert=needle+"""
    public static function records_checked( $application_id, $active_only = false ) {
        global $wpdb;
        $wpdb->last_error = '';
        $rows = self::records( $application_id, $active_only );
        if ( null === $rows || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_evidence_records_query', __( 'Credential evidence records could not be read safely.', 'global-doctor-onboarding' ) ); }
        return $rows;
    }
    public static function current_checked( $application_id, $type ) {
        global $wpdb;
        $wpdb->last_error = '';
        $row = self::current( $application_id, $type );
        if ( null === $row && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_evidence_current_query', __( 'Current credential evidence could not be read safely.', 'global-doctor-onboarding' ) ); }
        return $row;
    }
"""
if needle not in s: raise SystemExit('evidence method anchor missing')
s=s.replace(needle,insert,1)
s=s.replace("\t\t$raw_used = $wpdb->get_var", "\t\t$wpdb->last_error = '';\n\t\t$raw_used = $wpdb->get_var",1)
s=s.replace("        if ( 1 !== $updated ) {\n            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n            return new WP_Error( 'gdo_evidence_review_conflict'", "        if ( false === $updated ) {\n            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n            return new WP_Error( 'gdo_evidence_review_store_failed', __( 'The credential review could not be stored safely.', 'global-doctor-onboarding' ) );\n        }\n        if ( 1 !== $updated ) {\n            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }\n            return new WP_Error( 'gdo_evidence_review_conflict'",1)
s=s.replace("        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . \" WHERE id=%d AND retention_state='active' AND deleted_at IS NULL\", $evidence_id ) );\n        $app = $record ? GDO_Application::get( $record->application_id ) : null;", "        $wpdb->last_error = '';\n        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . \" WHERE id=%d AND retention_state='active' AND deleted_at IS NULL\", $evidence_id ) );\n        if ( null === $record && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_evidence_grant_evidence_query', __( 'Credential evidence could not be read safely for access grant.', 'global-doctor-onboarding' ) ); }\n        $wpdb->last_error = '';\n        $app = $record ? GDO_Application::get( $record->application_id ) : null;\n        if ( $record && null === $app && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_evidence_grant_application_query', __( 'Credential application could not be read safely for access grant.', 'global-doctor-onboarding' ) ); }",1)
s=s.replace("        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . GDO_Schema::table( 'access_grants' ) . ' WHERE reviewer_id=%d AND (expires_at<%s OR used_at IS NOT NULL)', $reviewer_id, current_time( 'mysql', true ) ) );", "        $cleaned = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . GDO_Schema::table( 'access_grants' ) . ' WHERE reviewer_id=%d AND (expires_at<%s OR used_at IS NOT NULL)', $reviewer_id, current_time( 'mysql', true ) ) );\n        if ( false === $cleaned ) { return new WP_Error( 'gdo_evidence_grant_cleanup_failed', __( 'Expired credential access grants could not be cleaned safely.', 'global-doctor-onboarding' ) ); }",1)
e.write_text(s)

p=Path('includes/class-gdo-privacy.php'); s=p.read_text()
s=s.replace("\t\t$apps = $wpdb->get_results( $wpdb->prepare(", "\t\t$wpdb->last_error = '';\n\t\t$apps = $wpdb->get_results( $wpdb->prepare(",1)
s=s.replace("\t\t\tforeach ( GDO_Evidence::records( $app->id ) as $record ) {", "\t\t\t$evidence_rows = GDO_Evidence::records_checked( $app->id );\n\t\t\tif ( is_wp_error( $evidence_rows ) ) { return array( 'data'=>$data, 'done'=>false ); }\n\t\t\tforeach ( $evidence_rows as $record ) {",1)
s=s.replace("\t\t\tforeach ( $queries as $label => $sql ) {\n\t\t\t\t$items =", "\t\t\tforeach ( $queries as $label => $sql ) {\n\t\t\t\t$wpdb->last_error = '';\n\t\t\t\t$items =",1)
s=s.replace("\t\t$limit = 10;\n\t\t$apps = $wpdb->get_results", "\t\t$limit = 10;\n\t\t$wpdb->last_error = '';\n\t\t$apps = $wpdb->get_results",1)
s=s.replace("\t\t$held_raw = $wpdb->get_var", "\t\t$wpdb->last_error = '';\n\t\t$held_raw = $wpdb->get_var",1)
s=s.replace("\t\tforeach ( $apps as $app ) {\n\t\t\t$current = GDO_Application::get( $app->id );", "\t\tforeach ( $apps as $app ) {\n\t\t\t$wpdb->last_error = '';\n\t\t\t$current = GDO_Application::get( $app->id );\n\t\t\tif ( null === $current && ! empty( $wpdb->last_error ) ) { $retained = true; $messages[] = 'Erasure is paused because the current application state could not be read safely.'; continue; }",1)
s=s.replace("\t\t\tforeach ( GDO_Evidence::records( $app->id, false ) as $record ) {", "\t\t\t$evidence_rows = GDO_Evidence::records_checked( $app->id, false );\n\t\t\tif ( is_wp_error( $evidence_rows ) ) { $retained = true; $messages[] = 'Erasure is paused because credential evidence inventory could not be read safely.'; continue; }\n\t\t\tforeach ( $evidence_rows as $record ) {",1)
s=s.replace("\t\t\t$locked_app = $wpdb->get_row( $wpdb->prepare(", "\t\t\t$wpdb->last_error = '';\n\t\t\t$locked_app = $wpdb->get_row( $wpdb->prepare(",1)
s=s.replace("\t\t\t$db_ok = (bool) $locked_app;", "\t\t\t$db_ok = (bool) $locked_app && empty( $wpdb->last_error );",1)
p.write_text(s)

m=Path('includes/class-gdo-membership-adapter.php'); s=m.read_text()
s=s.replace("\t\t\t$app = GDO_Application::get( $application_id );\n\t\t\t$profile = $wpdb->get_row", "\t\t\t$wpdb->last_error = '';\n\t\t\t$app = GDO_Application::get( $application_id );\n\t\t\tif ( null === $app && ! empty( $wpdb->last_error ) ) { $allowed = false; }\n\t\t\t$wpdb->last_error = '';\n\t\t\t$profile = $wpdb->get_row",1)
s=s.replace("\t\t\tif ( ! $app || ! $profile || absint( $app->user_id ) !== $applicant_id ) {", "\t\t\tif ( ! empty( $wpdb->last_error ) || ! $app || ! $profile || absint( $app->user_id ) !== $applicant_id ) {",1)
s=s.replace("\t\t$app = GDO_Application::get( $application_id );\n\t\tif ( ! $app || absint( $app->user_id ) !== $applicant_id ) {", "\t\t$wpdb->last_error = '';\n\t\t$app = GDO_Application::get( $application_id );\n\t\tif ( ! empty( $wpdb->last_error ) || ! $app || absint( $app->user_id ) !== $applicant_id ) {",1)
s=s.replace("\t\t\t$appeal_reviewer = absint( $wpdb->get_var", "\t\t\t$wpdb->last_error = '';\n\t\t\t$appeal_reviewer = absint( $wpdb->get_var",1)
s=s.replace("\t\t\tif ( $appeal_reviewer && $appeal_reviewer === $reviewer_id ) {", "\t\t\tif ( ! empty( $wpdb->last_error ) ) { return false; }\n\t\t\tif ( $appeal_reviewer && $appeal_reviewer === $reviewer_id ) {",1)
m.write_text(s)

mig=Path('includes/class-gdo-migration.php'); s=mig.read_text()
s=s.replace("\t\t\t\t$app = GDO_Application::latest_for_user( $user_id );", "\t\t\t\t$wpdb->last_error = '';\n\t\t\t\t$app = GDO_Application::latest_for_user( $user_id );\n\t\t\t\tif ( null === $app && ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Legacy File 09 application inventory could not be read safely.' ); }",1)
s=s.replace("\t\t$existing = absint( $wpdb->get_var( $wpdb->prepare(", "\t\t$wpdb->last_error = '';\n\t\t$existing = absint( $wpdb->get_var( $wpdb->prepare(",1)
s=s.replace("\t\tif ( $existing ) {", "\t\tif ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Legacy credential duplicate state could not be read safely.' ); }\n\t\tif ( $existing ) {",1)
s=s.replace("\t\t$version = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE application_id=%d AND document_type=%s', $app->id, $type ) ) ) + 1;", "\t\t$wpdb->last_error = '';\n\t\t$version_raw = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE application_id=%d AND document_type=%s', $app->id, $type ) );\n\t\tif ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Legacy credential version state could not be read safely.' ); }\n\t\t$version = absint( $version_raw ) + 1;",1)
mig.write_text(s)

n=Path('includes/class-gdo-notifications.php'); s=n.read_text()
s=s.replace("\t\tif ( 1 !== $inserted ) {\n\t\t\t$existing = absint( $wpdb->get_var", "\t\tif ( 1 !== $inserted ) {\n\t\t\t$wpdb->last_error = '';\n\t\t\t$existing = absint( $wpdb->get_var",1)
s=s.replace("\t\t\tif ( ! $existing ) {", "\t\t\tif ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_notification_outbox_query', __( 'The notification queue state could not be verified safely.', 'global-doctor-onboarding' ) ); }\n\t\t\tif ( ! $existing ) {",1)
s=s.replace("\t\t\t} catch ( Throwable $e ) {\n\t\t\t\t$result = new WP_Error( 'gdo_outbox_exception', $e->getMessage() );\n\t\t\t}", "\t\t\t} catch ( Throwable $e ) {\n\t\t\t\tGDO_Membership_Adapter::audit( 'doctor_verification_outbox_provider_exception', array( 'event_uuid'=>$row->event_uuid, 'event_type'=>$row->event_type, 'exception_class'=>get_class( $e ), 'exception_digest'=>hash( 'sha256', $e->getMessage() ) ) );\n\t\t\t\t$result = new WP_Error( 'gdo_outbox_exception', __( 'Notification provider processing failed.', 'global-doctor-onboarding' ) );\n\t\t\t}",1)
s=s.replace("\t\t\t$error = is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : 'Provider returned no explicit success.';", "\t\t\t$error = is_wp_error( $result ) ? sanitize_key( $result->get_error_code() ) : 'provider_no_explicit_success';",1)
n.write_text(s)

adv=Path('includes/class-gdo-advanced-trust.php'); s=adv.read_text()
s=s.replace("        $now = self::now();\n        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'jurisdiction_rules' )", "        $now = self::now();\n        $wpdb->last_error = '';\n        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'jurisdiction_rules' )",1)
s=s.replace("        $data = array(\n            'status'=>$status,", "        if ( null === $existing && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_jurisdiction_rule_query', __( 'Jurisdiction-rule state could not be read safely.', 'global-doctor-onboarding' ) ); }\n        $data = array(\n            'status'=>$status,",1)
s=s.replace("        if(1!==$updated){return new WP_Error('gdo_conflict_resolve',__('The active reviewer conflict could not be resolved.','global-doctor-onboarding'));}", "        if(false===$updated){return new WP_Error('gdo_conflict_resolve_store',__('The reviewer conflict resolution could not be stored safely.','global-doctor-onboarding'));}\n        if(1!==$updated){return new WP_Error('gdo_conflict_resolve',__('The active reviewer conflict could not be resolved because its state changed.','global-doctor-onboarding'));}",1)
adv.write_text(s)

h=Path('includes/class-gdo-advanced-trust-hardening.php'); s=h.read_text()
s=s.replace("        $application_id = absint( $application_id );\n        if ( ! $application_id || ! GDO_Application::get( $application_id ) ) { return false; }", "        $application_id = absint( $application_id );\n        if ( ! $application_id ) { return false; }\n        $wpdb->last_error = '';\n        $application = GDO_Application::get( $application_id );\n        if ( null === $application && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_reverification_application_query', __( 'Professional application state could not be read safely for reverification scheduling.', 'global-doctor-onboarding' ) ); }\n        if ( ! $application ) { return false; }",1)
h.write_text(s)

ev=Path('includes/class-gdo-advanced-trust-events.php'); s=ev.read_text()
s=s.replace("        $app = GDO_Application::get( $application_id );\n        if ( ! $app ) {\n            return;\n        }", "        global $wpdb;\n        $wpdb->last_error = '';\n        $app = GDO_Application::get( $application_id );\n        if ( null === $app && ! empty( $wpdb->last_error ) ) { GDO_Membership_Adapter::audit( 'doctor_continuous_verification_application_read_failed', array( 'application_id'=>$application_id, 'result'=>$result ) ); return; }\n        if ( ! $app ) { return; }",1)
ev.write_text(s)
