<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Plugin {
    public function run() {
        add_action( 'admin_notices', array($this,'dependency_notice') );
        add_action( 'wp_logout', array('GDO_Membership_Adapter','clear_step_up') );
        if ( ! GDO_Membership_Adapter::available() ) {
            return;
        }
        GDO_Migration::maybe_run();
        (new GDO_Frontend())->hooks();
        (new GDO_Admin())->hooks();
        (new GDO_Privacy())->hooks();
        (new GDO_Retention())->hooks();
        add_action( 'wp_enqueue_scripts', array($this,'assets') );
        add_action( 'admin_enqueue_scripts', array($this,'admin_assets') );
        add_action( 'template_redirect', array($this,'private_headers') );
        add_filter( 'wp_robots', array($this,'robots') );
        add_filter( 'sabri_file20_navigation_items', array($this,'shell_item') );
    }

    public function dependency_notice() {
        if ( ! current_user_can('activate_plugins') ) {
            return;
        }
        if ( ! GDO_Membership_Adapter::available() ) {
            echo '<div class="notice notice-error"><p><strong>File 09:</strong> File 00 Membership Core is required. Verification remains fail-closed.</p></div>';
            return;
        }
        if ( ! GDO_Crypto::available() ) {
            echo '<div class="notice notice-error"><p><strong>File 09:</strong> Configure a valid versioned GDO_KEYRING. Credential operations remain disabled.</p></div>';
            return;
        }
        $health = GDO_Storage::health();
        if ( is_wp_error($health) ) {
            echo '<div class="notice notice-error"><p><strong>File 09:</strong> '.esc_html($health->get_error_message()).'</p></div>';
        }
        if ( ! has_action('sabri_notify') && ! class_exists('SUN_Core') ) {
            echo '<div class="notice notice-warning"><p><strong>File 09:</strong> File 19 Unified Notifications is unavailable; notification events remain queued in the outbox.</p></div>';
        }
    }

    public static function page_id() {
        $map=(array)get_option('gdo_page_map',array());
        $id=!empty($map['apply'])?absint($map['apply']):0;
        return $id && 'doctor-application'===get_post_meta($id,'_gdo_managed_page_key',true) ? $id : 0;
    }

    public static function application_url(array $args=array()) {
        $id=self::page_id();
        $url=$id?get_permalink($id):home_url('/doctor-application-file-09/');
        return $args?add_query_arg($args,$url):$url;
    }

    public function private_headers() {
        if ( self::page_id() && is_page(self::page_id()) ) {
            nocache_headers();
            header('Cache-Control: private, no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Robots-Tag: noindex, nofollow, noarchive',true);
            header('Referrer-Policy: no-referrer');
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
            header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()");
        }
    }

    public function robots($robots) {
        if ( self::page_id() && is_page(self::page_id()) ) {
            $robots['noindex']=true;$robots['nofollow']=true;$robots['noarchive']=true;
        }
        return $robots;
    }

    public function assets() {
        global $post;
        if($post instanceof WP_Post&&has_shortcode($post->post_content,'gdo_doctor_application'))wp_enqueue_style('gdo-onboarding',GDO_URL.'assets/css/onboarding.css',array(),GDO_VERSION);
    }
    public function admin_assets($hook){if(false!==strpos($hook,'global-doctor-verification'))wp_enqueue_style('gdo-admin',GDO_URL.'assets/css/admin.css',array(),GDO_VERSION);}
    public function shell_item($items){$items['doctor-application']=array('label'=>'Doctor Application','url'=>self::application_url(),'capability'=>'read');return $items;}
}
