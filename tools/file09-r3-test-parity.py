#!/usr/bin/env python3
from pathlib import Path

p=Path('tests/eighty-round-audit.py')
s=p.read_text(encoding='utf-8')
repls={
"check(13, 'Reviewer object-scope/IDOR recheck', has(hard, 'reviewer_scope_allows', \"'gdo_check_forbidden'\"))":"check(13, 'Reviewer object-scope/IDOR recheck', has(hard, 'reviewer_case_allows', \"'gdo_check_forbidden'\"))",
"check(57, 'Resumable upload terminates in canonical malware/type/encryption path', has(trust, 'GDO_Evidence::stage_upload', 'gdo_is_uploaded_file'))":"check(57, 'Resumable upload terminates in canonical malware/type/encryption path with explicit private-temp provenance', has(trust, 'GDO_Evidence::stage_upload', 'stage_upload($app,$row->document_type,$file,true,$path)') and has(evidence, 'trusted_internal_path', 'native_uploaded && $filtered_uploaded'))",
"check(63, 'Advanced Trust REST check revalidates object scope', has(hard, 'rest_check', 'reviewer_scope_allows'))":"check(63, 'Advanced Trust REST check revalidates exact case relationship', has(hard, 'rest_check', 'reviewer_case_allows'))",
}
for old,new in repls.items():
    if old not in s:
        raise SystemExit('missing eighty-round parity anchor: '+old[:100])
    s=s.replace(old,new,1)
p.write_text(s,encoding='utf-8')
print('File 09 historical 80-round regression assertions aligned with current stronger contracts.')
