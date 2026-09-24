<?php
$src = file_get_contents(dirname(__DIR__) . '/includes/class-gdo-integration-contracts.php');
foreach ([
    "sabri_doctor_verification_public_projection_v1",
    "sabri_file09_verifiable_credentials_v1",
    "file03_public_projection",
    "file03_verifiable_credentials",
    "gdo_validate_public_projection",
    "raw_evidence_exposed",
] as $token) {
    if (strpos($src, $token) === false) { fwrite(STDERR, "Missing File 03 adapter token: $token\n"); exit(1); }
}
if (strpos($src, "'raw_evidence_exposed' => false") === false) { fwrite(STDERR, "Raw evidence guard missing\n"); exit(1); }
echo "File 09 -> File 03 exact projection contracts: PASS\n";
