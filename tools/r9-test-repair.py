from pathlib import Path
p=Path('tests/eighty-round-audit-r9.py')
s=p.read_text(encoding='utf-8')
old="and 'tests/eighty-round-audit-r9.py' in workflow and '60-entry' in manifest))"
new="and 'tests/eighty-round-audit-r9.py' in workflow and '60-entry' in manifest)"
if old not in s:
    raise SystemExit('R9 syntax anchor not found')
p.write_text(s.replace(old,new,1),encoding='utf-8')
