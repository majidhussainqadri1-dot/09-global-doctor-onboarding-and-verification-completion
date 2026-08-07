# File 09 Release Manifest — 1.2.0 RC3

- Plugin: Global Doctor Onboarding and Verification
- Canonical package: `global-doctor-onboarding-09-1.2.0-RC3.zip`
- Canonical package root: `global-doctor-onboarding-09/`
- Runtime: `1.2.0`
- Schema: `6`
- Minimum WordPress: `6.0`
- Project verification target: WordPress `7.0.1`, PHP `8.3.30`
- Minimum PHP: `7.4`
- Claim contract: `gdo.file00.professional-decision` `1.0.0`
- CF-01 contract: `gdo.cf01.practitioner-eligibility` `1.0.0`
- Build: deterministic GitHub workflow `.github/workflows/file09-rc2-final.yml` (historical branch/workflow path retained; RC3 output identity enforced)
- Integrity: SHA-256, file manifest, SBOM and one-root verification
- Secrets/real documents/database dumps: excluded
- Staging/live authorization: no; external acceptance required

## External acceptance
This RC3 is a repository/package candidate only. Staging, live and operational acceptance remain false until recorded in STAGING-ACCEPTANCE.md and approved by the Founder.

## Forty-round assurance
RC3 includes `REVIEW-40-ROUNDS-RC3.md`; 13 review rounds found and corrected defects and 27 subsequent rounds found no new defect. `tests/review40-adversarial.py` is part of exact-head source assurance.
