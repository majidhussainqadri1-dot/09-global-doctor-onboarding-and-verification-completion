# Corrective Release Manifest — File 09 v1.1.0

The release replaces legacy role/meta authority with a fail-closed File 00 boundary and introduces versioned applications, credential evidence, decisions, transition chains, consent evidence, access logs, appeals, notification outbox, database rate limits, retention, and migration quarantine.

## Security model

- AES-256-GCM `GDO2` envelopes
- External versioned keyring and active key identifier
- Authenticated metadata binding for application, user, document type, and document version
- Private storage outside public uploads
- Atomic private-file writes and verified deletion proofs
- Fail-closed malware-scanner boundary
- PDF active-content rejection and structural checks
- Image dimension limits, safe re-encoding, and metadata removal
- Assignment, scope, recent step-up authentication, access purpose, rate limiting, and immutable access audit

## Governance model

- File 00 identity and reviewer authority
- Applicant/reviewer separation
- Recommender/finalizer separation
- Optimistic row-version locking
- Strict state transitions
- Evidence-specific review checklists and license validity
- Immutable approved snapshot and fingerprint
- Suspension, revocation, expiry, renewal, appeal, and reinstatement
- Paginated privacy export and verified erasure/anonymization

No merge or deployment approval is expressed by this manifest.
