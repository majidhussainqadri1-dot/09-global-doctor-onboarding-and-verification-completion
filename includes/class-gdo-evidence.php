<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Evidence {
    const MAX_BYTES = 5242880;
    const MAX_PIXELS = 24000000;

    public static function types() {
        return array(
            'identity'      => __( 'Government-issued identity document', 'global-doctor-onboarding' ),
            'qualification' => __( 'Professional qualification or degree', 'global-doctor-onboarding' ),
            'license'       => __( 'Current license or registration evidence', 'global-doctor-onboarding' ),
        );
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

    private static function normalize_upload( array $file, $type ) {
        if ( empty( $file['tmp_name'] ) || ! isset( $file['error'], $file['size'], $file['name'] ) ) {
            return new WP_Error( 'gdo_missing_upload', __( 'A required credential file is missing.', 'global-doctor-onboarding' ) );
        }
        $is_uploaded = is_uploaded_file( $file['tmp_name'] );
        $is_uploaded = (bool) apply_filters( 'gdo_is_uploaded_file', $is_uploaded, $file['tmp_name'], $type );
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
        if ( ! class_exists( 'finfo' ) ) { return new WP_Error( 'gdo_fileinfo_missing', __( 'The server file-information extension is required.', 'global-doctor-onboarding' ) ); }
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
            if ( 0 !== strpos( $bytes, '%PDF-' ) || false === strrpos( substr( $bytes, -2048 ), '%%EOF' ) ) {
                return new WP_Error( 'gdo_pdf_structure', __( 'The PDF structure is invalid.', 'global-doctor-onboarding' ) );
            }
            if ( preg_match( '/\/(JavaScript|JS|OpenAction|Launch|EmbeddedFile|RichMedia)\b/i', $bytes ) ) {
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
        return array(
            'bytes' => $bytes,
            'mime'  => $mime,
            'name'  => sanitize_file_name( $file['name'] ),
            'size'  => strlen( $bytes ),
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
        return array( 'bytes' => $out, 'mime' => $mime );
    }

    public static function stage_upload( $application, $type, array $file ) {
        global $wpdb;
        $type = sanitize_key( $type );
        if ( ! isset( self::types()[ $type ] ) ) {
            return new WP_Error( 'gdo_document_type', __( 'Unknown credential type.', 'global-doctor-onboarding' ) );
        }
        $normalized = self::normalize_upload( $file, $type );
        if ( is_wp_error( $normalized ) ) {
            return $normalized;
        }
        $current = self::current( $application->id, $type );
        $version = $current ? absint( $current->version ) + 1 : 1;
        $meta = array(
            'application_uuid'    => $application->application_uuid,
            'application_version' => $application->version,
            'user_id'             => $application->user_id,
            'document_type'       => $type,
            'document_version'    => $version,
        );
        $encrypted = GDO_Crypto::encrypt( $normalized['bytes'], $meta );
        if ( is_wp_error( $encrypted ) ) {
            return $encrypted;
        }
        $storage_name = wp_generate_uuid4() . '.gdo2';
        $stored = GDO_Storage::atomic_write( $storage_name, $encrypted['bytes'] );
        if ( is_wp_error( $stored ) ) {
            return $stored;
        }
        $now = current_time( 'mysql', true );
        $data = array(
            'application_id'     => absint( $application->id ),
            'user_id'            => absint( $application->user_id ),
            'document_type'      => $type,
            'version'            => $version,
            'status'             => 'pending_review',
            'original_name'      => $normalized['name'],
            'mime_type'          => $normalized['mime'],
            'file_size'          => $normalized['size'],
            'storage_name'       => $storage_name,
            'ciphertext_sha256'  => $stored['sha256'],
            'content_hmac'       => $encrypted['content_hmac'],
            'key_id'             => $encrypted['key_id'],
            'envelope_version'   => $encrypted['version'],
            'retention_state'    => 'active',
            'created_at'         => $now,
            'updated_at'         => $now,
        );
        $inserted = $wpdb->insert( GDO_Schema::table( 'evidence' ), $data, array( '%d','%d','%s','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s' ) );
        if ( ! $inserted ) {
            GDO_Storage::delete_verified( $storage_name, $stored['sha256'] );
            return new WP_Error( 'gdo_evidence_insert', __( 'Credential evidence could not be recorded.', 'global-doctor-onboarding' ) );
        }
        if ( $current ) {
            $wpdb->update( GDO_Schema::table( 'evidence' ), array( 'retention_state'=>'superseded','updated_at'=>$now ), array( 'id'=>$current->id ), array( '%s','%s' ), array( '%d' ) );
        }
        return array( 'id'=>absint($wpdb->insert_id), 'storage_name'=>$storage_name, 'ciphertext_sha256'=>$stored['sha256'] );
    }

    public static function all_present( $application_id ) {
        foreach ( array_keys( self::types() ) as $type ) {
            if ( ! self::current( $application_id, $type ) ) {
                return false;
            }
        }
        return true;
    }

    public static function all_accepted( $application_id ) {
        foreach ( array_keys( self::types() ) as $type ) {
            $record = self::current( $application_id, $type );
            if ( ! $record || 'accepted' !== $record->status || empty( $record->reviewer_id ) || empty( $record->reviewed_at ) ) {
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
        return GDO_Crypto::decrypt( $envelope, $meta );
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
        if ( is_wp_error( $encrypted ) ) return $encrypted;
        $new_name = wp_generate_uuid4() . '.gdo2';
        $stored = GDO_Storage::atomic_write( $new_name, $encrypted['bytes'] );
        if ( is_wp_error( $stored ) ) return $stored;
        $ok = $wpdb->update( GDO_Schema::table('evidence'), array( 'storage_name'=>$new_name,'ciphertext_sha256'=>$stored['sha256'],'content_hmac'=>$encrypted['content_hmac'],'key_id'=>$encrypted['key_id'],'updated_at'=>current_time('mysql',true) ), array('id'=>$record->id), array('%s','%s','%s','%s','%s'), array('%d') );
        if ( false === $ok ) { GDO_Storage::delete_verified($new_name,$stored['sha256']); return new WP_Error('gdo_rotation_database',__('The rotated credential could not be committed.','global-doctor-onboarding')); }
        $deleted = GDO_Storage::delete_verified( $record->storage_name, $record->ciphertext_sha256 );
        GDO_Membership_Adapter::audit( 'doctor_credential_key_rotated', array('evidence_id'=>absint($record->id),'old_key_id'=>$record->key_id,'new_key_id'=>$encrypted['key_id'],'old_deleted'=>!is_wp_error($deleted)) );
        return true;
    }

    public static function review( $evidence_id, $reviewer_id, $status, array $checklist, $registry_result, $validity_from, $validity_until ) {
        global $wpdb;
        $record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GDO_Schema::table( 'evidence' ) . ' WHERE id=%d', absint( $evidence_id ) ) );
        if ( ! $record || ! in_array( $status, array( 'accepted', 'rejected', 'more_information' ), true ) ) {
            return new WP_Error( 'gdo_evidence_review', __( 'Invalid credential review.', 'global-doctor-onboarding' ) );
        }
        $required = array( 'name_match', 'document_legible', 'authenticity_method', 'scope_match' );
        foreach ( $required as $key ) {
            if ( empty( $checklist[ $key ] ) ) {
                return new WP_Error( 'gdo_evidence_checklist', __( 'Complete every credential review checklist item.', 'global-doctor-onboarding' ) );
            }
        }
        if ( 'license' === $record->document_type && ( ! $registry_result || ! $validity_until ) ) {
            return new WP_Error( 'gdo_license_review', __( 'License registry result and validity date are required.', 'global-doctor-onboarding' ) );
        }
        $data = array(
            'status'          => sanitize_key( $status ),
            'checklist_json'  => wp_json_encode( $checklist, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'registry_result' => sanitize_key( $registry_result ),
            'reviewer_id'     => absint( $reviewer_id ),
            'reviewed_at'     => current_time( 'mysql', true ),
            'validity_from'   => $validity_from ? sanitize_text_field( $validity_from ) : null,
            'validity_until'  => $validity_until ? sanitize_text_field( $validity_until ) : null,
            'updated_at'      => current_time( 'mysql', true ),
        );
        $wpdb->update( GDO_Schema::table( 'evidence' ), $data, array( 'id'=>$record->id ), array( '%s','%s','%s','%d','%s','%s','%s','%s' ), array( '%d' ) );
        GDO_Membership_Adapter::audit( 'doctor_evidence_reviewed', array( 'application_id'=>absint($record->application_id),'evidence_id'=>absint($record->id),'reviewer_id'=>absint($reviewer_id),'status'=>$status ) );
        return true;
    }
}
