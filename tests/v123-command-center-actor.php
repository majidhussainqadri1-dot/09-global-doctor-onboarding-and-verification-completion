<?php
/**
 * File09 V12.3 F09-AT-21: applicant-only private Command Center actor safety.
 * RED-first fixture against the actual GDO_Advanced_Trust PHP owner class.
 * This is not a WordPress installed/DB or Live proof.
 */
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
define('ARRAY_A','ARRAY_A');
function absint($value){return abs((int)$value);}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s));}
function __($s,$d=''){return $s;}
function esc_html__($s,$d=''){return $s;}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_html_e($s,$d=''){
 if(!empty($GLOBALS['f09_switch_during_shortcode']) && $s==='Professional Verification Command Center'){$GLOBALS['f09_active_actor']=8;}
 echo esc_html($s);
}
function is_wp_error($v){return $v instanceof WP_Error;}
function is_user_logged_in(){return get_current_user_id()>0;}
function get_current_user_id(){return $GLOBALS['f09_active_actor'];}
class WP_Error {
 public $code;
 public function __construct($code,$message=''){$this->code=$code;}
}
class GDO_Application {
 public static $reads=0;
 public static function latest_for_user($id){
   ++self::$reads;
   if(!empty($GLOBALS['f09_switch_on_latest'])){$GLOBALS['f09_active_actor']=8;}
   return (object)array('id'=>42,'user_id'=>7,'state'=>'more_information',
     'version'=>1,'verified_until'=>'PRIVATE_DATE_42',
     'more_info_due_at'=>'PRIVATE_DEADLINE_42');
 }
 public static function completeness($app){
   if(!empty($GLOBALS['f09_switch_on_complete'])){$GLOBALS['f09_active_actor']=8;}
   return array('query_error'=>false,'missing_fields'=>array('PRIVATE_FIELD_42'),
                'missing_evidence'=>array('PRIVATE_EVIDENCE_42'));
 }
 public static function verification_record_for_user($user){return null;}
}
class MockWpdb {
 public $last_error='';public $prefix='wp_';
 public function prepare($sql,...$args){return $sql;}
 public function get_results($sql,$format=null){
   if(!empty($GLOBALS['f09_switch_on_checks'])){$GLOBALS['f09_active_actor']=8;}
   return array(array('check_type'=>'qualification','status'=>'pending',
                      'checked_at'=>'2026-11-14','expires_at'=>''));
 }
}
$wpdb=new MockWpdb();
require dirname(__DIR__).'/includes/class-gdo-advanced-trust.php';
function assert_case($truth,$message){
 if(!$truth){fwrite(STDERR,"FAIL: $message\n");exit(1);}
 echo "PASS: $message\n";
}
function safe_denial($r){
 return is_wp_error($r) || (is_array($r) && empty($r['application']) && empty($r['checks']));
}
$GLOBALS['f09_active_actor']=8;$GLOBALS['f09_switch_on_latest']=false;
$before=GDO_Application::$reads;$r=GDO_Advanced_Trust::command_center(7);
assert_case(safe_denial($r) && GDO_Application::$reads===$before,
  'foreign actor is denied before private command center database lookup');
$GLOBALS['f09_active_actor']=0;
$before=GDO_Application::$reads;$r=GDO_Advanced_Trust::command_center(7);
assert_case(safe_denial($r) && GDO_Application::$reads===$before,
  'anonymous actor is denied before private command center lookup');
$GLOBALS['f09_active_actor']=7;$GLOBALS['f09_switch_on_latest']=true;
$r=GDO_Advanced_Trust::command_center(7);
assert_case(safe_denial($r),
  'actor switch during latest application lookup does not return private command center data');
$GLOBALS['f09_active_actor']=7;$GLOBALS['f09_switch_on_latest']=false;
$GLOBALS['f09_switch_on_complete']=true;
$r=GDO_Advanced_Trust::command_center(7);
assert_case(safe_denial($r),
  'actor switch during completeness does not expose private pending fields');
$GLOBALS['f09_active_actor']=7;$GLOBALS['f09_switch_on_complete']=false;
$GLOBALS['f09_switch_on_checks']=true;
$r=GDO_Advanced_Trust::command_center(7);
assert_case(safe_denial($r),
  'actor switch during provider checks does not expose private evidence or status');
$GLOBALS['f09_active_actor']=7;$GLOBALS['f09_switch_on_checks']=false;
$r=GDO_Advanced_Trust::command_center(7);
assert_case(is_array($r) && ($r['application']['id']??0)===42
 && ($r['completion']['missing_fields'][0]??'')==='PRIVATE_FIELD_42',
  'unchanged authenticated applicant retains own Command Center');
$GLOBALS['f09_active_actor']=7;
$GLOBALS['f09_switch_during_shortcode']=true;
$html=GDO_Advanced_Trust::command_center_shortcode();
assert_case(strpos($html,'PRIVATE_DEADLINE_42')===false,
 'actor switch during shortcode rendering never returns private Command Center markup');
$GLOBALS['f09_active_actor']=7;$GLOBALS['f09_switch_during_shortcode']=false;
$html=GDO_Advanced_Trust::command_center_shortcode();
assert_case(strpos($html,'PRIVATE_DEADLINE_42')!==false,
 'unchanged current applicant retains private Command Center shortcode details');
echo "File09 V12.3 Command Center actor: 8 PASS, 0 FAIL\n";
