<?php
/** File09 V12.3 F09-NFR-001: private consent and submission actor precheck. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
function absint($n){return abs((int)$n);}
function __($s,$d=''){return $s;}
function is_user_logged_in(){return get_current_user_id()>0;}
function get_current_user_id(){return $GLOBALS['f09_actor'];}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error {public $code;public function __construct($code,$msg=''){$this->code=$code;}}
class GDO_Schema {public static function table($n){return 'wp_gdo_'.$n;}}
class GDO_Operations {public static function mutation_allowed(){return true;}}
class FakeDB {
 public $last_error='';public $begins=0;public $rollbacks=0;
 public function prepare($q,...$a){return $q;}
 public function get_row($q){return null;}
 public function query($q){if($q==='START TRANSACTION'){++$this->begins;}if($q==='ROLLBACK'){++$this->rollbacks;}return 1;}
}
$wpdb=new FakeDB();
require dirname(__DIR__).'/includes/class-gdo-application.php';
function check($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL: $msg\n");exit(1);}echo "PASS: $msg\n";}
$GLOBALS['f09_actor']=8;
$r=GDO_Application::record_consent(42,7,true,true);
check(is_wp_error($r) && $wpdb->begins===0,
 'foreign active actor cannot begin another applicant consent transaction');
$r=GDO_Application::submit(42,7,1);
check(is_wp_error($r) && $wpdb->begins===0,
 'foreign active actor cannot begin another applicant submission transaction');
$GLOBALS['f09_actor']=7;
$r=GDO_Application::record_consent(42,7,true,true);
check(is_wp_error($r) && $wpdb->begins===1 && $wpdb->rollbacks>=1,
 'real applicant passes actor gate; missing application is rejected by normal checks');
$r=GDO_Application::submit(42,7,1);
check(is_wp_error($r) && $wpdb->begins===2 && $wpdb->rollbacks>=2,
 'real applicant passes submission actor gate; missing row is rejected safely');
echo "File09 V12.3 consent/submit actor: 4 PASS, 0 FAIL\n";
