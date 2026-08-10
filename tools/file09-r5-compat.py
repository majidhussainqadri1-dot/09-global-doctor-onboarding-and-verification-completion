from pathlib import Path

root = Path(__file__).resolve().parents[1]

# Preserve the historical R4 semantic control while accepting the stronger loop-based cleanup.
p = root / 'tests/eighty-round-audit-r4.py'
text = p.read_text(encoding='utf-8')
old = '''check(73,'Guarded destructive uninstall clears trust-monitor cron',has(uninstall,"wp_clear_scheduled_hook( 'gdo_trust_continuous_monitor' )"))'''
new = '''check(73,'Guarded destructive uninstall clears trust-monitor cron', 'gdo_trust_continuous_monitor' in uninstall and 'wp_clear_scheduled_hook' in uninstall)'''
if old not in text:
    raise SystemExit('R4 uninstall scheduler assertion was not found')
p.write_text(text.replace(old, new, 1).rstrip() + '\n', encoding='utf-8')

# Correct the generated R5 final synchronization assertion (one extra closing parenthesis in the generator).
p = root / 'tests/eighty-round-audit-r5.py'
text = p.read_text(encoding='utf-8')
needle = " and 'tests/eighty-round-audit-r5.py' in workflow and '56-entry' in manifest))\n"
if needle not in text:
    raise SystemExit('R5 synchronization assertion was not found')
text = text.replace(needle, " and 'tests/eighty-round-audit-r5.py' in workflow and '56-entry' in manifest)\n", 1)
p.write_text(text.rstrip() + '\n', encoding='utf-8')

print('Historical R4 compatibility and generated R5 syntax were reconciled.')
