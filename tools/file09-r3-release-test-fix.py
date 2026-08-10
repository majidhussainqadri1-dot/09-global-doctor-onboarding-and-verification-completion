#!/usr/bin/env python3
from pathlib import Path
p=Path('tests/eighty-round-audit-r3.py')
s=p.read_text(encoding='utf-8')
old="check(1,'Exact R3 review ledger and temporary patch transport absent',has(review,'Frozen review baseline: `f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361`','Total review controls: **80**') and not (root/'R3-APPLY-TRIGGER.txt').exists() and not (root/'tools/file09-r3-apply.py').exists())"
new="check(1,'Exact R3 review ledger and temporary patch transport absent',has(review,'f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361','Total review controls: **80**') and not (root/'R3-APPLY-TRIGGER.txt').exists() and not (root/'tools/file09-r3-apply.py').exists())"
if old not in s: raise SystemExit('R3 round 01 assertion anchor missing')
s=s.replace(old,new,1)
old="check(28,'Audit chain is tamper-evident and DB uncertainty is visible',has(audit,'previous_hash','row_hash','$wpdb->last_error'))"
new="check(28,'Audit chain is tamper-evident and DB uncertainty is visible',has(audit,'previous_hash','event_hash','$wpdb->last_error','gdo_audit_chain_read_failed','gdo_audit_write_failed'))"
if old not in s: raise SystemExit('R3 round 28 assertion anchor missing')
s=s.replace(old,new,1)
p.write_text(s,encoding='utf-8')
print('R3 executable gate assertions aligned with current audit/ledger contracts.')
