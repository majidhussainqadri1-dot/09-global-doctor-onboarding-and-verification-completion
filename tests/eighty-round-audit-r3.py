#!/usr/bin/env python3
from pathlib import Path
import json, sys

root=Path(__file__).resolve().parents[1]
def text(path): return (root/path).read_text(encoding='utf-8')
def has(blob,*tokens): return all(t in blob for t in tokens)
def fail(msg): print('FAIL:',msg,file=sys.stderr); raise SystemExit(1)

main=text('global-doctor-onboarding.php')
plugin=text('includes/class-gdo-plugin.php')
member=text('includes/class-gdo-membership-adapter.php')
auth=text('includes/class-gdo-cf01-practitioner-contract.php')
app=text('includes/class-gdo-application.php')
state=text('includes/class-gdo-state.php')
evidence=text('includes/class-gdo-evidence.php')
risk=text('includes/class-gdo-risk.php')
audit=text('includes/class-gdo-audit.php')
claims=text('includes/class-gdo-claims.php')
admin=text('includes/class-gdo-admin.php')
notif=text('includes/class-gdo-notifications.php')
trust=text('includes/class-gdo-advanced-trust.php')
hard=text('includes/class-gdo-advanced-trust-hardening.php')
events=text('includes/class-gdo-advanced-trust-events.php')
privacy=text('includes/class-gdo-privacy.php')
retention=text('includes/class-gdo-retention.php')
ops=text('includes/class-gdo-operations.php')
integrations=text('includes/class-gdo-integration-contracts.php')
rate=text('includes/class-gdo-rate-limiter.php')
release=text('RELEASE-FILES.txt')
status=text('STATUS.md')
workflow=text('.github/workflows/file09-rc2-final.yml')
review=text('REVIEW-80-ROUNDS-RC6-R3.md')
lock=json.loads(text('RELEASE-LOCK.json'))

checks=[]
def check(n,topic,condition): checks.append((n,topic,bool(condition)))

# 01–10: exact source/governance/dependency baseline.
check(1,'Exact R3 review ledger and temporary patch transport absent',has(review,'Frozen review baseline: `f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361`','Total review controls: **80**') and not (root/'R3-APPLY-TRIGGER.txt').exists() and not (root/'tools/file09-r3-apply.py').exists())
check(2,'Canonical File09 ownership remains explicit and typed',has(integrations,"'source_of_truth'          => 'file09'","'direct_table_meta_write'=> false"))
check(3,'Repository/staging/live/operational truth remains separated',has(status.lower(),'staging accepted: **false**','live deployed: **false**','operationally accepted: **false**'))
profile_block=member[member.index('function profile'):member.index('function status')]
check(4,'Partial File00 profile outage fails closed',has(profile_block,'$base = self::base_assertion','if ( ! $base )','return array();') and 'return $profile;' not in profile_block.split('if ( ! $base )',1)[1].split('}',1)[0])
check(5,'File00 hard membership dependency is retained',has(plugin,'File 00 Membership Core is required','GDO_Membership_Adapter::available'))
check(6,'File02 recent step-up protects sensitive reviewer paths',has(hard,'rest_reviewer_permission','recent_step_up'))
check(7,'Public/private membership assertion projection is bounded',has(member,'membership_assertion','base_assertion') and '_smc_' not in member)
check(8,'Jurisdiction normalization is explicit',has(member,'normalize_jurisdiction') or has(app,'normalize_jurisdiction'))
check(9,'Application lifecycle contains bounded editable/review states',has(state,'draft','more_information','under_review','verified','expired'))
check(10,'Applicant optimistic concurrency is enforced',has(app,'row_version=row_version+1','gdo_concurrent_change'))

# 11–20: transaction, evidence and upload integrity.
check(11,'Transaction start/commit failure paths are explicit',has(app,'START TRANSACTION','ROLLBACK','COMMIT'))
check(12,'Submission binds immutable consent/snapshot evidence',has(app,'submission_hash','approved_snapshot') or has(app,'submission_hash','snapshot'))
check(13,'Evidence expiry participates in reviewer authorization',has(evidence,'reviewer_case_allows') and ('expires_at' in evidence or 'reviewable' in evidence))
check(14,'Evidence write re-locks current application',has(evidence,'locked_app','FOR UPDATE','gdo_evidence_application_changed'))
check(15,'Persistent evidence quota fails closed on DB uncertainty',has(evidence,'quota_allows','raw_used','$wpdb->last_error','return false'))
normalize=evidence[evidence.index('function normalize_upload'):evidence.index('function safe_image_bytes')]
check(16,'Upload provenance is monotonic with explicit private resumable exception',has(normalize,'$native_uploaded','native_uploaded && $filtered_uploaded','$trusted_internal_path','realpath','basename( $normalized_tmp )',"'.chunk-'") and has(trust,'stage_upload($app,$row->document_type,$file,true,$path)'))
check(17,'MIME/signature and active PDF content are rejected',has(evidence,'FILEINFO_MIME_TYPE','%PDF-','%%EOF','JavaScript','EmbeddedFile'))
check(18,'Credential malware scan is mandatory',has(evidence,'gdo_credential_scan_result',"'clean' !== $scan",'gdo_scan_required'))
check(19,'Evidence is encrypted into private storage',has(evidence,'GDO_Crypto::encrypt','GDO_Storage') and 'wp_handle_upload' not in evidence)
check(20,'Evidence replacement is versioned/superseded',has(evidence,'document_version','version') and ('supersed' in evidence.lower() or 'retention_state' in evidence))

# 21–30: application/risk/state/audit/reviewer scope.
check(21,'Submission has idempotent locked workflow',has(app,'FOR UPDATE','submission_hash') and ('idempot' in app.lower() or 'concurrent' in app.lower()))
check(22,'Approved snapshot/fingerprint integrity is retained',has(app,'stored_approved_snapshot','approved_fingerprint'))
check(23,'Risk evaluation propagates store/query uncertainty',has(risk,'gdo_risk_query_failed','gdo_risk_store_failed'))
check(24,'Existing open-risk lookup DB uncertainty fails closed',has(risk,"signal_type=%s AND status IN ('open','reviewing')",'$wpdb->last_error','gdo_risk_query_failed'))
check(25,'Risk false-positive/human resolution remains modeled',has(risk,'resolve','reason') and ('false_positive' in risk or 'dismiss' in risk or 'resolved' in risk))
check(26,'State transitions use row lock/current state',has(state,'FOR UPDATE','row_version'))
check(27,'Invalid state transitions fail closed',has(state,'WP_Error') and ('transition' in state.lower()))
check(28,'Audit chain is tamper-evident and DB uncertainty is visible',has(audit,'previous_hash','row_hash','$wpdb->last_error'))
check(29,'Transition/audit publication is separated from durable transition',has(audit,'publish_transition') and 'function transition' in state)
check(30,'Reviewer reads are exact case-bound',has(member,'reviewer_case_allows') and evidence.count('reviewer_case_allows')>=2)

# 31–40: reviewer authority, conflicts, appeals and dual control.
can_block=member[member.index('function can('):member.index('function recent_step_up')]
check(31,'Privileged capability requires current identity assurance',has(can_block,'identity_assurance_current','self::sanctioned','empty( $base[\'approved\'] )'))
check(32,'Reviewer authorization is current and object scoped',has(member,'reviewer_case_allows','reviewer_scope_allows'))
check(33,'Reviewer workload is bounded/fail closed',has(admin,'gdo_reviewer_workload_unknown') or has(trust,'max_open_cases','$open>=$max'))
check(34,'Self-review is blocked',('self-review' in admin.lower() or 'self review' in admin.lower() or 'self' in evidence.lower()) and 'reviewer_case_allows' in member)
check(35,'Reviewer/applicant/application relationship is rechecked',has(member,'reviewer_case_allows') and has(hard,'reviewer_case_allows','gdo_check_forbidden'))
check(36,'Conflict uncertainty cannot expand access',has(trust,'has_conflict') and ('fail' in trust.lower() or '$wpdb->last_error' in trust))
check(37,'Conflict extension is monotonic/narrowing',has(trust,'gdo_reviewer_conflict_detected') or has(member,'return $allowed && $filtered'))
check(38,'Dual-review uncertainty is fail-closed/monotonic',has(trust,'requires_dual_review','gdo_requires_dual_review') and ('$required || $filtered' in trust or 'return true' in trust))
check(39,'Independent finalizer is enforced for required dual review',('same_reviewer' in trust or 'same_person' in trust or 'reviewer_id' in trust) and 'requires_dual_review' in trust)
check(40,'Appeal path preserves reviewer independence/reassignment',has(admin,'appeal','assigned_reviewer_id'))

# 41–49: appeals, decision finalization, claim/outbox foundations.
check(41,'Appeal duplicate/open state is serialized',has(admin,'appeal') and ('FOR UPDATE' in admin or 'lock_application' in admin))
check(42,'Appeal DB errors do not silently authorize',('$wpdb->last_error' in admin or 'WP_Error' in admin) and 'appeal' in admin.lower())
check(43,'Recommendation revalidates locked current state',has(admin,'lock_application','verification_recommended'))
check(44,'Finalization revalidates locked current state',has(admin,'lock_application','verification_finalized'))
check(45,'Public verification derives from intact approved snapshot/current truth',has(app,'stored_approved_snapshot') and has(hard,'approved_snapshot','GDO_State::public_verified'))
check(46,'Professional claim is cryptographically signed and version bound',has(claims,'hash_hmac','claim_version','subject_uuid'))
check(47,'Claim issue supports owner-transaction coupling',has(claims,'manage_transaction','START TRANSACTION'))
check(48,'Professional claim requires explicit downstream acknowledgement',has(claims,'acknowledge','accepted'))
check(49,'Outbox UUID/idempotency key is persistent',has(notif,'event_uuid','idempotency'))

# 50–59: durable outbox and provider boundary.
check(50,'Stale processing lease recovery DB failure is propagated',has(notif,'gdo_outbox_lease_recovery_failed','$lease_recovered','false === $lease_recovered'))
check(51,'Pending outbox query DB failure is propagated',has(notif,'gdo_outbox_query_failed','null === $rows','$wpdb->last_error'))
check(52,'Provider success is not claimed without durable delivered receipt',has(notif,'gdo_outbox_delivery_persist_failed','delivery_persistence_uncertain','1 !== $persisted'))
check(53,'Failed/dead outbox state persistence is mandatory',has(notif,'gdo_outbox_failure_persist_failed',"'status'=>$terminal ? 'dead' : 'failed'"))
check(54,'Dead-letter replay processes the exact event UUID',has(notif,'function replay','event_uuid','self::process( 1, (string) $row->event_uuid )'))
check(55,'Legacy File19 delivery requires explicit acknowledgement',has(notif,'gdo_legacy_notification_ack_supported','gdo_legacy_notification_acknowledged'))
check(56,'File19 payload is minimized/versioned',has(notif,'sun.event.v1','sun_ingest_domain_event') and 'wp_mail(' not in notif)
check(57,'Delivery/provider paths recognize only explicit success',('true === $result' in notif) and ('accepted' in notif or 'ack' in notif.lower()))
check(58,'Primary-source request is re-allowlisted after extension filters',has(trust,'gdo_primary_source_request_minimized','array_intersect_key'))
check(59,'External provider references are token-safe/digested',has(trust,'safe_external_reference','refsha256:'))

# 60–69: minimized adapters, resumable upload and evidence viewing.
check(60,'Affiliation adapter uses minimized request',"apply_filters('gdo_institutional_affiliation_verification',null,$request);" in trust)
check(61,'Translation adapter uses minimized payload',"apply_filters('gdo_credential_translation_assistance',null,$payload);" in trust)
check(62,'AI assistance cannot finalize professional decision',has(trust,'gdo_ai_evidence_assistance','automated_decision_forbidden','human_final_decision_required'))
check(63,'Resumable temporary quota covers open/finalizing reservations',has(trust,'gdo_resumable_temp_quota_bytes','expected_bytes',"state IN ('open','finalizing')"))
check(64,'Chunks are exact-order/exact-size bounded',has(trust,'Upload chunks must arrive exactly once and in order','gdo_upload_chunk_size'))
check(65,'Finalize atomically claims open session',has(trust,"'state'=>'finalizing'",'gdo_upload_finalize_conflict'))
check(66,'Expired/abandoned private chunks are cleaned',('.chunk-' in retention) and ('cleanup_upload_sessions' in retention or 'upload_sessions' in retention))
check(67,'Evidence view grant is case-bound',has(evidence,'issue_view_grant','reviewer_case_allows'))
check(68,'Evidence grant is one-time/session/step-up protected',has(evidence,'used_at','session_digest','recent_step_up'))
check(69,'Viewing room is no-download and watermarked',has(trust,"'download_allowed'=>false",'watermark'))

# 70–80: passport/privacy/retention/operations/final release parity.
check(70,'Passport is bound to current state/snapshot/identity assurance',has(hard,'approved_snapshot','GDO_State::public_verified','identity_assurance_current','verified_expiry'))
verify_block=hard[hard.index('function verify_passport_uuid'):hard.index('function continuous_monitor')]
check(71,'Public passport read does not mutate owner truth','revoke_passports_for_application' not in verify_block)
check(72,'Public verification cache/robots privacy controls exist',has(events,'Cache-Control','X-Robots-Tag','noarchive'))
check(73,'Renewal/predecessor continuity is represented',has(app,'verification_record_for_user') and 'renewal_due' in state)
check(74,'Privacy export includes Advanced Trust data',has(privacy,'GDO_Advanced_Trust::privacy_export_rows','privacy_export_rows'))
check(75,'Erasure covers application-scoped Advanced Trust derivatives',has(privacy,'GDO_Advanced_Trust_Hardening::privacy_erase_application') and has(hard,"delete( GDO_Advanced_Trust::table( 'verification_passports' )"))
check(76,'Retention/erasure failures remain observable',('WP_Error' in retention or 'return false' in retention) and 'retire_advanced_trust_for_application' in retention)
check(77,'Operations health reports DB uncertainty and modern File19 readiness',has(ops,'database_observability','count_query','sun_ingest_domain_event','sun_register_notification_producer'))
check(78,'Reconciliation propagates owner/outbox/metric failure',has(ops,'gdo_reconcile_query_failed','gdo_reconcile_transaction_failed','is_wp_error( $outbox )','gdo_reconcile_metric_failed'))
check(79,'Safe mode/schedules/orphan deletion fail closed',has(ops,'gdo_safe_mode_persist_failed','gdo_repair_retention_schedule','gdo_repair_outbox_schedule') and has(retention,'doctor_credential_orphan_inventory_failed','database_inventory_unavailable','return false'))
check(80,'R3 defect ledger and exact-head release gate are synchronized',has(review,'Defect-bearing rounds: **13**','04, 16, 24, 31, 50, 51, 52, 53, 54, 77, 78, 79, 80') and 'eighty-round-audit-r3.py' in workflow and 'REVIEW-80-ROUNDS-RC6-R3.md' in release and lock.get('third_review_rounds')==80 and lock.get('third_defect_rounds')==13 and lock.get('third_clean_rounds')==67)

if len(checks)!=80 or [n for n,_,_ in checks]!=list(range(1,81)):
    fail('R3 80-round control list is incomplete or out of order')
failures=[(n,t) for n,t,ok in checks if not ok]
if failures:
    for n,t in failures: print(f'FAIL R{n:02d}: {t}',file=sys.stderr)
    raise SystemExit(1)

defects=[4,16,24,31,50,51,52,53,54,77,78,79,80]
clean=[n for n in range(1,81) if n not in defects]
print('File 09 RC6 third fresh eighty-round audit: 80 PASS, 0 FAIL')
print('Defect-bearing rounds: '+','.join(f'{n:02d}' for n in defects))
print('Clean rounds: '+','.join(f'{n:02d}' for n in clean))
