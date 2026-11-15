<?php
/**
 * V12.3 File09 P00: read-only deployed reality collector.
 * Authorized WP-CLI use only: wp eval-file tools/file09-readonly-freeze.php
 * No data writes, log contents, identity records, credentials or raw keys.
 */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    fwrite(STDERR, "Refusing non-WP-CLI use.\n"); exit(20);
}
global $wpdb;
function file09_freeze_numeric($v) {
    return is_scalar($v) && preg_match('/^[0-9]{1,12}$/D', (string)$v)
        ? (string)$v : 'UNVERIFIED_OR_NONNUMERIC';
}
function file09_freeze_plugins($slug) {
    if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $active = (array)get_option('active_plugins', array());
    $list = array();
    foreach (get_plugins() as $path=>$meta) {
        if (strpos($path, $slug.'/') !== 0) continue;
        $list[] = array(
            'plugin_file'=>$path,
            'header_version'=>isset($meta['Version'])?(string)$meta['Version']:'UNKNOWN',
            'site_active'=>in_array($path,$active,true),
        );
    }
    return $list;
}
function file09_freeze_tree($slug) {
    $root=realpath(WP_PLUGIN_DIR.'/'.$slug);
    $plugin_dir=realpath(WP_PLUGIN_DIR);
    if (!$root || !$plugin_dir || is_link($root)
        || strpos($root,$plugin_dir.DIRECTORY_SEPARATOR)!==0) {
        return array('status'=>'DIRECTORY_UNVERIFIED');
    }
    $files=array();
    $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,
        FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($walk as $entry) {
        if (!$entry->isFile() || $entry->isLink()) continue;
        $resolved=realpath($entry->getPathname());
        if (!$resolved || strpos($resolved,$root.DIRECTORY_SEPARATOR)!==0) continue;
        $rel=str_replace('\\','/',substr($resolved,strlen($root)+1));
        // No hidden/config/private/storage data: hashes of allowlisted code/docs only.
        if (preg_match('~(^|/)\.~',$rel)
            || !preg_match('~\.(php|js|css|md|txt|json)$~i',$rel)
            || preg_match('~(^|/)(uploads|private|storage|cache|logs?)/~i',$rel)) continue;
        $hash=hash_file('sha256',$resolved);
        if ($hash!==false) $files[$rel]=$hash;
    }
    ksort($files,SORT_STRING);
    $identity='';
    foreach ($files as $path=>$digest) $identity.=$path."\t".$digest."\n";
    return array('status'=>'READ_ONLY_CODE_HASHES','file_count'=>count($files),
        'tree_sha256'=>hash('sha256',$identity),
        'tree_digest_is_not_zip_sha256'=>true,'file_hashes'=>$files);
}
function file09_freeze_schema() {
    global $wpdb;
    $prefix=$wpdb->prefix;
    $like=$wpdb->esc_like($prefix.'gdo_').'%';
    $wpdb->last_error='';
    $tables=$wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s',$like));
    if (!is_array($tables) || !empty($wpdb->last_error))
        return array('status'=>'TABLE_QUERY_FAILED');
    $res=array();
    foreach ($tables as $table) {
        if (!is_string($table) || strpos($table,$prefix.'gdo_')!==0
            || !preg_match('/^[a-zA-Z0-9_]+$/D',$table)) continue;
        $quoted=chr(96).$table.chr(96);
        $wpdb->last_error='';
        $columns=$wpdb->get_col('SHOW COLUMNS FROM '.$quoted);
        if (!is_array($columns) || !empty($wpdb->last_error)) {
            $res[substr($table,strlen($prefix))]=array('status'=>'COLUMNS_UNAVAILABLE');continue;
        }
        $wpdb->last_error='';
        $indexes=$wpdb->get_col('SHOW INDEX FROM '.$quoted,2);
        $res[substr($table,strlen($prefix))]=array(
            'columns'=>array_values($columns),
            'index_names'=>(!empty($wpdb->last_error)||!is_array($indexes))
                ? 'INDEX_QUERY_FAILED' : array_values(array_unique($indexes)),
        );
    }
    ksort($res,SORT_STRING);
    return array('status'=>'READ_ONLY_SCHEMA','tables'=>$res);
}
$data=array(
    'collector'=>'file09-v123-p00-v1',
    'collected_at_utc'=>gmdate('c'),
    'wp_version'=>get_bloginfo('version'),
    'php_version'=>PHP_VERSION,
    'db_server_version'=>$wpdb->db_version(),
    'declared_plugin_versions'=>array(
        'file09'=>file09_freeze_plugins('global-doctor-onboarding-09'),
        'file00'=>file09_freeze_plugins('sabri-membership-core'),
        'file02'=>file09_freeze_plugins('02-sabri-authentication'),
    ),
    'allowlisted_migration_markers'=>array(
        'gdo_schema_version'=>file09_freeze_numeric(get_option('gdo_schema_version',null)),
        'gdo_advanced_trust_schema'=>file09_freeze_numeric(get_option('gdo_advanced_trust_schema',null)),
    ),
    'deployed_file09_code'=>file09_freeze_tree('global-doctor-onboarding-09'),
    'gdo_table_schema'=>file09_freeze_schema(),
    'excluded'=>'No PII/doctor or patient records, row counts, secrets, key values, wp-config, logs, or option dumps',
    'parity_status'=>'NOT DETERMINED BY COLLECTION ALONE',
    'staging_acceptance'=>'NOT DETERMINED',
    'live_verified'=>false,
);
echo wp_json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
