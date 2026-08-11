from pathlib import Path
import subprocess
root=Path(__file__).resolve().parents[1]
BASE='75ea54ed5113bf7ee16e90443f17cc1b941933a9'
if subprocess.call(['git','merge-base','--is-ancestor',BASE,'HEAD'],cwd=root)!=0:
    raise SystemExit('R9 baseline ancestry mismatch')

def edit(rel,fn):
    p=root/rel; s=p.read_text(encoding='utf-8'); n=fn(s)
    if n==s: raise SystemExit(f'no R9 change for {rel}')
    p.write_text(n,encoding='utf-8')

# Current authoritative manifest paragraph was still at the R6-era 57-entry count.
def manifest_edit(s):
    old='using the exact **57-entry** release allowlist.'
    if old not in s: raise SystemExit('manifest current-count anchor missing')
    s=s.replace(old,'using the exact **60-entry** release allowlist.',1)
    addition='''\n\n## Ninth fresh 80-round corrective assurance — R9\n\nRC6 underwent a ninth independent 80-control repository review against frozen exact-head baseline `75ea54ed5113bf7ee16e90443f17cc1b941933a9`: **10 defect-bearing rounds corrected; 70 clean rounds**. `REVIEW-80-ROUNDS-RC6-R9.md` is the immutable ledger and `tests/eighty-round-audit-r9.py` is mandatory alongside R1–R8. The current deterministic release allowlist is **60-entry**. R9 closes evidence-review method-boundary authorization and DB-uncertainty gaps, credential-grant use-time download authorization, idempotent submission COMMIT truth, appeal/outbox failure visibility, dead-letter replay reauthorization, continuous-monitor exclusive leases, atomic Advanced Trust privacy erasure and release-evidence drift. Repository package/QA is green only after the authoritative exact-final-head PHP 7.4/8.3 workflow passes all nine 80-round gates, deterministic double build, 60-entry source/package parity and generated exact-head SPDX SBOM. Staging accepted, live deployed and operationally accepted remain false until external evidence exists.\n'''
    if '## Ninth fresh 80-round corrective assurance — R9' not in s: s += addition
    return s
edit('RELEASE-MANIFEST-1.3.0.md',manifest_edit)

def changelog_edit(s):
    section='''\n## Ninth fresh 80-round corrective assurance — R9\n\n- Froze R8 exact-head candidate `75ea54ed5113bf7ee16e90443f17cc1b941933a9` and performed a new 80-control review rather than reusing earlier green evidence.\n- Corrected 10 defect-bearing rounds: `04,05,06,07,08,09,10,11,12,13`; 70 rounds were clean.\n- Reauthorized evidence review and dead-letter replay at their mutation-method boundaries with current File 00 capability + File 02 step-up.\n- Made evidence review/grant and appeal reads fail visibly on DB uncertainty; rechecked download capability at grant consumption.\n- Verified idempotent submission COMMIT and bounded outbox processing success.\n- Added continuous-monitor exclusive processing leases/stale-lease recovery and prevented stale monitor work from overwriting newer scheduling.\n- Made Advanced Trust database-side privacy erasure atomic with rollback/COMMIT verification.\n- Synchronized R9 ledger, executable gate, 60-entry release allowlist and current release/status evidence.\n- External staging/live/operational gates remain false.\n'''
    if '## Ninth fresh 80-round corrective assurance — R9' in s: return s
    lines=s.splitlines(True)
    if lines and lines[0].startswith('# '): return lines[0]+section+'\n'+''.join(lines[1:])
    return section+s
edit('CHANGELOG.md',changelog_edit)

def trace_edit(s):
    addition='''\n\n## R9 — Ninth fresh 80-round corrective trace\n\nFrozen baseline: `75ea54ed5113bf7ee16e90443f17cc1b941933a9`. Defect-bearing rounds: `04–13`; clean rounds: `01–03, 14–80`.\n\n| R9 round | Requirement/control family | Corrected source/evidence | Permanent gate |\n|---|---|---|---|\n| 04 | F09-NFR-001 / reviewer authorization | `GDO_Evidence::review()` current actor + File00 capability + File02 step-up | `tests/eighty-round-audit-r9.py` |\n| 05 | F09-NFR-003/008 / DB reliability | explicit evidence/application review DB uncertainty | R9 gate |\n| 06 | F09-FR-008, F09-AT-23 | use-time download capability + grant/evidence/application DB checks | R9 gate |\n| 07 | F09-FR-006 / idempotency | idempotent submission COMMIT verification | R9 gate |\n| 08 | F09-FR-014 / appeal operability | appeal DB-failure visibility | R9 gate |\n| 09 | F09-NFR-003/008, File19 boundary | bounded outbox processing failure visibility | R9 gate |\n| 10 | F09-NFR-001 / recovery mutation | dead-letter replay current actor/capability/step-up | R9 gate |\n| 11 | F09-AT-06/07, F09-NFR-003 | continuous-monitor exclusive lease + stale-lease recovery | R9 gate |\n| 12 | F09-FR-016, F09-NFR-002 | atomic Advanced Trust DB privacy erasure | R9 gate |\n| 13 | DoD/release evidence | R9 ledger/test/lock/docs + 60-entry allowlist | R9 gate + authoritative workflow |\n\nR9 does not change canonical ownership. File 09 remains professional verification/evidence decision owner; File 00 identity, File 02 authentication/step-up, Files 03/07/26 public profile/search/ranking, File 08 clinic, File 19 notification transport, File 20 shell and File 24 security/privacy assurance remain native owners.\n'''
    return s if '## R9 — Ninth fresh 80-round corrective trace' in s else s+addition
edit('TRACEABILITY.md',trace_edit)
print('R9 evidence docs synchronized')
