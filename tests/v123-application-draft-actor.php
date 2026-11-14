<?php
/** File09 V12.3 RED-first: owner-only existing draft recovery. */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
function absint($x){return abs((int)$x);}
function __($s,$d=''){return $s;}
function is_wp_error($r){return $r instanceof WP_Error;}
$GLOBALS['draft_actor']=7;
$GLOBALS['draft_switch_on_db_lookup']=false;
function is_user_logged_in(){return (int)$GLOBALS['draft_actor'] > 0;}
function get_current_user_id(){return (int)$GLOBALS['draft_actor'];}
class WP_Error {
    public $code;
    public function __construct($code='',$message=''){$this->code=$code;}
    public function get_error_code(){return $this->code;}
}
class GDO_Schema {
    public static function table($name){return 'wp_gdo_'.$name;}
}
class V123_Fake_Application_DB {
    public $last_error='';
    public function prepare($sql,$user_id){return array($sql,$user_id);}
    public function get_row($prepared){
        if(!empty($GLOBALS['draft_switch_on_db_lookup'])){$GLOBALS['draft_actor']=8;}
        return (object)array('id'=>42,'user_id'=>7,'state'=>'draft','draft_expires_at'=>'2035-01-01 00:00:00','row_version'=>3,'profile_json'=>'{}');
    }
}
$wpdb=new V123_Fake_Application_DB();
require dirname(__DIR__).'/includes/class-gdo-application.php';
$checks=0;
function expect($ok,$what){
    global $checks;
    if(!$ok){fwrite(STDERR,'FAIL: '.$what."\n");exit(1);}
    ++$checks;echo 'PASS: '.$what."\n";
}
$GLOBALS['draft_actor']=7;
$own=GDO_Application::ensure_draft(7);
expect(!is_wp_error($own) && (int)$own->id===42,'existing draft is returned to current matching applicant');
$GLOBALS['draft_actor']=8;
$foreign=GDO_Application::ensure_draft(7);
expect(is_wp_error($foreign),'existing draft is denied to different signed-in actor');
$GLOBALS['draft_actor']=0;
$anonymous=GDO_Application::ensure_draft(7);
expect(is_wp_error($anonymous),'existing draft is denied to anonymous actor');
$GLOBALS['draft_actor']=7;
$GLOBALS['draft_switch_on_db_lookup']=true;
$switched=GDO_Application::ensure_draft(7);
expect(is_wp_error($switched),'current actor change during draft DB lookup does not disclose existing draft');
echo "File09 V12.3 draft actor boundary: {$checks} PASS / 0 FAIL\n";
