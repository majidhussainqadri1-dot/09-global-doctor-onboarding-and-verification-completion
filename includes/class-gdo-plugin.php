<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Plugin {
    public function run() {
        add_action( 'admin_notices', array($this,'dependency_notice') );
        add_action( 'wp_logout', array('GDO_Membership_Adapter','clear_step_up') );
        add_action( 'smc_professional_claim_acknowledged', array($this,'claim_acknowledged'), 10, 4 );
        if ( ! GDO_Membership_Adapter::available() ) {
            return;
        }
        $migration = GDO_Migration::maybe_run();
        if ( is_wp_error( $migration ) ) {
            GDO_Membership_Adapter::audit( 'doctor_verification_runtime_blocked', array( 'reason'=>$migration->get_error_code(), 'layer'=>'core_schema' ) );
            return;
        }
        $advanced = GDO_Advanced_Trust_Hardening::maybe_upgrade_schema();
        if ( is_wp_error( $advanced ) ) {
            GDO_Membership_Adapter::audit( 'doctor_verification_runtime_blocked', array( 'reason'=>$advanced->get_error_code(), 'layer'=>'advanced_trust_schema' ) );
            return;
        }
        (new GDO_REST())->hooks();
        (new GDO_Integration_Contracts())->hooks();
        GDO_Notifications::register_file19_producer();
        (new GDO_Advanced_Trust())->hooks();
        GDO_Advanced_Trust_Hardening::hooks();
        GDO_Advanced_Trust_Events::hooks();
        (new GDO_Frontend())->hooks();
        (new GDO_Admin())->hooks();
        (new GDO_Privacy())->hooks();
        (new GDO_Retention())->hooks();
        add_action( 'wp_enqueue_scripts', array($this,'assets') );
        add_action( 'admin_enqueue_scripts', array($this,'admin_assets') );
        add_action( 'template_redirect', array($this,'private_headers') );
        add_filter( 'wp_robots', array($this,'robots') );
        add_filter( 'sabri_file20_navigation_items', array($this,'shell_item') );
        add_filter( 'sabri_file20_module_health', array($this,'shell_health') );
    }

    public function dependency_notice() {
        if ( ! current_user_can('activate_plugins') ) {
            return;
        }
        if ( ! GDO_Membership_Adapter::available() ) {
            echo '<div class="notice notice-error"><p><strong>File 09:</strong> File 00 Membership Core is required. Verification remains fail-closed.</p></div>';
            return;
        }
        if ( GDO_Operations::safe_mode() ) {
            echo '<div class="notice notice-warning"><p><strong>File 09:</strong> Safe Mode is active. All application and verification mutations are disabled.</p></div>';
        }
        if ( ! GDO_Membership_Adapter::authentication_available() ) {
            echo '<div class="notice notice-error"><p><strong>File 09:</strong> File 02 professional reauthentication is unavailable; reviewer actions remain fail-closed.</p></div>';
            return;
        }
        if ( ! defined( 'GDO_CLAIM_SIGNING_KEY' ) || strlen( (string) GDO_CLAIM_SIGNING_KEY ) < 32 ) {
            echo '<div class="notice notice-error"><p><strong>File 09:</strong> Configure a private professional-claim signing key; decisions remain fail-closed.</p></div>';
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
        if ( ! function_exists('sun_ingest_domain_event') && ! has_action('sabri_notify') && ! class_exists('SUN_Core') ) {
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
        if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'gdo_doctor_application' ) ) {
            wp_enqueue_style( 'gdo-onboarding', GDO_URL . 'assets/css/onboarding.css', array(), GDO_VERSION );
            wp_enqueue_script( 'gdo-onboarding', GDO_URL . 'assets/js/onboarding.js', array(), GDO_VERSION, true );
            wp_localize_script( 'gdo-onboarding', 'gdoOnboarding', array(
                'restUrl'=>esc_url_raw( rest_url( GDO_REST::NAMESPACE_VERSION . '/application' ) ),
                'trustRestUrl'=>esc_url_raw( rest_url( GDO_Advanced_Trust::REST_NAMESPACE . '/trust' ) ),
                'nonce'=>wp_create_nonce( 'wp_rest' ),
                'autosaveDelay'=>1500,
                'messages'=>array( 'saving'=>__('Saving…','global-doctor-onboarding'), 'saved'=>__('Draft saved','global-doctor-onboarding'), 'conflict'=>__('The draft changed elsewhere. Reload before continuing.','global-doctor-onboarding') ),
            ) );
        }
    }
    public function admin_assets($hook){if(false!==strpos($hook,'global-doctor-verification'))wp_enqueue_style('gdo-admin',GDO_URL.'assets/css/admin.css',array(),GDO_VERSION);}
    public function shell_item($items){$items['doctor-application']=array('label'=>'Doctor Application','url'=>self::application_url(),'capability'=>'read');return $items;}

    public function shell_health( $health ) {
        $health['file09'] = GDO_Operations::health();
        $health['file09']['advanced_trust_schema'] = absint( get_option( 'gdo_advanced_trust_schema', 0 ) );
        $health['file09']['advanced_trust_contract'] = GDO_Advanced_Trust_Hardening::CONTRACT_VERSION;
        $health['file09']['review80_corrective_layer'] = true;
        return $health;
    }

    public function claim_acknowledged( $application_id, $claim_version, $status, $reason = '' ) {
        GDO_Claims::acknowledge( $application_id, $claim_version, $status, $reason );
    }
}
