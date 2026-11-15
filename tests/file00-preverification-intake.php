<?php
/** File09 RED-first consumer contract with pending doctor role + strict denials. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
define('SMC_VERSION','1.2.44');
define('SMC_CONTRACT_VERSION','1.2.3');
define('SMC_CF01_CONTRACT_VERSION','1.1.0');
define('SMC_FILE09_INTAKE_CONTRACT_VERSION','1.0.0');
define('SA_PROFESSIONAL_REAUTH_VERSION','1.0.0');
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function wp_generate_uuid4(){return '123e4567-e89b-42d3-a456-426614174000';}
function __($s,$d=''){return $s;}
function is_wp_error($v){return $v instanceof WP_Error;}
$GLOBALS['gdo_actor_switch_on_policy_filter']=false;
function apply_filters($h,$v,...$args){if($h==='gdo_eligibility_result' && !empty($GLOBALS['gdo_actor_switch_on_policy_filter'])){$GLOBALS['gdo_current_actor']=8;}return $v;}
$GLOBALS['gdo_current_actor']=7;
function get_current_user_id(){return (int)$GLOBALS['gdo_current_actor'];}
function is_user_logged_in(){return get_current_user_id()>0;}
function wp_strip_all_tags($v){return strip_tags((string)$v);}
class WP_Error{public function __construct($code='',$msg=''){} }
class SMC_Contracts{public static $base=array();public static function assertions($u){return self::$base;}}
class SMC_CF01_Contract{public static $legacy_calls=0;public static function membership_assertion($u,$c){++self::$legacy_calls;return array('result'=>'deny');}}
class SMC_File09_Intake_Contract{public static $assertion=array();public static function assertion($u,$c){return self::$assertion;}}
class SA_Professional_Reauthentication{public static function verify_and_record($u,$p,$o,$c){return array();}public static function assertion($u,$s){return array();}}
class GDO_Application{public static $switch_actor_on_lookup=false;public static $latest_state='';public static function latest_for_user($u){if(self::$switch_actor_on_lookup){$GLOBALS['gdo_current_actor']=8;return (object)array('state'=>'submitted','id'=>42);}return self::$latest_state ? (object)array('state'=>self::$latest_state,'id'=>42) : null;}}
$wpdb=(object)array('last_error'=>'');
require dirname(__DIR__).'/includes/class-gdo-membership-adapter.php';
require dirname(__DIR__).'/includes/class-gdo-policy.php';
function ensure($good,$label){global $passes;if(!$good){fwrite(STDERR,"FAIL: $label\n");exit(1);}++$passes;echo "PASS: $label\n";}
$passes=0;
$uuid='123e4567-e89b-42d3-a456-426614174000';
$base=array(
 'contract_version'=>'1.2.3','user_id'=>7,'application_exists'=>true,
 'account_class'=>'member','membership_type'=>'doctor',
 'approved_membership_types'=>array(),'requested_membership_types'=>array('doctor'),
 'status'=>'approved','approved'=>true,'suspended'=>false,
 'eligible'=>false,'professional_verified'=>false,'two_factor_ready'=>false,
 'mfa_required'=>false,'mfa_owner'=>'none','phone_verified'=>true,
 'email_verified'=>true,'guardian_verified'=>true,'identity_documents_current'=>true
);
$ok=array(
 'contract'=>'smc.file09.doctor-intake','contract_version'=>'1.0.0',
 'producer_version'=>'1.2.44','issued_at'=>gmdate('c',time()-1),
 'expires_at'=>gmdate('c',time()+60),'action'=>'doctor_verification_intake',
 'purpose'=>'professional_credential_application',
 'authorization_scope'=>'file09_doctor_application_intake_only',
 'result'=>'allow','reason_code'=>'doctor_intake_prerequisites_satisfied',
 'grants_clinical_authorization'=>false,'grants_professional_verification'=>false,
 'grants_patient_record_access'=>false,'grants_prescription_authority'=>false,
 'grants_public_badge'=>false,'grants_appointment_authority'=>false,
 'subject'=>array('platform_uuid'=>$uuid,'record_version'=>7,'source_owner'=>'File 00'),
 'age_context'=>array('known'=>true,'age_years'=>30),
 'jurisdiction_context'=>array('known'=>true,'canonical_country'=>'PK','requested_country'=>'PK','mismatch'=>false),
 'role_scope'=>array('doctor_role_requested'=>true,'professional_verification_required'=>true)
);
SMC_Contracts::$base=$base;
SMC_File09_Intake_Contract::$assertion=$ok;
ensure(GDO_Membership_Adapter::intake_available(),'File00 dedicated intake contract available');
ensure(GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'unverified approved membership with pending doctor role can request verification');
$r=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ensure(($r['eligible']??false)===true && ($r['reason_code']??'')==='eligible','File09 application policy recognizes limited File00 intake contract');
ensure(SMC_CF01_Contract::$legacy_calls===0,'no clinical identity link was queried to admit File09 intake');
$GLOBALS['gdo_current_actor']=0;
ensure(empty(GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'))['eligible']),'anonymous caller cannot inspect private applicant eligibility');
$GLOBALS['gdo_current_actor']=8;
ensure(empty(GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'))['eligible']),'different signed-in actor cannot evaluate the applicant-only workflow');
$GLOBALS['gdo_current_actor']=7;
ensure(!empty(GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'))['eligible']),'restored exact applicant actor remains eligible');
GDO_Application::$switch_actor_on_lookup=true;
$switched=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ensure(empty($switched['eligible']) && !isset($switched['application_id']) && ($switched['reason_code']??'')==='applicant_actor_changed','actor switch during application lookup cannot disclose private application identifier');
GDO_Application::$switch_actor_on_lookup=false;
$GLOBALS['gdo_current_actor']=7;
$GLOBALS['gdo_actor_switch_on_policy_filter']=true;
$filtered=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ensure(empty($filtered['eligible']) && !isset($filtered['subject_uuid']) && ($filtered['reason_code']??'')==='applicant_actor_changed','actor switch during final policy filter cannot expose private UUID');
$GLOBALS['gdo_actor_switch_on_policy_filter']=false;
$GLOBALS['gdo_current_actor']=7;
// Rejected and already-submitted applications may disclose their private row ID
// to the real applicant, but must never reveal that ID to another current actor.
GDO_Application::$latest_state='submitted';
$self_submitted=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ensure(empty($self_submitted['eligible']) && ($self_submitted['reason_code']??'')==='active_application_exists' && ($self_submitted['application_id']??0)===42,'existing submitted application ID is returned only to its authenticated owner');
$GLOBALS['gdo_current_actor']=8;
$foreign_submitted=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ensure(empty($foreign_submitted['eligible']) && !isset($foreign_submitted['application_id']) && !isset($foreign_submitted['subject_uuid']) && ($foreign_submitted['reason_code']??'')==='applicant_actor_mismatch','foreign actor cannot inspect an existing submitted applicant private row');
$GLOBALS['gdo_current_actor']=7;
GDO_Application::$latest_state='rejected';
$self_rejected=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ensure(empty($self_rejected['eligible']) && ($self_rejected['reason_code']??'')==='appeal_or_lifecycle_action_required' && ($self_rejected['application_id']??0)===42,'legitimate rejected applicant can find their private appeal/lifecycle row');
$GLOBALS['gdo_current_actor']=0;
$anonymous_rejected=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ensure(empty($anonymous_rejected['eligible']) && !isset($anonymous_rejected['application_id']) && ($anonymous_rejected['reason_code']??'')==='applicant_actor_mismatch','anonymous actor cannot inspect rejected application identity');
GDO_Application::$latest_state='';
$GLOBALS['gdo_current_actor']=7;
$multi=$base; $multi['membership_type']='member';SMC_Contracts::$base=$multi;
ensure(GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'approved ordinary member may enter intake with a separately pending doctor role');
$multi_result=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ensure(!empty($multi_result['eligible']),'multi-role doctor application must not depend on the primary membership role');
SMC_Contracts::$base=$base;
SMC_File09_Intake_Contract::$assertion=array_merge($ok,array('result'=>'deny','reason_code'=>'doctor_role_restricted'));
ensure(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'File00 intake deny must be respected');
$r=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
ensure(($r['eligible']??false)===false,'File09 policy never upgrades File00 deny');
SMC_File09_Intake_Contract::$assertion=$ok;
foreach (array(
 array('contract_version','2.0.0','future unknown intake major'),
 array('authorization_scope','clinical_read','widened authorization scope'),
 array('grants_clinical_authorization',true,'clinical authorization grant'),
 array('grants_public_badge',true,'public verified badge grant'),
 array('expires_at',gmdate('c',time()-30),'expired assertion'),
 array('issued_at',gmdate('c',time()+700),'future assertion'),
 array('action','clinical_identity_link','clinical action confusion'),
 array('purpose','patient_care','wrong consumer purpose')
) as $v){
 $bad=$ok;$bad[$v[0]]=$v[1];SMC_File09_Intake_Contract::$assertion=$bad;
 ensure(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),$v[2].' rejected');
}
SMC_File09_Intake_Contract::$assertion=$ok;
$bad=$ok;$bad['subject']['platform_uuid']='00000000-0000-0000-0000-000000000000';SMC_File09_Intake_Contract::$assertion=$bad;
ensure(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'invalid subject UUID rejected');
SMC_File09_Intake_Contract::$assertion=$ok;
$bad=$ok;$bad['jurisdiction_context']['canonical_country']='US';SMC_File09_Intake_Contract::$assertion=$bad;
ensure(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'jurisdiction mismatch rejected');
SMC_File09_Intake_Contract::$assertion=$ok;
$bad=$ok;$bad['age_context']['age_years']=17;SMC_File09_Intake_Contract::$assertion=$bad;
ensure(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'underage applicant rejected');
SMC_File09_Intake_Contract::$assertion=$ok;
$bad=$base;$bad['phone_verified']=false;SMC_Contracts::$base=$bad;
ensure(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'stale identity contact fails closed');
SMC_Contracts::$base=$base;
$bad=$base;$bad['status']='suspended';$bad['suspended']=true;SMC_Contracts::$base=$bad;
ensure(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'suspended membership denied');
SMC_Contracts::$base=$base;
$bad=$base;$bad['requested_membership_types']=array();SMC_Contracts::$base=$bad;
ensure(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'missing doctor role request denied');
SMC_Contracts::$base=$base;
echo "File09 File00 preverification intake consumer: $passes PASS, 0 FAIL\n";
