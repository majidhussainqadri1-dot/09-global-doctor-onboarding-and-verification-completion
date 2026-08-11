<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Evidence {
    const MAX_BYTES = 5242880;
    const MAX_PIXELS = 24000000;
    const MAX_USER_BYTES = 52428800;

    public static function types( $jurisdiction = '', $application_type = 'homeopathic_doctor' ) {
        return GDO_Policy::evidence_types( $jurisdiction, $application_type );
    }

    public static function records( $application_id, $active_only = false ) {
        global $wpdb;
        $sql = 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE application_id=%d';
        if ( $active_only ) {
            $sql .= " AND retention_state='active' AND deleted_at IS NULL";
        }
        $sql .= ' ORDER BY document_type ASC,version DESC';
        return $wpdb->get_results( $wpdb->prepare( $sql, absint( $application_id ) ) );
    }

    public static function current( $application_id, $type ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . " WHERE application_id=%d AND document_type=%s AND retention_state='active' AND deleted_at IS NULL ORDER BY version DESC LIMIT 1",
            absint( $application_id ), sanitize_key( $type )
        ) );
    }

    private static function normalize_upload( array $file, $type, $trusted_internal_path = '' ) {
        if ( empty( $file['tmp_name'] ) || ! isset( $file['error'], $file['size'], $file['name'] ) ) {
            return new WP_Error( 'gdo_missing_upload', __( 'A required credential file is missing.', 'global-doctor-onboarding' ) );
        }
        $native_uploaded = is_uploaded_file( $file['tmp_name'] );
        $filtered_uploaded = (bool) apply_filters( 'gdo_is_uploaded_file', $native_uploaded, $file['tmp_name'], $type );
        $is_uploaded = $native_uploaded && $filtered_uploaded;
        if ( ! $is_uploaded && $trusted_internal_path ) {
            $storage_dir = GDO_Storage::directory();
            $real_tmp = realpath( $file['tmp_name'] );
            $real_trusted = realpath( $trusted_internal_path );
            $real_storage = $storage_dir ? realpath( $storage_dir ) : false;
            $prefix = $real_storage ? trailingslashit( wp_normalize_path( $real_storage ) ) : '';
            $normalized_tmp = $real_tmp ? wp_normalize_path( $real_tmp ) : '';
            $is_uploaded = $real_tmp && $real_trusted && hash_equals( wp_normalize_path( $real_trusted ), $normalized_tmp )
                && $prefix && 0 === strpos( $normalized_tmp, $prefix )
                && 0 === strpos( basename( $normalized_tmp ), '.chunk-' )
                && is_file( $real_tmp ) && ! is_link( $file['tmp_name'] );
        }
        if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! $is_uploaded || (int) $file['size'] < 32 || (int) $file['size'] > self::MAX_BYTES ) {
            return new WP_Error( 'gdo_invalid_upload', __( 'The credential upload is invalid or exceeds 5 MB.', 'global-doctor-onboarding' ) );
        }
        if ( ! GDO_Rate_Limiter::hit( 'credential-upload:' . get_current_user_id(), 12, HOUR_IN_SECONDS ) ) {
            return new WP_Error( 'gdo_upload_rate', __( 'Too many credential uploads. Try again later.', 'global-doctor-onboarding' ) );
        }
        $bytes = file_get_contents( $file['tmp_name'] );
        if ( false === $bytes || strlen( $bytes ) !== (int) $file['size'] ) {
            return new WP_Error( 'gdo_upload_read', __( 'The credential upload could not be read completely.', 'global-doctor-onboarding' ) );
        }
        if ( ! class_exists( 'finfo' ) ) {
            return new WP_Error( 'gdo_fileinfo_missing', __( 'The server file-information extension is required.', 'global-doctor-onboarding' ) );
        }
        $finfo = new finfo( FILEINFO_MIME_TYPE );
        $mime = (string) $finfo->buffer( $bytes );
        $allowed = array( 'application/pdf', 'image/jpeg', 'image/png', 'image/webp' );
        if ( ! in_array( $mime, $allowed, true ) ) {
            return new WP_Error( 'gdo_mime', __( 'Credentials must be PDF, JPEG, PNG, or WebP.', 'global-doctor-onboarding' ) );
        }
        $scan = apply_filters( 'gdo_credential_scan_result', null, $bytes, $mime, $type, get_current_user_id() );
        if ( 'clean' !== $scan ) {
            return new WP_Error( 'gdo_scan_required', __( 'The credential could not pass the configured malware scan.', 'global-doctor-onboarding' ) );
        }
        if ( 'application/pdf' === $mime ) {
            if ( 0 !== strpos( $bytes, '%PDF-' ) || false === strrpos( substr( $bytes, -4096 ), '%%EOF' ) ) {
                return new WP_Error( 'gdo_pdf_structure', __( 'The PDF structure is invalid.', 'global-doctor-onboarding' ) );
            }
            if ( preg_match( '/\/(JavaScript|JS|OpenAction|AA|Launch|EmbeddedFile|RichMedia|XFA)\b/i', $bytes ) ) {
                return new WP_Error( 'gdo_pdf_active', __( 'Active or embedded PDF content is not allowed.', 'global-doctor-onboarding' ) );
            }
        } else {
            $info = @getimagesizefromstring( $bytes );
            if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) || ( (int) $info[0] * (int) $info[1] ) > self::MAX_PIXELS ) {
                return new WP_Error( 'gdo_image_dimensions', __( 'The credential image dimensions are invalid or excessive.', 'global-doctor-onboarding' ) );
            }
            $safe = self::safe_image_bytes( $bytes, $mime );
            if ( is_wp_error( $safe ) ) {
                return $safe;
            }
            $bytes = $safe['bytes'];
            $mime = $safe['mime'];
        }
        $dimensions = 'application/pdf' === $mime ? array(0,0) : @getimagesizefromstring( $bytes );
        return array(
            'bytes'=>$bytes, 'mime'=>$mime, 'name'=>sanitize_file_name($file['name']), 'size'=>strlen($bytes),
            'source_sha256'=>hash('sha256',$bytes), 'malware_status'=>'clean', 'scan_provider'=>sanitize_text_field((string)apply_filters('gdo_credential_scan_provider','configured-provider',$type)),
            'metadata_removed'=>'application/pdf' === $mime ? 0 : 1, 'pixel_width'=>is_array($dimensions)?absint($dimensions[0]):0, 'pixel_height'=>is_array($dimensions)?absint($dimensions[1]):0,
        );
    }

    private static function safe_image_bytes( $bytes, $mime ) {
        if ( ! function_exists( 'imagecreatefromstring' ) ) {
            return new WP_Error( 'gdo_image_library', __( 'A secure image re-encoding library is required.', 'global-doctor-onboarding' ) );
        }
        $image = @imagecreatefromstring( $bytes );
        if ( ! $image ) {
            return new WP_Error( 'gdo_image_decode', __( 'The credential image could not be decoded safely.', 'global-doctor-onboarding' ) );
        }
        ob_start();
        $ok = false;
        if ( 'image/jpeg' === $mime ) {
            $ok = imagejpeg( $image, null, 90 );
        } elseif ( 'image/png' === $mime ) {
            imagealphablending( $image, false );
            imagesavealpha( $image, true );
            $ok = imagepng( $image, null, 7 );
        } elseif ( 'image/webp' === $mime && function_exists( 'imagewebp' ) ) {
            $ok = imagewebp( $image, null, 90 );
        }
        $out = ob_get_clean();
        imagedestroy( $image );
        if ( ! $ok || ! $out ) {
            return new WP_Error( 'gdo_image_encode', __( 'The credential image could not be safely re-encoded.', 'global-doctor-onboarding' ) );
        }
        return array( 'bytes'=>$out, 'mime'=>$mime );
    }

    private static function quota_allows( $user_id, $new_size, $replacing_size = 0 ) {
        global $wpdb;
        $raw_used = $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(file_size),0) FROM ' . GDO_Schema::table('evidence') . " WHERE user_id=%d AND retention_state='active' AND deleted_at IS NULL",
            absint( $user_id )
        ) );
        if ( null === $raw_used || ! empty( $wpdb->last_error ) ) {
            return false;
        }
        $used = absint( $raw_used );
        $limit = absint( apply_filters( 'gdo_user_credential_quota_bytes', self::MAX_USER_BYTES, absint($user_id) ) );
        return max( 0, $used - absint($replacing_size) ) + absint($new_size) <= $limit;
    }

    public static function stage_upload( $application, $type, array $file, $manage_transaction = true, $trusted_internal_path = '' ) {
        global $wpdb;
        $type = sanitize_key( $type );
        $types = $application ? self::types( $application->jurisdiction, $application->application_type ) : array();
        if ( ! GDO_Operations::mutation_allowed() || ! $application || ! isset( $types[ $type ] ) || ! in_array( $application->state, array( 'draft','more_information' ), true ) ) {
            return new WP_Error( 'gdo_document_type', __( 'This credential cannot be uploaded for the current application.', 'global-doctor-onboarding' ) );
        }
        $normalized = self::normalize_upload( $file, $type, $trusted_internal_path );
        if ( is_wp_error( $normalized ) ) {
            return $normalized;
        }
        $actor_id = get_current_user_id();
        if ( ! $actor_id || absint( $application->user_id ) !== $actor_id || ! GDO_Membership_Adapter::is_active_doctor_candidate( $actor_id, $application->jurisdiction ) ) {
            return new WP_Error( 'gdo_evidence_owner_denied', __( 'Credential upload is not authorized for this application.', 'global-doctor-onboarding' ) );
        }
        if ( $manage_transaction && false === $wpdb->query( 'START TRANSACTION' ) ) {
            return new WP_Error( 'gdo_evidence_transaction', __( 'The private evidence transaction could not be started safely.', 'global-doctor-onboarding' ) );
        }

        // Re-read the entire application under row lock. The caller-supplied
        // object is only a preflight hint and must not authorize a write after a
        // concurrent submit/state/jurisdiction change.
        $locked_app = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d AND user_id=%d FOR UPDATE',
            absint( $application->id ),
            $actor_id
        ) );
        $locked_types = $locked_app ? self::types( $locked_app->jurisdiction, $locked_app->application_type ) : array();
        if ( ! $locked_app
            || ! in_array( $locked_app->state, array( 'draft', 'more_information' ), true )
            || ! isset( $locked_types[ $type ] )
            || ! GDO_Membership_Adapter::is_active_doctor_candidate( $actor_id, $locked_app->jurisdiction ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_application_changed', __( 'The application changed before the credential could be stored. Reload and try again.', 'global-doctor-onboarding' ) );
        }

        $current = self::current( $locked_app->id, $type );
        if ( ! self::quota_allows( $locked_app->user_id, $normalized['size'], $current ? $current->file_size : 0 ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_storage_quota', __( 'The private credential storage quota has been reached.', 'global-doctor-onboarding' ) );
        }
        $version = $current ? absint( $current->version ) + 1 : 1;
        $meta = array(
            'application_uuid'    => $locked_app->application_uuid,
            'application_version' => $locked_app->version,
            'user_id'             => $locked_app->user_id,
            'document_type'       => $type,
            'document_version'    => $version,
        );
        $encrypted = GDO_Crypto::encrypt( $normalized['bytes'], $meta );
        if ( is_wp_error( $encrypted ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return $encrypted;
        }
        $storage_name = wp_generate_uuid4() . '.gdo2';
        $stored = GDO_Storage::atomic_write( $storage_name, $encrypted['bytes'] );
        if ( is_wp_error( $stored ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return $stored;
        }
        $now = current_time( 'mysql', true );
        $data = array(
            'application_id'    => absint( $locked_app->id ),
            'user_id'           => absint( $locked_app->user_id ),
            'document_type'     => $type,
            'purpose_code'      => 'professional_verification',
            'version'           => $version,
            'status'            => 'pending_review',
            'original_name'     => $normalized['name'],
            'mime_type'         => $normalized['mime'],
            'file_size'         => $normalized['size'],
            'source_sha256'     => $normalized['source_sha256'],
            'storage_name'      => $storage_name,
            'ciphertext_sha256' => $stored['sha256'],
            'content_hmac'      => $encrypted['content_hmac'],
            'key_id'            => $encrypted['key_id'],
            'envelope_version'  => $encrypted['version'],
            'malware_status'    => $normalized['malware_status'],
            'scan_provider'     => $normalized['scan_provider'],
            'scan_reference'    => isset( $normalized['scan_reference'] ) ? $normalized['scan_reference'] : null,
            'metadata_removed'  => $normalized['metadata_removed'],
            'pixel_width'       => $normalized['pixel_width'] ?: null,
            'pixel_height'      => $normalized['pixel_height'] ?: null,
            'expires_at'        => gmdate( 'Y-m-d H:i:s', time() + absint( apply_filters( 'gdo_evidence_review_expiry_days', 365, $type ) ) * DAY_IN_SECONDS ),
            'retention_state'   => 'active',
            'created_at'        => $now,
            'updated_at'        => $now,
        );
        $formats = array(
            '%d','%d','%s','%s','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s',
            '%s','%s','%s','%d','%d','%d','%s','%s','%s','%s',
        );
        $inserted = $wpdb->insert( GDO_Schema::table( 'evidence' ), $data, $formats );
        if ( 1 !== $inserted ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            GDO_Storage::delete_verified( $storage_name, $stored['sha256'] );
            return new WP_Error( 'gdo_evidence_insert', __( 'Credential evidence could not be recorded.', 'global-doctor-onboarding' ) );
        }
        $new_id = absint( $wpdb->insert_id );
        if ( $current ) {
            $superseded = $wpdb->update(
                GDO_Schema::table( 'evidence' ),
                array( 'retention_state'=>'superseded', 'updated_at'=>$now ),
                array( 'id'=>absint( $current->id ), 'retention_state'=>'active' ),
                array( '%s','%s' ),
                array( '%d','%s' )
            );
            if ( 1 !== $superseded ) {
                if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                GDO_Storage::delete_verified( $storage_name, $stored['sha256'] );
                return new WP_Error( 'gdo_evidence_replace', __( 'The previous credential could not be replaced safely.', 'global-doctor-onboarding' ) );
            }
        }
        if ( $manage_transaction && false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            GDO_Storage::delete_verified( $storage_name, $stored['sha256'] );
            return new WP_Error( 'gdo_evidence_commit', __( 'Credential evidence could not be committed.', 'global-doctor-onboarding' ) );
        }

        $result = array(
            'id'=>$new_id,
            'application_id'=>absint( $locked_app->id ),
            'storage_name'=>$storage_name,
            'ciphertext_sha256'=>$stored['sha256'],
            'document_type'=>$type,
            'version'=>$version,
            'source_digest'=>$normalized['source_sha256'],
        );
        // Never emit an external/canonical audit fact for a transaction that is
        // still owned by the caller; the caller emits it only after COMMIT.
        if ( $manage_transaction ) {
            GDO_Membership_Adapter::audit( 'doctor_evidence_uploaded', array(
                'application_id'=>$result['application_id'], 'evidence_id'=>$new_id, 'document_type'=>$type,
                'version'=>$version, 'source_digest'=>$normalized['source_sha256'],
            ) );
        }
        return $result;
    }

    public static function all_submittable( $application_id ) {
        $app = GDO_Application::get( $application_id );
        foreach ( array_keys( self::types( $app ? $app->jurisdiction : '', $app ? $app->application_type : 'homeopathic_doctor' ) ) as $type ) {
            $record = self::current( $application_id, $type );
            if ( ! $record || ! in_array( $record->status, array( 'pending_review','accepted' ), true ) || ( ! empty( $record->expires_at ) && strtotime( $record->expires_at . ' UTC' ) <= time() ) ) {
                return false;
            }
        }
        return true;
    }

    public static function all_present( $application_id ) {
        return self::all_submittable( $application_id );
    }

    public static function all_accepted( $application_id ) {
        $app = GDO_Application::get( $application_id );
        foreach ( array_keys( self::types( $app ? $app->jurisdiction : '', $app ? $app->application_type : 'homeopathic_doctor' ) ) as $type ) {
            $record = self::current( $application_id, $type );
            if ( ! $record || 'accepted' !== $record->status || empty( $record->reviewer_id ) || empty( $record->reviewed_at ) || ( ! empty( $record->expires_at ) && strtotime( $record->expires_at . ' UTC' ) <= time() ) ) {
                return false;
            }
            if ( 'license' === $type && ( empty( $record->validity_until ) || strtotime( $record->validity_until . ' 23:59:59 UTC' ) <= time() ) ) {
                return false;
            }
        }
        return true;
    }

    public static function decrypt_record( $record ) {
        $app = GDO_Application::get( $record->application_id );
        if ( ! $app ) {
            return new WP_Error( 'gdo_application_missing', __( 'The credential application is unavailable.', 'global-doctor-onboarding' ) );
        }
        $envelope = GDO_Storage::read( $record->storage_name );
        if ( is_wp_error( $envelope ) ) {
            return $envelope;
        }
        if ( ! hash_equals( (string) $record->ciphertext_sha256, hash( 'sha256', $envelope ) ) ) {
            return new WP_Error( 'gdo_ciphertext_hash', __( 'The encrypted credential failed its integrity check.', 'global-doctor-onboarding' ) );
        }
        $meta = array(
            'application_uuid'    => $app->application_uuid,
            'application_version' => $app->version,
            'user_id'             => $record->user_id,
            'document_type'       => $record->document_type,
            'document_version'    => $record->version,
        );
        $plain = GDO_Crypto::decrypt( $envelope, $meta );
        if ( is_wp_error( $plain ) ) {
            return $plain;
        }
        if ( 'GDO2' === $record->envelope_version && ! GDO_Crypto::verify_content_hmac( $plain, $record->key_id, $record->content_hmac ) ) {
            return new WP_Error( 'gdo_content_hmac', __( 'The decrypted credential failed its content-integrity check.', 'global-doctor-onboarding' ) );
        }
        return $plain;
    }

    public static function rotate_key( $evidence_id ) {
        global $wpdb;
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE id=%d', absint( $evidence_id ) ) );
        $ring = GDO_Crypto::keyring();
        if ( ! $record || is_wp_error( $ring ) || $record->key_id === $ring['active'] || 'GDO2' !== $record->envelope_version ) {
            return true;
        }
        $app = GDO_Application::get( $record->application_id );
        $plain = self::decrypt_record( $record );
        if ( ! $app || is_wp_error( $plain ) ) {
            return is_wp_error( $plain ) ? $plain : new WP_Error( 'gdo_rotation_application', __( 'The credential application is unavailable.', 'global-doctor-onboarding' ) );
        }
        $meta = array( 'application_uuid'=>$app->application_uuid,'application_version'=>$app->version,'user_id'=>$record->user_id,'document_type'=>$record->document_type,'document_version'=>$record->version );
        $encrypted = GDO_Crypto::encrypt( $plain, $meta );
        if ( is_wp_error( $encrypted ) ) {
            return $encrypted;
        }
        $new_name = wp_generate_uuid4() . '.gdo2';
        $stored = GDO_Storage::atomic_write( $new_name, $encrypted['bytes'] );
        if ( is_wp_error( $stored ) ) {
            return $stored;
        }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            GDO_Storage::delete_verified( $new_name, $stored['sha256'] );
            return new WP_Error( 'gdo_rotation_transaction', __( 'Credential rotation could not start a safe database transaction.', 'global-doctor-onboarding' ) );
        }
        $ok = $wpdb->update( GDO_Schema::table('evidence'), array( 'storage_name'=>$new_name,'ciphertext_sha256'=>$stored['sha256'],'content_hmac'=>$encrypted['content_hmac'],'key_id'=>$encrypted['key_id'],'updated_at'=>current_time('mysql',true) ), array('id'=>$record->id,'storage_name'=>$record->storage_name), array('%s','%s','%s','%s','%s'), array('%d','%s') );
        if ( 1 !== $ok || false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            GDO_Storage::delete_verified( $new_name, $stored['sha256'] );
            return new WP_Error( 'gdo_rotation_database', __( 'The rotated credential could not be committed.', 'global-doctor-onboarding' ) );
        }
        $deleted = GDO_Storage::delete_verified( $record->storage_name, $record->ciphertext_sha256 );
        if ( is_wp_error( $deleted ) ) {
            GDO_Membership_Adapter::audit( 'doctor_credential_rotation_orphaned_old_file', array('application_id'=>absint($record->application_id),'evidence_id'=>absint($record->id),'old_storage_digest'=>hash('sha256',$record->storage_name),'error'=>$deleted->get_error_code()) );
        }
        GDO_Membership_Adapter::audit( 'doctor_credential_key_rotated', array('application_id'=>absint($record->application_id),'evidence_id'=>absint($record->id),'old_key_id'=>$record->key_id,'new_key_id'=>$encrypted['key_id'],'old_deleted'=>!is_wp_error($deleted)) );
        return true;
    }

    public static function review( $evidence_id, $reviewer_id, $status, array $checklist, $registry_result, $validity_from, $validity_until, $review_note, $manage_transaction = true ) {
        global $wpdb;
        $reviewer_id = absint( $reviewer_id );
        if ( ! $reviewer_id || $reviewer_id !== get_current_user_id() || ! GDO_Membership_Adapter::can( 'sabri_verify_doctors', $reviewer_id ) || ! GDO_Membership_Adapter::recent_step_up( $reviewer_id ) ) {
            return new WP_Error( 'gdo_evidence_review_forbidden', __( 'Credential review requires current reviewer authorization and recent step-up.', 'global-doctor-onboarding' ) );
        }
        if ( $manage_transaction && false === $wpdb->query( 'START TRANSACTION' ) ) {
            return new WP_Error( 'gdo_evidence_review_transaction', __( 'Credential review could not start a safe database transaction.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . " WHERE id=%d AND retention_state='active' AND deleted_at IS NULL FOR UPDATE", absint( $evidence_id ) ) );
        if ( null === $record && ! empty( $wpdb->last_error ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_review_query', __( 'Credential evidence could not be read safely for review.', 'global-doctor-onboarding' ) );
        }
        $app = null;
        if ( $record ) {
            $wpdb->last_error = '';
            $app = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'applications' ) . ' WHERE id=%d FOR UPDATE', absint( $record->application_id ) ) );
            if ( null === $app && ! empty( $wpdb->last_error ) ) {
                if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                return new WP_Error( 'gdo_evidence_review_application_query', __( 'The credential application could not be read safely for review.', 'global-doctor-onboarding' ) );
            }
        }
        $status = sanitize_key( $status );
        $review_note = sanitize_textarea_field( $review_note );
        if ( ! $record || ! $app || 'under_review' !== $app->state || absint( $app->assigned_reviewer_id ) !== absint( $reviewer_id ) || ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer_id, $app->user_id, $app->id ) || ! GDO_Membership_Adapter::reviewer_case_allows( $reviewer_id, $app->user_id, $app->id ) || ! in_array( $status, array( 'accepted', 'rejected', 'more_information' ), true ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_review', __( 'Invalid credential review.', 'global-doctor-onboarding' ) );
        }
        $clean = array(
            'name_match'          => sanitize_key( isset($checklist['name_match']) ? $checklist['name_match'] : '' ),
            'document_legible'    => sanitize_key( isset($checklist['document_legible']) ? $checklist['document_legible'] : '' ),
            'authenticity_method' => sanitize_text_field( isset($checklist['authenticity_method']) ? $checklist['authenticity_method'] : '' ),
            'scope_match'         => sanitize_key( isset($checklist['scope_match']) ? $checklist['scope_match'] : '' ),
        );
        $normalized_from = $validity_from ? GDO_Policy::normalize_date( $validity_from ) : '';
        $normalized_until = $validity_until ? GDO_Policy::normalize_date( $validity_until ) : '';
        if ( is_wp_error( $normalized_from ) || is_wp_error( $normalized_until ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_date', __( 'Credential validity dates must be real YYYY-MM-DD calendar dates.', 'global-doctor-onboarding' ) );
        }
        $validity_from = $normalized_from;
        $validity_until = $normalized_until;
        if ( 'accepted' === $status ) {
            if ( 'yes' !== $clean['name_match'] || 'yes' !== $clean['document_legible'] || 'yes' !== $clean['scope_match'] || strlen( $clean['authenticity_method'] ) < 5 ) {
                if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                return new WP_Error( 'gdo_evidence_checklist', __( 'Accepted credentials require a complete affirmative checklist and authenticity method.', 'global-doctor-onboarding' ) );
            }
            if ( 'license' === $record->document_type ) {
                $accepted_registry = array( 'verified','active','matched' );
                if ( ! in_array( sanitize_key($registry_result), $accepted_registry, true ) || ! $validity_until || strtotime( $validity_until . ' 23:59:59 UTC' ) <= time() ) {
                    if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                    return new WP_Error( 'gdo_license_review', __( 'An accepted license requires a verified registry result and future validity date.', 'global-doctor-onboarding' ) );
                }
            }
        } elseif ( strlen( $review_note ) < 20 ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_note', __( 'Explain the rejection or information request in at least 20 characters.', 'global-doctor-onboarding' ) );
        }
        if ( $validity_from && $validity_until && strtotime( $validity_from . ' 00:00:00 UTC' ) > strtotime( $validity_until . ' 23:59:59 UTC' ) ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_date_order', __( 'Credential validity start cannot be after its expiry.', 'global-doctor-onboarding' ) );
        }
        $data = array(
            'status'          => $status,
            'checklist_json'  => wp_json_encode( $clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'findings_json'   => wp_json_encode( array( 'field_findings'=>$clean, 'source_checked'=>sanitize_text_field( $registry_result ) ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'review_note'     => $review_note,
            'registry_result' => sanitize_key( $registry_result ),
            'registry_source' => $clean['authenticity_method'],
            'reviewer_id'     => absint( $reviewer_id ),
            'reviewed_at'     => current_time( 'mysql', true ),
            'validity_from'   => $validity_from ? sanitize_text_field( $validity_from ) : null,
            'validity_until'  => $validity_until ? sanitize_text_field( $validity_until ) : null,
            'updated_at'      => current_time( 'mysql', true ),
        );
        $updated = $wpdb->query( $wpdb->prepare(
            'UPDATE ' . GDO_Schema::table( 'evidence' ) . ' SET status=%s,checklist_json=%s,findings_json=%s,review_note=%s,registry_result=%s,registry_source=%s,reviewer_id=%d,reviewed_at=%s,validity_from=NULLIF(%s,\'\'),validity_until=NULLIF(%s,\'\'),updated_at=%s WHERE id=%d AND status IN (\'pending_review\',\'more_information\',\'rejected\')',
            $data['status'], $data['checklist_json'], $data['findings_json'], $data['review_note'], $data['registry_result'], $data['registry_source'], $data['reviewer_id'], $data['reviewed_at'], $data['validity_from'], $data['validity_until'], $data['updated_at'], absint( $record->id )
        ) );
        if ( 1 !== $updated ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'gdo_evidence_review_conflict', __( 'The credential review changed. Reload before recording another decision.', 'global-doctor-onboarding' ) );
        }
        if ( $manage_transaction && false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_review_commit', __( 'The credential review could not be committed.', 'global-doctor-onboarding' ) );
        }
        if ( $manage_transaction ) {
            GDO_Membership_Adapter::audit( 'doctor_evidence_reviewed', array( 'application_id'=>absint($record->application_id),'evidence_id'=>absint($record->id),'reviewer_id'=>absint($reviewer_id),'status'=>$status ) );
        }
        return true;
    }

    private static function purpose_code( $purpose ) {
        $code = substr( sanitize_key( wp_trim_words( (string) $purpose, 8, '' ) ), 0, 80 );
        return $code ? $code : 'credential_review_recorded_purpose';
    }

    public static function issue_view_grant( $evidence_id, $reviewer_id, $purpose, $mode = 'view' ) {
        global $wpdb;
        $evidence_id = absint( $evidence_id );
        $reviewer_id = absint( $reviewer_id );
        $purpose = sanitize_textarea_field( $purpose );
        $mode = sanitize_key( $mode );
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . " WHERE id=%d AND retention_state='active' AND deleted_at IS NULL", $evidence_id ) );
        $app = $record ? GDO_Application::get( $record->application_id ) : null;
        if ( ! $record || ! $app || strlen( $purpose ) < 10 || absint( $app->user_id ) === $reviewer_id || ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer_id, $app->user_id, $app->id ) || ! GDO_Membership_Adapter::reviewer_case_allows( $reviewer_id, $app->user_id, $app->id ) || ! GDO_Membership_Adapter::recent_step_up( $reviewer_id ) || ! in_array( $mode, array( 'view','download' ), true ) ) {
            if ( $record ) {
                GDO_Audit::access( $record->application_id, $evidence_id, $reviewer_id, $purpose, 'grant_denied' );
            }
            return new WP_Error( 'gdo_evidence_grant_denied', __( 'Credential access grant denied.', 'global-doctor-onboarding' ) );
        }
        if ( 'download' === $mode && ! GDO_Membership_Adapter::can( 'sabri_access_doctor_credentials', $reviewer_id ) ) {
            return new WP_Error( 'gdo_evidence_download_denied', __( 'Credential download is not authorized.', 'global-doctor-onboarding' ) );
        }
        if ( ! GDO_Rate_Limiter::hit( 'credential-grant:' . $reviewer_id, 20, HOUR_IN_SECONDS ) ) {
            return new WP_Error( 'gdo_evidence_grant_rate', __( 'Too many credential access requests.', 'global-doctor-onboarding' ) );
        }
        $token = wp_generate_password( 64, false, false );
        $hash = hash( 'sha256', $token );
        $session = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
        $session_digest = hash( 'sha256', $reviewer_id . '|' . $session );
        $expires = gmdate( 'Y-m-d H:i:s', time() + 300 );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . GDO_Schema::table( 'access_grants' ) . ' WHERE reviewer_id=%d AND (expires_at<%s OR used_at IS NOT NULL)', $reviewer_id, current_time( 'mysql', true ) ) );
        $ok = $wpdb->insert( GDO_Schema::table( 'access_grants' ), array(
            'grant_hash'=>$hash, 'application_id'=>absint( $app->id ), 'evidence_id'=>$evidence_id,
            'reviewer_id'=>$reviewer_id, 'purpose_code'=>self::purpose_code( $purpose ),
            'session_digest'=>$session_digest, 'mode'=>$mode, 'expires_at'=>$expires, 'created_at'=>current_time( 'mysql', true ),
        ), array( '%s','%d','%d','%d','%s','%s','%s','%s','%s' ) );
        if ( 1 !== $ok ) {
            return new WP_Error( 'gdo_evidence_grant_store', __( 'Credential access grant could not be stored.', 'global-doctor-onboarding' ) );
        }
        GDO_Audit::access( $app->id, $evidence_id, $reviewer_id, $purpose, 'grant_issued' );
        return array( 'token'=>$token, 'expires_at'=>$expires, 'mode'=>$mode );
    }

    public static function consume_view_grant( $token, $reviewer_id, $expected_mode = 'view' ) {
        global $wpdb;
        $reviewer_id = absint( $reviewer_id );
        $hash = hash( 'sha256', (string) $token );
        $table = GDO_Schema::table( 'access_grants' );
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            return new WP_Error( 'gdo_evidence_grant_transaction', __( 'Credential access could not start a safe database transaction.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $grant = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE grant_hash=%s FOR UPDATE", $hash ) );
        if ( null === $grant && ! empty( $wpdb->last_error ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_grant_query', __( 'Credential access grant state could not be read safely.', 'global-doctor-onboarding' ) );
        }
        $session = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
        $session_digest = hash( 'sha256', $reviewer_id . '|' . $session );
        if ( ! $grant || absint( $grant->reviewer_id ) !== $reviewer_id || $grant->used_at || strtotime( $grant->expires_at . ' UTC' ) <= time() || ! hash_equals( (string) $grant->session_digest, $session_digest ) || sanitize_key( $grant->mode ) !== sanitize_key( $expected_mode ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_grant_invalid', __( 'The credential access grant is invalid or expired.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . " WHERE id=%d AND retention_state='active' AND deleted_at IS NULL", absint( $grant->evidence_id ) ) );
        if ( null === $record && ! empty( $wpdb->last_error ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_grant_evidence_query', __( 'Credential evidence could not be read safely for access.', 'global-doctor-onboarding' ) );
        }
        $wpdb->last_error = '';
        $app = $record ? GDO_Application::get( $record->application_id ) : null;
        if ( $record && ! $app && ! empty( $wpdb->last_error ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_grant_application_query', __( 'The credential application could not be read safely for access.', 'global-doctor-onboarding' ) );
        }
        $download_authorized = 'download' !== sanitize_key( $expected_mode ) || GDO_Membership_Adapter::can( 'sabri_access_doctor_credentials', $reviewer_id );
        if ( ! $record || ! $app || ! $download_authorized || ! GDO_Membership_Adapter::reviewer_scope_allows( $reviewer_id, $app->user_id, $app->id ) || ! GDO_Membership_Adapter::reviewer_case_allows( $reviewer_id, $app->user_id, $app->id ) || ! GDO_Membership_Adapter::recent_step_up( $reviewer_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gdo_evidence_missing', __( 'The credential evidence is unavailable or no longer within reviewer scope.', 'global-doctor-onboarding' ) );
        }
        $used = $wpdb->update( $table, array( 'used_at'=>current_time( 'mysql', true ) ), array( 'id'=>$grant->id, 'used_at'=>null ), array( '%s' ), array( '%d','%s' ) );
        $event = 1 === $used ? GDO_Notifications::queue( 'doctor_credential_accessed', $app->user_id, array( 'application_id'=>absint( $app->id ), 'evidence_type'=>$record->document_type, 'access_mode'=>sanitize_key( $grant->mode ) ), false ) : new WP_Error( 'gdo_evidence_grant_conflict', __( 'The credential access grant was already used.', 'global-doctor-onboarding' ) );
        if ( 1 !== $used || is_wp_error( $event ) || false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            return is_wp_error( $event ) ? $event : new WP_Error( 'gdo_evidence_grant_conflict', __( 'The credential access grant was already used.', 'global-doctor-onboarding' ) );
        }
        GDO_Notifications::process( 1, $event );
        return array( 'grant'=>$grant, 'record'=>$record );
    }

    public static function review_bytes( $record, $reviewer_id ) {
        $bytes = self::decrypt_record( $record );
        if ( is_wp_error( $bytes ) ) {
            return $bytes;
        }
        $label = 'PRIVATE REVIEW • ' . absint( $reviewer_id ) . ' • ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';
        if ( 0 === strpos( (string) $record->mime_type, 'image/' ) ) {
            $image = @imagecreatefromstring( $bytes );
            if ( ! $image ) {
                return new WP_Error( 'gdo_watermark_image', __( 'The credential image could not be prepared for review.', 'global-doctor-onboarding' ) );
            }
            $black = imagecolorallocatealpha( $image, 0, 0, 0, 35 );
            $white = imagecolorallocate( $image, 255, 255, 255 );
            $height = imagesy( $image );
            imagefilledrectangle( $image, 0, max( 0, $height - 32 ), imagesx( $image ), $height, $black );
            imagestring( $image, 3, 8, max( 0, $height - 23 ), $label, $white );
            ob_start();
            $ok = 'image/png' === $record->mime_type ? imagepng( $image, null, 7 ) : ( 'image/webp' === $record->mime_type && function_exists( 'imagewebp' ) ? imagewebp( $image, null, 88 ) : imagejpeg( $image, null, 88 ) );
            $out = ob_get_clean();
            imagedestroy( $image );
            return $ok && $out ? $out : new WP_Error( 'gdo_watermark_image', __( 'The credential image watermark could not be created.', 'global-doctor-onboarding' ) );
        }
        if ( 'application/pdf' === $record->mime_type ) {
            $watermarked = apply_filters( 'gdo_watermark_pdf_bytes', null, $bytes, $label, $record );
            return is_string( $watermarked ) && 0 === strpos( $watermarked, '%PDF-' ) ? $watermarked : new WP_Error( 'gdo_pdf_watermark_provider', __( 'A secure PDF watermark provider is required for browser review.', 'global-doctor-onboarding' ) );
        }
        return new WP_Error( 'gdo_review_mime', __( 'This credential type cannot be rendered for review.', 'global-doctor-onboarding' ) );
    }

}
