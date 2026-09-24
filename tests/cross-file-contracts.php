<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
function absint($v){return abs((int)$v);} function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return trim((string)$v);} function __($v,$d=null){return $v;}
function add_filter($a,$b,$p=10,$n=1){return true;} function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error { public $code; public $message; public function __construct($c,$m){$this->code=$c;$this->message=$m;} }
class GDO_API { public static $decision=array(); public static function latest_decision($id){return self::$decision;} }
class GDO_Membership_Adapter { public static $available=true; public static function available(){return self::$available;} }
require dirname(__DIR__) . '/includes/class-gdo-integration-contracts.php';

GDO_API::$decision=array(
    'application_uuid'=>'00000000-0000-4000-8000-000000000001','version'=>4,'state'=>'verified','verified'=>true,
    'limited'=>false,'verified_until'=>'2030-12-31 23:59:59','fingerprint'=>str_repeat('a',64),
    'claim_version'=>8,'claim_status'=>'accepted','checked_at'=>'2026-08-10T00:00:00Z','private_note'=>'must not leak'
);
$consumers=array('file03','file07','file08','file21','file23','file26');
foreach($consumers as $consumer){
    $p=GDO_Integration_Contracts::projection(42,$consumer);
    if(is_wp_error($p)||empty($p['eligible'])||$p['consumer']!==$consumer||isset($p['private_note'])||isset($p['application_id'])){throw new RuntimeException('unsafe projection '.$consumer);}
    if(empty($p['authorization_rechecked'])||!empty($p['evidence_exposed'])||!empty($p['clinical_authorization'])||!empty($p['donor_rank_advantage'])){throw new RuntimeException('latest-plan projection boundary failed '.$consumer);}
    if('file09'!==$p['source_of_truth']||'c0_public_verification_projection'!==$p['privacy_class']){throw new RuntimeException('projection provenance/privacy failed '.$consumer);}
}
if(!gdo_file21_publishing_eligibility(42)['eligible']||!gdo_file23_dashboard_eligibility(42)['eligible']||!gdo_file26_search_eligibility(42)['eligible']){throw new RuntimeException('new consumer helper failed');}

$contracts=GDO_Integration_Contracts::register(array());
foreach(array(GDO_Integration_Contracts::FILE03,GDO_Integration_Contracts::FILE07,GDO_Integration_Contracts::FILE08,GDO_Integration_Contracts::FILE21,GDO_Integration_Contracts::FILE23,GDO_Integration_Contracts::FILE26) as $id){
    if(empty($contracts[$id]['fail_closed'])||'current_file00_and_file09_state'!==$contracts[$id]['recheck']){throw new RuntimeException('contract registration failed '.$id);}
}
$owner=$contracts[GDO_Integration_Contracts::OWNER];
if($owner['canonical_mutations']!=='owner_commands_only'||empty($owner['authorization_recheck'])||!empty($owner['direct_table_meta_write'])||empty($owner['central_laws']['donor_neutral'])||!empty($owner['central_laws']['paid_rank_advantage'])){throw new RuntimeException('owner contract failed');}
if($contracts['gdo.file19.notification-event']['version']!=='sun.event.v1'||$contracts['gdo.file19.notification-event']['payload']!=='minimized-no-evidence'){throw new RuntimeException('File19 contract metadata failed');}

$manifests=GDO_Integration_Contracts::file26_connector_manifests(array());
$m=$manifests[count($manifests)-1];
foreach(array('slug','owner_file','contract_version','entity_types','privacy_classes','visibility_fields','deletion_semantics','status') as $field){if(empty($m[$field]))throw new RuntimeException('File26 manifest missing '.$field);}
if('contract_tested'!==$m['status']||'09'!==$m['owner_file']||'restrict_on_verification_loss'!==$m['deletion_semantics']){throw new RuntimeException('File26 manifest unsafe');}
if(!GDO_Integration_Contracts::file26_can_view(array('payload'=>array('user_id'=>42)),array())){throw new RuntimeException('File26 current verification visibility failed');}
if(GDO_Integration_Contracts::file26_can_view(array(),array())){throw new RuntimeException('File26 missing subject must fail closed');}

$pages=GDO_Integration_Contracts::file20_page_contracts(array('home'=>array(array('spf_page_map','home'))));
if($pages['doctor_application']!==array(array('gdo_page_map','apply'))){throw new RuntimeException('File20 page contract missing');}

GDO_API::$decision=array('state'=>'suspended','verified'=>false,'claim_status'=>'accepted','checked_at'=>'2026-08-10T00:00:00Z');
$p=gdo_file07_directory_eligibility(42);if(!empty($p['eligible'])||$p['reason_code']!=='verification_suspended'||$p['fingerprint']!==''){throw new RuntimeException('fail-closed directory projection failed');}
$p=gdo_file26_search_eligibility(42);if(!empty($p['eligible'])||GDO_Integration_Contracts::file26_can_view(array('payload'=>array('user_id'=>42)),array())){throw new RuntimeException('search projection not revoked with verification');}

GDO_Membership_Adapter::$available=false;
$health=GDO_Integration_Contracts::file26_health();if('degraded'!==$health['state']){throw new RuntimeException('File26 health must degrade with File00');}

echo "File 09 latest-plan cross-file contracts passed.\n";
