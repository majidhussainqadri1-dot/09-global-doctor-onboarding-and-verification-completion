# Staging Acceptance — Required Before Merge

- File 00 missing, inactive, sanctioned, unverified-email, duplicate-identity, and age-gate failures
- Missing/malformed keyring and missing/private-storage failures
- GDO2 round-trip, AAD tamper rejection, old-key read, key rotation, interrupted rotation, and backup recovery
- Direct-web-access denial under the real production web server
- PDF active-content, malformed PDF, image pixel bomb, spoofed MIME, oversized file, malware rejection, quota, and rate limits
- Multi-document transaction rollback and orphan compensation
- Legacy GDO1 copy/quarantine and no automatic verification
- Reviewer assignment, scope, self-review denial, conflict declaration, recent step-up, recommender/finalizer separation, and concurrent review conflict
- Evidence checklists, registry result, validity, approved snapshot, fingerprint, and consumer APIs
- More-information resubmission, rejection appeal, suspension, revocation, expiry, renewal, and reinstatement
- Credential access purpose, audit, notification event, hardened headers, and rate limits
- Application-page `private, no-store`, robots, frame, MIME, referrer, and permissions headers
- Privacy export pagination, legal hold, physical deletion proof, anonymization, retention, orphan cleanup, and guarded uninstall
- File 19 outbox and File 20 shell integration
- Backup restoration and rollback

Acceptance evidence must identify WordPress/PHP/database/web-server versions and the exact corrective commit. This document grants no approval by itself.
