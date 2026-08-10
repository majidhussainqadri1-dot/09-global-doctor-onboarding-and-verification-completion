#!/usr/bin/env python3
from pathlib import Path


def replace_once(path, old, new):
    p=Path(path); s=p.read_text(encoding='utf-8')
    if old not in s:
        raise SystemExit(f'missing follow-up anchor in {path}: {old[:120]!r}')
    p.write_text(s.replace(old,new,1),encoding='utf-8')

# The generic upload filter can only narrow a native HTTP-upload verdict. Resumable
# uploads use a separate explicit internal path that must resolve inside GDO private
# storage and must be the exact .chunk-* file supplied by the owner workflow.
replace_once(
    'includes/class-gdo-evidence.php',
    "    private static function normalize_upload( array $file, $type ) {\n",
    "    private static function normalize_upload( array $file, $type, $trusted_internal_path = '' ) {\n",
)
replace_once(
    'includes/class-gdo-evidence.php',
    "        $is_uploaded = is_uploaded_file( $file['tmp_name'] );\n        $filtered_uploaded = (bool) apply_filters( 'gdo_is_uploaded_file', $is_uploaded, $file['tmp_name'], $type );\n        $is_uploaded = $is_uploaded && $filtered_uploaded;\n",
    "        $native_uploaded = is_uploaded_file( $file['tmp_name'] );\n        $filtered_uploaded = (bool) apply_filters( 'gdo_is_uploaded_file', $native_uploaded, $file['tmp_name'], $type );\n        $is_uploaded = $native_uploaded && $filtered_uploaded;\n        if ( ! $is_uploaded && $trusted_internal_path ) {\n            $storage_dir = GDO_Storage::directory();\n            $real_tmp = realpath( $file['tmp_name'] );\n            $real_trusted = realpath( $trusted_internal_path );\n            $real_storage = $storage_dir ? realpath( $storage_dir ) : false;\n            $prefix = $real_storage ? trailingslashit( wp_normalize_path( $real_storage ) ) : '';\n            $normalized_tmp = $real_tmp ? wp_normalize_path( $real_tmp ) : '';\n            $is_uploaded = $real_tmp && $real_trusted && hash_equals( wp_normalize_path( $real_trusted ), $normalized_tmp )\n                && $prefix && 0 === strpos( $normalized_tmp, $prefix )\n                && 0 === strpos( basename( $normalized_tmp ), '.chunk-' )\n                && is_file( $real_tmp ) && ! is_link( $file['tmp_name'] );\n        }\n",
)
replace_once(
    'includes/class-gdo-evidence.php',
    "    public static function stage_upload( $application, $type, array $file, $manage_transaction = true ) {\n",
    "    public static function stage_upload( $application, $type, array $file, $manage_transaction = true, $trusted_internal_path = '' ) {\n",
)
replace_once(
    'includes/class-gdo-evidence.php',
    "        $normalized = self::normalize_upload( $file, $type );\n",
    "        $normalized = self::normalize_upload( $file, $type, $trusted_internal_path );\n",
)

replace_once(
    'includes/class-gdo-advanced-trust.php',
    "$file=array('tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'size'=>filesize($path),'name'=>$row->original_name);$allow=function($is,$tmp)use($path){return wp_normalize_path($tmp)===wp_normalize_path($path)?true:$is;};add_filter('gdo_is_uploaded_file',$allow,99,2);$result=GDO_Evidence::stage_upload($app,$row->document_type,$file);remove_filter('gdo_is_uploaded_file',$allow,99);\n",
    "$file=array('tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'size'=>filesize($path),'name'=>$row->original_name);$result=GDO_Evidence::stage_upload($app,$row->document_type,$file,true,$path);\n",
)

# Historical R2 regression must track the stronger, explicit owner-internal path.
replace_once(
    'tests/eighty-round-audit-r2.py',
    "check(40,'Resumable finalize uses canonical evidence path',has(trust,'GDO_Evidence::stage_upload','gdo_is_uploaded_file'))",
    "check(40,'Resumable finalize uses canonical evidence path with explicit trusted internal provenance',has(trust,'GDO_Evidence::stage_upload','stage_upload($app,$row->document_type,$file,true,$path)') and has(evidence,'trusted_internal_path','native_uploaded && $filtered_uploaded'))",
)

print('File 09 R3 trusted internal resumable provenance follow-up applied.')
