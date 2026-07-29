<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Rate_Limiter {
    public static function hit( $bucket, $limit, $window ) {
        global $wpdb;
        $table = GDO_Schema::table( 'rate_limits' );
        $now = time();
        $window = max( 60, absint( $window ) );
        $started = $now - ( $now % $window );
        $hash = hash( 'sha256', (string) $bucket . '|' . $started . '|' . wp_salt( 'nonce' ) );
        $expires = $started + $window + 60;
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table} (bucket_hash,window_started,hits,expires_at) VALUES (%s,%d,1,%d)
             ON DUPLICATE KEY UPDATE hits=IF(window_started=VALUES(window_started),hits+1,1),window_started=VALUES(window_started),expires_at=VALUES(expires_at)",
            $hash, $started, $expires
        ) );
        $hits = absint( $wpdb->get_var( $wpdb->prepare( "SELECT hits FROM {$table} WHERE bucket_hash=%s", $hash ) ) );
        return $hits <= absint( $limit );
    }

    public static function cleanup() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . GDO_Schema::table( 'rate_limits' ) . ' WHERE expires_at < %d', time() ) );
    }
}
