<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Crypto {
    const MAGIC = 'GDO2';

    public static function keyring() {
        $ring = null;
        if ( defined( 'GDO_KEYRING' ) ) {
            $ring = is_array( GDO_KEYRING ) ? GDO_KEYRING : json_decode( (string) GDO_KEYRING, true );
        }
        $ring = apply_filters( 'gdo_keyring', $ring );
        if ( ! is_array( $ring ) || empty( $ring['active'] ) || empty( $ring['keys'] ) || ! is_array( $ring['keys'] ) ) {
            return new WP_Error( 'gdo_keyring_missing', __( 'A versioned File 09 encryption keyring is required.', 'global-doctor-onboarding' ) );
        }
        foreach ( $ring['keys'] as $id => $encoded ) {
            $raw = base64_decode( (string) $encoded, true );
            if ( ! preg_match( '/^[A-Za-z0-9._-]{1,64}$/', (string) $id ) || false === $raw || 32 !== strlen( $raw ) ) {
                return new WP_Error( 'gdo_keyring_invalid', __( 'The File 09 encryption keyring is invalid.', 'global-doctor-onboarding' ) );
            }
        }
        if ( ! isset( $ring['keys'][ $ring['active'] ] ) ) {
            return new WP_Error( 'gdo_active_key_missing', __( 'The active File 09 encryption key is missing.', 'global-doctor-onboarding' ) );
        }
        return $ring;
    }

    public static function available() {
        return function_exists( 'openssl_encrypt' ) && ! is_wp_error( self::keyring() );
    }

    private static function key( $key_id ) {
        $ring = self::keyring();
        if ( is_wp_error( $ring ) || ! isset( $ring['keys'][ $key_id ] ) ) {
            return new WP_Error( 'gdo_key_unavailable', __( 'The credential key version is unavailable.', 'global-doctor-onboarding' ) );
        }
        return base64_decode( (string) $ring['keys'][ $key_id ], true );
    }

    public static function aad( array $meta ) {
        $required = array( 'application_uuid', 'application_version', 'user_id', 'document_type', 'document_version' );
        $clean = array();
        foreach ( $required as $key ) {
            if ( ! isset( $meta[ $key ] ) || '' === (string) $meta[ $key ] ) {
                return new WP_Error( 'gdo_aad_missing', __( 'Credential metadata is incomplete.', 'global-doctor-onboarding' ) );
            }
            $clean[ $key ] = (string) $meta[ $key ];
        }
        return wp_json_encode( $clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    public static function encrypt( $plaintext, array $meta ) {
        $ring = self::keyring();
        $aad = self::aad( $meta );
        if ( is_wp_error( $ring ) || is_wp_error( $aad ) ) {
            return is_wp_error( $ring ) ? $ring : $aad;
        }
        $key_id = (string) $ring['active'];
        $key = self::key( $key_id );
        if ( is_wp_error( $key ) ) {
            return $key;
        }
        try {
            $iv = random_bytes( 12 );
        } catch ( Exception $e ) {
            return new WP_Error( 'gdo_random_failure', __( 'Secure random data could not be generated.', 'global-doctor-onboarding' ) );
        }
        $tag = '';
        $cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16 );
        if ( false === $cipher || 16 !== strlen( $tag ) ) {
            return new WP_Error( 'gdo_encrypt_failure', __( 'The credential could not be encrypted.', 'global-doctor-onboarding' ) );
        }
        $id_length = strlen( $key_id );
        $envelope = self::MAGIC . pack( 'n', $id_length ) . $key_id . $iv . $tag . $cipher;
        return array(
            'bytes'        => $envelope,
            'key_id'       => $key_id,
            'version'      => self::MAGIC,
            'content_hmac' => hash_hmac( 'sha256', $plaintext, $key ),
        );
    }

    public static function verify_content_hmac( $plaintext, $key_id, $expected ) {
        $key = self::key( (string) $key_id );
        if ( is_wp_error( $key ) || ! is_string( $expected ) || 64 !== strlen( $expected ) ) {
            return false;
        }
        return hash_equals( $expected, hash_hmac( 'sha256', $plaintext, $key ) );
    }

    public static function decrypt( $envelope, array $meta ) {
        if ( 0 === strpos( $envelope, self::MAGIC ) ) {
            $ring = self::keyring();
            $aad = self::aad( $meta );
            if ( is_wp_error( $ring ) || is_wp_error( $aad ) || strlen( $envelope ) < 34 ) {
                return new WP_Error( 'gdo_decrypt_failure', __( 'The credential envelope is invalid.', 'global-doctor-onboarding' ) );
            }
            $length = unpack( 'nlength', substr( $envelope, 4, 2 ) );
            $id_length = isset( $length['length'] ) ? absint( $length['length'] ) : 0;
            if ( $id_length < 1 || $id_length > 64 ) {
                return new WP_Error( 'gdo_decrypt_failure', __( 'The credential envelope is invalid.', 'global-doctor-onboarding' ) );
            }
            $offset = 6;
            $key_id = substr( $envelope, $offset, $id_length );
            $offset += $id_length;
            if ( ! isset( $ring['keys'][ $key_id ] ) || strlen( $envelope ) < $offset + 29 ) {
                return new WP_Error( 'gdo_key_unavailable', __( 'The credential key version is unavailable.', 'global-doctor-onboarding' ) );
            }
            $iv = substr( $envelope, $offset, 12 );
            $tag = substr( $envelope, $offset + 12, 16 );
            $cipher = substr( $envelope, $offset + 28 );
            $key = self::key( $key_id );
            if ( is_wp_error( $key ) ) {
                return $key;
            }
            $plain = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad );
            return false === $plain ? new WP_Error( 'gdo_authentication_failure', __( 'Credential authentication failed.', 'global-doctor-onboarding' ) ) : $plain;
        }
        if ( 0 === strpos( $envelope, 'GDO1' ) && apply_filters( 'gdo_allow_legacy_gdo1_decrypt', false ) ) {
            if ( strlen( $envelope ) < 33 ) {
                return new WP_Error( 'gdo_legacy_decrypt_failure', __( 'Legacy credential decryption failed.', 'global-doctor-onboarding' ) );
            }
            $legacy_key = hash( 'sha256', wp_salt( 'auth' ) . '|gdo-credentials', true );
            $iv = substr( $envelope, 4, 12 );
            $tag = substr( $envelope, 16, 16 );
            $plain = openssl_decrypt( substr( $envelope, 32 ), 'aes-256-gcm', $legacy_key, OPENSSL_RAW_DATA, $iv, $tag );
            return false === $plain ? new WP_Error( 'gdo_legacy_decrypt_failure', __( 'Legacy credential decryption failed.', 'global-doctor-onboarding' ) ) : $plain;
        }
        return new WP_Error( 'gdo_unknown_envelope', __( 'Unknown credential envelope version.', 'global-doctor-onboarding' ) );
    }
}
