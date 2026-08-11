from pathlib import Path
p=Path('tests/eighty-round-audit-r9.py')
s=p.read_text(encoding='utf-8')
repls=[
("and 'tests/eighty-round-audit-r9.py' in workflow and '60-entry' in manifest))","and 'tests/eighty-round-audit-r9.py' in workflow and '60-entry' in manifest)"),
("c(21,'Submission remains snapshot/idempotency bound',has(app,'submission_hash','submission_snapshot_json','gdo_submit_conflict'))","c(21,'Submission remains snapshot/idempotency bound',has(app,'submission_hash','GDO_Evidence::records( $app->id, true )','hash_equals( (string) $app->submission_hash, $submission_hash )','gdo_submit_state'))"),
("c(29,'Continuous monitoring does not silently auto-revoke professional status','gdo_continuous_verification_adverse_result' in hard and 'revoke' not in hard[hard.index('public static function continuous_monitor'):hard.index('public static function cleanup_upload_sessions')].lower())","c(29,'Continuous monitoring does not silently auto-revoke professional status','gdo_continuous_verification_adverse_result' in hard and 'GDO_State::transition' not in hard[hard.index('public static function continuous_monitor'):hard.index('public static function cleanup_upload_sessions')] and 'GDO_Claims::issue' not in hard[hard.index('public static function continuous_monitor'):hard.index('public static function cleanup_upload_sessions')])"),
("c(51,'Notification outbox remains retry/dead-letter capable',has(notify,'dead_letter','retry','attempt_count'))","c(51,'Notification outbox remains retry/dead-letter capable',has(notify,'doctor_verification_outbox_dead_letter',\"'status'=>$terminal ? 'dead' : 'failed'\",\"'available_at'=>gmdate\",'$attempts'))"),
("c(65,'Privacy export remains paginated/minimized and failure-aware',has(privacy,'export','page','gdo_privacy_export'))","c(65,'Privacy export remains paginated/minimized and failure-aware',has(privacy,'public function export( $email, $page = 1 )','$per = 20','LIMIT %d OFFSET %d',\"'done'=>false\"))"),
("c(68,'Retention refuses unsafe active professional states',has(retention,'verified','suspended','revoked'))","c(68,'Retention refuses unsafe active professional states',has(retention,\"array( 'verified','reinstated','under_review','recommended','appeal_pending','submitted','resubmitted','renewal_due' )\",'continue;'))"),
("c(72,'File03/07/08/21/23/26 consume public-safe verification projections',has(integ,'gdo.file03.profile-verification','gdo.file07.directory-eligibility','gdo.file08.clinic-eligibility','gdo.file21.publishing-eligibility','gdo.file23.publishing-dashboard-eligibility','gdo.file26.doctor-verification-projection'))","c(72,'File03/07/08/21/23/26 consume public-safe verification projections',has(integ,'gdo.file03.doctor-profile-eligibility','gdo.file07.directory-eligibility','gdo.file08.clinic-eligibility','gdo.file21.publishing-eligibility','gdo.file23.publishing-dashboard-eligibility','gdo.file26.doctor-verification-projection'))"),
("c(73,'File26 projection excludes private evidence and donor/rank advantage',has(integ,\"'evidence_exposed'=>false\",\"'donor_rank_advantage'=>false\"))","c(73,'File26 projection excludes private evidence and donor/rank advantage',has(integ,\"'evidence_exposed'\",\"'donor_rank_advantage'\",\"'private_indexing' => false\"))"),
]
for old,new in repls:
    if old not in s:
        raise SystemExit('R9 repair anchor not found: '+old[:90])
    s=s.replace(old,new,1)
p.write_text(s,encoding='utf-8')
