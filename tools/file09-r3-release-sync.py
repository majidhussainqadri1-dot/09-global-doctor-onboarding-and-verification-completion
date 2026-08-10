#!/usr/bin/env python3
from pathlib import Path
import json

root=Path('.')

# Release allowlist: R3 ledger is release evidence; executable tests remain repository QA.
p=root/'RELEASE-FILES.txt'
files=[x for x in p.read_text(encoding='utf-8').splitlines() if x.strip()]
if 'REVIEW-80-ROUNDS-RC6-R3.md' not in files:
    files.append('REVIEW-80-ROUNDS-RC6-R3.md')
if len(files)!=54:
    raise SystemExit(f'unexpected R3 release allowlist size: {len(files)}')
p.write_text('\n'.join(files)+'\n',encoding='utf-8')

# Machine-readable release lock.
p=root/'RELEASE-LOCK.json'
lock=json.loads(p.read_text(encoding='utf-8'))
lock['release_file_count']=54
lock['third_review_baseline']='f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361'
lock['third_review_rounds']=80
lock['third_defect_rounds']=13
lock['third_clean_rounds']=67
p.write_text(json.dumps(lock,indent=2,sort_keys=True)+'\n',encoding='utf-8')

# Deterministic package manifest records all three repository review series.
p=root/'tools/build-release.py'; s=p.read_text(encoding='utf-8')
old="'release_candidate':release_candidate,'source_head':head,'review_rounds':80,'defect_rounds':49,'second_review_rounds':80,'second_defect_rounds':47,'second_clean_rounds':33,"
new="'release_candidate':release_candidate,'source_head':head,'review_rounds':80,'defect_rounds':49,'second_review_rounds':80,'second_defect_rounds':47,'second_clean_rounds':33,'third_review_rounds':80,'third_defect_rounds':13,'third_clean_rounds':67,"
if old not in s and new not in s:
    raise SystemExit('build-release R3 anchor missing')
s=s.replace(old,new,1)
p.write_text(s,encoding='utf-8')

# Release integrity must pin R3 lock fields and include its ledger.
p=root/'tests/release-integrity.py'; s=p.read_text(encoding='utf-8')
old="lock.get('second_review_baseline')!='c3fbbadbee06d2be13b23822f5f17fce07cdab4e' or lock.get('release_file_count')!=len(release_files)"
new="lock.get('second_review_baseline')!='c3fbbadbee06d2be13b23822f5f17fce07cdab4e' or lock.get('third_review_rounds')!=80 or lock.get('third_defect_rounds')!=13 or lock.get('third_clean_rounds')!=67 or lock.get('third_review_baseline')!='f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361' or lock.get('release_file_count')!=len(release_files)"
if old not in s and new not in s:
    raise SystemExit('release-integrity R3 lock anchor missing')
s=s.replace(old,new,1)
old2="'REVIEW-80-ROUNDS-RC6.md','REVIEW-80-ROUNDS-RC6-R2.md']"
new2="'REVIEW-80-ROUNDS-RC6.md','REVIEW-80-ROUNDS-RC6-R2.md','REVIEW-80-ROUNDS-RC6-R3.md']"
if old2 not in s and new2 not in s:
    raise SystemExit('release-integrity R3 evidence anchor missing')
s=s.replace(old2,new2,1)
p.write_text(s,encoding='utf-8')

# Human-readable evidence synchronization. Do not fabricate CI success; exact-head CI remains external evidence.
for name in ['README.md','STATUS.md','RELEASE-MANIFEST-1.3.0.md','STAGING-ACCEPTANCE.md']:
    p=root/name; s=p.read_text(encoding='utf-8').replace('53-entry','54-entry')
    p.write_text(s,encoding='utf-8')

p=root/'README.md'; s=p.read_text(encoding='utf-8')
row="| Third fresh 80-round re-review | `REVIEW-80-ROUNDS-RC6-R3.md`: 80 rounds; 13 defect-bearing corrected; 67 clean |\n"
anchor="| Fresh-second 80-round re-review | `REVIEW-80-ROUNDS-RC6-R2.md`: 80 rounds; 47 defect-bearing corrected; 33 clean |\n"
if row not in s:
    if anchor not in s: raise SystemExit('README R2 row anchor missing')
    s=s.replace(anchor,anchor+row,1)
s=s.replace('`REVIEW-80-ROUNDS-RC6.md`, `REVIEW-80-ROUNDS-RC6-R2.md`, `SECURITY-PRIVACY.md`','`REVIEW-80-ROUNDS-RC6.md`, `REVIEW-80-ROUNDS-RC6-R2.md`, `REVIEW-80-ROUNDS-RC6-R3.md`, `SECURITY-PRIVACY.md`')
oldhist='A fresh-second independent RC6 review then completed **80 further controls: 47 defect-bearing rounds corrected and 33 clean rounds**.'
newhist=oldhist+' A third fresh independent review then completed **80 further controls: 13 defect-bearing rounds corrected and 67 clean rounds**, concentrating on DB uncertainty, durable outbox truth, identity freshness, upload provenance and QA/evidence drift.'
s=s.replace(oldhist,newhist,1)
p.write_text(s,encoding='utf-8')

p=root/'STATUS.md'; s=p.read_text(encoding='utf-8')
section='''\n## Third fresh 80-round re-review — 10 August 2026\n\nA third independent 80-control review was opened against frozen baseline `f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361`. It found **13 defect-bearing rounds**, corrected them immediately, and left **67 clean rounds**. The ledger is `REVIEW-80-ROUNDS-RC6-R3.md`; the executable gate is `tests/eighty-round-audit-r3.py`. The principal corrections are fail-closed File 00 profile/privileged identity handling, explicit trusted-internal resumable provenance, risk-query uncertainty, durable outbox persistence/replay semantics, operational health/reconciliation truth, safe-mode/scheduler persistence, orphan-deletion DB safety, and historical QA contract drift.\n\nThe final repository QA/package claim remains conditional on the authoritative workflow passing against the **exact current HEAD** after this R3 evidence synchronization. Staging accepted, live deployed and operationally accepted remain false.\n'''
if '## Third fresh 80-round re-review' not in s: s=s.rstrip()+section.rstrip()+'\n'
p.write_text(s,encoding='utf-8')

p=root/'RELEASE-MANIFEST-1.3.0.md'; s=p.read_text(encoding='utf-8')
section='''\n## Third fresh 80-round corrective assurance\n\nRC6 has undergone a third independent 80-control repository review against frozen baseline `f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361`: **13 defect-bearing rounds corrected; 67 clean rounds**. `REVIEW-80-ROUNDS-RC6-R3.md` records the ledger and `tests/eighty-round-audit-r3.py` is mandatory alongside both earlier 80-round gates. The release allowlist is **54 entries**. The exact current HEAD must pass PHP 7.4/8.3 source suites, all three 80-round executable gates, deterministic double build, source/package parity and generated exact-head SPDX SBOM before repository package/QA status is green.\n'''
if '## Third fresh 80-round corrective assurance' not in s: s=s.rstrip()+section.rstrip()+'\n'
p.write_text(s,encoding='utf-8')

p=root/'STAGING-ACCEPTANCE.md'; s=p.read_text(encoding='utf-8')
note='\nR3 repository evidence: `REVIEW-80-ROUNDS-RC6-R3.md` records 80 controls (13 defect-bearing corrected; 67 clean). This does not replace any staging acceptance item below.\n'
if 'R3 repository evidence:' not in s: s=s.rstrip()+note
p.write_text(s,encoding='utf-8')

p=root/'CHANGELOG.md'; s=p.read_text(encoding='utf-8')
entry='''\n### Third fresh 80-round corrective review (R3)\n- Frozen baseline: `f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361`.\n- 80 controls: **13 defect-bearing rounds corrected; 67 clean rounds**.\n- Hardened File 00 fail-closed profile/privileged identity use, explicit private resumable provenance, risk DB uncertainty, durable outbox persistence/replay, operational health/reconciliation, safe-mode/scheduler persistence and orphan-deletion safety.\n- Synchronized historical QA assertions with the stronger current reviewer-case, Advanced Trust, rate-limit and resumable-upload contracts.\n- Added `REVIEW-80-ROUNDS-RC6-R3.md`, `tests/eighty-round-audit-r3.py`, 54-entry release parity and R3 release-lock fields.\n'''
marker='## 1.3.0-RC6 — Eighty-Round Corrective Assurance — 2026-08-10\n'
if '### Third fresh 80-round corrective review (R3)' not in s:
    if marker not in s: raise SystemExit('CHANGELOG RC6 marker missing')
    s=s.replace(marker,marker+entry,1)
p.write_text(s,encoding='utf-8')

print('File 09 R3 release evidence synchronized: 54-entry release, third-review lock and documents.')
