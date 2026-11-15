<?php
/**
 * File09 V12.3 F09-AT-24: actual installed WordPress/MariaDB aggregate privacy.
 * Ephemeral CI only. Never run on staging or production containing real data.
 * wp eval-file tests/wpdb-transparency-smoke.php --path=/tmp/wordpress
 */
defined('ABSPATH') || exit;
global $wpdb;
if (wp_get_environment_type() !== 'staging' || !defined('WP_CLI') || !WP_CLI) {
    fwrite(STDERR, "FAIL: refuse non-CLI/non-staging aggregate fixture\n");
    exit(91);
}
$table = GDO_Schema::table('applications');
$appeals = GDO_Schema::table('appeals');
$inserted = array();
$appeal_ids = array();
$rejected_ids = array();
$verified_ids = array();
$now = current_time('mysql', true);
function f09_db_check($value, $label) {
    if (!$value) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . "\n";
}
try {
    for ($i = 0; $i < 25; ++$i) {
        $verified = $i < 20;
        $profile = '{"display_name":"Synthetic CI aggregate only"}';
        $row = array(
            'application_uuid' => wp_generate_uuid4(),
            'user_id' => 1,
            'version' => 500 + $i,
            'application_type' => 'homeopathic_doctor',
            'jurisdiction' => 'PK',
            'preferred_language' => 'en-US',
            'state' => $verified ? 'verified' : 'rejected',
            'row_version' => 1,
            'profile_json' => $profile,
            'profile_fingerprint' => hash('sha256', $profile),
            'decision_at' => $i < 21 ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        );
        $ok = $wpdb->insert($table, $row);
        f09_db_check($ok === 1, 'synthetic MariaDB application fixture insert ' . ($i + 1));
        $id = absint($wpdb->insert_id);
        $inserted[] = $id;
        if ($verified) {
            $verified_ids[] = $id;
        } else {
            $rejected_ids[] = $id;
        }
    }
    // 21 decided applications: 20 verified + 1 rejected. Publishing the
    // nested 20 would disclose a 1-person complement by subtraction.
    $one = GDO_Advanced_Trust::public_transparency_snapshot();
    f09_db_check(!is_wp_error($one) && empty($one['suppressed'])
        && absint($one['decisions']) === 21
        && array_key_exists('verified', $one) && $one['verified'] === null
        && in_array('verified', $one['suppressed_fields'], true),
        'MariaDB complementary group of one is not inferable');
    // Open 4 additional decisions, so complement now equals minimum cell 5.
    foreach (array_slice($rejected_ids, 1) as $id) {
        f09_db_check($wpdb->update($table, array('decision_at' => $now),
            array('id' => $id)) === 1, 'additional synthetic decision stored');
    }
    $five = GDO_Advanced_Trust::public_transparency_snapshot();
    f09_db_check(!is_wp_error($five) && absint($five['decisions']) === 25
        && $five['verified'] === 20
        && !in_array('verified', $five['suppressed_fields'], true),
        'safe complement of five remains visible');
    // Direct small-cell suppression remains separate from complement checks.
    foreach (array_slice($verified_ids, 0, 16) as $id) {
        f09_db_check($wpdb->update($table, array('state' => 'rejected'),
            array('id' => $id)) === 1, 'synthetic verified state correction stored');
    }
    $direct = GDO_Advanced_Trust::public_transparency_snapshot();
    f09_db_check(!is_wp_error($direct) && absint($direct['decisions']) === 25
        && $direct['verified'] === null,
        'MariaDB direct 4-person cell is suppressed');
    // 19 decisions must be fully suppressed regardless safe-looking nested cells.
    foreach (array_slice($rejected_ids, 0, 5) as $id) {
        f09_db_check($wpdb->update($table, array('decision_at' => null),
            array('id' => $id)) === 1, 'synthetic decision moved outside cohort');
    }
    $wpdb->update($table, array('decision_at' => null),
        array('id' => $verified_ids[0]));
    $low = GDO_Advanced_Trust::public_transparency_snapshot();
    f09_db_check(!is_wp_error($low) && !empty($low['suppressed']),
        'MariaDB below-minimum cohort fails closed');
    echo "PASS File09 installed WordPress/MariaDB aggregate privacy regression\n";
} finally {
    foreach ($appeal_ids as $id) {
        $wpdb->delete($appeals, array('id' => $id));
    }
    foreach ($inserted as $id) {
        $wpdb->delete($table, array('id' => $id));
    }
}
