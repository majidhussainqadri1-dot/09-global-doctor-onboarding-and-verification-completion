# File 09 Operations and Resilience

## System Check

The health contract reports File 00/File 02 compatibility, keyring, private storage, schema, cron schedules, File 19, claim signing/consumer, dead letters, stale claims and critical risk signals. A failing required dependency keeps mutations closed.

## Safe Mode

Safe Mode blocks application and verification mutations while preserving safe status reading and operator diagnostics. Enabling or disabling it requires senior capability, recent step-up, a meaningful reason and audit evidence.

## Queues and reconciliation

Owner state and outbox facts are persisted in the same transaction. Delivery assumes at-least-once semantics, uses event UUID deduplication, bounded exponential retry, dead-letter state and manual replay with reason. Scheduled and request-time reconciliation expire stale verifications and redeliver unacknowledged claims.

## Bounded repairs

Allowed repairs are schema reconciliation, cron restoration, outbox processing, lifecycle reconciliation and storage health checks. Repairs cannot alter File 00 roles, silently change decisions or destroy evidence.

## Backup/restore runbook gate

A production release requires an isolated restore of database, encrypted objects, keyring/configuration, counts/checksums, successful decrypt, cron/outbox recovery and representative applicant/reviewer journeys. Backup existence alone is not acceptance.
