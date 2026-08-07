# File 09 Security and Privacy Architecture

## Native security boundary

File 09 validates File 00 identity/membership/capabilities and File 02 professional reauthentication at action time. It never reads File 00 private metadata, TOTP/recovery secrets or password hashes. Reviewer scope, assignment, applicant ownership, record state and optimistic version are checked server-side.

## Evidence protection

Evidence is stored outside public uploads, encrypted with versioned AES-256-GCM envelopes and authenticated metadata. Uploads are checked by actual file signatures, bounded size/pixels, PDF active-content rules, safe image re-encoding, metadata removal, content hashes, quotas, rate limits and a fail-closed malware-scanner contract. Reviewer access is purpose-bound, short-lived, session-bound, one-time and audited. Image views are watermarked; PDF views require an approved watermark provider or fail closed.

## Claims and public projection

Only an independently finalized File 09 decision issues the signed `gdo.file00.professional-decision` claim. File 00 acknowledgment is required before File 09 returns public verified status. Claims expose opaque subject UUID/version and public-safe decision facts, never evidence, license number, contact data or reviewer notes.

## Privacy lifecycle

Consent wording/version/purpose/retention are recorded. Applicant exports exclude secrets and private reviewer notes. Erasure first revokes any public status, honors scoped legal holds, proves physical evidence deletion and anonymizes retained accountability records. Backups and provider copies remain subject to documented expiry/deletion propagation.

## Production configuration

Required private configuration: `GDO_KEYRING`, `GDO_PRIVATE_STORAGE_DIR`, `GDO_CLAIM_SIGNING_KEY`, compatible File 00/File 02 contracts, File 19 delivery and a real malware scanner. Secrets and real documents must never enter GitHub.
