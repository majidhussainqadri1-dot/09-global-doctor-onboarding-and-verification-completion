<?php
defined('ABSPATH')||exit;
final class GDO_Helpers{
	public static function types(){return array('identity'=>'Government-issued identity document','qualification'=>'Professional qualification or degree','license'=>'Current license or registration evidence');}
	public static function fields(){return array('country','city','clinic','qualification','licence_number','experience_years','specialty','languages','consultation_modes','phone','whatsapp','bio');}
	public static function documents($user){global $wpdb;return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gdo_documents WHERE user_id=%d ORDER BY uploaded_at DESC",absint($user)));}
	public static function has_document($user,$type){global $wpdb;return(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}gdo_documents WHERE user_id=%d AND document_type=%s LIMIT 1",absint($user),sanitize_key($type)));}
	public static function completion($user){if(SDD_Helpers::is_founder($user)){return 100;}$done=0;$total=17;if(get_the_author_meta('display_name',$user)){$done++;}if(absint(SPD_Helpers::get($user,'profile_photo_id',0))){$done++;}foreach(self::fields() as $key){if(trim((string)SPD_Helpers::get($user,$key))){$done++;}}foreach(array_keys(self::types()) as $type){if(self::has_document($user,$type)){$done++;}}return min(100,(int)round($done*100/$total));}
	public static function storage(){ $u=wp_upload_dir();$dir=trailingslashit($u['basedir']).'gdo-secure';if(!is_dir($dir)){wp_mkdir_p($dir);file_put_contents($dir.'/index.php','<?php http_response_code(403); exit;');file_put_contents($dir.'/.htaccess','Deny from all');file_put_contents($dir.'/web.config','<configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');}return $dir;}
	private static function key(){return hash('sha256',wp_salt('auth').'|gdo-credentials',true);}
	public static function encrypt($bytes){$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($bytes,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);return false===$cipher?false:'GDO1'.$iv.$tag.$cipher;}
	public static function decrypt($bytes){if(0!==strpos($bytes,'GDO1')){return false;}$iv=substr($bytes,4,12);$tag=substr($bytes,16,16);return openssl_decrypt(substr($bytes,32),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);}
	public static function status($user){return SPD_Helpers::verification_status($user);}
	public static function page(){ $m=(array)get_option('gdo_page_map',array());return !empty($m['apply'])?get_permalink($m['apply']):home_url('/doctor-application/');}
}

