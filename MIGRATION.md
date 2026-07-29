# Migration — File 09 v1.0.0 to v1.1.0

1. Take full database and filesystem backups.
2. Configure File 00, `GDO_KEYRING`, `GDO_PRIVATE_STORAGE_DIR`, and the malware-scanner filter before activation.
3. Activation creates versioned v1.1.0 tables and removes only the obsolete File 09 administrator capability.
4. Legacy users are imported as `legacy_review_required`; no legacy account is auto-verified.
5. Existing `GDO1` ciphertext is copied to private storage as `legacy_quarantine`. The old copy is removed only after the new ciphertext hash is verified.
6. Legacy `GDO1` decryption is disabled by default and may be enabled only for controlled migration/review through `gdo_allow_legacy_gdo1_decrypt`.
7. File 00 must perform any legacy role cleanup through the `gdo_legacy_role_cleanup_required` event.
8. Complete independent re-review and create a v1.1.0 approved snapshot before public eligibility.
9. Validate Files 03/04/07/08 against the read-only File 09 APIs.
10. Roll back from backups if any schema, storage, key, or integration acceptance test fails.
