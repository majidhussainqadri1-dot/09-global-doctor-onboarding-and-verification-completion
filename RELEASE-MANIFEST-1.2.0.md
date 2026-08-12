# File 09 Release Manifest — 1.2.0 RC4

- Plugin: Global Doctor Onboarding and Verification
- Canonical package: `global-doctor-onboarding-09-1.2.0-RC4.zip`
- Canonical package root: `global-doctor-onboarding-09/`
- Runtime: `1.2.0`
- Schema: `6`
- Minimum WordPress: `6.0`
- Project verification target: WordPress `7.0.1`, PHP `8.3.30`
- Minimum PHP: `7.4`
- Claim contract: `gdo.file00.professional-decision` `1.0.0`
- CF-01 contract: `gdo.cf01.practitioner-eligibility` `1.0.0`
- File 09 owner/read contracts: `1.1.0`
- File 19 notification contract: `sun.event.v1`
- File 26 verification projection: `gdo.file26.doctor-verification-projection` `1.1.0`
- Build: deterministic GitHub workflow `.github/workflows/file09-rc2-final.yml` (historical path retained; RC4 output identity enforced)
- Integrity: SHA-256, allowlisted file manifest, exact-head generated SPDX 2.3 SBOM and one-root verification
- Secrets/real documents/database dumps: excluded
- Staging/live authorization: **no**; external acceptance required

## Latest-plan closure

RC4 adds permanent repository gates for `F09-CEN-01`, `F09-CEN-02`, `CEN-SEARCH-001` and the applicable AJ journeys. Canonical File 09 professional truth is projected to Files 03/07/08/21/23/26 through fail-closed versioned contracts; File 19 receives minimized `sun.event.v1` facts; File 20 remains the sole shell owner.

## External acceptance

This RC4 is a repository/package candidate only. Staging, live and operational acceptance remain false until the exact RC4 package is exercised on Hostinger-equivalent staging, all required companion contracts/providers are accepted, two fresh staging review/fix/retest rounds close, rollback/restore is rehearsed and the Founder explicitly approves release.

## Prior forty-round assurance

RC3 completed 40 corrected-tree review rounds: 13 defect-bearing rounds were corrected and 27 later rounds found no new defect. RC4 does not erase that evidence; it reopens and extends assurance for the newer governing plans.
