# File 09 — Evidence-Based Status

Runtime version: **1.2.0 RC4 candidate**  
Schema: **6**  
Canonical corrective branch: `codex/file09-1.2.0-rc2-final`

## Repository assurance

- The original RC3 implementation already covered the 17 File 09 functional requirements, 10 non-functional gates, private credential lifecycle, independent review/appeal, signed File 00 claims, migration/rollback, privacy, retention, quality and operational recovery.
- RC4 reopens exact-head assurance against the newer central and File 09 plans and closes the identified repository gaps: canonical plugin title, File 21/File 23 eligibility projections, File 26 verification/search projection boundary, File 20 page-contract registration, File 19 `sun.event.v1` event integration, minimized notification payloads, latest-plan traceability and exact-head generated SBOM packaging.
- File 09 private application/evidence remains non-searchable. File 26 owns search/ranking; File 03/File 07 own public doctor/profile/directory projections. The File 09 File 26 connector is `contract_tested`, never silently activated.
- Current notification delivery remains through File 19. File 09 owns only its transactional outbox and factual producer events; it does not implement email/SMS/push transport.
- Exact-head CI and deterministic artifact evidence are mandatory. Any subsequent source, release-document or assurance-workflow change reopens automated verification.

## Completion truth

- Specified against latest central + File 09 plans: **complete repository trace candidate**
- Coded RC4 candidate: **complete repository candidate**
- Deterministic package candidate: **complete only after latest exact-head CI**
- Automated QA: **must be green on the latest exact head**
- Staging accepted: **false**
- Live deployed: **false**
- Operationally accepted: **false**

## Review record

### Latest-plan corrective review round 1 — 10 Aug 2026

Defects/gaps found before RC4 correction:

1. File 09 did not publish explicit File 21/File 23 current professional-eligibility contracts.
2. File 26 search/verification projection and privacy-preserving connector negotiation were absent.
3. File 19 integration used only legacy transport-style adapters instead of the current versioned `sun.event.v1` producer contract.
4. File 20 page ownership was exposed through navigation but not its versioned page-contract registry.
5. Plugin display title retained the obsolete “Completion” suffix.
6. RC3 SBOM checksums were repository-static and therefore reopened by any later exact-head change.
7. Latest `F09-CEN-01`, `F09-CEN-02`, `CEN-SEARCH-001` and applicable AJ traceability was not explicit in repository evidence.

All seven were corrected in the initial RC4 commit and received permanent regression assertions in `tests/latest-plan-parity.py` and/or `tests/cross-file-contracts.php`.

### RC4 corrective retest finding

The first RC4 exact-head source matrix passed on PHP 7.4 and PHP 8.3, but its deterministic-package job exposed one release-integrity defect: on a pull-request run, the generated SBOM could bind to the workflow's synthetic `GITHUB_SHA` rather than the explicitly checked-out File 09 source head. The package verifier correctly rejected that mismatch. The builder was then corrected to bind first to `EXPECTED_SHA`, the same exact source-head invariant used by checkout and verification.

### Latest-plan corrective review round 2 — 10 Aug 2026

Fresh review and retest on corrected head `b892f7da10fd8449395db3450b2eb40d1a3e90b1` found **no new repository-level source defect**. GitHub Actions run `31355094846` completed successfully for:

- PHP 7.4 full source, legacy adversarial, forty-round and latest-plan assurance;
- PHP 8.3 full source, legacy adversarial, forty-round and latest-plan assurance;
- deterministic double RC4 build;
- 47-entry package/source parity;
- generated SPDX 2.3 exact-head SBOM coverage.

That run produced installable RC4 ZIP SHA-256 `2a8967145929bc71d04a074a7fb323427eba610e29d5bfbc0f74f8557760aecb` and GitHub artifact ID `9050345430`. This status-record update itself changes the repository exact head, therefore a final exact-head CI rerun remains mandatory; the prior green run is evidence for the reviewed source tree, not automatic certification of this documentation commit.

## External acceptance gates

`staging_accepted=false`, `live_deployed=false`, `operationally_accepted=false` until Hostinger WordPress staging completes verified backup/restore, fresh install, real upgrade and legacy migration, current File 00/02/03/07/08/19/20/21/23/24/26 companion-contract acceptance, production keyring and malware-scanner failure modes, malicious-upload/private-storage tests, concurrency/stale-state tests, mobile/desktop/RTL/accessibility applicant-reviewer journeys, low-bandwidth/degraded-provider tests, privacy export/erasure, rollback rehearsal, error-log review, two fresh staging review/fix/retest rounds and explicit Founder acceptance.

## Prior forty-round RC3 evidence

RC3 completed forty corrected-tree review/fix rounds: **13 rounds found and corrected defects; 27 rounds found no new defect**. That historical evidence remains useful but does not replace RC4 exact-head or staging evidence.
