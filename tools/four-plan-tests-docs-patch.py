from pathlib import Path
import hashlib,json,datetime
root=Path.cwd()
def r(p): return (root/p).read_text(encoding='utf-8')
def w(p,s): (root/p).write_text(s,encoding='utf-8')
def rep(p,a,b,n=1):
 s=r(p)
 if s.count(a)<n: raise SystemExit(f'{p}: missing expected text: {a[:100]!r}')
 w(p,s.replace(a,b,n)); print('patched',p)
def app(p,marker,block):
 s=r(p)
 if marker not in s:
  w(p,s.rstrip()+'\n\n'+block.strip()+'\n'); print('documented',p)

p='tests/membership-adapter.php'
rep(p,"define( 'SMC_VERSION', '1.2.7' );","define( 'SMC_VERSION', '1.2.11' );")
rep(p,"define( 'SMC_CONTRACT_VERSION', '1.1.2' );","define( 'SMC_CONTRACT_VERSION', '1.2.0' );")
rep(p,"\t'contract_version' => '1.1.2',","\t'contract_version' => '1.2.0',")
rep(p,"\t'user_id' => 7,\n\t'membership_type' => 'doctor',","\t'user_id' => 7,\n\t'application_exists' => true,\n\t'account_class' => 'member',\n\t'membership_type' => 'doctor',\n\t'approved_membership_types' => array( 'doctor' ),")
rep(p,"\t'professional_verified' => false,\n);","\t'professional_verified' => false,\n\t'eligible' => false,\n\t'identity_documents_current' => true,\n);")
rep(p,"\t'subject' => array( 'platform_uuid' => $uuid, 'record_version' => 3 ),\n\t'age_context' => array( 'known' => true, 'age_years' => 30 ),","\t'subject' => array( 'platform_uuid' => $uuid, 'record_version' => 3 ),\n\t'membership' => array( 'account_class'=>'member', 'membership_type'=>'doctor', 'status'=>'approved', 'active'=>false, 'suspended'=>false, 'identity_assurance'=>'none', 'two_factor_ready'=>true, 'session_two_factor'=>false, 'guardian_required'=>false, 'guardian_verified'=>true, 'policy_version'=>'test' ),\n\t'age_context' => array( 'known' => true, 'age_years' => 30, 'guardian_required'=>false ),\n\t'jurisdiction_context' => array( 'known'=>false, 'canonical_country'=>'', 'requested_country'=>'', 'mismatch'=>false ),")
marker="gdo_adapter_assert( GDO_Membership_Adapter::is_active_doctor_candidate( 7 ), 'adult professional eligibility does not require guardian verification' );\nSMC_Contracts::$assertion = $base;"
block="""gdo_adapter_assert( GDO_Membership_Adapter::is_active_doctor_candidate( 7 ), 'adult professional eligibility does not require guardian verification' );
SMC_Contracts::$assertion = $base;

$identity_missing = $base;
$identity_missing['identity_documents_current'] = false;
SMC_Contracts::$assertion = $identity_missing;
gdo_adapter_assert( ! GDO_Membership_Adapter::is_active_doctor_candidate( 7 ), 'current identity documents are mandatory for File 09 entry' );
SMC_Contracts::$assertion = $base;

$grant_missing = $base;
$grant_missing['approved_membership_types'] = array( 'member' );
SMC_Contracts::$assertion = $grant_missing;
gdo_adapter_assert( ! GDO_Membership_Adapter::is_active_doctor_candidate( 7 ), 'approved doctor grant is mandatory for File 09 entry' );
SMC_Contracts::$assertion = $base;

$stale_status = $base;
$stale_status['status'] = 'verification_pending';
SMC_Contracts::$assertion = $stale_status;
gdo_adapter_assert( ! GDO_Membership_Adapter::is_active_doctor_candidate( 7 ), 'stale approved boolean cannot bypass current membership status' );
SMC_Contracts::$assertion = $base;"""
rep(p,marker,block)
rep(p,"gdo_adapter_assert( false === $profile['doctor_verified'], 'legacy professional display flag does not fabricate File 09 approval' );","gdo_adapter_assert( true === $profile['identity_verified'], 'identity assurance requires current identity evidence plus verified contacts and second factor' );\ngdo_adapter_assert( false === $profile['doctor_verified'], 'File 00 professional display flag cannot fabricate File 09 approval' );")

rep('tests/policy-runtime.php',"const VERSION       = '2026-08-06.1'","const VERSION       = '2026-08-07.2'")
rep('tests/rc2-adversarial.py',"if 'guardian_ok' not in member or '&& $guardian_ok' not in member: fail('guardian applicability control missing')","if 'identity_documents_current' not in member or 'approved_membership_types' not in member or 'age_years >= $minimum_age' not in member: fail('current adult professional identity/eligibility control missing')")

p='tests/cf01-practitioner-contract.php'
rep(p,"const FILE00_BASE_VERSION  = '1.1.2'","const FILE00_BASE_VERSION  = '1.2.0'")
s=r(p); needle='echo "File 09 CF-01 static contract: {$tests} PASS, 0 FAIL\\n";'
extra="""gdo_cf01_static_assert( false === strpos( $contract, "3 !== absint" ), 'CF-01 does not fossilize approved snapshots at schema 3' );
gdo_cf01_static_assert( false !== strpos( $contract, "GDO_SCHEMA_VERSION" ), 'CF-01 accepts supported snapshots through the current schema' );
gdo_cf01_static_assert( false !== strpos( $contract, "identity_documents_current" ) && false !== strpos( $contract, "professional_verified" ), 'CF-01 requires current high-trust and professional assurance' );
gdo_cf01_static_assert( false === strpos( $contract, "empty( \\$base['guardian_verified'] ) )" ), 'adult practitioner eligibility has no unconditional guardian gate' );
"""+needle
if 'does not fossilize approved snapshots' not in s: w(p,s.replace(needle,extra,1))

p='tests/cf01-practitioner.php'
rep(p,"define( 'GDO_VERSION', '1.2.0' );","define( 'GDO_VERSION', '1.2.0' );\ndefine( 'GDO_SCHEMA_VERSION', 6 );")
rep(p,"\t'subject' => array( 'platform_uuid' => $uuid, 'record_version' => 4 ),\n);","\t'subject' => array( 'platform_uuid' => $uuid, 'record_version' => 4 ),\n\t'membership' => array( 'identity_assurance' => 'verified' ),\n);")
rep(p,"\t'guardian_verified' => true,\n\t'professional_verified' => false,","\t'guardian_verified' => false,\n\t'professional_verified' => true,\n\t'eligible' => true,\n\t'identity_documents_current' => true,")
rep(p,"\t'schema' => 3,","\t'schema' => 6,")
rep(p,"gdo_runtime_assert( 'allow' === $result['result'], 'File 09 may verify an approved doctor without circular prior professional verification' );","gdo_runtime_assert( 'allow' === $result['result'], 'CF-01 accepts the current File 09 verified doctor with current File 00 professional assurance' );")
marker="GDO_Membership_Adapter::$base = $base_active;\n\n$identity_incomplete = $base_active;"
block="""GDO_Membership_Adapter::$base = $base_active;

$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'allow' === $result['result'], 'adult practitioner does not require an irrelevant guardian assertion' );

$legacy_snapshot = $snapshot_valid;
$legacy_snapshot['schema'] = 3;
GDO_Application::$snapshot = $legacy_snapshot;
$result = GDO_CF01_Practitioner_Contract::assertion( 7, array( 'action' => 'clinical_read', 'purpose' => 'patient_care' ) );
gdo_runtime_assert( 'allow' === $result['result'], 'supported legacy schema 3 approved snapshot remains readable during migration' );
GDO_Application::$snapshot = $snapshot_valid;

$identity_incomplete = $base_active;"""
rep(p,marker,block)

p='tests/final-hardening.py'; s=r(p); needle="print('File 09 final hardening invariants passed.')"
extra=r'''adapter = text('includes/class-gdo-membership-adapter.php')
for token in ["const FILE00_BASE_VERSION  = '1.2.0'", 'identity_documents_current', 'approved_membership_types', "function_exists( 'gdo_user_is_verified' )"]:
    if token not in adapter: fail('current File 00/high-trust adapter hardening missing: ' + token)
if "identity_verified'] = ! empty( $base['email_verified'] ) && ! empty( $base['phone_verified'] )" in adapter: fail('contact ownership is still misrepresented as identity verification')
policy = text('includes/class-gdo-policy.php')
for token in ['2026-08-07.2', 'identity_documents_current', 'approved_membership_types']:
    if token not in policy: fail('four-plan policy harmonization missing: ' + token)
frontend = text('includes/class-gdo-frontend.php')
if 'gdo-shell' in frontend or '<main class="gdo-application"' not in frontend: fail('File 09 still duplicates/misnames the File 20 shell boundary')
if 'Verified professional phone' in frontend: fail('File 09 UI still claims it verifies its local professional phone field')
application = text('includes/class-gdo-application.php')
for token in ['stored_approved_snapshot', 'refresh_approved_snapshot', 'GDO_SCHEMA_VERSION', "! in_array( $field, $required, true )"]:
    if token not in application: fail('snapshot/optional-field hardening missing: ' + token)
cf01 = text('includes/class-gdo-cf01-practitioner-contract.php')
if '3 !== absint' in cf01: fail('CF-01 still hard-codes approved snapshot schema 3')
for token in ['GDO_SCHEMA_VERSION', 'identity_documents_current', 'professional_verified', 'identity_assurance']:
    if token not in cf01: fail('CF-01 current-assurance hardening missing: ' + token)
if "empty( $base['guardian_verified'] ) )" in cf01: fail('CF-01 still imposes an unconditional guardian gate on adult practitioners')
admin = text('includes/class-gdo-admin.php')
for token in ['GDO_Membership_Adapter::is_active_doctor_candidate( $app->user_id )', 'GDO_Application::refresh_approved_snapshot', "'approved_snapshot_json'", "'approved_fingerprint'"]:
    if token not in admin: fail('finalization/reinstatement current-assurance hardening missing: ' + token)
if "'schema'=>6" in admin: fail('finalization still hard-codes snapshot schema 6')
claims = text('includes/class-gdo-claims.php')
for token in ['gdo_claim_membership_not_current', 'GDO_Application::stored_approved_snapshot', 'GDO_Membership_Adapter::is_active_doctor_candidate']:
    if token not in claims: fail('claim-time high-trust revalidation missing: ' + token)

'''+needle
if 'current File 00/high-trust adapter hardening' not in s: w(p,s.replace(needle,extra,1))

app('REVIEW-ROUND-3-RC2.md','Four-Plan Harmonization Review — 7 August 2026','''## Four-Plan Harmonization Review — 7 August 2026

The RC2 source was re-opened against four governing sources: (1) Definitive Master Plan v3.0, (2) All-Chats Recovered Directives v2.2, (3) Continuous-Value / Top-20 Superset Master Plan v1.0, and (4) File 09 Complete Master Plan v1.0.

### Round 1 — precedence, ownership and product constitution
Confirmed File 09 remains the professional-evidence/review/decision owner, File 00 owns membership/identity assertions, and File 20 owns the application shell. Corrected the local `gdo-shell` wrapper and retained the current green/free-system governance without reviving superseded orange/paid baselines.

### Round 2 — High-Trust membership and identity assurance
Found that RC2 pinned File 00 general contract 1.1.2 while current File 00 main publishes 1.2.0. Corrected the fail-closed pin, required current identity documents and an approved doctor membership grant for File 09 entry, stopped equating email+phone with identity verification, and preserved non-circularity because File 09 itself owns professional verification.

### Round 3 — professional/clinical boundary and lifecycle integrity
Corrected an unconditional guardian check in CF-01, a schema-3 fossil that rejected current schema-6 snapshots, and reinstatement paths that changed validity without refreshing the approved snapshot. Finalization, reinstatement and claim issuance now recheck current File 00 assurance; reinstatement also rechecks risk/evidence and refreshes snapshot validity metadata.

### Round 4 — fresh adversarial UX and release review
Corrected WhatsApp being effectively mandatory despite policy, removed misleading local phone-verification wording, and added permanent regression gates. Deterministic packaging and exact-head CI remain mandatory; Hostinger staging/live/operational acceptance remains separate.''')
app('CHANGELOG.md','Four-plan harmonization hardening (7 Aug 2026)','''### Four-plan harmonization hardening (7 Aug 2026)
- Aligned File 09 with current File 00 general contract 1.2.0 / runtime 1.2.11.
- Added current identity-document and approved-doctor-grant checks without circular pre-verification.
- Corrected CF-01 adult guardian logic and approved-snapshot schema compatibility.
- Revalidated File 00 assurance and unresolved high-risk signals at verification/reinstatement/claim time.
- Refreshed approved snapshot validity metadata on reinstatement.
- Made WhatsApp optional as specified; corrected phone copy and File 20 shell naming boundary.''')
app('TRACEABILITY.md','Four-plan harmonization addendum — 7 Aug 2026','''## Four-plan harmonization addendum — 7 Aug 2026

| Governing concern | Corrected implementation evidence |
| --- | --- |
| Current File 00 contract | `GDO_Membership_Adapter` pins general contract 1.2.0 and consumes high-trust fields. |
| Non-circular File 09 entry | Requires approved doctor membership, current identity evidence, verified contacts, 2FA and adult age; it does not require pre-existing File 09 professional approval. |
| Professional truth | File 09 decision, not File 00 display compatibility, controls doctor verification. |
| CF-01 boundary | Current File 00 professional/identity assurance + File 09 decision/snapshot/evidence; no unconditional adult guardian gate. |
| Snapshot lifecycle | Schema 3..current is integrity-checked; reinstatement refreshes validity metadata. |
| Action-time safety | Verification/reinstatement/claims recheck current File 00 assurance; reinstatement also rechecks unresolved high-risk state. |
| File 20 ownership | Local wrapper is `gdo-application`, never a second platform shell. |
| Required fields | Phone remains required; WhatsApp is optional unless future approved policy changes it. |''')

sbp=root/'SBOM.spdx.json'
if sbp.exists():
 sb=json.loads(r('SBOM.spdx.json')); now=datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0).isoformat().replace('+00:00','Z')
 sb['creationInfo']['created']=now
 for a in sb.get('annotations',[]): a['annotationDate']=now
 sb['documentNamespace']='https://sabrihomeopathy.com/spdx/file09/1.2.0/rc2/'+hashlib.sha256(now.encode()).hexdigest()[:16]
 for item in sb.get('files',[]):
  rel=item['fileName'].removeprefix('./'); data=(root/rel).read_bytes(); dig=hashlib.sha256(data).hexdigest()
  for c in item.get('checksums',[]):
   if c.get('algorithm')=='SHA256': c['checksumValue']=dig
 w('SBOM.spdx.json',json.dumps(sb,indent=2,ensure_ascii=False)+'\n'); print('refreshed SBOM')
print('tests/docs patch complete')
