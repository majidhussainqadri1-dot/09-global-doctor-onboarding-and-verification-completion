<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
function absint($v){return abs((int)$v);} function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function __($v,$d=null){return $v;} function add_filter($a,$b){return true;}
class WP_Error { public function __construct($c,$m){$this->code=$c;$this->message=$m;} }
class GDO_API { public static $decision=array(); public static function latest_decision($id){return self::$decision;} }
require dirname(__DIR__) . '/includes/class-gdo-integration-contracts.php';
GDO_API::$decision=array('application_uuid'=>'00000000-0000-4000-8000-000000000001','version'=>4,'state'=>'verified','verified'=>true,'limited'=>false,'verified_until'=>'2030-12-31 23:59:59','fingerprint'=>str_repeat('a',64),'claim_version'=>8,'claim_status'=>'accepted','checked_at'=>'2026-08-07T00:00:00Z','private_note'=>'must not leak');
foreach(array('file03','file07','file08') as $consumer){$p=GDO_Integration_Contracts::projection(42,$consumer);if(empty($p['eligible'])||$p['consumer']!==$consumer||isset($p['private_note'])||isset($p['application_id'])){throw new RuntimeException('unsafe projection '.$consumer);} }
GDO_API::$decision=array('state'=>'suspended','verified'=>false,'claim_status'=>'accepted','checked_at'=>'2026-08-07T00:00:00Z');
$p=gdo_file07_directory_eligibility(42);if(!empty($p['eligible'])||$p['reason_code']!=='verification_suspended'||$p['fingerprint']!==''){throw new RuntimeException('fail-closed directory projection failed');}
$contracts=GDO_Integration_Contracts::register(array());foreach(array(GDO_Integration_Contracts::FILE03,GDO_Integration_Contracts::FILE07,GDO_Integration_Contracts::FILE08) as $id){if(empty($contracts[$id]['fail_closed']))throw new RuntimeException('contract registration failed');}
echo "File 09 cross-file contracts passed.
";
