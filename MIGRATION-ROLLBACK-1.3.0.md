# File 09 — 1.3.0 RC6 Advanced Trust Migration and Rollback

Core File 09 schema: **6**  
Advanced Trust schema: **2**  
Advanced Trust contract: **1.1.0**

## Forward migration

1. Verify backup and restore evidence before activation.
2. Verify File 00, File 02, private credential storage, keyring and claim-signing key health.
3. Run the existing File 09 core schema migration first (`GDO_SCHEMA_VERSION=6`).
4. Run `GDO_Advanced_Trust_Hardening::maybe_upgrade_schema()`; it first ensures the eight additive Advanced Trust tables exist and then idempotently reconciles schema **2** indexes.
5. Confirm all eight Advanced Trust tables exist. Schema 2 requires the `application_status` index on verification passports and `application_state` index on resumable upload sessions.
6. Confirm `gdo_advanced_trust_schema=2`; a failed index migration is an activation blocker, not a warning.
7. Configure trusted issuers as `proposed`; initial `verified` state requires a second authorized reviewer. Configure jurisdiction rules draft-first; a second authorized reviewer approves the unchanged version. Never import provider secrets into WordPress tables.
8. Confirm the daily `gdo_trust_continuous_monitor` event exists and that RC6 may schedule bounded single retry wakeups after provider degradation/adverse results.
9. Reconcile any pre-RC6 `monitor_state='degraded'` rows; RC6 processes both scheduled and degraded rows rather than stranding them.
10. Expire abandoned `open`, `failed` and stale `finalizing` resumable upload sessions, then verify no `.chunk-*` orphan remains outside the private-storage grace policy.
11. Exercise public passport issue/supersession/expiry/revocation/read-only verification, command center, provider unavailable/mismatch/revoked/expired, conflict/dual-review, resumable upload and mature viewing-room failure paths before acceptance.
12. Run privacy export/erasure and retention against Advanced Trust records. Derivative passports are deleted on erasure/retention rather than anonymized to shared `user_id=0`, avoiding `(user_id,version)` collisions.

The migration is additive. It does not rewrite core File 09 applications/evidence and does not change File 00, File 03, File 07, File 08, File 19, File 24 or File 26 native tables.

## RC5 → RC6 data behavior

- Existing Advanced Trust records remain valid records; provider facts are not assumed current merely because they are stored.
- Existing active passports remain subject to current application/File 00 checks at public read time.
- Existing jurisdiction rules should be reviewed before production use; new RC6 changes are versioned rather than silently editing an approved rule.
- Existing resumable sessions may be allowed to expire before upgrade if staging rollback simplicity is preferred.
- No automatic professional approval/revocation is introduced by schema 2.

## Rollback

A code rollback from RC6 must be non-destructive by default:

- deactivate the RC6 candidate;
- clear `gdo_trust_continuous_monitor` including one-time retry events;
- restore the exact previously accepted package only after checksum verification;
- leave Advanced Trust tables/indexes in place so professional evidence/history is not silently lost;
- do not drop or truncate Advanced Trust tables during emergency rollback;
- verify the existing core schema remains version 6;
- record that a downgraded runtime may not understand `gdo_advanced_trust_schema=2`; either keep it disabled or perform a Founder-approved metadata reconciliation before reactivation;
- re-test File 00 claims, application/review/appeal/renewal, passport invalidation and private evidence before declaring rollback accepted.

A destructive purge of Advanced Trust tables is a separate Founder-approved data-lifecycle operation requiring retention, legal-hold, export and erasure checks. It is not part of ordinary rollback.

## Roll-forward after rollback

Reinstall the exact approved RC6 package, run the idempotent schema-2 upgrader, reconcile monitor state, expire abandoned upload sessions, verify passport expiry/revocation and public read-only semantics, and re-run external acceptance. Repository success alone does not prove staging/live migration success.
