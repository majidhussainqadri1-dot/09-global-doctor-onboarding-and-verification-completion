from pathlib import Path
import json,re,sys
root=Path(__file__).resolve().parents[1]
def fail(m): print('FAIL:',m,file=sys.stderr); raise SystemExit(1)
main=(root/'global-doctor-onboarding.php').read_text()
readme=(root/'readme.txt').read_text()
if 'Version: 1.2.0' not in main or "define( 'GDO_SCHEMA_VERSION', 6 )" not in main: fail('runtime version/schema mismatch')
if 'Stable tag: 1.2.0' not in readme: fail('stable tag mismatch')
for p in ['TRACEABILITY.md','RELEASE-MANIFEST-1.2.0.md','SBOM.spdx.json','REVIEW-ROUND-1.md','REVIEW-ROUND-2.md','STAGING-ACCEPTANCE.md','MIGRATION-ROLLBACK-1.2.0.md','SECURITY-PRIVACY.md','OPERATIONS.md']:
    if not (root/p).is_file(): fail('missing release evidence '+p)
sbom=json.loads((root/'SBOM.spdx.json').read_text())
if sbom.get('spdxVersion')!='SPDX-2.3': fail('invalid SBOM')
package=(sbom.get('packages') or [{}])[0]
if package.get('versionInfo')!='1.2.0-RC1' or package.get('filesAnalyzed') is not True: fail('incomplete SBOM package identity')
for item in sbom.get('files',[]):
    rel=item.get('fileName','').removeprefix('./')
    if not rel or not (root/rel).is_file(): fail('SBOM path missing: '+rel)
    checks=item.get('checksums') or []
    if not any(c.get('algorithm')=='SHA256' and len(c.get('checksumValue',''))==64 for c in checks): fail('SBOM SHA-256 missing: '+rel)
manifest=(root/'RELEASE-MANIFEST-1.2.0.md').read_text()
if 'global-doctor-onboarding-09/' not in manifest or 'global-doctor-onboarding-09-1.2.0-RC1.zip' not in manifest: fail('canonical package identity mismatch')
lock=json.loads((root/'RELEASE-LOCK.json').read_text())
if lock.get('runtime')!='1.2.0' or lock.get('schema')!=6 or lock.get('release_file_count')!=42: fail('release lock mismatch')
trace=(root/'TRACEABILITY.md').read_text()
for i in range(1,18):
    if f'F09-FR-{i:03d}' not in trace: fail(f'missing FR {i}')
for i in range(1,11):
    if f'F09-NFR-{i:03d}' not in trace: fail(f'missing NFR {i}')
for i in range(1,14):
    if f'DoD-{i:02d}' not in trace: fail(f'missing DoD {i}')
for p in root.rglob('*'):
    if not p.is_file() or '.git' in p.parts or 'dist' in p.parts: continue
    if p.suffix.lower() in {'.zip','.sql','.sqlite','.pem','.key','.p12','.pfx'} or p.name in {'.env','wp-config.php'}: fail('forbidden repository artifact '+str(p))
print('File 09 release-integrity checks passed.')
