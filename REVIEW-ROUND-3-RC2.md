# File 09 — RC2 Fresh Adversarial Review Round 3

Date: 2026-08-07

A fresh post-green review found and corrected defects that earlier static suites did not cover:

1. Renewal/expired draft creation could bypass a newly failed File 00 eligibility decision.
2. Privacy erasure used mutating OFFSET pagination and could skip application versions.
3. Privacy anonymization used `user_id=0`, conflicting with the unique `(user_id, version)` key across different erased users.
4. Public-verification erasure could commit revocation locally before the File 00 professional claim was safely queued.
5. Privacy erasure considered only active evidence and could leave superseded credential files.
6. Retention expiry removed credential bytes but did not anonymize the retained subject/profile linkage.
7. Schema backfill stopped after 5,000 rows while still promoting the schema version.
8. Legacy migration lacked bounded row/user checkpoints and could delete the legacy source before the new evidence transaction committed.
9. Professional claim issuance did not independently require the caller-supplied claim state to equal the current locked application state.

Corrections are fail-closed, migration-safe, privacy-minimized and covered by `tests/final-hardening.py`. The canonical PHP 7.4/8.3 exact-head workflow must pass again after this correction. Hostinger staging remains a separate acceptance gate.

## Four-Plan Harmonization Review — 7 August 2026

The RC2 source was re-opened against four governing sources: (1) Definitive Master Plan v3.0, (2) All-Chats Recovered Directives v2.2, (3) Continuous-Value / Top-20 Superset Master Plan v1.0, and (4) File 09 Complete Master Plan v1.0.

### Round 1 — precedence, ownership and product constitution
Confirmed File 09 remains the professional-evidence/review/decision owner, File 00 owns membership/identity assertions, and File 20 owns the application shell. Corrected the local `gdo-shell` wrapper and retained the current green/free-system governance without reviving superseded orange/paid baselines.

### Round 2 — High-Trust membership and identity assurance
Found that RC2 pinned File 00 general contract 1.1.2 while current File 00 main publishes 1.2.0. Corrected the fail-closed pin, required current identity documents and an approved doctor membership grant for File 09 entry, stopped equating email+phone with identity verification, and preserved non-circularity because File 09 itself owns professional verification.

### Round 3 — professional/clinical boundary and lifecycle integrity
Corrected an unconditional guardian check in CF-01, a schema-3 fossil that rejected current schema-6 snapshots, and reinstatement paths that changed validity without refreshing the approved snapshot. Finalization, reinstatement and claim issuance now recheck current File 00 assurance; reinstatement also rechecks risk/evidence and refreshes snapshot validity metadata.

### Round 4 — fresh adversarial UX and release review
Corrected WhatsApp being effectively mandatory despite policy, removed misleading local phone-verification wording, and added permanent regression gates. Deterministic packaging and exact-head CI remain mandatory; Hostinger staging/live/operational acceptance remains separate.
