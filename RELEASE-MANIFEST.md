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

## Generated release identity

- Version: `1.1.0`
- Schema: `3`
- Source files: `23`
- PHP files: `20`
- Source bytes: `161992`
- Source-tree SHA-256: `f49230b0266954a21cb29f02bce917eafb4707dd463f734c64ddeaf0155f5495`
- RELEASE-LOCK SHA-256: `fb3be8657dd96005209dec3ef7c2d4d0f63696c86d3c809d5fa123cad4eae5b0`

No merge, staging, production, or live-installation authorization is expressed by this manifest.
