<?php
defined('WP_UNINSTALL_PLUGIN')||exit;
$allow=defined('SABRI_ALLOW_DESTRUCTIVE_UNINSTALL')&&SABRI_ALLOW_DESTRUCTIVE_UNINSTALL&&'1'===get_option('gdo_allow_destructive_uninstall','0');
if(!$allow)return;
global $wpdb;
$dir=defined('GDO_PRIVATE_STORAGE_DIR')?wp_normalize_path(untrailingslashit(GDO_PRIVATE_STORAGE_DIR)):'';
$uploads=wp_normalize_path(untrailingslashit(wp_upload_dir()['basedir']));
$content=wp_normalize_path(untrailingslashit(WP_CONTENT_DIR));
if($dir&&is_dir($dir)&&0!==strpos($dir.'/',$uploads.'/')&&0!==strpos($dir.'/',$content.'/')&&!is_link($dir)){
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file){if($file->isLink())continue;$file->isDir()?@rmdir($file->getPathname()):@unlink($file->getPathname());}
    @rmdir($dir);
}
foreach(array('applications','evidence','transitions','consents','access_log','appeals','outbox','rate_limits','documents') as $name){$wpdb->query('DROP TABLE IF EXISTS `'.$wpdb->prefix.'gdo_'.$name.'`');}
$map=(array)get_option('gdo_page_map',array());
if(!empty($map['apply'])&&'doctor-application'===get_post_meta(absint($map['apply']),'_gdo_managed_page_key',true))wp_delete_post(absint($map['apply']),true);
foreach(array('gdo_page_map','gdo_version','gdo_schema_version','gdo_allow_destructive_uninstall') as $option)delete_option($option);
foreach(wp_roles()->roles as $role_name=>$details){$role=get_role($role_name);if($role&&$role->has_cap('manage_global_doctor_verification'))$role->remove_cap('manage_global_doctor_verification');}
wp_clear_scheduled_hook('gdo_daily_retention');
wp_clear_scheduled_hook('gdo_notification_outbox');
