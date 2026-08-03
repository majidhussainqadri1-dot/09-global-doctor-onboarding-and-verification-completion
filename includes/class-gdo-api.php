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
		$verified = GDO_State::public_verified( $app->state )
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
			'verified_until'   => (string) $app->verified_until,
			'fingerprint'      => $verified ? (string) $app->approved_fingerprint : '',
			'checked_at'       => $checked_at,
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
