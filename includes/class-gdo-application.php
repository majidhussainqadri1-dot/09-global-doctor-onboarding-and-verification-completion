<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Application {
    public static function fields() {
        return array( 'display_name','country','city','clinic','qualification','license_number','licensing_authority','experience_years','specialty','languages','consultation_modes','phone','whatsapp','bio' );
    }

    public static function get( $application_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d', absint( $application_id ) ) );
    }

    public static function latest_for_user( $user_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE user_id=%d ORDER BY version DESC LIMIT 1', absint( $user_id ) ) );
    }

    public static function sanitize_profile( array $source ) {
        $data = array();
        foreach ( self::fields() as $field ) {
            $value = isset( $source[ $field ] ) ? wp_unslash( $source[ $field ] ) : '';
            $value = 'bio' === $field ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
            if ( in_array( $field, array( 'phone', 'whatsapp' ), true ) ) {
                $value = preg_replace( '/[^0-9+() .-]/', '', $value );
            }
            $data[ $field ] = trim( (string) $value );
        }
        return $data;
    }

    public static function validate_profile( array $profile ) {
        foreach ( self::fields() as $field ) {
            if ( empty( $profile[ $field ] ) ) {
                return new WP_Error( 'gdo_profile_incomplete', sprintf( __( 'Complete the required field: %s.', 'global-doctor-onboarding' ), $field ) );
            }
        }
        if ( ! ctype_digit( (string) $profile['experience_years'] ) || absint( $profile['experience_years'] ) > 80 ) {
            return new WP_Error( 'gdo_experience', __( 'Professional experience must be a valid number of years.', 'global-doctor-onboarding' ) );
        }
        return true;
    }

    public static function fingerprint( array $profile, array $evidence = array() ) {
        ksort( $profile );
        ksort( $evidence );
        return hash( 'sha256', wp_json_encode( array( 'profile'=>$profile,'evidence'=>$evidence ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
    }

    public static function ensure_draft( $user_id ) {
        global $wpdb;
        $user_id = absint( $user_id );
        if ( ! GDO_Membership_Adapter::is_active_doctor_candidate( $user_id ) ) {
            return new WP_Error( 'gdo_not_eligible', __( 'File 00 has not approved this account for the doctor application workflow.', 'global-doctor-onboarding' ) );
        }
        $latest = self::latest_for_user( $user_id );
        if ( $latest && in_array( $latest->state, array( 'draft','more_information','resubmitted','renewal_due','expired' ), true ) ) {
            return $latest;
        }
        if ( $latest && in_array( $latest->state, array( 'submitted','under_review','recommended','verified','suspended','appeal_pending','reinstated' ), true ) ) {
            return new WP_Error( 'gdo_application_locked', __( 'The current application cannot be edited in its present state.', 'global-doctor-onboarding' ) );
        }
        $version = $latest ? absint( $latest->version ) + 1 : 1;
        $empty = array_fill_keys( self::fields(), '' );
        $now = current_time( 'mysql', true );
        $data = array(
            'application_uuid'   => wp_generate_uuid4(),
            'user_id'            => $user_id,
            'version'            => $version,
            'state'              => 'draft',
            'row_version'        => 1,
            'profile_json'       => wp_json_encode( $empty ),
            'profile_fingerprint'=> self::fingerprint( $empty ),
            'created_at'         => $now,
            'updated_at'         => $now,
        );
        $wpdb->insert( GDO_Schema::table( 'applications' ), $data, array( '%s','%d','%d','%s','%d','%s','%s','%s','%s' ) );
        return self::get( $wpdb->insert_id );
    }

    public static function save_draft( $application_id, $user_id, array $profile, $expected_row_version ) {
        global $wpdb;
        $app = self::get( $application_id );
        if ( ! $app || absint( $app->user_id ) !== absint( $user_id ) || ! in_array( $app->state, array( 'draft','more_information','resubmitted','renewal_due','expired' ), true ) ) {
            return new WP_Error( 'gdo_draft_access', __( 'This application cannot be edited.', 'global-doctor-onboarding' ) );
        }
        $valid = self::validate_profile( $profile );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }
        $updated = $wpdb->query( $wpdb->prepare(
            'UPDATE ' . GDO_Schema::table( 'applications' ) . ' SET profile_json=%s,profile_fingerprint=%s,row_version=row_version+1,updated_at=%s WHERE id=%d AND row_version=%d',
            wp_json_encode( $profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), self::fingerprint( $profile ), current_time( 'mysql', true ), absint( $application_id ), absint( $expected_row_version )
        ) );
        return 1 === $updated ? true : new WP_Error( 'gdo_concurrent_change', __( 'The application changed. Reload and try again.', 'global-doctor-onboarding' ) );
    }

    public static function consent_text() {
        return array(
            'version'   => '2026-07-29.1',
            'wording'   => 'I certify that the professional information and credential evidence are authentic, current, and belong to me. I consent to private processing for identity, qualification, license, safety, fraud-prevention, audit, appeal, and legal-accountability purposes.',
            'purpose'   => 'Doctor identity, qualification, license, safety, fraud prevention, audit, appeal, and platform eligibility.',
            'retention' => 'Credential evidence is retained only for the active review, verification, appeal, legal-hold, and configured accountability periods. Verified physical erasure is applied when eligible.',
        );
    }

    public static function record_consent( $application_id, $user_id, $accepted ) {
        global $wpdb;
        if ( ! $accepted ) {
            return new WP_Error( 'gdo_consent_required', __( 'Credential-processing consent is required.', 'global-doctor-onboarding' ) );
        }
        $text = self::consent_text();
        $accepted_at = current_time( 'mysql', true );
        $evidence_hash = hash( 'sha256', absint( $application_id ) . '|' . absint( $user_id ) . '|' . $text['version'] . '|' . $accepted_at . '|' . hash( 'sha256', $text['wording'] ) );
        $data = array(
            'application_id'  => absint( $application_id ),
            'user_id'         => absint( $user_id ),
            'consent_version' => $text['version'],
            'wording_hash'    => hash( 'sha256', $text['wording'] ),
            'purpose'         => $text['purpose'],
            'retention_notice'=> $text['retention'],
            'accepted_at'     => $accepted_at,
            'evidence_hash'   => $evidence_hash,
        );
        $ok = $wpdb->replace( GDO_Schema::table( 'consents' ), $data, array( '%d','%d','%s','%s','%s','%s','%s','%s' ) );
        if ( ! $ok ) {
            return new WP_Error( 'gdo_consent_store', __( 'Consent evidence could not be stored.', 'global-doctor-onboarding' ) );
        }
        $wpdb->update( GDO_Schema::table( 'applications' ), array( 'consent_version'=>$text['version'] ), array( 'id'=>absint($application_id) ), array( '%s' ), array( '%d' ) );
        return true;
    }

    public static function submit( $application_id, $user_id, $expected_row_version ) {
        global $wpdb;
        $app = self::get( $application_id );
        if ( ! $app || absint( $app->user_id ) !== absint( $user_id ) || ! GDO_Membership_Adapter::is_active_doctor_candidate( $user_id ) ) {
            return new WP_Error( 'gdo_submit_access', __( 'The application cannot be submitted.', 'global-doctor-onboarding' ) );
        }
        if ( ! in_array( $app->state, array( 'draft','more_information','resubmitted','renewal_due','expired' ), true ) || absint( $app->row_version ) !== absint( $expected_row_version ) ) {
            return new WP_Error( 'gdo_submit_state', __( 'The application state changed. Reload and try again.', 'global-doctor-onboarding' ) );
        }
        $profile = json_decode( $app->profile_json, true );
        $valid = self::validate_profile( is_array( $profile ) ? $profile : array() );
        if ( is_wp_error( $valid ) || ! GDO_Evidence::all_present( $app->id ) || empty( $app->consent_version ) ) {
            return new WP_Error( 'gdo_submit_incomplete', __( 'Complete all profile, consent, and credential requirements before submission.', 'global-doctor-onboarding' ) );
        }
        $from = $app->state;
        $to = 'draft' === $from ? 'submitted' : 'under_review';
        if ( 'draft' !== $from ) {
            if ( ! GDO_State::can_transition( $from, 'resubmitted' ) && 'resubmitted' !== $from ) {
                return new WP_Error( 'gdo_resubmit_state', __( 'This application cannot be resubmitted.', 'global-doctor-onboarding' ) );
            }
            if ( 'resubmitted' !== $from ) {
                $first = GDO_State::transition( $app->id, 'resubmitted', $user_id, 'applicant_resubmission', 'Applicant submitted a new application version.', $expected_row_version );
                if ( is_wp_error( $first ) ) {
                    return $first;
                }
                $app = self::get( $app->id );
            }
            $to = 'under_review';
        }
        $result = GDO_State::transition( $app->id, $to, $user_id, 'applicant_submission', 'Applicant submitted the complete application.', $app->row_version );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $wpdb->update( GDO_Schema::table( 'applications' ), array( 'submitted_at'=>current_time('mysql',true) ), array( 'id'=>$app->id ), array( '%s' ), array( '%d' ) );
        GDO_Notifications::queue( 'doctor_application_submitted', $user_id, array( 'application_id'=>$app->id,'version'=>$app->version ) );
        do_action( 'gdo_application_submitted', $app->id, $user_id );
        return true;
    }

    public static function approved_snapshot( $application_id ) {
        $app = self::get( $application_id );
        if ( ! $app || empty( $app->approved_snapshot_json ) || ! GDO_State::public_verified( $app->state ) ) {
            return array();
        }
        $snapshot = json_decode( $app->approved_snapshot_json, true );
        return is_array( $snapshot ) ? $snapshot : array();
    }
}
