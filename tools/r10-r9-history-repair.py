from pathlib import Path
p=Path('tests/eighty-round-audit-r9.py')
s=p.read_text(encoding='utf-8')
old13="c(13,'Current release metadata is synchronized to ninth review and 60-entry package truth',has(status,'Ninth fresh 80-round','60-entry') and has(manifest,'Ninth fresh 80-round','60-entry') and has(readme,'Ninth fresh 80-round') and lock.get('ninth_review_rounds')==80 and lock.get('ninth_defect_rounds')==10 and lock.get('ninth_clean_rounds')==70 and lock.get('release_file_count')==60 and len(release_files)==60)"
new13="c(13,'Historical ninth-review metadata remains preserved after later release evolution',has(manifest,'Ninth fresh 80-round') and (root/'REVIEW-80-ROUNDS-RC6-R9.md').exists() and lock.get('ninth_review_baseline')=='75ea54ed5113bf7ee16e90443f17cc1b941933a9' and lock.get('ninth_review_rounds')==80 and lock.get('ninth_defect_rounds')==10 and lock.get('ninth_clean_rounds')==70)"
old80="c(80,'Ninth fresh ledger/executable workflow/release-lock/package count are synchronized',(root/'REVIEW-80-ROUNDS-RC6-R9.md').exists() and lock.get('ninth_review_baseline')=='75ea54ed5113bf7ee16e90443f17cc1b941933a9' and lock.get('ninth_review_rounds')==80 and lock.get('ninth_defect_rounds')==10 and lock.get('ninth_clean_rounds')==70 and lock.get('release_file_count')==len(release_files)==60 and 'tests/eighty-round-audit-r9.py' in workflow and '60-entry' in manifest)"
new80="c(80,'Ninth fresh historical ledger/gate/release-lock evidence remains preserved',(root/'REVIEW-80-ROUNDS-RC6-R9.md').exists() and lock.get('ninth_review_baseline')=='75ea54ed5113bf7ee16e90443f17cc1b941933a9' and lock.get('ninth_review_rounds')==80 and lock.get('ninth_defect_rounds')==10 and lock.get('ninth_clean_rounds')==70 and 'tests/eighty-round-audit-r9.py' in workflow)"
for old,new in ((old13,new13),(old80,new80)):
    if old not in s: raise SystemExit('historical R9 assertion anchor missing')
    s=s.replace(old,new,1)
p.write_text(s,encoding='utf-8')
print('Historical R9 assertions decoupled from mutable current release metadata')
