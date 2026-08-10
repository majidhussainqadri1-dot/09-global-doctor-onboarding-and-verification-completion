from pathlib import Path

root = Path(__file__).resolve().parents[1]
p = root / 'tests/eighty-round-audit-r4.py'
text = p.read_text(encoding='utf-8')
old = '''check(73,'Guarded destructive uninstall clears trust-monitor cron',has(uninstall,"wp_clear_scheduled_hook( 'gdo_trust_continuous_monitor' )"))'''
new = '''check(73,'Guarded destructive uninstall clears trust-monitor cron', 'gdo_trust_continuous_monitor' in uninstall and 'wp_clear_scheduled_hook' in uninstall)'''
if old not in text:
    raise SystemExit('R4 uninstall scheduler assertion was not found')
p.write_text(text.replace(old, new, 1).rstrip() + '\n', encoding='utf-8')
print('R4 compatibility gate updated for the stronger loop-based scheduler cleanup.')
