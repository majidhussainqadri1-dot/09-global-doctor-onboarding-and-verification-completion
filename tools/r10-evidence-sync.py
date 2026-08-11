from pathlib import Path
root=Path(__file__).resolve().parents[1]

def edit(rel,fn):
    p=root/rel;s=p.read_text(encoding='utf-8');n=fn(s)
    if n==s: raise SystemExit(f'R10 evidence sync made no change: {rel}')
    p.write_text(n,encoding='utf-8')

def manifest(s):
    old='`tools/build-release.py` builds `global-doctor-onboarding-09-1.3.0-RC6.zip` twice using the exact **60-entry** release allowlist.'
    if old not in s: raise SystemExit('manifest current package anchor missing')
    s=s.replace(old,'`tools/build-release.py` builds `global-doctor-onboarding-09-1.3.0-RC6.zip` twice using the exact **61-entry** release allowlist.',1)
    sec='''\n\n## Tenth fresh 80-round corrective assurance — R10\n\nRC6 underwent a tenth independent 80-control repository review against frozen exact-head baseline `ec3ca2dd715e05b66cf29c42f2f996c80987fcd7`: **19 defect-bearing rounds corrected; 61 clean rounds**. `REVIEW-80-ROUNDS-RC6-R10.md` is the immutable ledger and `tests/eighty-round-audit-r10.py` is mandatory alongside R1–R9. The current deterministic release allowlist is **61-entry**. R10 closes fail-visible database-uncertainty gaps in verification projection, private application REST/autosave, draft/consent/submission, privileged reviewer/evidence/appeal reads, evidence upload/decrypt/key rotation, state transition, claim issuance and completeness; requires current privileged step-up for health access; and makes post-decision quality-sampling failure observable. Repository package/QA is green only after the authoritative exact-final-head PHP 7.4/8.3 workflow passes all ten 80-round gates, deterministic double build, 61-entry source/package parity and generated exact-head SPDX SBOM. Staging accepted, live deployed and operationally accepted remain false until external evidence exists.\n'''
    if '## Tenth fresh 80-round corrective assurance — R10' in s: raise SystemExit('manifest R10 already present')
    return s+sec
edit('RELEASE-MANIFEST-1.3.0.md',manifest)

def changelog(s):
    anchor='# Changelog\n'
    if anchor not in s: raise SystemExit('changelog anchor missing')
    sec='''\n## Tenth fresh 80-round corrective assurance — R10\n\n- Froze the R9 exact-head candidate `ec3ca2dd715e05b66cf29c42f2f996c80987fcd7` and performed a new independent 80-control review.\n- Corrected **19** defect-bearing rounds (`04–22`); **61** rounds were clean.\n- Made public verification projection, private application REST/autosave, draft creation/save, consent/submission and completeness checks fail visibly on DB uncertainty.\n- Hardened privileged application/reviewer/evidence/appeal lock/read paths and required recent File 02 step-up for the privileged health endpoint.\n- Hardened evidence upload/replacement/decrypt/key rotation, generic state transition and professional claim issuance against DB-read ambiguity.\n- Made post-decision quality-sampling failure structured and auditable instead of silent.\n- Added R10 permanent ledger/executable gate, 61-entry release parity and R10 release-lock evidence.\n- External staging/live/operational gates remain false.\n\n'''
    return s.replace(anchor,anchor+sec,1)
edit('CHANGELOG.md',changelog)

def trace(s):
    if '## R10 — Tenth fresh 80-round corrective trace' in s: raise SystemExit('trace R10 already present')
    sec='''\n\n## R10 — Tenth fresh 80-round corrective trace\n\nFrozen baseline: `ec3ca2dd715e05b66cf29c42f2f996c80987fcd7`. Defect-bearing rounds: `04–22`; clean rounds: `01–03, 23–80`.\n\n| R10 rounds | Requirement/control family | Corrective evidence | Permanent gate |\n|---|---|---|---|\n| 04–05 | F09-NFR-003/008; public/private read truth | `GDO_API`, `GDO_REST` DB uncertainty | `tests/eighty-round-audit-r10.py` |\n| 06 | F09-NFR-001; privileged access | health permission current actor + File00 capability + File02 step-up | R10 gate |\n| 07–09 | F09-FR-003/005/006; reliability | draft/consent/submission DB uncertainty | R10 gate |\n| 10–13 | F09-FR-007/008/014; reviewer/appeal operability | hardened application/reviewer/evidence/appeal locks and reads | R10 gate |\n| 14–15 | F09-FR-004/008; F09-AT-23; privacy | evidence replacement/decrypt/key-rotation DB truth | R10 gate |\n| 16 | F09-FR-017; reviewer quality | structured/audited post-decision quality-sample failure | R10 gate |\n| 17–18 | F09-FR-003/014; applicant UI | front-end state/appeal DB failure visibility | R10 gate |\n| 19 | F09-NFR-003; lifecycle state machine | transition row-lock DB uncertainty | R10 gate |\n| 20 | F09-FR-010/011; File00 claim boundary | claim-issuance application DB uncertainty | R10 gate |\n| 21–22 | F09-FR-003/005/006; operability | completeness query truth + draft-save DB truth | R10 gate |\n| 23–80 | FR/NFR/AT/CEN/DoD regression | clean fresh controls | R10 gate + exact-head workflow |\n\nR10 does not change canonical ownership. File 09 remains professional verification/evidence decision owner; File 00 identity, File 02 authentication/step-up, Files 03/07/26 public profile/search/ranking, File 08 clinic, File 19 notification transport, File 20 shell and File 24 security/privacy assurance remain native owners.\n'''
    return s+sec
edit('TRACEABILITY.md',trace)

def release_integrity(s):
    anchor="builder=(root/'tools/build-release.py').read_text(encoding='utf-8'); verifier=(root/'tools/verify-release.py').read_text(encoding='utf-8')"
    if anchor not in s: raise SystemExit('release-integrity insertion anchor missing')
    block="""for key, expected in {'ninth_review_baseline':'75ea54ed5113bf7ee16e90443f17cc1b941933a9','ninth_review_rounds':80,'ninth_defect_rounds':10,'ninth_clean_rounds':70}.items():\n    if lock.get(key) != expected: fail('release lock R9 mismatch '+key)\nif 'REVIEW-80-ROUNDS-RC6-R9.md' not in release_files: fail('RC6 R9 release ledger missing')\nif not (root/'tests/eighty-round-audit-r9.py').is_file(): fail('RC6 R9 executable gate missing')\nfor key, expected in {'tenth_review_baseline':'ec3ca2dd715e05b66cf29c42f2f996c80987fcd7','tenth_review_rounds':80,'tenth_defect_rounds':19,'tenth_clean_rounds':61}.items():\n    if lock.get(key) != expected: fail('release lock R10 mismatch '+key)\nif lock.get('release_file_count') != 61 or len(release_files) != 61: fail('R10 release file count mismatch')\nif 'REVIEW-80-ROUNDS-RC6-R10.md' not in release_files: fail('RC6 R10 release ledger missing')\nif not (root/'tests/eighty-round-audit-r10.py').is_file(): fail('RC6 R10 executable gate missing')\n"""
    if "release lock R10 mismatch" in s: raise SystemExit('release-integrity R10 already present')
    return s.replace(anchor,block+anchor,1)
edit('tests/release-integrity.py',release_integrity)
print('R10 evidence synchronization applied')
