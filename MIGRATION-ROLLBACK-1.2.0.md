# File 09 1.2.0 Migration and Rollback

## Supported paths

- Fresh install to schema 6.
- Corrective 1.1.0/1.1.1 schema 3 to 1.2.0 schema 6.
- Legacy GDO1 encrypted objects are migration-only: quarantine, decrypt with approved legacy handler, re-encrypt as GDO2, verify, then physically move/delete the old object.

## Migration controls

Migration uses a stale-safe lock, idempotent `dbDelta`, bounded backfills and explicit quarantine. It backfills policy/language/draft expiry/identity fingerprints without fabricating verified status from a role, badge or user metadata. File 00 cleanup is requested through an owner action, never a direct write.

## Pre-cutover gate

Record exact source/target versions, database/object/key backup, isolated restore proof, dry-run counts, exception inventory and downstream consumer compatibility. Freeze verification mutations for the final delta.

## Rollback

Rollback restores the pre-migration database/object/key set, preserves post-cutover new records through an export/compensation ledger, restores the previous plugin package and validates File 00 claims/public projections. Destructive schema downgrade is prohibited without an approved data migration.
