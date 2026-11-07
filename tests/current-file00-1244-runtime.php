<?php
/**
 * File09 current File00 1.2.44 contracts + retired File00 MFA, fail-first.
 * No real user, no live or staging claim. File02 step-up still owns review actions.
 */
define('ABSPATH', __DIR__.'/');
define('SMC_VERSION','1.2.44');
define('SMC_CONTRACT_VERSION','1.2.3');
define('SMC_CF01_CONTRACT_VERSION','1.1.0');
define('SA_PROFESSIONAL_REAUTH_VERSION','1.0.0');
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function is_wp_error($v){return $v instanceof WP_Error;}
function __($s,$d=''){return $s;}
function wp_generate_uuid4(){return '123e4567-e89b-42d3-a456-426614174000';}
final class WP_Error{public function __construct($code='',$message=''){} }
final class SMC_Contracts{public static $out;public static function assertions($user_id){return self::$out;}}
final class SMC_CF01_Contract{public static $out;public static function membership_assertion($user_id,$context){return self::$out;}}
final class SA_Professional_Reauthentication{public static function verify_and_record($u,$p,$o,$c){return array();}public static function assertion($u,$s){return array();}}
require dirname(__DIR__).'/includes/class-gdo-membership-adapter.php';
function check_f09(bool $good,string $message){if(!$good){fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}}
$base=array(
    'contract_version'=>'1.2.3','user_id'=>7,'application_exists'=>true,
    'account_class'=>'member','membership_type'=>'doctor',
    'approved_membership_types'=>array('doctor'),'status'=>'approved',
    'approved'=>true,'suspended'=>false,'eligible'=>false,
    'two_factor_ready'=>false,'mfa_required'=>false,'mfa_owner'=>'none',
    'phone_verified'=>true,'email_verified'=>true,'guardian_verified'=>true,
    'professional_verified'=>false,'identity_documents_current'=>true
);
$uuid='123e4567-e89b-42d3-a456-426614174000';
$subject=array(
    'contract'=>'smc.cf01.membership-assurance','contract_version'=>'1.1.0',
    'result'=>'deny','subject'=>array('platform_uuid'=>$uuid,'record_version'=>7),
    'membership'=>array('status'=>'approved'),
    'age_context'=>array('known'=>true,'age_years'=>30),
    'jurisdiction_context'=>array('known'=>true,'mismatch'=>false),
    'issued_at'=>gmdate('c',time()-1),'expires_at'=>gmdate('c',time()+60)
);
SMC_Contracts::$out=$base;
SMC_CF01_Contract::$out=$subject;
check_f09(GDO_Membership_Adapter::available(),'current File00 version should be accepted by strict compatible major/minimum bounds');
check_f09(GDO_Membership_Adapter::authentication_available(),'File02 review step-up must remain available');
check_f09(count(GDO_Membership_Adapter::base_assertion(7))>0,'current File00 complete base assertion should validate');
check_f09(count(GDO_Membership_Adapter::membership_assertion(7))>0,'current CF01 deny decision must validate structurally without being upgraded to allow');
check_f09(GDO_Membership_Adapter::membership_allows(GDO_Membership_Adapter::membership_assertion(7))===false,'File00 CF01 deny decision must remain deny');
check_f09(GDO_Membership_Adapter::identity_assurance_current_checked(7)===true,'File00 retired MFA must not permanently block verified membership identity with File02 step-up available');
$invalid=$base;$invalid['contract_version']='2.0.0';SMC_Contracts::$out=$invalid;
check_f09(GDO_Membership_Adapter::base_assertion(7)===array(),'unknown future File00 major must fail closed');
SMC_Contracts::$out=$base;$invalid=$subject;$invalid['contract_version']='2.0.0';SMC_CF01_Contract::$out=$invalid;
check_f09(GDO_Membership_Adapter::membership_assertion(7)===array(),'unknown future CF01 major must fail closed');
SMC_CF01_Contract::$out=$subject;
$unverified=$base;$unverified['identity_documents_current']=false;SMC_Contracts::$out=$unverified;
check_f09(GDO_Membership_Adapter::identity_assurance_current_checked(7)===false,'stale identity documentation must fail closed');
$unverified=$base;$unverified['phone_verified']=false;SMC_Contracts::$out=$unverified;
check_f09(GDO_Membership_Adapter::identity_assurance_current_checked(7)===false,'unverified phone must fail closed');
echo "PASS File09 current File00/02 contract compatibility and identity assurance security\n";
