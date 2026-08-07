<?php
defined( 'ABSPATH' ) || exit;

final class GDO_API {
	public static function latest_decision( $user_id ) {
		$user_id = absint( $user_id );
		$app = GDO_Application::latest_for_user( $user_id );
		$checked_at = gmdate( 'c' );
		if ( ! $app ) {
			return array(
				'state'      => 'not_applied',
				'verified'   => false,
				'checked_at' => $checked_at,
			);
		}
		$expires = $app->verified_until ? strtotime( $app->verified_until . ' UTC' ) : 0;
		$expired = $expires && $expires <= time();
		$snapshot = GDO_Application::approved_snapshot( $app->id );
		$claim_current = ! apply_filters( 'gdo_require_file00_claim_ack', true, $app ) || 'accepted' === sanitize_key( $app->claim_status );
		$verified = GDO_State::public_verified( $app->state )
			&& $claim_current
			&& ! $expired
			&& ! GDO_Membership_Adapter::sanctioned( $user_id )
			&& ! empty( $snapshot );
		return array(
			'application_id'   => absint( $app->id ),
			'application_uuid' => (string) $app->application_uuid,
			'version'          => absint( $app->version ),
			'row_version'      => absint( $app->row_version ),
			'state'            => $expired ? 'expired' : sanitize_key( $app->state ),
			'verified'         => (bool) $verified,
			'limited'          => 'renewal_due' === sanitize_key( $app->state ),
			'verified_until'   => (string) $app->verified_until,
			'fingerprint'      => $verified ? (string) $app->approved_fingerprint : '',
			'claim_version'    => absint( $app->claim_version ),
			'claim_status'     => sanitize_key( $app->claim_status ),
			'checked_at'       => $checked_at,
		);
	}


	public static function application_edit_model( $app, $user_id ) {
		if ( ! $app || absint( $app->user_id ) !== absint( $user_id ) ) {
			return array( 'state'=>'not_applied', 'row_version'=>0, 'profile'=>array(), 'completeness'=>array() );
		}
		$profile = json_decode( $app->profile_json, true );
		$allowed_states = array( 'draft','more_information' );
		return array(
			'application_id'=>absint( $app->id ),
			'application_uuid'=>(string) $app->application_uuid,
			'version'=>absint( $app->version ),
			'row_version'=>absint( $app->row_version ),
			'state'=>sanitize_key( $app->state ),
			'editable'=>in_array( $app->state, $allowed_states, true ),
			'jurisdiction'=>(string) $app->jurisdiction,
			'application_type'=>(string) $app->application_type,
			'preferred_language'=>(string) $app->preferred_language,
			'profile'=>is_array( $profile ) ? array_intersect_key( $profile, array_flip( GDO_Application::fields() ) ) : array(),
			'completeness'=>GDO_Application::completeness( $app ),
			'draft_expires_at'=>(string) $app->draft_expires_at,
			'more_info_due_at'=>(string) $app->more_info_due_at,
			'updated_at'=>(string) $app->updated_at,
		);
	}
	public static function snapshot( $user_id ) {
		$decision = self::latest_decision( $user_id );
		return ! empty( $decision['verified'] ) ? GDO_Application::approved_snapshot( $decision['application_id'] ) : array();
	}
}

function gdo_get_verification_decision( $user_id ) { return GDO_API::latest_decision( $user_id ); }
function gdo_get_approved_snapshot( $user_id ) { return GDO_API::snapshot( $user_id ); }
function gdo_user_is_verified( $user_id ) { $decision = GDO_API::latest_decision( $user_id ); return ! empty( $decision['verified'] ); }
function gdo_get_application_status( $user_id ) { $decision = GDO_API::latest_decision( $user_id ); return $decision['state']; }
