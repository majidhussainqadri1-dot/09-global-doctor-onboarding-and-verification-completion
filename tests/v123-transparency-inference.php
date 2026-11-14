<?php
/**
 * V12.3 F09-AT-24: complementary-cell inference prevention.
 *
 * This is a PHP fixture against the genuine owner class, NOT a staging/Live test.
 * A small cohort must not be recoverable by subtracting two public counts.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
function absint($value) { return abs((int) $value); }
function __($message, $domain = '') { return $message; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function apply_filters($hook, $value, ...$args) { return $value; }
class WP_Error {
    public function __construct($code = '', $message = '', $data = null) {}
}
class GDO_Schema {
    public static function table($name) { return 'wp_gdo_' . $name; }
}
class V123_Public_Counts_Fixture {
    public $last_error = '';
    public $counts = array();
    public function prepare($sql, ...$params) { return $sql; }
    public function get_var($sql) {
        if (strpos($sql, 'wp_gdo_applications') !== false) {
            return strpos($sql, 'state IN') !== false ? $this->counts['verified'] : $this->counts['decisions'];
        }
        if (strpos($sql, 'wp_gdo_appeals') !== false) {
            return strpos($sql, 'decision IN') !== false ? $this->counts['appeals_overturned'] : $this->counts['appeals_resolved'];
        }
        if (strpos($sql, 'wp_gdo_quality_samples') !== false) { return $this->counts['quality_samples']; }
        if (strpos($sql, 'wp_gdo_risk_signals') !== false) { return $this->counts['fraud_signals']; }
        $this->last_error = 'unexpected SQL';
        return null;
    }
}
$wpdb = new V123_Public_Counts_Fixture();
require dirname(__DIR__) . '/includes/class-gdo-advanced-trust.php';
$passes = 0;
function ok($condition, $label) {
    global $passes;
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $label . "\n");
        exit(1);
    }
    ++$passes;
    echo "PASS: " . $label . "\n";
}
function public_counts(array $data) {
    global $wpdb;
    $wpdb->counts = array_merge(array(
        'decisions' => 0, 'verified' => 0, 'appeals_resolved' => 0,
        'appeals_overturned' => 0, 'quality_samples' => 0, 'fraud_signals' => 0
    ), $data);
    return GDO_Advanced_Trust::public_transparency_snapshot();
}
// Direct cell suppression is not enough: 21 decisions - 20 verified = 1.
$a = public_counts(array('decisions' => 21, 'verified' => 20));
ok(is_array($a) && empty($a['suppressed']) && array_key_exists('verified', $a) && $a['verified'] === null
    && in_array('verified', $a['suppressed_fields'] ?? array(), true),
    'complementary nonverified group of 1 is not recoverable from public totals');
// Resolved appeals and overturned appeals are another nested public pair.
$b = public_counts(array('decisions' => 100, 'verified' => 80, 'appeals_resolved' => 20, 'appeals_overturned' => 19));
ok(is_array($b) && array_key_exists('appeals_overturned', $b) && $b['appeals_overturned'] === null
    && in_array('appeals_overturned', $b['suppressed_fields'] ?? array(), true),
    'complementary nonoverturned appeal group of 1 is suppressed');
// Regular non-sensitive cells remain useful.
$c = public_counts(array('decisions' => 100, 'verified' => 80, 'appeals_resolved' => 20, 'appeals_overturned' => 10, 'quality_samples' => 9));
ok(($c['verified'] ?? null) === 80 && ($c['appeals_overturned'] ?? null) === 10
    && ($c['quality_samples'] ?? null) === 9 && empty($c['suppressed_fields']),
    'safe above-threshold aggregate remains available');
// Direct small-cell suppression remains intact.
$d = public_counts(array('decisions' => 40, 'verified' => 3));
ok(array_key_exists('verified', $d) && $d['verified'] === null,
    'original direct small-cell threshold is preserved');
// Cohort-level suppression stays fail-closed.
$e = public_counts(array('decisions' => 19, 'verified' => 10));
ok(($e['suppressed'] ?? false) === true && !array_key_exists('verified', $e),
    'low-count primary cohort remains fully suppressed');
echo "File09 V12.3 public transparency complement test: {$passes} PASS / 0 FAIL\n";
