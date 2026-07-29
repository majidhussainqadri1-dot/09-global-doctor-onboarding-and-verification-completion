<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Storage {
    public static function directory() {
        $dir = defined( 'GDO_PRIVATE_STORAGE_DIR' ) ? (string) GDO_PRIVATE_STORAGE_DIR : '';
        $dir = (string) apply_filters( 'gdo_private_storage_directory', $dir );
        return $dir ? wp_normalize_path( untrailingslashit( $dir ) ) : '';
    }

    public static function health() {
        $dir = self::directory();
        if ( ! $dir || ! wp_is_writable( dirname( $dir ) ) && ! is_dir( $dir ) ) {
            return new WP_Error( 'gdo_storage_unconfigured', __( 'Private credential storage is not configured or writable.', 'global-doctor-onboarding' ) );
        }
        $uploads = wp_upload_dir();
        $upload_base = wp_normalize_path( untrailingslashit( $uploads['basedir'] ) );
        if ( 0 === strpos( $dir . '/', $upload_base . '/' ) ) {
            return new WP_Error( 'gdo_storage_public', __( 'Credential storage must be outside the public uploads directory.', 'global-doctor-onboarding' ) );
        }
        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return new WP_Error( 'gdo_storage_create_failed', __( 'Private credential storage could not be created.', 'global-doctor-onboarding' ) );
        }
        @chmod( $dir, 0700 );
        if ( ! is_writable( $dir ) ) {
            return new WP_Error( 'gdo_storage_not_writable', __( 'Private credential storage is not writable.', 'global-doctor-onboarding' ) );
        }
        $url_exposed = (bool) apply_filters( 'gdo_private_storage_url_exposed', false, $dir );
        if ( $url_exposed ) {
            return new WP_Error( 'gdo_storage_url_exposed', __( 'Private credential storage is web-accessible.', 'global-doctor-onboarding' ) );
        }
        return true;
    }

    public static function path( $storage_name ) {
        return trailingslashit( self::directory() ) . basename( (string) $storage_name );
    }

    public static function atomic_write( $storage_name, $bytes ) {
        $health = self::health();
        if ( is_wp_error( $health ) ) {
            return $health;
        }
        $final = self::path( $storage_name );
        $temp = self::path( '.tmp-' . wp_generate_uuid4() );
        $handle = @fopen( $temp, 'xb' );
        if ( ! $handle ) {
            return new WP_Error( 'gdo_storage_temp_failed', __( 'A private temporary credential file could not be created.', 'global-doctor-onboarding' ) );
        }
        @chmod( $temp, 0600 );
        $written = fwrite( $handle, $bytes );
        if ( function_exists( 'fsync' ) ) {
            @fsync( $handle );
        }
        fclose( $handle );
        if ( strlen( $bytes ) !== $written || ! @rename( $temp, $final ) ) {
            @unlink( $temp );
            return new WP_Error( 'gdo_storage_commit_failed', __( 'The encrypted credential could not be committed.', 'global-doctor-onboarding' ) );
        }
        @chmod( $final, 0600 );
        return array( 'path' => $final, 'sha256' => hash_file( 'sha256', $final ) );
    }

    public static function read( $storage_name ) {
        $path = self::path( $storage_name );
        if ( ! is_file( $path ) || ! is_readable( $path ) ) {
            return new WP_Error( 'gdo_storage_missing', __( 'The credential file is unavailable.', 'global-doctor-onboarding' ) );
        }
        $bytes = file_get_contents( $path );
        return false === $bytes ? new WP_Error( 'gdo_storage_read_failed', __( 'The credential file could not be read.', 'global-doctor-onboarding' ) ) : $bytes;
    }

    public static function delete_verified( $storage_name, $expected_sha256 ) {
        $path = self::path( $storage_name );
        if ( ! is_file( $path ) ) {
            return true;
        }
        if ( $expected_sha256 && ! hash_equals( (string) $expected_sha256, (string) hash_file( 'sha256', $path ) ) ) {
            return new WP_Error( 'gdo_delete_hash_mismatch', __( 'Credential deletion was stopped because the encrypted file hash did not match.', 'global-doctor-onboarding' ) );
        }
        if ( ! @unlink( $path ) || is_file( $path ) ) {
            return new WP_Error( 'gdo_delete_failed', __( 'The credential file could not be physically deleted.', 'global-doctor-onboarding' ) );
        }
        return hash( 'sha256', $storage_name . '|' . $expected_sha256 . '|' . current_time( 'mysql', true ) );
    }
}
