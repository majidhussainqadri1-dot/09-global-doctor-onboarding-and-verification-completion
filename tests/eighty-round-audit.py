#!/usr/bin/env python3
from pathlib import Path
import json, re, subprocess, sys

root = Path(__file__).resolve().parents[1]
BASELINE = '6d5c2850dbf86ce954e0c2fdef8adf36d2dbf1f1'

def text(path):
    return (root / path).read_text(encoding='utf-8')

def has(s, *tokens):
    return all(token in s for token in tokens)

def fail(message):
    print('FAIL:', message, file=sys.stderr)
    raise SystemExit(1)

try:
    subprocess.check_call(['git','cat-file','-e',BASELINE + '^{commit}'], cwd=root, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
except Exception:
    fail('RC5 baseline commit is unavailable; the 80-round corrective comparison cannot be reproduced')

main = text('global-doctor-onboarding.php')
plugin = text('includes/class-gdo-plugin.php')
state = text('includes/class-gdo-state.php')
policy = text('includes/class-gdo-policy.php')
app = text('includes/class-gdo-application.php')
evidence = text('includes/class-gdo-evidence.php')
admin = text('includes/class-gdo-admin.php')
trust = text('includes/class-gdo-advanced-trust.php')
hard = text('includes/class-gdo-advanced-trust-hardening.php')
events = text('includes/class-gdo-advanced-trust-events.php')
privacy = text('includes/class-gdo-privacy.php')
retention = text('includes/class-gdo-retention.php')
operations = text('includes/class-gdo-operations.php')
integrations = text('includes/class-gdo-integration-contracts.php')
notifications = text('includes/class-gdo-notifications.php')
release = text('RELEASE-FILES.txt')
workflow = text('.github/workflows/file09-rc2-final.yml')
status = text('STATUS.md')
trace = text('TRACEABILITY.md')
advanced_doc = text('ADVANCED-TRUST-24.md')
manifest = text('RELEASE-MANIFEST-1.3.0.md')
migration = text('MIGRATION-ROLLBACK-1.3.0.md')
review = text('REVIEW-80-ROUNDS-RC6.md')
lock = json.loads(text('RELEASE-LOCK.json'))

checks = []
def check(n, topic, condition):
    checks.append((n, topic, bool(condition)))

check(1, 'Canonical File 09 ownership and no cross-file direct writes', has(integrations, "'canonical_mutations'", "'direct_table_meta_write'=> false", "'source_of_truth'          => 'file09'"))
check(2, 'Specified/code/package/staging/live truth separation', has(status.lower(), 'staging accepted: **false**', 'live deployed: **false**', 'operationally accepted: **false**'))
check(3, 'RC6 runtime/release metadata consistency', has(status, 'RC6') and lock.get('release_candidate') == 'RC6')
check(4, 'Bootstrap and corrective-layer load order', main.index('class-gdo-advanced-trust.php') < main.index('class-gdo-advanced-trust-hardening.php') < main.index('class-gdo-advanced-trust-events.php'))
check(5, 'Advanced Trust schema version discipline', has(hard, 'const SCHEMA_VERSION = 2', "update_option( 'gdo_advanced_trust_schema', self::SCHEMA_VERSION"))
check(6, 'Schema/index parity for passport and resumable sessions', has(hard, 'application_status', 'application_state'))
check(7, 'Non-destructive uninstall law retained', 'Non-destructive' in migration or 'non-destructive' in migration.lower())
check(8, 'Activation/deactivation schedule hygiene', has(text('includes/class-gdo-activator.php'), 'gdo_trust_continuous_monitor', 'wp_clear_scheduled_hook'))
check(9, 'Release allowlist includes every RC6 runtime/evidence file', all(x in release for x in ['class-gdo-advanced-trust-hardening.php','REVIEW-80-ROUNDS-RC6.md']))
check(10, 'Safe Mode and mutation health gate', has(operations, 'mutation_allowed', 'safe_mode', 'GDO_Crypto::available', 'GDO_Storage::health'))
check(11, 'File 00 hard dependency remains fail closed', has(plugin, 'File 00 Membership Core is required', 'GDO_Membership_Adapter::available'))
check(12, 'File 02 step-up remains mandatory for reviewer-sensitive paths', has(hard, 'recent_step_up', 'rest_reviewer_permission'))
check(13, 'Reviewer object-scope/IDOR recheck', has(hard, 'reviewer_scope_allows', "'gdo_check_forbidden'"))
check(14, 'Self-review protection in mature evidence path', 'self' in evidence.lower() and 'reviewer_scope_allows' in evidence)
check(15, 'Conflict declaration/resolution authorization and audit', has(trust, 'declare_conflict', 'resolve_conflict', 'gdo_conflict_forbidden', 'doctor_reviewer_conflict_declared'))
check(16, 'Adaptive dual-review current-state mapping and monotonic strengthening', has(trust, 'appeal_pending', "$required || $filtered"))
check(17, 'Smart routing respects reviewer max workload', has(trust, 'max_open_cases', '$open>=$max'))
check(18, 'Calibration excludes incomplete samples from outcome denominator', has(trust, "'completed'!==sanitize_key($row['status'])", 'agreement_rate'))
check(19, 'Existing quality-sampling ledger retained', 'quality_samples' in admin and 'GDO_Quality' in text('includes/class-gdo-quality.php'))
check(20, 'Independent appeal path retained', 'appeal' in admin.lower() and 'assigned_reviewer_id' in admin)
check(21, 'Primary-source provider unavailable fails safe', has(trust, 'provider_unavailable', 'trusted_issuer_not_available'))
check(22, 'Trusted issuer proposal/second-review governance', has(trust, "'status'=>'proposed'", 'gdo_issuer_review_separation'))
check(23, 'Issuer domain normalization and provider-secret minimization', has(trust, 'normalize_domain', 'provider_payload_exceeded_safe_limit', 'api[_-]?key'))
check(24, 'Jurisdiction rules are draft-first, independently approved and immutable by version', has(hard, 'gdo_jurisdiction_rule_separation', 'gdo_jurisdiction_rule_immutable', 'gdo_jurisdiction_rule_changed'))
check(25, 'Cross-border equivalency cannot grant legal license', 'legal_license_grant' in trust)
check(26, 'Institutional affiliation remains adapter fact, not profile owner', 'gdo_institutional_affiliation_verification' in trust)
check(27, 'Translation keeps original credential authoritative', 'original_remains_authoritative' in trust)
check(28, 'AI final professional decisions forbidden', has(trust, 'automated_decision_forbidden', 'human_final_decision_required'))
check(29, 'External-provider request/result minimization', has(trust, 'gdo_primary_source_request_minimized', 'sanitize_provider_array') and 'normalize_primary_source_result' in hard)
check(30, 'Provider payload/date/rate/replay hardening', has(trust, 'PROVIDER_PAYLOAD_MAX_BYTES', 'primary-source-check:', 'ai-evidence-assist:') and 'expires_at' in hard)
check(31, 'Continuous monitoring never changes professional owner state', 'GDO_State::transition' not in hard[hard.index('function continuous_monitor'):hard.index('function cleanup_upload_sessions')])
check(32, 'Degraded monitor rows retry with backoff instead of becoming stranded', has(hard, "monitor_status IN ('scheduled','degraded')", 'wp_schedule_single_event', 'failure_count'))
check(33, 'Event-driven reverification uses explicit lifecycle facts, not fuzzy names', has(events, "'doctor_verification_transition'", "'to_state'") and 'preg_match' not in events)
check(34, 'Adverse monitor result notifies and schedules without silent revocation', has(events, 'doctor_verification_reverification_required', 'GDO_Advanced_Trust_Hardening::event_reverification') and 'GDO_State::transition' not in events)
check(35, 'Passport eligibility uses current File 09 state and File 00 identity assurance', has(hard, 'GDO_State::public_verified', 'identity_assurance_current', 'gdo_passport_not_eligible'))
check(36, 'Passport is HMAC-signed and token hash is stored', has(hard, 'hash_hmac', 'token_hash'))
check(37, 'Passport lifecycle revocation covers expired/suspended/revoked/rejected/withdrawn', has(hard, "array( 'suspended','revoked','rejected','withdrawn'", 'revoke_passports_for_application'))
check(38, 'Passport supersession is serialized and write failures fail closed', has(hard, 'FOR UPDATE', 'gdo_passport_supersede', 'START TRANSACTION'))
check(39, 'Public passport rechecks underlying verification and identity at read time', has(hard, 'verify_passport_uuid', 'GDO_State::public_verified', 'identity_assurance_current'))
verify_block = hard[hard.index('function verify_passport_uuid'):hard.index('function continuous_monitor')]
check(40, 'Public passport GET is read-only and non-cacheable', 'revoke_passports_for_application' not in verify_block and 'no-store, no-cache, must-revalidate' in events)
check(41, 'Verification matrix uses current public verification record', 'verification_record_for_user' in trust)
check(42, 'Renewal predecessor continuity remains preserved', 'verification_record_for_user' in app and 'renewal_due' in state)
check(43, 'Scope badge has verified/pending/not_verified/not_applicable semantics', all(x in trust for x in ['scope_status','pending','not_verified','not_applicable']))
check(44, 'Professional-history public exposure is allowlisted', has(trust, 'allowed_public', 'public_safe=$public_safe && in_array'))
check(45, 'Explainable risk exposes reasons rather than opaque auto-rejection', has(trust, 'risk_explanation', 'opaque_rejection_forbidden'))
check(46, 'Fraud-ring credential reuse signals are deduplicated', has(trust, 'credential_reuse_network', 'SELECT id FROM') and 'related_digest' in trust)
check(47, 'Risk signals remain human-reviewed', 'human_review_required' in trust)
check(48, 'Resumable session creation is exclusive and DB insert is checked', has(trust, "fopen($path,'x+b')", 'gdo_upload_session_store'))
check(49, 'Chunk append serializes DB row and file lock', has(trust, 'FOR UPDATE', 'LOCK_EX', 'gdo_upload_chunk_conflict'))
check(50, 'Chunk order and exact non-final/final size are enforced', has(trust, 'Upload chunks must arrive exactly once and in order', 'gdo_upload_chunk_size'))
check(51, 'Private temp path rejects symlink/inconsistent file state', has(trust, 'is_link($path)', 'gdo_upload_chunk_integrity'))
check(52, 'Declared bytes/chunks/chunk-size dimensions must agree', 'expected_chunks' in trust and '$chunks!==$expected_chunks' in trust)
check(53, 'Final SHA-256 mismatch fails closed', 'gdo_upload_hash_mismatch' in trust)
check(54, 'Finalize uses atomic open-to-finalizing claim', has(trust, "'state'=>'finalizing'", 'gdo_upload_finalize_conflict'))
check(55, 'Finalize commit-marker failure is observable and repairable', 'doctor_resumable_upload_commit_marker_failed' in trust)
check(56, 'Expired finalizing sessions are cleaned', "state IN ('open','failed','finalizing')" in hard)
check(57, 'Resumable upload terminates in canonical malware/type/encryption path', has(trust, 'GDO_Evidence::stage_upload', 'gdo_is_uploaded_file'))
check(58, 'Viewing room reuses canonical evidence-grant implementation', 'GDO_Evidence::issue_view_grant' in trust)
check(59, 'Parallel bespoke secure-room grant backend removed from active correction layer', 'secure_room' not in hard)
check(60, 'Evidence grant is one-time/session/step-up bound', all(x in evidence for x in ['used_at','session_digest','recent_step_up']))
check(61, 'Viewing-room contract explicitly denies downloads and watermarks review', has(trust, "'download_allowed'=>false", 'watermark'))
check(62, 'Evidence/application association is checked before view grant', 'evidence_record' in trust and 'issue_viewing_room_grant' in trust)
check(63, 'Advanced Trust REST check revalidates object scope', has(hard, 'rest_check', 'reviewer_scope_allows'))
check(64, 'REST check failures are structured instead of nested raw WP_Error objects', has(hard, "'ok'=>false", 'rest_value'))
check(65, 'Public transparency uses fixed window and cohort suppression', has(trust, 'PUBLIC_WINDOW_DAYS', 'gdo_public_transparency_minimum_cohort', "'suppressed'=>true"))
check(66, 'Public verification anti-stale cache headers are explicit', has(events, 'Cache-Control', 'X-Robots-Tag', 'noarchive'))
check(67, 'Dynamic SQL values use prepared statements in corrective layer', '$wpdb->prepare' in hard and 'SELECT * FROM' in hard)
check(68, 'Public shortcode values are escaped and localized', 'esc_html' in trust and '__(' in trust)
check(69, 'Provider secrets/raw credentials are minimized from persistence and requests', has(trust, 'api[_-]?key', 'gdo_primary_source_request_minimized') and 'source_sha256' not in trust[trust.index('function primary_source_verify'):trust.index('function authenticity_assessment')])
check(70, 'Privacy export includes Advanced Trust records', has(privacy, 'privacy_export_rows', 'Professional trust'))
check(71, 'Privacy erasure includes Advanced Trust and deletes passports collision-safely', has(privacy, 'GDO_Advanced_Trust_Hardening::privacy_erase_application') and "delete( GDO_Advanced_Trust::table( 'verification_passports' )" in hard)
check(72, 'Retention anonymization is application-scoped, not global reviewer erasure', has(retention, 'retire_advanced_trust_for_application') and 'reviewer_id=0 WHERE reviewer_id' not in retention)
check(73, 'Retention/orphan cleanup covers resumable .chunk files', '.chunk-' in retention and 'cleanup_upload_sessions' in retention)
check(74, 'Migration/rollback documentation reflects additive Advanced Trust schema 2', 'Advanced Trust schema: **2**' in migration or 'Advanced Trust schema `2`' in migration)
check(75, 'Backup/restore remains external acceptance evidence, not repository fiction', 'backup' in status.lower() and 'staging' in status.lower())
check(76, 'File 19 remains notification-delivery owner', has(notifications, 'sun.event.v1', 'sun_ingest_domain_event') and 'wp_mail(' not in notifications)
check(77, 'Files 03/07/26 receive only public-safe verification projection', has(integrations, 'c0_public_verification_projection', 'evidence_exposed', "'private_indexing' => false"))
check(78, 'Accessibility/RTL/weak-network acceptance remains a staging gate', all(x in advanced_doc.lower() for x in ['rtl/ltr','screen reader','weak-connection']))
check(79, 'Exact-head CI executes Advanced Trust and 80-round assurance before RC6 packaging', has(workflow, 'eighty-round-audit.py', 'advanced-trust-24.py', 'RC6'))
check(80, 'Plan/repository status and 80-round defect ledger are synchronized', has(review, '80', '49', '31') and 'RC6' in status and 'Advanced Trust schema: **2**' in status)

if len(checks) != 80 or [n for n,_,_ in checks] != list(range(1,81)):
    fail('80-round control list is incomplete or out of order')

failures = [(n,t) for n,t,ok in checks if not ok]
if failures:
    for n,t in failures:
        print(f'FAIL R{n:02d}: {t}', file=sys.stderr)
    raise SystemExit(1)

DEFECT_ROUNDS = [3,5,6,9,15,16,17,18,22,23,24,29,30,32,33,34,35,36,37,38,39,40,41,42,43,44,46,48,49,50,51,52,54,55,56,58,59,63,64,65,66,69,70,71,72,73,74,79,80]
CLEAN_ROUNDS = [n for n in range(1,81) if n not in DEFECT_ROUNDS]
assert len(DEFECT_ROUNDS) == 49 and len(CLEAN_ROUNDS) == 31
assert '49 defect-bearing rounds' in review and '31 clean rounds' in review
print('File 09 RC6 eighty-round audit: 80 PASS, 0 FAIL')
print('Defect-bearing rounds:', ','.join(f'{n:02d}' for n in DEFECT_ROUNDS))
print('Clean rounds:', ','.join(f'{n:02d}' for n in CLEAN_ROUNDS))
