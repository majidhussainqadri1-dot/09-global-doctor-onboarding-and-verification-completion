<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'SMC_VERSION', '1.2.11' );
define( 'SMC_CONTRACT_VERSION', '1.2.0' );
define( 'SMC_CF01_CONTRACT_VERSION', '1.0.0' );
define( 'SA_PROFESSIONAL_REAUTH_VERSION', '1.0.0' );
$GLOBALS['gdo_current_user']=7; $GLOBALS['gdo_user_caps']=array('smc_review_verification'=>true);
function absint($v){return abs((int)$v);} function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));} function wp_generate_uuid4(){return '123e4567-e89b-42d3-a456-426614174000';} function get_current_user_id(){return 7;} function user_can($u,$c){return !empty($GLOBALS['gdo_user_caps'][$c]);} function apply_filters($h,$v){return $v;} function do_action(){} function __($v){return $v;} function smc_get_profile($u){return array('display_name'=>'Doctor Candidate');} function is_wp_error($v){return $v instanceof WP_Error;}
final class WP_Error{public function __construct($c='',$m=''){}}
final class SMC_Contracts{public static $assertion=array(); public static function assertions($u){return self::$assertion;}}
final class SMC_CF01_Contract{public static $assertion=array(); public static function membership_assertion($u,$c){return self::$assertion;}}
final class SA_Professional_Reauthentication{public static function verify_and_record($u,$p,$o,$c){return array();} public static function assertion($u,$s){return array();} public static function clear_current_session(){}}
require dirname(__DIR__).'/includes/class-gdo-membership-adapter.php';
$tests=0; function ok($c,$m){global $tests;++$tests;if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}
$uuid='123e4567-e89b-42d3-a456-426614174000';
$base=array('contract_version'=>'1.2.0','user_id'=>7,'application_exists'=>true,'account_class'=>'member','membership_type'=>'doctor','approved_membership_types'=>array('doctor'),'status'=>'approved','approved'=>true,'suspended'=>false,'two_factor_ready'=>true,'phone_verified'=>true,'email_verified'=>true,'guardian_verified'=>false,'professional_verified'=>false,'eligible'=>false,'identity_documents_current'=>true);
$subject=array('contract'=>'smc.cf01.membership-assurance','contract_version'=>'1.0.0','result'=>'allow','reason_code'=>'doctor_application_allowed','subject'=>array('platform_uuid'=>$uuid,'record_version'=>3),'membership'=>array('identity_assurance'=>'verified'),'age_context'=>array('known'=>true,'age_years'=>30,'guardian_required'=>false),'jurisdiction_context'=>array('known'=>true,'canonical_country'=>'PK','requested_country'=>'PK','mismatch'=>false),'issued_at'=>gmdate('c',time()-1),'expires_at'=>gmdate('c',time()+60));
SMC_Contracts::$assertion=$base; SMC_CF01_Contract::$assertion=$subject;
ok(GDO_Membership_Adapter::available(),'exact File00 contracts available');
ok(GDO_Membership_Adapter::membership_allows($subject),'allow result recognized');
ok(GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'approved adult doctor candidate accepted');
$deny=$subject;$deny['result']='deny';SMC_CF01_Contract::$assertion=$deny;ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'deny assertion fails closed');
$unknown=$subject;$unknown['result']='unknown';SMC_CF01_Contract::$assertion=$unknown;ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'unknown assertion fails closed');
SMC_CF01_Contract::$assertion=$subject;$identity=$base;$identity['identity_documents_current']=false;SMC_Contracts::$assertion=$identity;ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'stale identity evidence blocks entry');
SMC_Contracts::$assertion=$base;$grant=$base;$grant['approved_membership_types']=array('member');SMC_Contracts::$assertion=$grant;ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'doctor grant required');
SMC_Contracts::$assertion=$base;$young=$subject;$young['age_context']['age_years']=17;SMC_CF01_Contract::$assertion=$young;ok(!GDO_Membership_Adapter::is_active_doctor_candidate(7,'PK'),'under-18 professional blocked');
SMC_CF01_Contract::$assertion=$subject;ok(GDO_Membership_Adapter::can('sabri_verify_doctors',7),'review capability uses File00');
$susp=$base;$susp['suspended']=true;$susp['status']='suspended';SMC_Contracts::$assertion=$susp;ok(!GDO_Membership_Adapter::can('sabri_verify_doctors',7),'suspended reviewer blocked');
echo "File 09 membership adapter: {$tests} PASS, 0 FAIL\n";
