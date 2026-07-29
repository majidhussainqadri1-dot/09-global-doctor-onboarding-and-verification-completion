<?php
/**
 * Plugin Name: Global Doctor Onboarding and Verification Completion
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Complete American English doctor application, encrypted credentials, review, resubmission, and verification workflow.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: global-doctor-onboarding
 */
defined('ABSPATH')||exit;define('GDO_VERSION','1.0.0');define('GDO_FILE',__FILE__);define('GDO_DIR',plugin_dir_path(__FILE__));define('GDO_URL',plugin_dir_url(__FILE__));
require_once GDO_DIR.'includes/class-gdo-helpers.php';require_once GDO_DIR.'includes/class-gdo-activator.php';require_once GDO_DIR.'includes/class-gdo-frontend.php';require_once GDO_DIR.'includes/class-gdo-admin.php';require_once GDO_DIR.'includes/class-gdo-privacy.php';require_once GDO_DIR.'includes/class-gdo-plugin.php';
register_activation_hook(GDO_FILE,array('GDO_Activator','activate'));register_deactivation_hook(GDO_FILE,array('GDO_Activator','deactivate'));function gdo_start(){if(class_exists('SPD_Helpers')&&class_exists('SDD_Helpers')){(new GDO_Plugin())->run();}else{add_action('admin_notices',function(){echo '<div class="notice notice-error"><p><strong>Global Doctor Onboarding:</strong> Activate Files 03 and 07 first.</p></div>';});}}add_action('plugins_loaded','gdo_start',40);

