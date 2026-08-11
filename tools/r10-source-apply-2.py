from pathlib import Path
import subprocess
root=Path(__file__).resolve().parents[1]
BASE='eaf31dd4283770a41a84b5045e09519a91a6ccc6'
if subprocess.call(['git','merge-base','--is-ancestor',BASE,'HEAD'],cwd=root)!=0:
    raise SystemExit('R10 second corrective baseline is not an ancestor')
def rep(rel,old,new):
    p=root/rel;s=p.read_text(encoding='utf-8');c=s.count(old)
    if c!=1: raise SystemExit(f'anchor mismatch {rel}: {c}')
    p.write_text(s.replace(old,new,1),encoding='utf-8')

# R19 — generic state transition must distinguish DB lock/read failure from invalid state.
rel='includes/class-gdo-state.php'
rep(rel,"""        $app = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE id=%d FOR UPDATE\", $application_id ) );
        if ( ! $app || ! self::can_transition( $app->state, $to ) ) {
""","""        $wpdb->last_error = '';
        $app = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE id=%d FOR UPDATE\", $application_id ) );
        if ( null === $app && ! empty( $wpdb->last_error ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_transition_application_query', __( 'The application state could not be locked/read safely for transition.', 'global-doctor-onboarding' ) );
        }
        if ( ! $app || ! self::can_transition( $app->state, $to ) ) {
""")

# R20 — claim issuance must distinguish DB read failure from missing application.
rel='includes/class-gdo-claims.php'
rep(rel,"""\t\t$app = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', $application_id ) );
\t\tif ( ! $app ) {
""","""\t\t$wpdb->last_error = '';
\t\t$app = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', $application_id ) );
\t\tif ( null === $app && ! empty( $wpdb->last_error ) ) {
\t\t\tif ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
\t\t\treturn new WP_Error( 'gdo_claim_application_query', __( 'The professional claim application state could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\tif ( ! $app ) {
""")

# R21 — completeness must preserve DB uncertainty and submission must surface it.
rel='includes/class-gdo-application.php'
rep(rel,"""\t\t$missing_evidence = array();
\t\tforeach ( array_keys( GDO_Policy::evidence_types( $application->jurisdiction, $application->application_type ) ) as $type ) {
\t\t\t$record = GDO_Evidence::current( $application->id, $type );
\t\t\tif ( ! $record
""","""\t\t$missing_evidence = array();
\t\t$query_error = false;
\t\tglobal $wpdb;
\t\tforeach ( array_keys( GDO_Policy::evidence_types( $application->jurisdiction, $application->application_type ) ) as $type ) {
\t\t\t$wpdb->last_error = '';
\t\t\t$record = GDO_Evidence::current( $application->id, $type );
\t\t\tif ( null === $record && ! empty( $wpdb->last_error ) ) { $query_error = true; }
\t\t\tif ( ! $record
""")
rep(rel,"""\t\t$consent = self::active_consent( $application );
\t\treturn array(
\t\t\t'complete'=>! $missing_fields && ! $missing_evidence && $consent,
""","""\t\t$wpdb->last_error = '';
\t\t$consent = self::active_consent( $application );
\t\tif ( ! empty( $wpdb->last_error ) ) { $query_error = true; }
\t\treturn array(
\t\t\t'complete'=>! $query_error && ! $missing_fields && ! $missing_evidence && $consent,
\t\t\t'query_error'=>$query_error,
""")
rep(rel,"""\t\t$complete = self::completeness( $app );
\t\tif ( is_wp_error( $valid ) || empty( $complete['complete'] ) ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\treturn new WP_Error( 'gdo_submit_incomplete', __( 'Complete all profile, declaration, consent, and current credential requirements before submission.', 'global-doctor-onboarding' ) );
\t\t}
""","""\t\t$complete = self::completeness( $app );
\t\tif ( ! empty( $complete['query_error'] ) ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\treturn new WP_Error( 'gdo_submit_completeness_query', __( 'Application completeness could not be verified safely because a database read failed.', 'global-doctor-onboarding' ) );
\t\t}
\t\tif ( is_wp_error( $valid ) || empty( $complete['complete'] ) ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\treturn new WP_Error( 'gdo_submit_incomplete', __( 'Complete all profile, declaration, consent, and current credential requirements before submission.', 'global-doctor-onboarding' ) );
\t\t}
""")

# R22 — save_draft must not report a DB read failure as ordinary access denial.
rep(rel,"""\tpublic static function save_draft( $application_id, $user_id, array $profile, $expected_row_version, $allow_incomplete = false ) {
\t\tglobal $wpdb;
\t\t$app = self::get( $application_id );
\t\tif ( ! GDO_Operations::mutation_allowed() ) {
""","""\tpublic static function save_draft( $application_id, $user_id, array $profile, $expected_row_version, $allow_incomplete = false ) {
\t\tglobal $wpdb;
\t\t$wpdb->last_error = '';
\t\t$app = self::get( $application_id );
\t\tif ( null === $app && ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'gdo_draft_application_query', __( 'The draft application could not be read safely.', 'global-doctor-onboarding' ) );
\t\t}
\t\tif ( ! GDO_Operations::mutation_allowed() ) {
""")
print('R10 second corrections applied: rounds 19-22')
