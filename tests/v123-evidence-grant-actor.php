<?php
/**
 * V12.3 F09-FR-008 / F09-AT-23: reviewer-only evidence grant is
 * bound to actual active actor across callback/rate-limit and audit hooks.
 * RED-first deterministic PHP class test, not a real WordPress deployment.
 */
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
define('HOUR_IN_SECONDS',3600);
function absint($x){return abs((int)$x);}
function sanitize_key($x){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$x));}
function sanitize_textarea_field($v){return (string)$v;}
function wp_trim_words($text,$limit=55,$more=''){return implode(' ',array_slice(explode(' ',trim((string)$text)),0,$limit));}
function __($s,$d=''){return $s;}
function is_wp_error($v){return $v instanceof WP_Error;}
function is_user_logged_in(){return get_current_user_id()>0;}
function get_current_user_id(){return $GLOBALS['f09_review_actor'];}
function wp_generate_password($size=64,$special=false,$extra=false){return str_repeat('A',$size);}
function wp_get_session_token(){return 'fixture-session';}
function current_time($format,$gmt=true){return '2026-11-14 16:00:00';}
class WP_Error{
 public $code;public function __construct($code,$msg=''){$this->code=$code;}
}
class GDO_Schema{public static function table($name){return 'wp_gdo_'.$name;}}
class GDO_Application{
 public static function get($id){return (object)array('id'=>42,'user_id'=>99);}
}
class GDO_Membership_Adapter{
 public static function reviewer_scope_allows($reviewer,$applicant,$id){return true;}
 public static function reviewer_case_allows($reviewer,$applicant,$id){return true;}
 public static function recent_step_up($id){return $id===get_current_user_id();}
 public static function can($cap,$id){return true;}
}
class GDO_Rate_Limiter{
 public static function hit($key,$limit,$period){
  if(!empty($GLOBALS['f09_switch_on_rate_limit'])){$GLOBALS['f09_review_actor']=8;}
  return true;
 }
}
class GDO_Audit{
 public static function access($app,$evidence,$reviewer,$purpose,$result){
  if($result==='grant_issued'&&!empty($GLOBALS['f09_switch_on_audit'])){$GLOBALS['f09_review_actor']=8;}
  return true;
 }
}
class GDO_Notifications{
 public static function queue($event,$user,$context,$process=false){
  if(!empty($GLOBALS['f09_switch_on_notification'])){$GLOBALS['f09_review_actor']=8;}
  return 'fixture-event-uuid';
 }
 public static function process($count,$event){
  if(!empty($GLOBALS['f09_switch_on_process'])){$GLOBALS['f09_review_actor']=8;}
  return true;
 }
}
class GrantDB{
 public $last_error='';public $inserts=0;public $deletes=0;public $updates=0;public $rolled_back=0;
 public function prepare($query,...$params){return $query;}
 public function get_row($sql){
  if(strpos($sql,'access_grants')!==false){
    return (object)array('id'=>55,'reviewer_id'=>7,'evidence_id'=>33,
      'session_digest'=>hash('sha256','7|fixture-session'),'mode'=>'view',
      'used_at'=>null,'expires_at'=>gmdate('Y-m-d H:i:s',time()+300));
  }
  return (object)array('id'=>33,'application_id'=>42,'document_type'=>'license');
 }
 public function query($q){if($q==='ROLLBACK'){++$this->rolled_back;}return 1;}
 public function insert($table,$data,$fmt=array()){++$this->inserts;return 1;}
 public function update($table,$data,$where,$formats=array(),$where_format=array()){++$this->updates;return 1;}
 public function delete($table,$where,$format=array()){++$this->deletes;return 1;}
}
$wpdb=new GrantDB();
require dirname(__DIR__).'/includes/class-gdo-evidence.php';
function verdict($truth,$message){
 if(!$truth){fwrite(STDERR,"FAIL: $message\n");exit(1);}
 echo "PASS: $message\n";
}
$GLOBALS['f09_review_actor']=8;$GLOBALS['f09_switch_on_rate_limit']=false;
$grant=GDO_Evidence::issue_view_grant(33,7,'credential_review','view');
verdict(is_wp_error($grant)&&$wpdb->inserts===0,
 'other WordPress actor cannot mint a reviewer 7 credential-view token');
$GLOBALS['f09_review_actor']=7;$GLOBALS['f09_switch_on_rate_limit']=true;
$grant=GDO_Evidence::issue_view_grant(33,7,'credential_review','view');
verdict(is_wp_error($grant)&&$wpdb->inserts===0,
 'actor change after successful reviewer step-up does not mint a private token');
$GLOBALS['f09_review_actor']=7;$GLOBALS['f09_switch_on_rate_limit']=false;
$GLOBALS['f09_switch_on_audit']=true;
$grant=GDO_Evidence::issue_view_grant(33,7,'credential_review','view');
verdict(is_wp_error($grant)&&$wpdb->deletes===1,
 'actor change in audit does not return an already minted credential grant');
$GLOBALS['f09_review_actor']=7;$GLOBALS['f09_switch_on_audit']=false;
$grant=GDO_Evidence::issue_view_grant(33,7,'credential_review','view');
verdict(is_array($grant)&&!empty($grant['token'])&&$wpdb->inserts===2,
 'same authenticated reviewer still obtains a properly audited token');
$GLOBALS['f09_review_actor']=7;$GLOBALS['f09_switch_on_notification']=true;
$consumed=GDO_Evidence::consume_view_grant('fixture-private-token',7,'view');
verdict(is_wp_error($consumed) && $wpdb->rolled_back>=1,
 'reviewer actor switching during private access notification aborts grant consumption');
$GLOBALS['f09_review_actor']=7;$GLOBALS['f09_switch_on_notification']=false;
$GLOBALS['f09_switch_on_process']=true;
$consumed=GDO_Evidence::consume_view_grant('fixture-private-token',7,'view');
verdict(is_wp_error($consumed),
 'reviewer actor switching during post-commit notification never receives private record');
$GLOBALS['f09_review_actor']=7;$GLOBALS['f09_switch_on_process']=false;
$consumed=GDO_Evidence::consume_view_grant('fixture-private-token',7,'view');
verdict(is_array($consumed) && ($consumed['record']->id??0)===33,
 'unchanged reviewer can consume grant with current step-up and case relation');
echo "V12.3 private evidence grant actor binding: 7 PASS, 0 FAIL\n";
