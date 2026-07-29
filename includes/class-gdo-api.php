<?php
defined( 'ABSPATH' ) || exit;
final class GDO_API {
    public static function latest_decision($user_id){$app=GDO_Application::latest_for_user($user_id);if(!$app)return array('state'=>'not_applied','verified'=>false);$expired=$app->verified_until&&strtotime($app->verified_until.' UTC')<=time();$verified=GDO_State::public_verified($app->state)&&!$expired&&!GDO_Membership_Adapter::sanctioned($user_id)&&!empty($app->approved_fingerprint);return array('application_id'=>absint($app->id),'application_uuid'=>$app->application_uuid,'version'=>absint($app->version),'state'=>$expired?'expired':$app->state,'verified'=>$verified,'verified_until'=>$app->verified_until,'fingerprint'=>$verified?$app->approved_fingerprint:'');}
    public static function snapshot($user_id){$decision=self::latest_decision($user_id);return !empty($decision['verified'])?GDO_Application::approved_snapshot($decision['application_id']):array();}
}
function gdo_get_verification_decision($user_id){return GDO_API::latest_decision($user_id);}function gdo_get_approved_snapshot($user_id){return GDO_API::snapshot($user_id);}function gdo_user_is_verified($user_id){$d=GDO_API::latest_decision($user_id);return !empty($d['verified']);}function gdo_get_application_status($user_id){$d=GDO_API::latest_decision($user_id);return $d['state'];}
