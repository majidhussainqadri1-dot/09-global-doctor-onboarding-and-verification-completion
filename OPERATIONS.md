# File 09 Operations and Resilience

## System Check

The health contract reports File 00/File 02 compatibility, keyring, private storage, core schema, Advanced Trust schema/contract, cron schedules, File 19, claim signing/consumer, dead letters, stale claims and critical risk signals. A failing required dependency keeps mutations closed. RC6 expects core schema `6`, Advanced Trust schema `2` and Advanced Trust contract `1.1.0`.

## Safe Mode

Safe Mode blocks application and verification mutations while preserving safe status reading and operator diagnostics. Enabling or disabling it requires senior capability, recent step-up, a meaningful reason and audit evidence. Advanced Trust upload/issuer/rule/reviewer mutations must not bypass this owner safety state.

## Queues and reconciliation

Owner state and outbox facts are persisted under the established File 09 transaction rules. Delivery assumes at-least-once semantics, uses event UUID deduplication, bounded retry, dead-letter state and manual replay with reason. Scheduled and request-time reconciliation expire stale verifications and redeliver unacknowledged claims.

Advanced Trust continuous monitoring processes both `scheduled` and `degraded` rows. Provider degradation uses bounded exponential backoff and a single retry wakeup; adverse external facts queue a minimized reverification-required notification and targeted recheck without silently changing File 09 professional state. Operator diagnostics must surface repeated provider degradation and monitor persistence failures.

## Passport reconciliation

Professional verification passports are derivative credentials, never owner truth. Issuance/supersession is serialized. Suspension, revocation and expiry invalidate derivative passports through lifecycle/reconciliation paths. Public lookup rechecks the current application and File 00 identity assurance and is read-only/no-cache; a stale passport row must never restore professional eligibility.

## Resumable-upload reconciliation

Private resumable sessions have `open`, `finalizing`, `committed`, `failed` and `expired` lifecycle semantics. Chunk append serializes database row and file state; finalization uses an atomic `open → finalizing` claim before canonical evidence staging. Expired open/failed/stale-finalizing sessions and `.chunk-*` orphan files are cleanup targets. A commit-marker failure after evidence storage is a repair-required event and must not be reported as ordinary success.

## Bounded repairs

Allowed repairs are core/Advanced Trust schema reconciliation, cron restoration, outbox processing, lifecycle reconciliation, stale monitor/session cleanup and storage health checks. Repairs cannot alter File 00 roles, silently change professional decisions, approve an issuer/jurisdiction rule without its governance lifecycle or destroy evidence.

## Privacy/retention operations

Personal-data export includes Advanced Trust metadata required by the approved privacy contract. Erasure honors legal hold, removes derivative passports/temp/monitor state and anonymizes justified retained trust accountability. Retention is application-scoped; a single application expiry must not globally anonymize a reviewer identity from unrelated cases. Failed physical/temp deletion is observable and blocks false completion.

## Backup/restore runbook gate

A production release requires an isolated restore of database, encrypted objects, keyring/configuration, counts/checksums, successful decrypt, core schema 6, Advanced Trust schema 2/indexes, passport/revocation state, monitor state, professional-history/privacy state, cron/outbox recovery and representative applicant/reviewer/provider journeys. Backup existence alone is not acceptance.

## Release truth

RC6 repository exact-head CI/package evidence, Hostinger staging evidence, deployed artifact parity and live operational evidence are four different records. No operator may infer one from another. After any deployment, record Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status separately.
