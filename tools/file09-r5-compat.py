from pathlib import Path

root = Path(__file__).resolve().parents[1]

def rw(path, old, new):
    p = root / path
    text = p.read_text(encoding='utf-8')
    if old not in text:
        raise SystemExit(f'compatibility block not found in {path}: {old[:100]!r}')
    p.write_text(text.replace(old, new, 1).rstrip() + '\n', encoding='utf-8')

# The current RC6 activator differs from the older source shape used by the main R5 transformer.
# Apply the same R17-R20 controls against the actual current source, not a historical template.
p = root / 'includes/class-gdo-activator.php'
act = p.read_text(encoding='utf-8')
act = act.replace(
"\t\t\tif ( wp_next_scheduled( $hook ) ) { continue; }\n\t\t\t$scheduled = wp_schedule_event( $timestamp, $recurrence, $hook, array(), true );\n\t\t\tif ( is_wp_error( $scheduled ) || false === $scheduled || ! wp_next_scheduled( $hook ) ) {",
"\t\t\tif ( self::recurring_schedule_ready( $hook, $recurrence ) ) { continue; }\n\t\t\twp_clear_scheduled_hook( $hook );\n\t\t\t$scheduled = wp_schedule_event( $timestamp, $recurrence, $hook, array(), true );\n\t\t\tif ( is_wp_error( $scheduled ) || false === $scheduled || ! self::recurring_schedule_ready( $hook, $recurrence ) ) {",
1)
act = act.replace(
"\t\t\t\twp_update_post( array( 'ID'=>$id, 'post_content'=>$shortcode ) );",
"\t\t\t\t$updated = wp_update_post( array( 'ID'=>$id, 'post_content'=>$shortcode ), true );\n\t\t\t\tif ( is_wp_error( $updated ) || ! $updated ) { wp_die( esc_html__( 'File 09 could not update its managed application page safely.', 'global-doctor-onboarding' ) ); }",
1)
act = act.replace(
"\t\t\tif ( is_wp_error( $id ) ) { wp_die( esc_html( $id->get_error_message() ) ); }\n\t\t\tupdate_post_meta( $id, '_gdo_managed_page_key', $key );\n\t\t}\n\t\tupdate_option( 'gdo_page_map', array( 'apply'=>absint( $id ) ), false );",
"\t\t\tif ( is_wp_error( $id ) || ! $id ) { wp_die( esc_html( is_wp_error( $id ) ? $id->get_error_message() : __( 'File 09 could not create its managed application page.', 'global-doctor-onboarding' ) ) ); }\n\t\t}\n\t\t$meta = update_post_meta( $id, '_gdo_managed_page_key', $key );\n\t\tif ( false === $meta && $key !== get_post_meta( $id, '_gdo_managed_page_key', true ) ) { wp_die( esc_html__( 'File 09 could not persist managed-page ownership metadata.', 'global-doctor-onboarding' ) ); }\n\t\t$map = array( 'apply'=>absint( $id ) );\n\t\tif ( ! update_option( 'gdo_page_map', $map, false ) && $map !== (array) get_option( 'gdo_page_map', array() ) ) { wp_die( esc_html__( 'File 09 could not persist its managed page map.', 'global-doctor-onboarding' ) ); }",
1)
act = act.replace(
"\t\tupdate_option( 'gdo_version', GDO_VERSION, false );\n\t\tupdate_option( 'gdo_activation_evidence', array( 'version'=>GDO_VERSION, 'schema'=>GDO_SCHEMA_VERSION, 'advanced_trust_schema'=>GDO_Advanced_Trust_Hardening::SCHEMA_VERSION, 'advanced_trust_contract'=>GDO_Advanced_Trust_Hardening::CONTRACT_VERSION, 'review80_corrective_layer'=>true, 'activated_at'=>gmdate( 'c' ) ), false );",
"\t\tif ( ! update_option( 'gdo_version', GDO_VERSION, false ) && GDO_VERSION !== (string) get_option( 'gdo_version', '' ) ) {\n\t\t\twp_die( esc_html__( 'File 09 runtime version evidence could not be persisted.', 'global-doctor-onboarding' ) );\n\t\t}\n\t\t$activation_evidence = array( 'version'=>GDO_VERSION, 'schema'=>GDO_SCHEMA_VERSION, 'advanced_trust_schema'=>GDO_Advanced_Trust_Hardening::SCHEMA_VERSION, 'advanced_trust_contract'=>GDO_Advanced_Trust_Hardening::CONTRACT_VERSION, 'review80_corrective_layer'=>true, 'activated_at'=>gmdate( 'c' ) );\n\t\tif ( ! update_option( 'gdo_activation_evidence', $activation_evidence, false ) && $activation_evidence !== (array) get_option( 'gdo_activation_evidence', array() ) ) {\n\t\t\twp_die( esc_html__( 'File 09 activation evidence could not be persisted.', 'global-doctor-onboarding' ) );\n\t\t}",
1)
for required in [
    'self::recurring_schedule_ready( $hook, $recurrence )',
    'File 09 could not update its managed application page safely.',
    'managed-page ownership metadata',
    'managed page map',
    'runtime version evidence could not be persisted',
    'activation evidence could not be persisted',
]:
    if required not in act:
        raise SystemExit(f'current activator correction missing after transform: {required}')
p.write_text(act.rstrip() + '\n', encoding='utf-8')

# Preserve the historical R4 semantic control while accepting the stronger loop-based cleanup.
p = root / 'tests/eighty-round-audit-r4.py'
text = p.read_text(encoding='utf-8')
old = '''check(73,'Guarded destructive uninstall clears trust-monitor cron',has(uninstall,"wp_clear_scheduled_hook( 'gdo_trust_continuous_monitor' )"))'''
new = '''check(73,'Guarded destructive uninstall clears trust-monitor cron', 'gdo_trust_continuous_monitor' in uninstall and 'wp_clear_scheduled_hook' in uninstall)'''
if old not in text:
    raise SystemExit('R4 uninstall scheduler assertion was not found')
p.write_text(text.replace(old, new, 1).rstrip() + '\n', encoding='utf-8')

# Correct the generated R5 final synchronization assertion and make R17 assert actual use, not mere helper presence.
p = root / 'tests/eighty-round-audit-r5.py'
text = p.read_text(encoding='utf-8')
needle = " and 'tests/eighty-round-audit-r5.py' in workflow and '56-entry' in manifest))\n"
if needle not in text:
    raise SystemExit('R5 synchronization assertion was not found')
text = text.replace(needle, " and 'tests/eighty-round-audit-r5.py' in workflow and '56-entry' in manifest)\n", 1)
old17 = "c(17,'Recurring jobs are validated by recurrence, not only next timestamp',has(act,'recurring_schedule_ready','wp_get_scheduled_event') and has(ops,'recurring_schedule_ready','wp_get_scheduled_event'))"
new17 = "c(17,'Recurring jobs are validated by recurrence, not only next timestamp',has(act,'recurring_schedule_ready','wp_get_scheduled_event','self::recurring_schedule_ready( $hook, $recurrence )') and has(ops,'recurring_schedule_ready','wp_get_scheduled_event'))"
if old17 not in text:
    raise SystemExit('R5 recurrence assertion was not found')
text = text.replace(old17, new17, 1)
p.write_text(text.rstrip() + '\n', encoding='utf-8')

print('Current RC6 activator, historical R4 gate, and generated R5 gate were reconciled.')
