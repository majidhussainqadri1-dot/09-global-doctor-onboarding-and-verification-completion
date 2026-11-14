<?php
/**
 * File 09 RED-first behavioural check: File 00 1.2.3+ retired MFA must not
 * veto a genuinely permitted doctor-onboarding context; File 02 remains owner.
 * A separate producer/consumer test must prove File 00's real application
 * assertion, which may currently deny pre-verification: do not bypass it.
 */
define('ABSPATH', __DIR__.'/');
function absint($v){ return abs((int)$v); }
function sanitize_key($v){ return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)); }
function wp_generate_uuid4(){ return '123e4567-e89b-42d3-a456-426614174000'; }
// Existing MFA test models the authenticated applicant, not an anonymous caller.
function get_current_user_id(){ return 7; }
function is_user_logged_in(){ return true; }
function apply_filters($hook,$value){ return $value; }
function wp_strip_all_tags($v){ return strip_tags((string)$v); }
function __($v,$d=''){return $v;}
final class GDO_Membership_Adapter {
    public static $base=array(),$subject=array(),$factor_ready=true;
    public static function available(){return true;} public static function intake_available(){return true;} public static function intake_assertion($u,$jurisdiction=''){return self::$subject;}
    public static function base_assertion($u){return self::$base;}
    public static function membership_assertion($u,$action='',$purpose='',$jurisdiction=''){return self::$subject;}
    public static function membership_allows($v){return is_array($v)&&isset($v['result'])&&'allow'===$v['result'];}
    public static function sanctioned($u){return false;}
    public static function identity_assurance_current_checked($u){return self::$factor_ready;}
}
final class GDO_Application { public static function latest_for_user($u){return null;} }
$wpdb=(object)array('last_error'=>'');
require dirname(__DIR__).'/includes/class-gdo-policy.php';
function gate($condition,$message){if(!$condition){fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}echo "PASS: ".$message."\n";}
GDO_Membership_Adapter::$base=array(
    'application_exists'=>true,'status'=>'approved','membership_type'=>'doctor',
    'approved'=>true,'approved_membership_types'=>array('doctor'),
    'requested_membership_types'=>array('doctor'),
    'identity_documents_current'=>true,'email_verified'=>true,'phone_verified'=>true,
    'two_factor_ready'=>false,'mfa_required'=>false,'mfa_owner'=>'none'
);
GDO_Membership_Adapter::$subject=array(
    'result'=>'allow','age_context'=>array('known'=>true,'age_years'=>30),
    'subject'=>array('platform_uuid'=>'123e4567-e89b-42d3-a456-426614174000','record_version'=>7)
);
$r=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
gate(!empty($r['eligible'])&&'eligible'===$r['reason_code'],'retired File00 MFA must not block approved doctor application when current File02 factor readiness is available');
GDO_Membership_Adapter::$factor_ready=false;
$r=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
gate(empty($r['eligible'])&&'identity_assurance_incomplete'===$r['reason_code'],'lost current File02 factor readiness must deny doctor application');
GDO_Membership_Adapter::$factor_ready=true;
GDO_Membership_Adapter::$subject['result']='deny';
$r=GDO_Policy::eligibility(7,array('jurisdiction'=>'PK'));
gate(empty($r['eligible'])&&'membership_context_denied'===$r['reason_code'],'File00 denial is never upgraded to allow by File09');
echo "File09 retired-MFA onboarding policy: 3 PASS, 0 FAIL\n";
