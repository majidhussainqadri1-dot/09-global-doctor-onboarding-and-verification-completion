# File 09 Security and Privacy Architecture

## Native security boundary

File 09 validates File 00 identity/membership/capabilities and File 02 professional reauthentication at action time. It never reads File 00 private metadata, TOTP/recovery secrets or password hashes. Reviewer scope, assignment, applicant ownership, record state and optimistic version are checked server-side. File 09 remains the professional-verification owner; Files 03/07/08/19/20/24/26 retain their canonical profile, discovery, clinic, delivery, shell, assurance and search responsibilities.

## Evidence protection

Evidence is stored outside public uploads, encrypted with versioned AES-256-GCM envelopes and authenticated metadata. Uploads are checked by actual file signatures, bounded size/pixels, PDF active-content rules, safe image re-encoding, metadata removal, content hashes, quotas, rate limits and a fail-closed malware-scanner contract. Reviewer access is purpose-bound, short-lived, session-bound, one-time and audited. The RC6 Secure Evidence Viewing Room reuses this mature evidence-grant path; it does not create a second raw-file/grant backend. Image views are watermarked; PDF views require an approved watermark provider or fail closed. `download_allowed=false` is a controlled-workflow contract, not an impossible guarantee that a human can never copy screen content.

## Advanced Professional Trust provider boundary

Trusted issuer, primary-source, equivalency, affiliation, translation and AI providers are fact/assistance adapters only. They may not approve, reject, suspend, revoke or grant clinical authorization. New issuers begin proposed and require independent initial verification. Jurisdiction rules are draft-first, independently approved and immutable by approved version. Provider requests are minimized; response payloads are bounded/sanitized and secret-like keys are excluded from persistence. Provider failure/degradation is observable and schedules human reverification; it never silently changes professional owner state.

## Passport and public trust projection

Only a current File 09 professional decision plus current File 00 identity assurance can support a professional verification passport. Issuance/supersession is serialized; suspension/revocation/expiry invalidates derivative passports. Public passport/QR lookup is read-only, rechecks current owner truth, returns public-safe scope only and is explicitly `no-store/no-cache`. Professional verification never guarantees a treatment outcome and never grants clinical authorization. Public transparency is aggregate-only, fixed-window and minimum-cohort suppressed.

## Claims and public projection

Only an independently finalized File 09 decision issues the signed `gdo.file00.professional-decision` claim. File 00 acknowledgment is required before File 09 returns public verified status. Claims expose opaque subject UUID/version and public-safe decision facts, never evidence, license number, contact data or reviewer notes. File 07/File 26 ranking/search consume only public-safe verification projection and cannot index private File 09 evidence.

## Resumable upload security

RC6 resumable uploads use exclusive private session files, declared total/chunk geometry, exact ordered chunks, database row locking, file locking, size/hash invariants and atomic finalization ownership. Finalized bytes still pass through canonical `GDO_Evidence::stage_upload()` malware/type/quota/metadata/encryption validation. Expired/open/failed/finalizing temporary sessions and `.chunk-*` orphan files are part of cleanup/retention.

## Privacy lifecycle

Consent wording/version/purpose/retention are recorded. WordPress personal-data export includes Advanced Trust credential-check, professional-history, passport metadata and conflict records without private evidence blobs/secrets. Erasure first makes public verification non-current, honors scoped legal holds, proves physical evidence deletion, deletes derivative passports/temp/monitor records and anonymizes retained Advanced Trust accountability records. Application retention is application-scoped and must not erase a reviewer identity from unrelated cases. Backups and provider copies remain subject to documented expiry/deletion propagation.

## Production configuration

Required private configuration: `GDO_KEYRING`, `GDO_PRIVATE_STORAGE_DIR`, `GDO_CLAIM_SIGNING_KEY`, compatible File 00/File 02 contracts, File 19 delivery and a real malware scanner. Secrets and real documents must never enter GitHub. Provider credentials belong in deployment secret/configuration storage, not issuer metadata or public WordPress options.

## Evidence status law

The repository can prove source/test/package properties only. Hostinger schema-2 migration, real provider integrations, browser/accessibility, backup/restore, penetration/security acceptance, deployment parity and live behavior require separate external evidence. No RC6 repository result may be described as staging/live/operational proof.


## RC6 R6 storage and privacy completion truth

Private credential storage now treats directory/file permission persistence, canonical path health and post-write hash verification as security postconditions. Resumable temporary files are never considered deleted merely because cleanup was attempted; unsafe path, storage-health, filesystem deletion or cleanup-state DB uncertainty is surfaced and audited. Privacy export/erasure and public transparency likewise fail or pause on incomplete Advanced Trust DB/storage evidence rather than reporting a false complete result.
