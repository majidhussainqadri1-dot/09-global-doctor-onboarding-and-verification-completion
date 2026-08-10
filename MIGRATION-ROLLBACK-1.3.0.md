# File 09 — 1.3.0 Advanced Trust Migration and Rollback

## Forward migration

1. Verify backup and restore evidence before activation.
2. Verify File 00, File 02, private credential storage, keyring and claim-signing key health.
3. Run the existing File 09 core schema migration first (`GDO_SCHEMA_VERSION=6`).
4. Run `GDO_Advanced_Trust::maybe_install()`; expected advanced-trust schema version is `1`.
5. Confirm all eight advanced trust tables exist and are empty or contain only intended staging records.
6. Configure trusted issuers and approved jurisdiction rules through privileged step-up operations; never import provider secrets into WordPress tables.
7. Confirm `gdo_trust_continuous_monitor` is scheduled exactly once.
8. Exercise public passport, command center, provider-unavailable, conflict, dual-review, resumable upload and viewing-room failure paths before acceptance.

The migration is additive. It does not rewrite core File 09 applications/evidence and does not change File 00, File 03, File 07, File 08, File 19, File 24 or File 26 native tables.

## Rollback

A code rollback from 1.3.0 to 1.2.0 must be non-destructive by default:

- deactivate 1.3.0;
- clear `gdo_trust_continuous_monitor`;
- restore the prior verified 1.2.0 package only after exact package checksum verification;
- leave advanced-trust tables in place, inaccessible to the old runtime, so professional evidence/history is not silently lost;
- do not drop or truncate advanced-trust tables during emergency rollback;
- verify the existing core schema remains version 6;
- re-test File 00 claims, application/review/appeal/renewal and private evidence before declaring rollback accepted.

A destructive purge of advanced-trust tables is a separate Founder-approved data-lifecycle operation requiring retention, legal-hold, export and erasure checks. It is not part of ordinary rollback.

## Roll-forward after rollback

Reinstall the exact approved 1.3.0 candidate, run the idempotent advanced-trust installer, reconcile monitor state, expire abandoned upload sessions, verify passport expiry/revocation state, and re-run external acceptance. No historical provider result is treated as current merely because it is stored.
