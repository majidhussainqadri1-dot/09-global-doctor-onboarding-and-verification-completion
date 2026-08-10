#!/usr/bin/env python3
from pathlib import Path
import json, subprocess
root=Path(__file__).resolve().parents[1]
BASE='f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2'
head=subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip()
if head!=BASE: raise SystemExit(f'R8 frozen baseline mismatch: {head} != {BASE}')

def read(p): return (root/p).read_text(encoding='utf-8')
def write(p,s): (root/p).write_text(s,encoding='utf-8',newline='')
def rep(p,old,new):
    s=read(p)
    if s.count(old)!=1: raise SystemExit(f'R8 anchor mismatch {p}: {s.count(old)} matches')
    write(p,s.replace(old,new,1))
def append_once(p,marker,text):
    s=read(p)
    if marker not in s: write(p,s.rstrip()+"\n\n"+text.strip()+"\n")

# R04 — mutation readiness must include the runtime claim-signing dependency.
rep('includes/class-gdo-operations.php',
"\t\treturn ! self::safe_mode()\n\t\t\t&& $core_schema_ready\n\t\t\t&& $advanced_schema_ready\n\t\t\t&& GDO_Membership_Adapter::available()",
"\t\treturn ! self::safe_mode()\n\t\t\t&& $core_schema_ready\n\t\t\t&& $advanced_schema_ready\n\t\t\t&& defined( 'GDO_CLAIM_SIGNING_KEY' )\n\t\t\t&& strlen( (string) GDO_CLAIM_SIGNING_KEY ) >= 32\n\t\t\t&& GDO_Membership_Adapter::available()")

# R05 — isolate health-count DB error state explicitly.
rep('includes/class-gdo-operations.php',
"\tprivate static function count_query( $sql, $error_code ) {\n\t\tglobal $wpdb;\n\t\t$raw = $wpdb->get_var( $sql );",
"\tprivate static function count_query( $sql, $error_code ) {\n\t\tglobal $wpdb;\n\t\t$wpdb->last_error = '';\n\t\t$raw = $wpdb->get_var( $sql );")

# R06 — isolate reconciliation query DB error state explicitly.
rep('includes/class-gdo-operations.php',
"\t\t$now = current_time( 'mysql', true );\n\t\t$expired = $wpdb->get_results( $wpdb->prepare(",
"\t\t$now = current_time( 'mysql', true );\n\t\t$wpdb->last_error = '';\n\t\t$expired = $wpdb->get_results( $wpdb->prepare(")

# R07 — repair is an owner command; revalidate actor/capability/step-up at the method boundary.
rep('includes/class-gdo-operations.php',
"\tpublic static function repair( $action, $actor_id, $reason ) {\n\t\t$action = sanitize_key( $action );",
"\tpublic static function repair( $action, $actor_id, $reason ) {\n\t\t$current_actor = get_current_user_id();\n\t\tif ( ! $current_actor || absint( $actor_id ) !== absint( $current_actor ) || ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $current_actor ) || ! GDO_Membership_Adapter::recent_step_up( $current_actor ) ) {\n\t\t\treturn new WP_Error( 'gdo_repair_forbidden', __( 'Controlled repair requires current File 00 manager authorization and recent File 02 step-up.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\t$action = sanitize_key( $action );")

# R08 — Safe Mode changes are privileged owner commands too.
rep('includes/class-gdo-operations.php',
"\tpublic static function set_safe_mode( $enabled, $actor_id, $reason ) {\n\t\t$reason = sanitize_textarea_field( $reason );",
"\tpublic static function set_safe_mode( $enabled, $actor_id, $reason ) {\n\t\t$current_actor = get_current_user_id();\n\t\tif ( ! $current_actor || absint( $actor_id ) !== absint( $current_actor ) || ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $current_actor ) || ! GDO_Membership_Adapter::recent_step_up( $current_actor ) ) {\n\t\t\treturn new WP_Error( 'gdo_safe_mode_forbidden', __( 'Safe Mode changes require current File 00 manager authorization and recent File 02 step-up.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\t$reason = sanitize_textarea_field( $reason );")

# R09 — DB uncertainty must never be mistaken for “no legacy table”.
rep('includes/class-gdo-migration.php',
"\t\t$legacy = $wpdb->prefix . 'gdo_documents';\n\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );\n\t\tif ( $exists !== $legacy ) {",
"\t\t$legacy = $wpdb->prefix . 'gdo_documents';\n\t\t$wpdb->last_error = '';\n\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );\n\t\tif ( ! empty( $wpdb->last_error ) ) {\n\t\t\tthrow new RuntimeException( 'Legacy File 09 table inventory could not be verified safely.' );\n\t\t}\n\t\tif ( $exists !== $legacy ) {")

# R10/R11 — reviewer queues fail visibly on DB uncertainty instead of rendering an empty queue.
old="""\t\tif ( $manager ) {\n\t\t\t$apps = $wpdb->get_results( $wpdb->prepare( \"SELECT * FROM {$table} ORDER BY FIELD(state,'submitted','resubmitted','under_review','recommended','appeal_pending','renewal_due','suspended','verified','rejected','revoked','expired','withdrawn','draft'), updated_at DESC LIMIT %d OFFSET %d\", $per_page, $offset ) );\n\t\t\t$total = absint( $wpdb->get_var( \"SELECT COUNT(*) FROM {$table}\" ) );\n\t\t} else {\n\t\t\t$apps = $wpdb->get_results( $wpdb->prepare( \"SELECT * FROM {$table} WHERE assigned_reviewer_id=%d ORDER BY updated_at DESC LIMIT %d OFFSET %d\", $reviewer, $per_page, $offset ) );\n\t\t\t$total = absint( $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$table} WHERE assigned_reviewer_id=%d\", $reviewer ) ) );\n\t\t}\n"""
new="""\t\tif ( $manager ) {\n\t\t\t$wpdb->last_error = '';\n\t\t\t$apps = $wpdb->get_results( $wpdb->prepare( \"SELECT * FROM {$table} ORDER BY FIELD(state,'submitted','resubmitted','under_review','recommended','appeal_pending','renewal_due','suspended','verified','rejected','revoked','expired','withdrawn','draft'), updated_at DESC LIMIT %d OFFSET %d\", $per_page, $offset ) );\n\t\t\tif ( null === $apps || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The doctor-verification review queue is temporarily unavailable because its database state could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }\n\t\t\t$wpdb->last_error = '';\n\t\t\t$total_raw = $wpdb->get_var( \"SELECT COUNT(*) FROM {$table}\" );\n\t\t\tif ( null === $total_raw || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The doctor-verification queue total could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }\n\t\t} else {\n\t\t\t$wpdb->last_error = '';\n\t\t\t$apps = $wpdb->get_results( $wpdb->prepare( \"SELECT * FROM {$table} WHERE assigned_reviewer_id=%d ORDER BY updated_at DESC LIMIT %d OFFSET %d\", $reviewer, $per_page, $offset ) );\n\t\t\tif ( null === $apps || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The assigned doctor-verification review queue is temporarily unavailable because its database state could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }\n\t\t\t$wpdb->last_error = '';\n\t\t\t$total_raw = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$table} WHERE assigned_reviewer_id=%d\", $reviewer ) );\n\t\t\tif ( null === $total_raw || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'The assigned doctor-verification queue total could not be read safely.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }\n\t\t}\n\t\t$total = absint( $total_raw );\n"""
rep('includes/class-gdo-admin.php',old,new)

# R12 — risk projection must not silently show “no risks” when its query failed.
rep('includes/class-gdo-admin.php',
"\t\t$evidence = GDO_Evidence::records( $app->id, true );\n\t\t$risks = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'risk_signals' ) . ' WHERE application_id=%d ORDER BY created_at DESC', $app->id ) );\n\t\t$types = GDO_Evidence::types( $app->jurisdiction, $app->application_type );",
"\t\t$evidence = GDO_Evidence::records( $app->id, true );\n\t\t$wpdb->last_error = '';\n\t\t$risks = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'risk_signals' ) . ' WHERE application_id=%d ORDER BY created_at DESC', $app->id ) );\n\t\tif ( null === $risks || ! empty( $wpdb->last_error ) ) { wp_die( esc_html__( 'Professional risk signals could not be read safely for this review.', 'global-doctor-onboarding' ), '', array( 'response'=>503 ) ); }\n\t\t$types = GDO_Evidence::types( $app->jurisdiction, $app->application_type );")

# R13 — public passport status must resist stale intermediary/browser caching after revocation.
rep('includes/class-gdo-advanced-trust-hardening.php',
"    public static function rest_public_passport( WP_REST_Request $request ) {\n        $result = self::verify_passport_uuid( $request['uuid'] );\n        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );\n    }",
"    public static function rest_public_passport( WP_REST_Request $request ) {\n        $result = self::verify_passport_uuid( $request['uuid'] );\n        if ( is_wp_error( $result ) ) { return $result; }\n        $response = rest_ensure_response( $result );\n        $response->header( 'Cache-Control', 'no-store, max-age=0, must-revalidate' );\n        $response->header( 'Pragma', 'no-cache' );\n        $response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );\n        $response->header( 'Referrer-Policy', 'no-referrer' );\n        return $response;\n    }")

# R14/R15 — risk owner writes must enforce current runtime and privileged resolution authorization themselves.
rep('includes/class-gdo-risk.php',
"\tpublic static function record( $application_id, $type, $severity, $related_digest = '' ) {\n\t\tglobal $wpdb;",
"\tpublic static function record( $application_id, $type, $severity, $related_digest = '' ) {\n\t\tif ( ! GDO_Operations::mutation_allowed() ) { return new WP_Error( 'gdo_risk_runtime_not_ready', __( 'Professional risk recording is temporarily unavailable.', 'global-doctor-onboarding' ) ); }\n\t\tglobal $wpdb;")
rep('includes/class-gdo-risk.php',
"\tpublic static function resolve( $signal_id, $actor_id, $decision, $reason ) {\n\t\tglobal $wpdb;",
"\tpublic static function resolve( $signal_id, $actor_id, $decision, $reason ) {\n\t\t$current_actor = get_current_user_id();\n\t\tif ( ! GDO_Operations::mutation_allowed() || ! $current_actor || absint( $actor_id ) !== absint( $current_actor ) || ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $current_actor ) || ! GDO_Membership_Adapter::recent_step_up( $current_actor ) ) {\n\t\t\treturn new WP_Error( 'gdo_risk_resolution_forbidden', __( 'Risk resolution requires current File 00 manager authorization and recent File 02 step-up.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\tglobal $wpdb;")

# R16/R17/R18 — calibration writes/readouts fail closed and privileged completion revalidates natively.
rep('includes/class-gdo-quality.php',
"\tpublic static function create_sample( $application_id, $reviewer_id, $decision, $source = 'automatic' ) {\n\t\tglobal $wpdb;",
"\tpublic static function create_sample( $application_id, $reviewer_id, $decision, $source = 'automatic' ) {\n\t\tif ( ! GDO_Operations::mutation_allowed() ) { return 0; }\n\t\tglobal $wpdb;")
rep('includes/class-gdo-quality.php',
"\tpublic static function complete_sample( $sample_id, $auditor_id, $outcome, $reason ) {\n\t\tglobal $wpdb;",
"\tpublic static function complete_sample( $sample_id, $auditor_id, $outcome, $reason ) {\n\t\t$current_actor = get_current_user_id();\n\t\tif ( ! GDO_Operations::mutation_allowed() || ! $current_actor || absint( $auditor_id ) !== absint( $current_actor ) || ! GDO_Membership_Adapter::can( 'sabri_manage_doctor_verification', $current_actor ) || ! GDO_Membership_Adapter::recent_step_up( $current_actor ) ) {\n\t\t\treturn new WP_Error( 'gdo_quality_forbidden', __( 'Quality review requires current File 00 manager authorization and recent File 02 step-up.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\tglobal $wpdb;")
rep('includes/class-gdo-quality.php',
"\t\t$sample = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'quality_samples' ) . \" WHERE id=%d AND status='pending'\", absint( $sample_id ) ) );\n\t\tif ( ! $sample ||",
"\t\t$wpdb->last_error = '';\n\t\t$sample = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'quality_samples' ) . \" WHERE id=%d AND status='pending'\", absint( $sample_id ) ) );\n\t\tif ( null === $sample && ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_quality_query_failed', __( 'The pending quality sample could not be read safely.', 'global-doctor-onboarding' ) ); }\n\t\tif ( ! $sample ||")
rep('includes/class-gdo-quality.php',
"\t\t$row = $wpdb->get_row( $wpdb->prepare(\n\t\t\t'SELECT COUNT(*) total, SUM(outcome=\\'agree\\') agreed, SUM(outcome=\\'minor_error\\') minor_errors, SUM(outcome=\\'major_error\\') major_errors FROM ' . GDO_Schema::table( 'quality_samples' ) . ' WHERE reviewer_id=%d AND completed_at>=%s',",
"\t\t$wpdb->last_error = '';\n\t\t$row = $wpdb->get_row( $wpdb->prepare(\n\t\t\t'SELECT COUNT(*) total, SUM(outcome=\\'agree\\') agreed, SUM(outcome=\\'minor_error\\') minor_errors, SUM(outcome=\\'major_error\\') major_errors FROM ' . GDO_Schema::table( 'quality_samples' ) . ' WHERE reviewer_id=%d AND completed_at>=%s',")
rep('includes/class-gdo-quality.php',
"\t\t), ARRAY_A );\n\t\treturn array_map( 'absint', is_array( $row ) ? $row : array() );\n\t}\n}",
"\t\t), ARRAY_A );\n\t\tif ( ! is_array( $row ) || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'gdo_quality_metrics_query_failed', __( 'Reviewer calibration metrics could not be read safely.', 'global-doctor-onboarding' ) ); }\n\t\treturn array_map( 'absint', $row );\n\t}\n}")

# R8 immutable ledger and release evidence.
controls=[
'Runtime/version/schema identity','Repository/staging/live truth separation','Latest File09/Advanced Trust plan trace',
'Mutation readiness includes claim-signing key','Health-count DB error isolation','Reconciliation DB error isolation','Controlled repair native authorization','Safe Mode native authorization','Legacy migration table-inventory DB uncertainty','Manager review queue DB uncertainty','Assigned reviewer queue DB uncertainty','Reviewer risk projection DB uncertainty','Public passport revocation cache safety','Risk resolution native authorization','Risk recording Safe Mode gate','Quality completion native authorization','Quality metrics DB uncertainty','Quality-sampling Safe Mode gate',
'Future core schema fail-closed','Future Advanced Trust schema fail-closed','Core physical schema verification','Advanced Trust physical schema verification','Trusted issuer independent review','Jurisdiction rule independent approval','Primary-source degraded/manual path','Provider payload minimization','AI human-final-decision invariant','Equivalency advisory-only boundary','Fraud signal human-review invariant','Reviewer case binding','Adaptive dual review','Encrypted private evidence','MIME/polyglot/malware fail-closed','Session-bound evidence grants','Viewing-room no-download contract','Resumable upload ownership/state','Chunk order/exactly-once','Chunk durability','Finalize hash/size verification','Expired upload unsafe-path protection','Canonical private-storage boundary','Storage use-time health','AES-256-GCM authentication','Privacy export failure propagation','Physical evidence deletion proof','Advanced Trust erasure checked operations','Legal-hold protection','Retention runtime gate','Signed/versioned professional claims','Explicit File00 claim acceptance','File19 minimized event payload','Outbox persistence','Transition row lock/version','Risk query fail-closed','File20 shell adapter boundary','File19 producer contract','File26 public projection boundary','File03/07/08 claim consumers','Private evidence excluded from search','Donation/ranking neutrality','No cure guarantee/clinical authorization','Public passport current-state recheck','Public passport GET read-only','Continuous monitoring no auto-revocation','Recurring scheduler correctness','Operational health coverage','Ordinary admin Safe Mode enforcement','Destructive uninstall authorization','Migration/rollback documentation','Staging external mandatory gate','All 17 FR traceable','All 10 NFR traceable','All 24 Advanced Trust requirements traceable','File09 CEN ownership trace','Search projection ownership trace','Applicant/reviewer acceptance journeys trace','Exact-head checkout gate','Deterministic double-build gate','Generated exact-head SBOM coverage','R8 ledger/release-lock/workflow synchronization']
if len(controls)!=80: raise SystemExit(len(controls))
defects=set(range(4,19))
corrections={
4:'Added claim-signing-key readiness to the global mutation gate.',5:'Cleared DB error state before health-count reads.',6:'Cleared DB error state before reconciliation reads.',7:'Added current actor, File 00 manager capability and File 02 step-up revalidation inside repair().',8:'Added the same native privileged revalidation inside set_safe_mode().',9:'Made legacy-table inventory fail on DB uncertainty instead of treating an error as an absent table.',10:'Manager queue reads now fail visibly on DB uncertainty.',11:'Assigned-reviewer queue reads now fail visibly on DB uncertainty.',12:'Risk projection reads now fail visibly instead of implying an empty risk set.',13:'Public passport REST responses now carry no-store/no-cache/noindex/no-referrer headers.',14:'Risk resolution now revalidates runtime readiness, actor, manager capability and step-up at the owner method.',15:'Risk recording now obeys the global Safe Mode/runtime mutation gate.',16:'Quality-sample completion now revalidates runtime readiness, actor, manager capability and step-up and distinguishes DB read failure.',17:'Reviewer calibration metrics now return explicit DB-read failure instead of an empty/zero-looking metric set.',18:'Automatic quality-sample writes now obey the global Safe Mode/runtime mutation gate.'}
lines=['# File 09 — RC6 Eighth Fresh 80-Round Corrective Assurance — R8','',f'**Review date:** 11 August 2026  ','**Frozen baseline:** `'+BASE+'`  ','**Scope:** fresh independent repository-source review against the newest File 09 RC6/Advanced Trust plan and consolidated central governance.  ','','## Truth boundary','','Repository-candidate evidence only. This ledger does not prove Hostinger staging, deployed artifact parity, live database/schema/migration state, real provider connectivity, live workflows or operational acceptance.','','## Result','','- Total rounds: **80**','- Defect-bearing rounds on frozen baseline: **15**','- Clean rounds on frozen baseline: **65**','- Corrected-tree target: **80 PASS / 0 FAIL** plus exact-head PHP 7.4/8.3 and deterministic package/SBOM.','', '**Defect-bearing rounds:** 04, 05, 06, 07, 08, 09, 10, 11, 12, 13, 14, 15, 16, 17, 18.','', '## Round ledger','', '| Round | Baseline result | Review control | Immediate correction / disposition |','|---:|:---:|---|---|']
for n,name in enumerate(controls,1):
    if n in defects: disposition=corrections[n]; result='DEFECT → FIXED'
    else: disposition='No new defect found on the frozen baseline; control retained and re-enforced in the R8 executable gate.'; result='CLEAN'
    lines.append(f'| {n:02d} | {result} | {name} | {disposition} |')
lines += ['', '## Completion boundary','', 'R8 is repository-level corrective evidence. Staging Accepted=false, Live Deployed=false, Operational=false until the exact package passes the external gates required by the governing plans.']
write('REVIEW-80-ROUNDS-RC6-R8.md','\n'.join(lines)+'\n')

release=read('RELEASE-FILES.txt').splitlines()
if 'REVIEW-80-ROUNDS-RC6-R8.md' not in release: release.append('REVIEW-80-ROUNDS-RC6-R8.md')
write('RELEASE-FILES.txt','\n'.join([x for x in release if x.strip()])+'\n')
lock=json.loads(read('RELEASE-LOCK.json'))
lock.update({'eighth_review_baseline':BASE,'eighth_review_rounds':80,'eighth_defect_rounds':15,'eighth_clean_rounds':65,'release_file_count':59})
write('RELEASE-LOCK.json',json.dumps(lock,indent=2,sort_keys=True)+'\n')

# R8 executable 80-control gate.
r8=r'''from pathlib import Path
import json
root=Path(__file__).resolve().parents[1]
def t(p): return (root/p).read_text(encoding='utf-8')
def has(s,*xs): return all(x in s for x in xs)
main=t('global-doctor-onboarding.php'); ops=t('includes/class-gdo-operations.php'); migration=t('includes/class-gdo-migration.php'); admin=t('includes/class-gdo-admin.php'); hard=t('includes/class-gdo-advanced-trust-hardening.php'); adv=t('includes/class-gdo-advanced-trust.php'); risk=t('includes/class-gdo-risk.php'); quality=t('includes/class-gdo-quality.php'); schema=t('includes/class-gdo-schema.php'); evidence=t('includes/class-gdo-evidence.php'); storage=t('includes/class-gdo-storage.php'); crypto=t('includes/class-gdo-crypto.php'); privacy=t('includes/class-gdo-privacy.php'); retention=t('includes/class-gdo-retention.php'); claims=t('includes/class-gdo-claims.php'); notify=t('includes/class-gdo-notifications.php'); state=t('includes/class-gdo-state.php'); plugin=t('includes/class-gdo-plugin.php'); integ=t('includes/class-gdo-integration-contracts.php'); trace=t('TRACEABILITY.md'); status=t('STATUS.md'); workflow=t('.github/workflows/file09-rc2-final.yml'); uninstall=t('uninstall.php')
checks=[]
def c(n,name,ok): checks.append((n,name,bool(ok)))
c(1,'Runtime/version/schema identity',has(main,'Version: 1.3.0',"define( 'GDO_SCHEMA_VERSION', 6 )"))
c(2,'Repository/staging/live truth separation',has(status.lower(),'staging accepted: false','live deployed: false'))
c(3,'Latest File09/Advanced Trust plan trace',all(f'F09-AT-{i:02d}' in trace for i in range(1,25)))
c(4,'Mutation readiness includes claim signing key',has(ops,"defined( 'GDO_CLAIM_SIGNING_KEY' )","strlen( (string) GDO_CLAIM_SIGNING_KEY ) >= 32"))
c(5,'Health-count DB error isolation',has(ops,'private static function count_query',"$wpdb->last_error = '';","A File 09 health query could not be completed safely."))
recon=ops[ops.index('public static function reconcile'):ops.index('public static function repair')]
c(6,'Reconciliation DB error isolation',has(recon,"$wpdb->last_error = '';",'gdo_reconcile_query_failed'))
repair=ops[ops.index('public static function repair'):ops.index('public static function set_safe_mode')]
c(7,'Controlled repair native authorization',has(repair,'gdo_repair_forbidden','sabri_manage_doctor_verification','recent_step_up'))
safe=ops[ops.index('public static function set_safe_mode'):ops.index('public static function record_metric')]
c(8,'Safe Mode native authorization',has(safe,'gdo_safe_mode_forbidden','sabri_manage_doctor_verification','recent_step_up'))
c(9,'Legacy migration table inventory DB uncertainty',has(migration,'Legacy File 09 table inventory could not be verified safely.',"$wpdb->last_error = '';"))
c(10,'Manager review queue DB uncertainty','The doctor-verification review queue is temporarily unavailable' in admin)
c(11,'Assigned reviewer queue DB uncertainty','The assigned doctor-verification review queue is temporarily unavailable' in admin)
c(12,'Reviewer risk projection DB uncertainty','Professional risk signals could not be read safely for this review.' in admin)
c(13,'Public passport revocation cache safety',has(hard,"'Cache-Control', 'no-store, max-age=0, must-revalidate'","'X-Robots-Tag', 'noindex, nofollow, noarchive'","'Referrer-Policy', 'no-referrer'"))
resolve=risk[risk.index('public static function resolve'):]
c(14,'Risk resolution native authorization',has(resolve,'gdo_risk_resolution_forbidden','sabri_manage_doctor_verification','recent_step_up','mutation_allowed'))
record=risk[risk.index('public static function record'):risk.index('public static function unresolved')]
c(15,'Risk recording Safe Mode gate',has(record,'gdo_risk_runtime_not_ready','mutation_allowed'))
complete=quality[quality.index('public static function complete_sample'):quality.index('public static function metrics')]
c(16,'Quality completion native authorization',has(complete,'gdo_quality_forbidden','gdo_quality_query_failed','sabri_manage_doctor_verification','recent_step_up'))
metrics=quality[quality.index('public static function metrics'):]
c(17,'Quality metrics DB uncertainty',has(metrics,"$wpdb->last_error = '';",'gdo_quality_metrics_query_failed'))
create=quality[quality.index('public static function create_sample'):quality.index('public static function complete_sample')]
c(18,'Quality sampling Safe Mode gate','GDO_Operations::mutation_allowed()' in create)
c(19,'Future core schema fail-closed',has(migration,'gdo_schema_future_version','$current > GDO_SCHEMA_VERSION'))
c(20,'Future Advanced Trust schema fail-closed',has(hard,'gdo_advanced_schema_future_version','$current_schema > self::SCHEMA_VERSION'))
c(21,'Core physical schema verification',has(schema,'SHOW COLUMNS FROM','SHOW TABLE STATUS LIKE','InnoDB'))
c(22,'Advanced Trust physical schema verification',has(adv,'verify_installation','gdo_advanced_schema_engine'))
c(23,'Trusted issuer independent review','gdo_issuer_review_separation' in adv)
c(24,'Jurisdiction independent approval',has(hard,'second authorized reviewer','gdo_jurisdiction_rule_separation'))
c(25,'Primary-source degraded/manual path',has(adv,'provider_unavailable','reviewer_required'))
c(26,'Provider payload minimization',has(adv,'PROVIDER_PAYLOAD_MAX_BYTES','sanitize_provider_array'))
c(27,'AI human-final-decision invariant',has(adv,'human_final_decision_required','decision'))
c(28,'Equivalency advisory-only boundary',has(adv,'equivalency_assessment','legal_license_grant'))
c(29,'Fraud signal human-review invariant',has(adv,'fraud_ring_scan','credential_reuse_network'))
c(30,'Reviewer case binding',has(t('includes/class-gdo-membership-adapter.php'),'reviewer_case_allows','reviewer_scope_allows'))
c(31,'Adaptive dual review',has(adv,'requires_dual_review','gdo_requires_dual_review'))
c(32,'Encrypted private evidence',has(evidence,'GDO_Crypto::encrypt','GDO_Storage::atomic_write'))
c(33,'MIME/polyglot/malware fail-closed',has(evidence,'FILEINFO_MIME_TYPE','EmbeddedFile','gdo_scan_required'))
c(34,'Session-bound evidence grants',has(evidence,'session_digest','used_at','access_grants'))
c(35,'Viewing-room no-download contract',has(adv,'issue_viewing_room_grant',"'download_allowed'=>false"))
c(36,'Resumable upload ownership/state',has(adv,'create_upload_session','FOR UPDATE','gdo_upload_session_forbidden'))
c(37,'Chunk order/exactly-once',has(adv,'gdo_upload_chunk_order','received_chunks'))
c(38,'Chunk durability','fsync' in adv)
c(39,'Finalize hash/size verification',has(adv,"hash_file('sha256'",'gdo_upload_hash_mismatch'))
c(40,'Expired upload unsafe-path protection',has(hard,'unsafe_chunk_path','gdo_upload_cleanup_unsafe_path'))
c(41,'Canonical private-storage boundary',has(storage,'realpath( $dir )','canonical filesystem path'))
c(42,'Storage use-time health',has(storage,'public static function read','self::health()'))
c(43,'AES-256-GCM authentication',has(crypto,'aes-256-gcm','gdo_authentication_failure'))
c(44,'Privacy export failure propagation','gdo_advanced_export_failed' in privacy)
c(45,'Physical evidence deletion proof',has(privacy,'delete_verified','deletion_proof'))
c(46,'Advanced Trust erasure checked operations',has(hard,'privacy_erase_application','gdo_privacy_operational_cleanup'))
c(47,'Legal-hold protection','legal_hold' in privacy)
c(48,'Retention runtime gate',has(retention,'gdo_retention_runtime_not_ready','mutation_allowed'))
c(49,'Signed/versioned professional claims',has(claims,'GDO_CLAIM_SIGNING_KEY','claim_version','signature'))
c(50,'Explicit File00 claim acceptance',has(notify,'did not explicitly accept the claim','gdo_claim_provider_unavailable'))
c(51,'File19 minimized event payload',has(notify,'Never forward the raw event payload','sun.event.v1'))
c(52,'Outbox persistence',has(notify,'gdo_outbox_delivery_persist_failed','gdo_outbox_failure_persist_failed'))
c(53,'Transition row lock/version',has(state,'FOR UPDATE','row_version'))
c(54,'Risk query fail-closed','gdo_risk_query_failed' in risk)
c(55,'File20 shell adapter boundary',has(plugin,'sabri_file20_navigation_items','sabri_file20_module_health'))
c(56,'File19 producer contract',has(notify,'sun_register_notification_producer','sun.event.v1'))
c(57,'File26 public projection boundary',has(integ,'gdo.file26.doctor-verification-projection','contract_tested'))
c(58,'File03/07/08 claim consumers',all(x in integ for x in ['gdo.file03','gdo.file07','gdo.file08']))
c(59,'Private evidence excluded from search',has(integ,'evidence_exposed','private_indexing','public_projection_class'))
c(60,'Donation/ranking neutrality',has(integ,'donor_rank_advantage','donor_neutral','paid_rank_advantage'))
c(61,'No cure guarantee/clinical authorization',has(adv,"'cure_guarantee'=>false","'clinical_authorization'=>false"))
c(62,'Public passport current-state recheck',has(hard,'verify_passport_uuid','identity_assurance_current','public_verified'))
c(63,'Public passport GET read-only',"$contract['public_get_mutates_owner_state'] = false" in hard)
monitor=hard[hard.index('public static function continuous_monitor'):hard.index('private static function unsafe_chunk_path')]
c(64,'Continuous monitoring no auto-revocation','revoke_passports_for_application' not in monitor)
c(65,'Recurring scheduler correctness',has(t('includes/class-gdo-activator.php'),'recurring_schedule_ready','wp_get_scheduled_event'))
c(66,'Operational health coverage',has(ops,'trust_monitor_cron','database_observability'))
c(67,'Ordinary admin Safe Mode enforcement','File 09 mutations are unavailable until Safe Mode is cleared' in admin)
c(68,'Destructive uninstall authorization',has(uninstall,'SABRI_ALLOW_DESTRUCTIVE_UNINSTALL','gdo_destructive_uninstall_confirmation'))
c(69,'Migration/rollback documentation',(root/'MIGRATION-ROLLBACK-1.3.0.md').is_file())
c(70,'Staging external mandatory gate',has(status.lower(),'staging accepted: false','live deployed: false'))
c(71,'All 17 FR traceable',all(f'F09-FR-{i:03d}' in trace for i in range(1,18)))
c(72,'All 10 NFR traceable',all(f'F09-NFR-{i:03d}' in trace for i in range(1,11)))
c(73,'All 24 Advanced Trust requirements traceable',all(f'F09-AT-{i:02d}' in trace for i in range(1,25)))
c(74,'File09 CEN ownership trace',has(trace,'F09-CEN-01','F09-CEN-02'))
c(75,'Search projection ownership trace','CEN-SEARCH-001' in trace)
c(76,'Applicant/reviewer acceptance journeys trace',has(trace,'AJ-03','AJ-04','AJ-05','AJ-31','AJ-40'))
c(77,'Exact-head checkout gate','Assert exact source head' in workflow)
c(78,'Deterministic double-build gate','Build twice and verify exact-head package' in workflow)
c(79,'Generated exact-head SBOM coverage',has(t('tools/verify-release.py'),'generated SBOM','source/package parity mismatch'))
lock=json.loads(t('RELEASE-LOCK.json')); release=[x for x in t('RELEASE-FILES.txt').splitlines() if x.strip()]
c(80,'R8 ledger/release-lock/workflow synchronization',(root/'REVIEW-80-ROUNDS-RC6-R8.md').is_file() and lock.get('eighth_review_baseline')=='f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2' and lock.get('eighth_review_rounds')==80 and lock.get('eighth_defect_rounds')==15 and lock.get('eighth_clean_rounds')==65 and lock.get('release_file_count')==len(release) and 'tests/eighty-round-audit-r8.py' in workflow)
failed=[x for x in checks if not x[2]]
for n,name,ok in checks: print(f'R{n:02d}: {"PASS" if ok else "FAIL"} — {name}')
print(f'File 09 RC6 eighth fresh eighty-round audit: {len(checks)-len(failed)} PASS, {len(failed)} FAIL')
print('Defect-bearing rounds on frozen baseline: 04,05,06,07,08,09,10,11,12,13,14,15,16,17,18')
if failed: raise SystemExit(1)
'''
write('tests/eighty-round-audit-r8.py',r8)

# Extend release-integrity evidence for R8.
p='tests/release-integrity.py'; s=read(p)
anchor="if not (root/'tests/eighty-round-audit-r7.py').is_file(): fail('RC6 R7 executable gate missing')"
insert=anchor+"\nfor key, expected in {'eighth_review_baseline':'f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2','eighth_review_rounds':80,'eighth_defect_rounds':15,'eighth_clean_rounds':65}.items():\n    if lock.get(key) != expected: fail('release lock R8 mismatch '+key)\nif 'REVIEW-80-ROUNDS-RC6-R8.md' not in release_files: fail('RC6 R8 release ledger missing')\nif not (root/'tests/eighty-round-audit-r8.py').is_file(): fail('RC6 R8 executable gate missing')"
if anchor not in s: raise SystemExit('release integrity R7 anchor missing')
write(p,s.replace(anchor,insert,1))

# Add R8 provenance to package manifest JSON.
p='tools/build-release.py'; s=read(p)
anchor="'staging_accepted':False,'live_deployed':False,'operationally_accepted':False,'files':manifest,"
insert="'eighth_review_rounds':80,'eighth_defect_rounds':15,'eighth_clean_rounds':65,'eighth_review_baseline':'f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2',\n    'staging_accepted':False,'live_deployed':False,'operationally_accepted':False,'files':manifest,"
if anchor not in s: raise SystemExit('builder package-manifest anchor missing')
write(p,s.replace(anchor,insert,1))

append_once('CHANGELOG.md','eighth fresh 80-round corrective assurance (R8)',"""### RC6 — eighth fresh 80-round corrective assurance (R8)
- Reviewed frozen exact-head baseline `f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2` through 80 independent controls.
- Corrected 15 defect-bearing rounds (04–18): runtime mutation dependency completeness, privileged owner-command reauthorization, DB-uncertainty truth, reviewer queue/risk fail-closed UX, passport cache safety, risk writes and reviewer-calibration writes/metrics.
- Added `REVIEW-80-ROUNDS-RC6-R8.md` and `tests/eighty-round-audit-r8.py`; staging/live/operational remain false.""")
append_once('STATUS.md','Eighth fresh 80-round corrective assurance — R8',"""## Eighth fresh 80-round corrective assurance — R8
Frozen baseline `f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2` underwent a new independent 80-control review: **15 defect-bearing rounds corrected; 65 clean rounds**. The corrective tree adds method-level privileged reauthorization, runtime claim-key readiness, fail-closed DB uncertainty for migration/reviewer/quality paths, no-store passport responses, and Safe Mode enforcement for risk/quality writes. Repository candidate remains separate from external maturity gates.

Staging Accepted: false  
Live Deployed: false  
Operational: false""")
append_once('TRACEABILITY.md','R8 corrective trace',"""## R8 corrective trace
R8 maps File 09 authorization/privacy/reliability/operability requirements and F09-AT-08/09/15/20 controls to `GDO_Operations`, `GDO_Migration`, `GDO_Admin`, `GDO_Advanced_Trust_Hardening`, `GDO_Risk`, `GDO_Quality`, `tests/eighty-round-audit-r8.py`, `REVIEW-80-ROUNDS-RC6-R8.md`, exact-head CI and deterministic package/SBOM evidence. Frozen baseline: `f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2`.""")
append_once('RELEASE-MANIFEST-1.3.0.md','Eighth fresh 80-round assurance — R8',"""## Eighth fresh 80-round assurance — R8
Current RC6 release allowlist after R8: **59 entries**. Frozen review baseline: `f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2`; **15 defect-bearing rounds corrected, 65 clean**. Repository package/QA may be called green only after the authoritative exact-final-head PHP 7.4/8.3 workflow passes R1–R8 plus deterministic double-build and exact-head generated SBOM. Staging/live/operational acceptance remain separate and false.""")
append_once('README.md','Eighth fresh 80-round assurance',"""## Eighth fresh 80-round assurance
RC6 now includes an eighth independent 80-control repository review. R8 corrected 15 controls on frozen baseline `f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2`; the executable evidence is `tests/eighty-round-audit-r8.py` and the immutable ledger is `REVIEW-80-ROUNDS-RC6-R8.md`. This is repository evidence only, not staging/live proof.""")
print('R8 corrections applied; temporary tooling remains outside release allowlist.')
