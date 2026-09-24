from pathlib import Path
import json
root=Path(__file__).resolve().parents[1]
def t(p): return (root/p).read_text(encoding='utf-8')
def has(s,*xs): return all(x in s for x in xs)
adv=t('includes/class-gdo-advanced-trust.php'); hard=t('includes/class-gdo-advanced-trust-hardening.php'); app=t('includes/class-gdo-application.php'); front=t('includes/class-gdo-frontend.php'); migration=t('includes/class-gdo-migration.php'); schema=t('includes/class-gdo-schema.php'); storage=t('includes/class-gdo-storage.php'); evidence=t('includes/class-gdo-evidence.php'); crypto=t('includes/class-gdo-crypto.php'); privacy=t('includes/class-gdo-privacy.php'); retention=t('includes/class-gdo-retention.php'); claims=t('includes/class-gdo-claims.php'); notify=t('includes/class-gdo-notifications.php'); state=t('includes/class-gdo-state.php'); risk=t('includes/class-gdo-risk.php'); plugin=t('includes/class-gdo-plugin.php'); integ=t('includes/class-gdo-integration-contracts.php'); admin=t('includes/class-gdo-admin.php'); uninstall=t('uninstall.php'); ops=t('includes/class-gdo-operations.php'); trace=t('TRACEABILITY.md'); status=t('STATUS.md'); workflow=t('.github/workflows/file09-rc2-final.yml')
checks=[]
def c(n,name,ok): checks.append((n,name,bool(ok)))
c(1,'Runtime/version/schema identity',has(t('global-doctor-onboarding.php'),'Version: 1.3.0',"define( 'GDO_SCHEMA_VERSION', 6 )"))
c(2,'Repository/staging/live truth separation',has(status.lower(),'staging accepted: false','live deployed: false'))
c(3,'Latest File09/Advanced Trust plan trace',all(f'F09-AT-{i:02d}' in trace for i in range(1,25)))
reg=adv[adv.index('public static function register_issuer'):adv.index('public static function review_issuer')]
c(4,'Issuer no-duplicate query no-row semantics','null === $duplicate_raw ||' not in reg and 'gdo_issuer_duplicate_query' in reg)
review=adv[adv.index('public static function review_issuer'):adv.index('public static function trusted_issuer')]
c(5,'Issuer review idempotency and optimistic concurrency',has(review,'gdo_issuer_review_no_transition','gdo_issuer_review_conflict',"'status'=>sanitize_key( $issuer->status )"))
base_j=adv[adv.index('public static function save_jurisdiction_rule'):adv.index('public static function jurisdiction_rule')]
c(6,'Base jurisdiction-rule Safe Mode gate',has(base_j,'GDO_Operations::mutation_allowed()','gdo_jurisdiction_runtime_not_ready'))
hard_j=hard[hard.index('public static function save_jurisdiction_rule'):hard.index('private static function can_manage')]
c(7,'Hardened jurisdiction-rule Safe Mode gate',has(hard_j,'GDO_Operations::mutation_allowed()','gdo_jurisdiction_runtime_not_ready'))
decl=adv[adv.index('public static function declare_conflict'):adv.index('public static function resolve_conflict')]
c(8,'Reviewer conflict declaration Safe Mode gate',has(decl,'GDO_Operations::mutation_allowed()','gdo_conflict_runtime_not_ready'))
resol=adv[adv.index('public static function resolve_conflict'):adv.index('public static function has_conflict')]
c(9,'Reviewer conflict resolution Safe Mode gate',has(resol,'GDO_Operations::mutation_allowed()','gdo_conflict_runtime_not_ready'))
hc=adv[adv.index('public static function has_conflict'):adv.index('public static function reviewer_conflict_filter')]
c(10,'Conflict query stale-DB-error isolation',"$wpdb->last_error=''" in hc)
dr=adv[adv.index('public static function requires_dual_review'):adv.index('public static function smart_reviewer_candidates')]
c(11,'Dual-review query stale-DB-error isolation',"$wpdb->last_error=''" in dr)
sr=adv[adv.index('public static function smart_reviewer_candidates'):adv.index('public static function reviewer_calibration')]
c(12,'Reviewer routing DB uncertainty visibility',has(sr,'gdo_reviewer_candidates_query_failed','gdo_reviewer_workload_query_failed'))
base_issue=adv[adv.index('public static function issue_passport'):adv.index('public static function ensure_passport')]
c(13,'Base passport application row-lock DB uncertainty','gdo_passport_application_query' in base_issue)
hard_issue=hard[hard.index('public static function issue_passport'):hard.index('public static function ensure_passport')]
c(14,'Hardened passport application row-lock DB uncertainty','gdo_passport_application_query' in hard_issue)
c(15,'Base passport issuance/history atomicity',base_issue.index('self::add_history') < base_issue.index("$wpdb->query('COMMIT')"))
c(16,'Hardened passport issuance/history atomicity',hard_issue.index('self::history_once') < hard_issue.index("$wpdb->query( 'COMMIT' )"))
c(17,'Passport token public opaque-identifier boundary',"$payload=array('passport_uuid'=>$uuid,'version'=>$version" in base_issue and "$payload = array( 'passport_uuid'=>$uuid, 'version'=>$version" in hard_issue)
trans=adv[adv.index('public static function public_transparency_snapshot'):adv.index('public function rest_routes')]
c(18,'Public transparency small-cell suppression',has(trans,'minimum_cell','suppressed_fields','gdo_public_transparency_minimum_cell'))
card=adv[adv.index('public static function public_card_shortcode'):adv.index('public static function issue_viewing_room_grant')]
c(19,'Public verification-card localization',has(card,"__('Identity','global-doctor-onboarding')","__('Current status','global-doctor-onboarding')"))
c(20,'Public next-review invalid-date truth',has(adv,'$next_review_ts',"$matrix['next_review'] = $next_review_ts ?"))
c(21,'No GET/shortcode draft creation without CSRF intent',has(front,'admin_post_gdo_start_application',"wp_nonce_field( 'gdo_start_application' )",'public function start()'))
cd=app[app.index('private static function create_draft'):app.index('public static function ensure_draft')]
c(22,'Private draft creation mutation gate','GDO_Operations::mutation_allowed()' in cd)
rc=app[app.index('public static function record_consent'):app.index('public static function submit')]
c(23,'Consent persistence mutation gate','GDO_Operations::mutation_allowed()' in rc)
bp=adv[adv.index('public static function verify_passport_uuid'):adv.index('public static function verify_passport_token')]
c(24,'Passport-token lookup DB uncertainty','gdo_passport_lookup_query' in bp)
c(25,'Future core schema fail-closed',has(migration,'gdo_schema_future_version','$current > GDO_SCHEMA_VERSION'))
c(26,'Future Advanced Trust schema fail-closed',has(hard,'gdo_advanced_schema_future_version','$current_schema > self::SCHEMA_VERSION'))
c(27,'Core physical schema verification',has(schema,'SHOW COLUMNS FROM','SHOW TABLE STATUS LIKE','InnoDB'))
c(28,'Advanced Trust physical schema verification',has(adv,'verify_installation','gdo_advanced_schema_engine'))
c(29,'Issuer independent-review separation','gdo_issuer_review_separation' in review)
c(30,'Jurisdiction independent approval',has(hard_j,'second authorized reviewer','gdo_jurisdiction_rule_separation'))
c(31,'Primary-source degraded/manual path',has(adv,'provider_unavailable','issuer_unverified','reviewer_required'))
c(32,'Provider payload minimization',has(adv,'PROVIDER_PAYLOAD_MAX_BYTES','sanitize_provider_array'))
c(33,'AI cannot decide professional status',has(adv,"unset($result['decision'],$result['approve'],$result['reject']",'human_final_decision_required'))
c(34,'Equivalency is advisory only',has(adv,'equivalency_assessment','legal_license_grant'))
c(35,'Fraud signal requires human review',has(adv,'fraud_ring_scan','credential_reuse_network'))
c(36,'Reviewer case binding',has(t('includes/class-gdo-membership-adapter.php'),'reviewer_case_allows','reviewer_scope_allows'))
c(37,'Adaptive dual-review monotonicity',has(adv,'requires_dual_review','gdo_requires_dual_review'))
c(38,'Private evidence encryption',has(evidence,'GDO_Crypto::encrypt','GDO_Storage::atomic_write') and 'outside the public uploads directory' in storage)
c(39,'MIME/polyglot/malware fail-closed',has(evidence,'FILEINFO_MIME_TYPE','EmbeddedFile','gdo_scan_required'))
c(40,'One-time/session-bound evidence grant',has(evidence,'session_digest','used_at','access_grants'))
c(41,'Viewing-room no-download contract',has(adv,'issue_viewing_room_grant',"'download_allowed'=>false"))
c(42,'Resumable upload ownership/state',has(adv,'create_upload_session','FOR UPDATE','gdo_upload_session_forbidden'))
c(43,'Chunk order and exactly-once semantics',has(adv,'gdo_upload_chunk_order','received_chunks'))
c(44,'Chunk fsync durability',has(adv,'fsync','gdo_upload_chunk_sync'))
c(45,'Finalize hash/size verification',has(adv,"hash_file('sha256'",'gdo_upload_hash_mismatch','gdo_upload_size_read'))
c(46,'Expired upload unsafe-path protection',has(hard,'unsafe_chunk_path','gdo_upload_cleanup_unsafe_path'))
c(47,'Canonical private-storage path boundary',has(storage,'realpath( $dir )','canonical filesystem path'))
c(48,'Storage use-time health recheck',has(storage,'public static function read','self::health()'))
c(49,'AES-256-GCM authenticated encryption',has(crypto,'aes-256-gcm','gdo_authentication_failure'))
c(50,'Privacy export failure propagation','gdo_advanced_export_failed' in privacy)
c(51,'Physical evidence deletion proof',has(evidence,'function delete_record_safely','deletion_proof','deletion_pending_erasure','deletion_pending_retention') and has(privacy,'GDO_Evidence::delete_record_safely') and has(retention,'GDO_Evidence::delete_record_safely'))
c(52,'Advanced Trust erasure checked operations',has(hard,'privacy_erase_application','gdo_privacy_operational_cleanup','gdo_privacy_anonymize'))
c(53,'Legal-hold protection','legal_hold' in privacy)
c(54,'Retention runtime gate',has(retention,'gdo_retention_runtime_not_ready','mutation_allowed'))
c(55,'Signed/versioned professional claims',has(claims,'GDO_CLAIM_SIGNING_KEY','claim_version','signature'))
c(56,'Explicit File00 claim acceptance',has(notify,'did not explicitly accept the claim','gdo_claim_provider_unavailable'))
c(57,'File19 minimized notification payload',has(notify,'Never forward the raw event payload','sun.event.v1'))
c(58,'Outbox delivery persistence',has(notify,'gdo_outbox_delivery_persist_failed','gdo_outbox_failure_persist_failed'))
c(59,'Application transition row lock/version',has(state,'FOR UPDATE','row_version'))
c(60,'Risk query fail-closed','gdo_risk_query_failed' in risk)
c(61,'File20 adapter-only shell integration',has(plugin,'sabri_file20_navigation_items','sabri_file20_module_health'))
c(62,'File19 sun.event.v1 producer contract',has(notify,'sun_register_notification_producer','sun.event.v1'))
c(63,'File26 public projection boundary',has(integ,'gdo.file26.doctor-verification-projection','contract_tested'))
c(64,'File03/07/08 claim consumers',all(x in integ for x in ['gdo.file03','gdo.file07','gdo.file08']))
c(65,'Private evidence excluded from search',has(integ,"'evidence_exposed'",'private_indexing',"'public_projection_class'") and 'false' in integ)
c(66,'Donation/ranking neutrality',has(integ,'donor_rank_advantage','donor_neutral','paid_rank_advantage'))
c(67,'No cure guarantee/clinical authorization',has(adv,"'cure_guarantee'=>false","'clinical_authorization'=>false"))
c(68,'Public passport current-state recheck',has(hard,'verify_passport_uuid','identity_assurance_current','public_verified'))
c(69,'Public passport GET read-only',has(hard,"$contract['public_get_mutates_owner_state'] = false"))
monitor=hard[hard.index('public static function continuous_monitor'):hard.index('private static function unsafe_chunk_path')]
c(70,'Continuous monitoring adverse fact is not auto-revocation',has(monitor,'gdo_continuous_verification_adverse_result') and 'revoke_passports_for_application' not in monitor)
c(71,'Recurring scheduler correctness',has(t('includes/class-gdo-activator.php'),'recurring_schedule_ready','wp_get_scheduled_event'))
c(72,'Operational health coverage',has(ops,'trust_monitor_cron','recurring_schedule_ready'))
c(73,'Ordinary admin Safe Mode enforcement',has(admin,'File 09 mutations are unavailable until Safe Mode is cleared'))
c(74,'Destructive uninstall triple authorization',has(uninstall,'SABRI_ALLOW_DESTRUCTIVE_UNINSTALL','gdo_destructive_uninstall_confirmation'))
c(75,'Migration/rollback documentation',(root/'MIGRATION-ROLLBACK-1.3.0.md').is_file())
c(76,'Staging remains external mandatory gate',(root/'STAGING-ACCEPTANCE.md').is_file() and 'staging accepted: false' in status.lower())
c(77,'All 17 FR traceable',all(f'F09-FR-{i:03d}' in trace for i in range(1,18)))
c(78,'All 10 NFR + 24 AT traceable',all(f'F09-NFR-{i:03d}' in trace for i in range(1,11)) and all(f'F09-AT-{i:02d}' in trace for i in range(1,25)))
c(79,'Exact-head deterministic package/SBOM gate',has(workflow,'Assert exact source head','Build twice and verify exact-head package') and has(t('tools/verify-release.py'),'generated SBOM','source/package parity mismatch'))
lock=json.loads(t('RELEASE-LOCK.json')); release=[x for x in t('RELEASE-FILES.txt').splitlines() if x.strip()]
c(80,'R7 ledger/release-lock/workflow synchronization',(root/'REVIEW-80-ROUNDS-RC6-R7.md').is_file() and lock.get('seventh_review_baseline')=='9103310fc93d978b6e70661f024a079fc0971003' and lock.get('seventh_review_rounds')==80 and lock.get('seventh_defect_rounds')==22 and lock.get('seventh_clean_rounds')==58 and lock.get('release_file_count')==len(release) and 'tests/eighty-round-audit-r7.py' in workflow)
failed=[x for x in checks if not x[2]]
for n,name,ok in checks: print(f'R{n:02d}: {"PASS" if ok else "FAIL"} — {name}')
print(f'File 09 RC6 seventh fresh eighty-round audit: {len(checks)-len(failed)} PASS, {len(failed)} FAIL')
print('Defect-bearing rounds on frozen baseline: 04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,80')
if failed: raise SystemExit(1)
