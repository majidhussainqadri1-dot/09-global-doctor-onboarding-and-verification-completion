# File 09 → CF-01 Practitioner Eligibility Contract

## Status

- File 09 candidate runtime: `1.2.0`.
- Contract: `gdo.cf01.practitioner-eligibility` `1.0.0`.
- Native owner: File 09.
- State: 1.2.0 implementation candidate; staging and production remain unaccepted.
- CF-01 clinical runtime, staging, production and live deployment are not authorized by this document.

## Purpose

This contract answers one narrow question at action time:

> Does File 09 currently hold an internally consistent, non-expired and non-suspended professional verification record that may be considered by CF-01 for the requested professional purpose?

An `allow` answer is not clinical authorization. CF-01 must separately validate current File 02 authentication assurance, File 00 membership, treating relationship, patient/guardian consent, purpose, object, field, record version, jurisdiction and all clinical safety rules.

## Dependencies

| Dependency | Exact contract | Use |
|---|---|---|
| File 00 | `smc.cf01.membership-assurance` `1.0.0` | opaque subject UUID, membership status, suspension, identity assurance and record version |
| File 02 | `sa.professional-reauthentication` `1.0.0` | current-password plus session-bound AAL2 reviewer authentication; not part of the practitioner assertion itself |
| File 09 | approved snapshot schema `6` | current professional decision, evidence status, validity and bounded scope projection |

Unknown, unavailable, stale or incompatible dependencies fail closed.

## Request

```php
gdo_cf01_practitioner_assertion(
    int $user_id,
    array(
        'action'       => 'clinical_read',
        'purpose'      => 'patient_care',
        'jurisdiction' => 'Pakistan',
        'trace_id'     => 'opaque-uuid-v4',
    )
): array
```

Supported actions:

- `clinical_identity_link`
- `clinical_read`
- `clinical_write`
- `prescription_sign`
- `clinical_export`
- `clinical_transfer`
- `break_glass`

## Response envelope

The response contains:

- exact contract and producer versions;
- issued-at, expiry and trace identifiers;
- requested action and purpose;
- `allow`, `deny` or `unknown` result with safe reason code;
- File 00 opaque subject UUID and membership record version;
- current membership status, suspension and identity-assurance level;
- File 09 application UUID, generation, mutable row version, professional state, validity, snapshot fingerprint and check timestamp;
- accepted/current evidence status for identity, qualification and license;
- bounded professional scope and jurisdiction decision;
- explicit downstream authorization limits.

## Privacy allowlist

The professional scope may expose only bounded, approved-snapshot values needed for eligibility interpretation:

- profession;
- country and city;
- qualification;
- licensing authority;
- specialty;
- consultation modes;
- structured restriction status and safe restriction codes.

The assertion must not expose:

- WordPress user ID as a public identity;
- license/registration number;
- credential documents or storage locations;
- evidence content HMACs or encryption metadata;
- private reviewer notes, conflicts, registry evidence or audit bodies;
- phone, WhatsApp, email or home address;
- patient, appointment, message or clinical content;
- password, TOTP, recovery code, session token or provider secret.

## Decision law

`allow` requires all of the following:

1. exact File 00 contract and current opaque subject binding;
2. active and non-suspended File 00 membership;
3. current File 09 state `verified` or `reinstated`;
4. non-expired verification validity;
5. valid approved snapshot and fingerprint;
6. matching application generation and positive mutable row version;
7. accepted/current identity, qualification and license evidence;
8. requested jurisdiction compatible with the approved scope, when supplied;
9. no provider or extension narrowing decision.

`prescription_sign` and `break_glass` additionally require independently structured and approved professional restriction data. Until that field is modeled, these actions return `unknown` with `professional_scope_restrictions_not_structured`.

## Non-authorization constitution

The contract always states `grants_clinical_authorization: false`.

It does not prove or grant:

- authentication or recent step-up;
- a treating relationship;
- patient or guardian consent;
- access to a chart, encounter, attachment or field;
- prescription content, potency or dosage authority;
- break-glass approval;
- appointment ownership or completion;
- public badge authority;
- clinical record write permission.

## Concurrency and expiry

- Assertion TTL: 60 seconds.
- File 00 assertion must be current and bounded.
- File 09 decision includes the mutable `row_version` and `checked_at` timestamp.
- Every protected CF-01 command must compare current provider versions and record versions again immediately before mutation.
- Cached or previously allowed assertions never authorize a later action.

## Extension law

`gdo_cf01_practitioner_scope` may add only structured restriction status/codes through an approved owner integration.

`gdo_cf01_practitioner_result` is monotonic: it may narrow `allow` to `deny` or `unknown`; it cannot convert a denied/unknown native result into allow.

## Acceptance still required

- File 09 base PR `1.1.0` acceptance and merge;
- File 00 and File 02 provider acceptance/merge;
- exact merged-version producer/consumer fixtures;
- structured professional scope/restriction owner decision;
- legal/professional applicability review for launch jurisdictions;
- privacy and independent security review;
- Hostinger-equivalent staging, migration/rollback and Founder approval.
