<?php
/** File09 V12.3 F09-NFR-001: private draft mutation actor isolation. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
function absint($n){return abs((int)$n);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function __($s,$d=''){return $s;}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function current_time($format,$gmt=false){return '2026-11-14 16:00:00';}
function is_user_logged_in(){return get_current_user_id()>0;}
function get_current_user_id(){return $GLOBALS['f09_actor'];}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error {public $code;public function __construct($code,$msg=''){$this->code=$code;}}
class GDO_Schema {public static function table($name){return 'wp_gdo_'.$name;}}
class GDO_Operations {public static function mutation_allowed(){return true;}}
class GDO_Policy {
 const VERSION='2026-08-07.2';
 public static function normalize_jurisdiction($v){return $v;}
 public static function required_fields($jur='',$type=''){return array();}
}
class GDO_Risk {public static function identity_fingerprint($profile){return 'fixture';}}
class GDO_Membership_Adapter {
 public static function is_active_doctor_candidate($u,$jur=''){
  if(!empty($GLOBALS['f09_switch_during_candidate'])){$GLOBALS['f09_actor']=8;}
  return true;
 }
}
class FakeDB {
 public $last_error='';public $writes=0;public $app;
 public function prepare($s,...$args){return $s;}
 public function get_row($q){return $this->app;}
 public function query($q){if(strpos($q,'UPDATE ')===0){++$this->writes;}return 1;}
}
$wpdb=new FakeDB();
$wpdb->app=(object)array('id'=>42,'user_id'=>7,'state'=>'draft','jurisdiction'=>'PK','application_type'=>'homeopathic_doctor','row_version'=>1);
require dirname(__DIR__).'/includes/class-gdo-application.php';
function expect($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$profile=array('display_name'=>'PRIVATE_DOCTOR_7','license_jurisdiction'=>'PK');
$GLOBALS['f09_actor']=8;
$a=GDO_Application::save_draft(42,7,$profile,1,true);
expect(is_wp_error($a) && $wpdb->writes===0,
 'other active WordPress actor cannot mutate private draft for user 7');
$GLOBALS['f09_actor']=7;$GLOBALS['f09_switch_during_candidate']=true;
$b=GDO_Application::save_draft(42,7,$profile,1,true);
expect(is_wp_error($b) && $wpdb->writes===0,
 'active actor switched by File00 candidate hook cannot write prior applicant draft');
$GLOBALS['f09_actor']=7;$GLOBALS['f09_switch_during_candidate']=false;
$c=GDO_Application::save_draft(42,7,$profile,1,true);
expect($c===true && $wpdb->writes===1,
 'unchanged authenticated applicant can write own draft');
echo "File09 draft mutation actor: 3 PASS, 0 FAIL\n";
