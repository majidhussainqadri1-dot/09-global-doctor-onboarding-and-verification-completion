<?php
/**
 * Exact-locked joint File 00 PRODUCTION PROVIDER CLASS + File 09 consumer.
 * Storage/WordPress functions are deterministic stubs; no live-site claims.
 * External File00 checkout must be pinned separately by workflow SHA.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__.'/');
define('SMC_VERSION','1.2.44');
define('SMC_CONTRACT_VERSION','1.2.3');
define('SMC_CF01_CONTRACT_VERSION','1.1.0');
define('SMC_FILE09_INTAKE_CONTRACT_VERSION','1.0.0');
define('SA_PROFESSIONAL_REAUTH_VERSION','1.0.0');
function absint($x){return abs((int)$x);}
function sanitize_key($x){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$x));}
function wp_generate_uuid4(){return '123e4567-e89b-42d3-a456-426614174000';}
function __($x,$d=''){return $x;}
function is_wp_error($x){return $x instanceof WP_Error;}
$GLOBALS['gdo_actor_switch_during_policy'] = false;
function apply_filters($h,$x,...$y){
    if ('smc_file09_intake_narrow' === $h && !empty($GLOBALS['gdo_actor_switch_during_policy'])) {
        $GLOBALS['gdo_intake_actor'] = 8;
    }
    return $x;
}
function smc_age_from_dob($x){return $x==='2010-01-01'?16:30;}
function get_userdata($x){return (int)$x===7?(object)array('ID'=>7):false;}
$GLOBALS['gdo_intake_actor'] = 7;
function get_current_user_id(){return (int)$GLOBALS['gdo_intake_actor'];}
function is_user_logged_in(){return get_current_user_id() > 0;}
function smc_application($x){return (int)$x===7?$GLOBALS['app']:false;}
class WP_Error{public function __construct($code='',$message=''){}}
class SMC_Security{public static function decrypt($x,$p,$ctx){return $x;}}
class SMC_MFA_Retirement{public static $advanced_ok=true;public static function advanced_trust_allows_without_mfa($u){return self::$advanced_ok;}}
class SMC_Contracts{
    public static function assertions($x){return (int)$x===7?$GLOBALS['base']:array();}
    public static function role_grants($x){return (int)$x===7?$GLOBALS['grants']:array();}
}
class SMC_CF01_Contract{
    public static $clinical_calls=0;
    public static function membership_assertion($u,$c){++self::$clinical_calls;return array('result'=>'deny');}
    public static function ensure_subject_uuid($u){return (int)$u===7?'123e4567-e89b-42d3-a456-426614174000':'';}
}
class SA_Professional_Reauthentication{
    public static function verify_and_record($u,$p,$o,$c){return array();}
    public static function assertion($u,$s){return array();}
}
class FakeIntakeDb{
    public $prefix='wp_';public $last_error='';public $canonical='PK';public $broken=false;
    public function prepare($s,$id){return array($s,$id);}
    public function get_var($query){if($this->broken){$this->last_error='synthetic-failure';return null;}return $this->canonical;}
}
$wpdb=new FakeIntakeDb();
class GDO_Application{public static function latest_for_user($u){return null;}}
$owner=dirname(__DIR__).'/.external/file00/source/sabri-membership-core/includes/class-smc-file09-intake-contract.php';
if(!is_file($owner)){fwrite(STDERR,"FAIL: pinned File00 production class checkout unavailable\n");exit(1);}
require $owner;
require dirname(__DIR__).'/includes/class-gdo-membership-adapter.php';
require dirname(__DIR__).'/includes/class-gdo-policy.php';
function ok($v,$msg){global $count;if(!$v){fwrite(STDERR,"FAIL: ".$msg."\n");exit(1);}++$count;echo "PASS: ".$msg."\n";}
$count=0;
$GLOBALS['base']=array(
 'contract_version'=>'1.2.3','user_id'=>7,'application_exists'=>true,
 'account_class'=>'member','membership_type'=>'member',
 'approved_membership_types'=>array('member'),'requested_membership_types'=>array('member','doctor'),
 'status'=>'approved','approved'=>true,'suspended'=>false,
 'eligible'=>false,'professional_verified'=>false,'guardian_verified'=>true,
 'phone_verified'=>true,'email_verified'=>true,'identity_documents_current'=>true,
 'two_factor_ready'=>false,'mfa_required'=>false,'mfa_owner'=>'none'
);
$GLOBALS['app']=array('row_version'=>7,'date_of_birth_enc'=>'1990-01-01','guardian_required'=>false);
$GLOBALS['grants']=array(
 array('membership_type'=>'member','status'=>'approved','expires_at'=>''),
 array('membership_type'=>'doctor','status'=>'pending','expires_at'=>'')
);
$r=SMC_File09_Intake_Contract::assertion(7,array('action'=>'doctor_verification_intake','purpose'=>'professional_credential_application','jurisdiction'=>'PK'));
ok($r['result']==='allow','exact File00 production provider allows pending doctor intake but does not approve doctor');
ok($r['grants_clinical_authorization']===false && $r['grants_professional_verification']===false,'owner emits no clinical or verified grant');
ok(GDO_Membership_Adapter::intake_available(),'exact contract and provider availability detected');
ok(GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'File09 consumer admits File00-approved member with secondary pending doctor role');
$policy=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ok(!empty($policy['eligible']),'File09 onboarding policy admits exact owner assertion');
ok(SMC_CF01_Contract::$clinical_calls===0,'legacy File00 clinical identity link never invoked for professional-intake application');
$GLOBALS['gdo_intake_actor']=0;
ok((SMC_File09_Intake_Contract::assertion(7,array('action'=>'doctor_verification_intake','purpose'=>'professional_credential_application','jurisdiction'=>'PK'))['result']??'')!=='allow','anonymous context cannot produce a foreign File00 doctor-intake allow');
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'File09 consumer refuses anonymous foreign subject');
$GLOBALS['gdo_intake_actor']=8;
ok((SMC_File09_Intake_Contract::assertion(7,array('action'=>'doctor_verification_intake','purpose'=>'professional_credential_application','jurisdiction'=>'PK'))['result']??'')!=='allow','different signed-in principal cannot produce File00 doctor-intake allow');
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'File09 consumer refuses different logged-in subject');
$GLOBALS['gdo_intake_actor']=7;
ok(GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'matched logged-in applicant still admitted for intake only');
$GLOBALS['gdo_actor_switch_during_policy']=true;
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'File09 cannot consume File00 allow after principal switches inside policy hook');
$GLOBALS['gdo_actor_switch_during_policy']=false;
$GLOBALS['gdo_intake_actor']=7;
ok(GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'File09 re-admits only unchanged authenticated principal');
$GLOBALS['grants'][1]['status']='suspended';
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'owner doctor role suspension denies');
$GLOBALS['grants'][1]['status']='pending';
$GLOBALS['grants'][1]['expires_at']=gmdate('Y-m-d H:i:s',time()-180);
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'owner expired doctor role denies');
$GLOBALS['grants'][1]['expires_at']='';
$GLOBALS['base']['phone_verified']=false;
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'owner stale phone verification denies');
$GLOBALS['base']['phone_verified']=true;
$GLOBALS['app']['date_of_birth_enc']='2010-01-01';
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'owner underage professional denies');
$GLOBALS['app']['date_of_birth_enc']='1990-01-01';
$wpdb->canonical='US';
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'owner jurisdiction mismatch denies');
$wpdb->canonical='PK';$wpdb->broken=true;
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'owner database failure denies');
$wpdb->broken=false;$wpdb->last_error='';
SMC_MFA_Retirement::$advanced_ok=false;
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'File00 Advanced Trust containment/reverification hold blocks onboarding intake');
SMC_MFA_Retirement::$advanced_ok=true;
$GLOBALS['base']['status']='suspended';$GLOBALS['base']['suspended']=true;
ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'owner suspended membership denies');
echo "File00 source + File09 consumer joint integration: $count PASS, 0 FAIL\n";
