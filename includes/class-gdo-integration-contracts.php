<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Integration_Contracts {
    const VERSION = '1.0.0';
    const FILE03 = 'gdo.file03.doctor-profile-eligibility';
    const FILE07 = 'gdo.file07.directory-eligibility';
    const FILE08 = 'gdo.file08.clinic-eligibility';

    public function hooks() {
        add_filter( 'sabri_platform_contracts', array( __CLASS__, 'register' ) );
    }

    public static function register( $contracts ) {
        $contracts = is_array( $contracts ) ? $contracts : array();
        foreach ( self::identities() as $consumer => $identity ) {
            $contracts[ $identity ] = array(
                'owner'      => 'file09',
                'consumer'   => $consumer,
                'version'    => self::VERSION,
                'direction'  => 'read',
                'fail_closed'=> true,
                'fields'     => array( 'contract','version','consumer','user_id','application_uuid','application_version','state','verified','limited','eligible','reason_code','verified_until','fingerprint','claim_version','claim_status','checked_at' ),
            );
        }
        return $contracts;
    }

    public static function projection( $user_id, $consumer ) {
        $consumer = sanitize_key( $consumer );
        $identities = self::identities();
        if ( ! isset( $identities[ $consumer ] ) ) {
            return new WP_Error( 'gdo_contract_consumer', __( 'Unknown doctor-verification contract consumer.', 'global-doctor-onboarding' ) );
        }
        $decision = GDO_API::latest_decision( absint( $user_id ) );
        $verified = ! empty( $decision['verified'] );
        $state = isset( $decision['state'] ) ? sanitize_key( $decision['state'] ) : 'unavailable';
        $reason = $verified ? 'verified_current' : self::reason_code( $state, $decision );
        return array(
            'contract'            => $identities[ $consumer ],
            'version'             => self::VERSION,
            'consumer'            => $consumer,
            'user_id'             => absint( $user_id ),
            'application_uuid'    => isset( $decision['application_uuid'] ) ? (string) $decision['application_uuid'] : '',
            'application_version' => isset( $decision['version'] ) ? absint( $decision['version'] ) : 0,
            'state'               => $state,
            'verified'            => $verified,
            'limited'             => ! empty( $decision['limited'] ),
            'eligible'            => $verified,
            'reason_code'         => $reason,
            'verified_until'      => isset( $decision['verified_until'] ) ? (string) $decision['verified_until'] : '',
            'fingerprint'         => $verified && isset( $decision['fingerprint'] ) ? (string) $decision['fingerprint'] : '',
            'claim_version'       => isset( $decision['claim_version'] ) ? absint( $decision['claim_version'] ) : 0,
            'claim_status'        => isset( $decision['claim_status'] ) ? sanitize_key( $decision['claim_status'] ) : '',
            'checked_at'          => isset( $decision['checked_at'] ) ? (string) $decision['checked_at'] : gmdate( 'c' ),
        );
    }

    private static function identities() {
        return array( 'file03'=>self::FILE03, 'file07'=>self::FILE07, 'file08'=>self::FILE08 );
    }

    private static function reason_code( $state, array $decision ) {
        if ( isset( $decision['claim_status'] ) && 'accepted' !== sanitize_key( $decision['claim_status'] ) && in_array( $state, array( 'verified','reinstated','renewal_due' ), true ) ) {
            return 'file00_claim_not_current';
        }
        $map = array(
            'not_applied'=>'not_applied', 'draft'=>'application_incomplete', 'submitted'=>'verification_pending',
            'resubmitted'=>'verification_pending', 'under_review'=>'verification_under_review',
            'more_information'=>'more_information_required', 'recommended'=>'decision_pending',
            'rejected'=>'verification_rejected', 'suspended'=>'verification_suspended',
            'revoked'=>'verification_revoked', 'expired'=>'verification_expired',
            'renewal_due'=>'renewal_due', 'appeal_pending'=>'appeal_pending', 'withdrawn'=>'application_withdrawn',
        );
        return isset( $map[ $state ] ) ? $map[ $state ] : 'verification_unavailable';
    }
}

function gdo_file03_doctor_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file03' ); }
function gdo_file07_directory_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file07' ); }
function gdo_file08_clinic_eligibility( $user_id ) { return GDO_Integration_Contracts::projection( $user_id, 'file08' ); }
