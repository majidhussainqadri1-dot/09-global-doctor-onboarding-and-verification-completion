<?php
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
define('DAY_IN_SECONDS',86400);
define('GDO_KEYRING',array('active'=>'test-v2','keys'=>array('test-v1'=>base64_encode(str_repeat('A',32)),'test-v2'=>base64_encode(str_repeat('B',32)))));
class WP_Error{private $code;private $message;public function __construct($c='',$m=''){$this->code=$c;$this->message=$m;}public function get_error_message(){return $this->message;}public function get_error_code(){return $this->code;}}
function is_wp_error($v){return $v instanceof WP_Error;}function __($s,$d=null){return $s;}function apply_filters($tag,$value){return $value;}function absint($v){return abs((int)$v);}function sanitize_key($v){return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/','',(string)$v));}function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}function fail($m){fwrite(STDERR,"FAIL: $m\n");exit(1);}function ok($c,$m){if(!$c)fail($m);}
require_once dirname(__DIR__).'/includes/class-gdo-state.php';
require_once dirname(__DIR__).'/includes/class-gdo-crypto.php';

ok(GDO_State::can_transition('draft','submitted'),'draft should submit');
ok(!GDO_State::can_transition('rejected','submitted'),'rejection must not be bypassed by resubmission');
ok(!GDO_State::can_transition('suspended','under_review'),'suspension must not be bypassed');
ok(GDO_State::can_transition('under_review','recommended'),'review should recommend');
ok(GDO_State::can_transition('recommended','verified'),'independent finalization should verify');
ok(GDO_State::can_transition('recommended','rejected'),'independent finalization should reject');
ok(!GDO_State::can_transition('verified','under_review'),'verified state must not silently reset');
ok(GDO_State::can_transition('rejected','appeal_pending'),'rejection should permit appeal');
ok(GDO_State::can_transition('revoked','appeal_pending'),'revocation should permit appeal');
ok(GDO_State::can_transition('reinstated','expired'),'reinstated verification must expire');
ok(GDO_State::public_verified('verified')&&!GDO_State::public_verified('suspended'),'only current public states should verify');

$meta=array('application_uuid'=>'11111111-1111-4111-8111-111111111111','application_version'=>2,'user_id'=>7,'document_type'=>'license','document_version'=>3);
$plain="private credential bytes\0\1";
$enc=GDO_Crypto::encrypt($plain,$meta);
ok(!is_wp_error($enc),'encryption should succeed');
ok(0===strpos($enc['bytes'],'GDO2'),'GDO2 envelope required');
$dec=GDO_Crypto::decrypt($enc['bytes'],$meta);
ok($dec===$plain,'authenticated decryption should round-trip');
ok(GDO_Crypto::verify_content_hmac($plain,$enc['key_id'],$enc['content_hmac']),'content HMAC should verify');
ok(!GDO_Crypto::verify_content_hmac($plain.'x',$enc['key_id'],$enc['content_hmac']),'content HMAC must detect change');
$tampered=$meta;$tampered['user_id']=8;
$bad=GDO_Crypto::decrypt($enc['bytes'],$tampered);
ok(is_wp_error($bad),'AAD tampering must fail');
$corrupt=$enc['bytes'];$corrupt[strlen($corrupt)-1]=chr(ord($corrupt[strlen($corrupt)-1])^1);
ok(is_wp_error(GDO_Crypto::decrypt($corrupt,$meta)),'ciphertext tampering must fail');
$oversized='GDO2'.pack('n',65).str_repeat('a',65).str_repeat('x',29);
ok(is_wp_error(GDO_Crypto::decrypt($oversized,$meta)),'oversized key identifier must fail');
echo "Security unit tests passed.\n";
