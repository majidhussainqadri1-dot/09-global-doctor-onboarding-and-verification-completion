#!/usr/bin/env python3
from pathlib import Path
import json
root=Path('.')
baseline='7875ce20a0fd40c0952960b6e45cf1e869b9c4dd'
# release allowlist
p=root/'RELEASE-FILES.txt'; files=[x for x in p.read_text(encoding='utf-8').splitlines() if x.strip()]
if 'REVIEW-80-ROUNDS-RC6-R4.md' not in files: files.append('REVIEW-80-ROUNDS-RC6-R4.md')
if len(files)!=55: raise SystemExit(f'unexpected R4 release allowlist size {len(files)}')
p.write_text('\n'.join(files)+'\n',encoding='utf-8')
# lock
p=root/'RELEASE-LOCK.json'; lock=json.loads(p.read_text(encoding='utf-8'))
lock.update({'release_file_count':55,'fourth_review_baseline':baseline,'fourth_review_rounds':80,'fourth_defect_rounds':30,'fourth_clean_rounds':50})
p.write_text(json.dumps(lock,indent=2,sort_keys=True)+'\n',encoding='utf-8')
# builder
p=root/'tools/build-release.py'; s=p.read_text(encoding='utf-8')
old="'third_review_rounds':80,'third_defect_rounds':13,'third_clean_rounds':67,"
new=old+"'fourth_review_rounds':80,'fourth_defect_rounds':30,'fourth_clean_rounds':50,"
if old not in s and new not in s: raise SystemExit('builder R4 anchor missing')
s=s.replace(old,new,1); p.write_text(s,encoding='utf-8')
# release integrity
p=root/'tests/release-integrity.py'; s=p.read_text(encoding='utf-8')
old="lock.get('third_review_baseline')!='f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361' or lock.get('release_file_count')!=len(release_files)"
new="lock.get('third_review_baseline')!='f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361' or lock.get('fourth_review_rounds')!=80 or lock.get('fourth_defect_rounds')!=30 or lock.get('fourth_clean_rounds')!=50 or lock.get('fourth_review_baseline')!='7875ce20a0fd40c0952960b6e45cf1e869b9c4dd' or lock.get('release_file_count')!=len(release_files)"
if old not in s and new not in s: raise SystemExit('release-integrity lock anchor missing')
s=s.replace(old,new,1)
old2="'REVIEW-80-ROUNDS-RC6-R3.md']"
new2="'REVIEW-80-ROUNDS-RC6-R3.md','REVIEW-80-ROUNDS-RC6-R4.md']"
if old2 not in s and new2 not in s: raise SystemExit('release-integrity evidence anchor missing')
s=s.replace(old2,new2,1)
p.write_text(s,encoding='utf-8')
print('R4 release evidence synchronized: 55-entry package and fourth-review lock.')
