<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Storage {
    public static function directory() {
        $dir = defined( 'GDO_PRIVATE_STORAGE_DIR' ) ? (string) GDO_PRIVATE_STORAGE_DIR : '';
        $dir = (string) apply_filters( 'gdo_private_storage_directory', $dir );
        return $dir ? wp_normalize_path( untrailingslashit( $dir ) ) : '';
    }
    public static function health() {
        $dir=self::directory();
        if(!$dir||(!is_dir($dir)&&!is_writable(dirname($dir)))){return new WP_Error('gdo_storage_unconfigured',__('Private credential storage is not configured or writable.','global-doctor-onboarding'));}
        if(!is_dir($dir)&&!wp_mkdir_p($dir)){return new WP_Error('gdo_storage_create_failed',__('Private credential storage could not be created.','global-doctor-onboarding'));}
        if(!@chmod($dir,0700)){return new WP_Error('gdo_storage_permissions',__('Private credential storage permissions could not be secured.','global-doctor-onboarding'));}
        if(!is_writable($dir)||is_link( $dir )){return new WP_Error('gdo_storage_not_writable',__('Private credential storage is unsafe or not writable.','global-doctor-onboarding'));}
        $uploads=wp_upload_dir();$real_dir = realpath( $dir ); $real_upload = realpath( $uploads['basedir'] ); $real_content = realpath( WP_CONTENT_DIR );
        if(false===$real_dir||false===$real_upload||false===$real_content){return new WP_Error('gdo_storage_realpath',__('Private credential storage could not be resolved to a canonical filesystem path.','global-doctor-onboarding'));}
        $canonical_dir=trailingslashit(wp_normalize_path($real_dir));$upload_base=trailingslashit(wp_normalize_path($real_upload));$content_base=trailingslashit(wp_normalize_path($real_content));
        if(0===strpos($canonical_dir,$upload_base)){return new WP_Error('gdo_storage_public',__('Credential storage must be outside the public uploads directory.','global-doctor-onboarding'));}
        if((bool)apply_filters('gdo_require_storage_outside_wp_content',true)&&0===strpos($canonical_dir,$content_base)){return new WP_Error('gdo_storage_wp_content',__('Credential storage must be outside the publicly served WordPress content tree.','global-doctor-onboarding'));}
        if((bool)apply_filters('gdo_private_storage_url_exposed',false,wp_normalize_path($real_dir))){return new WP_Error('gdo_storage_url_exposed',__('Private credential storage is web-accessible.','global-doctor-onboarding'));}
        return true;
    }

    public static function path( $storage_name ) {
        $name = basename( (string) $storage_name );
        if ( ! preg_match( '/^(?:[a-f0-9-]{36}|legacy-[a-f0-9-]{36}|\.tmp-[a-f0-9-]{36})\.(?:gdo1|gdo2)?$/i', $name ) && 0 !== strpos( $name, '.tmp-' ) ) {
            $name = basename( sanitize_file_name( $name ) );
        }
        return trailingslashit( self::directory() ) . $name;
    }
    public static function atomic_write( $storage_name, $bytes ) {
        $health=self::health();if(is_wp_error($health)){return $health;}$final=self::path($storage_name);if(is_file($final)){return new WP_Error('gdo_storage_collision',__('A credential storage collision was detected.','global-doctor-onboarding'));}
        $temp=trailingslashit(self::directory()).'.tmp-'.wp_generate_uuid4();$handle=@fopen($temp,'xb');if(!$handle){return new WP_Error('gdo_storage_temp_failed',__('A private temporary credential file could not be created.','global-doctor-onboarding'));}
        if(!@chmod($temp,0600)){fclose($handle);@unlink($temp);return new WP_Error('gdo_storage_temp_permissions',__('Private temporary credential permissions could not be secured.','global-doctor-onboarding'));}
        $written=fwrite($handle,$bytes);$synced=true;if(function_exists('fsync')){$synced=fsync($handle);}fclose($handle);
        if(strlen($bytes)!==$written||!$synced||!@rename($temp,$final)){@unlink($temp);return new WP_Error('gdo_storage_commit_failed',__('The encrypted credential could not be committed durably.','global-doctor-onboarding'));}
        if(!@chmod($final,0600)){@unlink($final);return new WP_Error('gdo_storage_final_permissions',__('Committed credential permissions could not be secured.','global-doctor-onboarding'));}
        $sha256 = hash_file( 'sha256', $final );if(!is_string($sha256)||64!==strlen($sha256)){@unlink($final);return new WP_Error('gdo_storage_hash_failed',__('The committed credential could not be verified after storage.','global-doctor-onboarding'));}
        return array('path'=>$final,'sha256'=>$sha256);
    }

    public static function read( $storage_name ) {
        $health = self::health();
        if ( is_wp_error( $health ) ) { return $health; }
        $path = self::path( $storage_name );
        if ( ! is_file( $path ) || ! is_readable( $path ) || is_link( $path ) ) {
            return new WP_Error( 'gdo_storage_missing', __( 'The credential file is unavailable.', 'global-doctor-onboarding' ) );
        }
        $bytes = file_get_contents( $path );
        return false === $bytes ? new WP_Error( 'gdo_storage_read_failed', __( 'The credential file could not be read.', 'global-doctor-onboarding' ) ) : $bytes;
    }

    public static function delete_verified( $storage_name, $expected_sha256 ) {
        $health = self::health();
        if ( is_wp_error( $health ) ) { return $health; }
        $path = self::path( $storage_name );
        if ( ! is_file( $path ) ) {
            return true;
        }
        if ( is_link( $path ) ) {
            return new WP_Error( 'gdo_delete_symlink', __( 'Credential deletion was stopped because the path is unsafe.', 'global-doctor-onboarding' ) );
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
