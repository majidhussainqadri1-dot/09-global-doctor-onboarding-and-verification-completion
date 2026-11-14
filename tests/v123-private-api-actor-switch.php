<?php
/** File09 V12.3 F09-NFR-001 — private REST edit DTO actor binding. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
function absint($n){return abs((int)$n);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function is_user_logged_in(){return get_current_user_id()>0;}
function get_current_user_id(){return $GLOBALS['f09_actor'];}
class GDO_Application {
 public static function fields(){return array('display_name','city');}
 public static function completeness($app){
  if(!empty($GLOBALS['f09_switch_during_completeness'])){$GLOBALS['f09_actor']=8;}
  return array('complete'=>true,'missing_fields'=>array(),'missing_evidence'=>array());
 }
}
require dirname(__DIR__).'/includes/class-gdo-api.php';
function ensure($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$app=(object)array(
 'user_id'=>7,'id'=>42,'application_uuid'=>'owner7-app',
 'version'=>1,'row_version'=>6,'state'=>'draft','jurisdiction'=>'PK',
 'application_type'=>'homeopathic_doctor','preferred_language'=>'ur',
 'profile_json'=>'{"display_name":"PRIVATE_DOCTOR_7","city":"private"}',
 'draft_expires_at'=>'2030-01-01','more_info_due_at'=>'','updated_at'=>'2026-01-01');
$GLOBALS['f09_actor']=8;
$a=GDO_API::application_edit_model($app,7);
ensure(empty($a['profile']['display_name']) && !isset($a['application_id']),
 'different signed-in actor never receives applicant private edit model');
$GLOBALS['f09_actor']=7;$GLOBALS['f09_switch_during_completeness']=true;
$b=GDO_API::application_edit_model($app,7);
ensure(empty($b['profile']['display_name']) && !isset($b['application_id']),
 'actor switch in completeness never reveals private application model');
$GLOBALS['f09_actor']=7;$GLOBALS['f09_switch_during_completeness']=false;
$c=GDO_API::application_edit_model($app,7);
ensure(($c['profile']['display_name']??'')==='PRIVATE_DOCTOR_7',
 'authenticated unchanged applicant retains private edit model');
echo "File09 V12.3 private REST actor: 3 PASS, 0 FAIL\n";
