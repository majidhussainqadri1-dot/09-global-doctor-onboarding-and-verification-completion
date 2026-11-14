<?php
/** V12.3 F09-FR-003/F09-NFR-001: private frontend status actor-switch regression. */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
function __($s,$d=''){return $s;}
function esc_html__($s,$d=''){return $s;}
function esc_html_e($s,$d=''){echo htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function absint($v){return abs((int)$v);}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s));}
function is_wp_error($v){return $v instanceof WP_Error;}
function is_user_logged_in(){return get_current_user_id()>0;}
function get_current_user_id(){return $GLOBALS['f09_actor'];}
class WP_Error {
  private $msg;
  public function __construct($c,$m=''){ $this->msg=$m; }
  public function get_error_message(){return $this->msg;}
}
class GDO_Application {
  public static function latest_for_user($u){
    if(!empty($GLOBALS['switch_on_lookup'])){$GLOBALS['f09_actor']=8;}
    return (object)array('id'=>42,'state'=>'submitted','version'=>1,
      'verified_until'=>'PRIVATE_UNTIL_42','claim_status'=>'PRIVATE_CLAIM_42','claim_version'=>1);
  }
}
class GDO_Policy {
  public static function eligibility($u){
    if(!empty($GLOBALS['switch_on_policy'])){$GLOBALS['f09_actor']=8;}
    return array('eligible'=>false,'reason_code'=>'applicant_actor_mismatch');
  }
}
class GDO_State {public static function can_transition($a,$b){if(!empty($GLOBALS['switch_during_status_panel'])){$GLOBALS['f09_actor']=8;}return false;}}
$wpdb=(object)array('last_error'=>'');
require dirname(__DIR__) . '/includes/class-gdo-frontend.php';
function check($ok,$message){
  if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
  echo "PASS: $message\n";
}
$GLOBALS['f09_actor']=7;$GLOBALS['switch_on_lookup']=true;$GLOBALS['switch_on_policy']=false;
$a=(new GDO_Frontend())->form();
check(strpos($a,'PRIVATE_CLAIM_42')===false && strpos($a,'PRIVATE_UNTIL_42')===false,
  'actor switch during latest application query does not disclose applicant status');
$GLOBALS['f09_actor']=7;$GLOBALS['switch_on_lookup']=false;$GLOBALS['switch_on_policy']=true;
$b=(new GDO_Frontend())->form();
check(strpos($b,'PRIVATE_CLAIM_42')===false && strpos($b,'PRIVATE_UNTIL_42')===false,
  'actor switch during eligibility filter does not disclose applicant status');
$GLOBALS['f09_actor']=7;$GLOBALS['switch_on_policy']=false;
$GLOBALS['switch_during_status_panel']=true;
$status=(new GDO_Frontend())->form();
check(strpos($status,'PRIVATE_CLAIM_42')===false && strpos($status,'PRIVATE_UNTIL_42')===false,
  'actor switch during buffered status panel does not expose private metadata');
$GLOBALS['f09_actor']=7;$GLOBALS['switch_during_status_panel']=false;
$c=(new GDO_Frontend())->form();
check(strpos($c,'PRIVATE_CLAIM_42')!==false,
  'unchanged authenticated applicant retains own private submitted status');
echo "File09 frontend actor-switch regression: 4 PASS, 0 FAIL\n";
