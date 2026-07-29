<?php
defined('ABSPATH')||exit;
final class GDO_Plugin{
	public function run(){(new GDO_Frontend())->hooks();(new GDO_Admin())->hooks();(new GDO_Privacy())->hooks();add_action('wp_enqueue_scripts',array($this,'assets'));add_action('admin_enqueue_scripts',array($this,'admin_assets'));add_action('template_redirect',array($this,'headers'));add_filter('wp_robots',array($this,'robots'));add_filter('do_shortcode_tag',array($this,'application_link'),20,2);}
	public function assets(){global $post;if($post instanceof WP_Post&&has_shortcode($post->post_content,'gdo_doctor_application')){wp_enqueue_style('gdo-onboarding',GDO_URL.'assets/css/onboarding.css',array(),GDO_VERSION);}}
	public function admin_assets($hook){if(false!==strpos($hook,'global-doctor-verification')){wp_enqueue_style('gdo-admin',GDO_URL.'assets/css/admin.css',array(),GDO_VERSION);}}
	public function headers(){$m=(array)get_option('gdo_page_map',array());if(!empty($m['apply'])&&is_page($m['apply'])){nocache_headers();}}
	public function robots($r){$m=(array)get_option('gdo_page_map',array());if(!empty($m['apply'])&&is_page($m['apply'])){$r['noindex']=true;$r['noarchive']=true;}return $r;}
	public function application_link($output,$tag){if(is_user_logged_in()&&in_array($tag,array('sabri_edit_profile','sdd_profile_settings'),true)){return $output.'<div class="gdo-notice"><h2>Doctor Verification</h2><p>Complete the global application and encrypted credential review before public listing.</p><a class="gdo-button" href="'.esc_url(GDO_Helpers::page()).'">Open Doctor Application</a></div>';}return $output;}
}
