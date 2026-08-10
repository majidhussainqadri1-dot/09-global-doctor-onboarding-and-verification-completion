from pathlib import Path
import json, sys
root=Path(__file__).resolve().parents[1]
def fail(m): print('FAIL:',m,file=sys.stderr); raise SystemExit(1)
main=(root/'global-doctor-onboarding.php').read_text(encoding='utf-8'); readme=(root/'readme.txt').read_text(encoding='utf-8')
if 'Plugin Name: Global Doctor Onboarding and Verification\n' not in main: fail('canonical plugin title mismatch')
if 'Version: 1.3.0' not in main or "define( 'GDO_SCHEMA_VERSION', 6 )" not in main: fail('runtime version/schema mismatch')
if 'Stable tag: 1.3.0' not in readme: fail('stable tag mismatch')
for p in ['TRACEABILITY.md','RELEASE-MANIFEST-1.3.0.md','ADVANCED-TRUST-24.md','SBOM.spdx.json','REVIEW-ROUND-1.md','REVIEW-ROUND-2.md','REVIEW-40-ROUNDS-RC3.md','REVIEW-80-ROUNDS-RC6.md','STAGING-ACCEPTANCE.md','MIGRATION-ROLLBACK-1.3.0.md','SECURITY-PRIVACY.md','OPERATIONS.md']:
    if not (root/p).is_file(): fail('missing release evidence '+p)
sbom=json.loads((root/'SBOM.spdx.json').read_text(encoding='utf-8'))
if sbom.get('spdxVersion')!='SPDX-2.3': fail('invalid SBOM template')
package=(sbom.get('packages') or [{}])[0]
if package.get('versionInfo')!='1.3.0-RC6' or package.get('filesAnalyzed') is not False: fail('SBOM repository template identity mismatch')
if sbom.get('files') not in ([], None): fail('repository SBOM template must not pretend to contain exact-head checksums')
release_files=[x for x in (root/'RELEASE-FILES.txt').read_text(encoding='utf-8').splitlines() if x.strip()]
manifest=(root/'RELEASE-MANIFEST-1.3.0.md').read_text(encoding='utf-8')
if 'global-doctor-onboarding-09/' not in manifest or 'global-doctor-onboarding-09-1.3.0-RC6.zip' not in manifest: fail('canonical package identity mismatch')
lock=json.loads((root/'RELEASE-LOCK.json').read_text(encoding='utf-8'))
if lock.get('runtime')!='1.3.0' or lock.get('schema')!=6 or lock.get('advanced_trust_schema')!=2 or lock.get('advanced_trust_contract')!='1.1.0' or lock.get('release_candidate')!='RC6' or lock.get('review_rounds')!=80 or lock.get('defect_rounds')!=49 or lock.get('second_review_rounds')!=80 or lock.get('second_defect_rounds')!=47 or lock.get('second_clean_rounds')!=33 or lock.get('second_review_baseline')!='c3fbbadbee06d2be13b23822f5f17fce07cdab4e' or lock.get('third_review_rounds')!=80 or lock.get('third_defect_rounds')!=13 or lock.get('third_clean_rounds')!=67 or lock.get('third_review_baseline')!='f1901a2326ddf1189b90a5f34f8bbcc7e6eb0361' or lock.get('fourth_review_rounds')!=80 or lock.get('fourth_defect_rounds')!=30 or lock.get('fourth_clean_rounds')!=50 or lock.get('fourth_review_baseline')!='7875ce20a0fd40c0952960b6e45cf1e869b9c4dd' or lock.get('fifth_review_rounds')!=80 or lock.get('fifth_defect_rounds')!=29 or lock.get('fifth_clean_rounds')!=51 or lock.get('fifth_review_baseline')!='58313a67e1d21ad17c9a066e9a29c34245a0763e' or lock.get('release_file_count')!=len(release_files): fail('release lock mismatch')
for key, expected in {'sixth_review_baseline':'6fa0a5cb7063b6b821bd50c105c735470f589b80','sixth_review_rounds':80,'sixth_defect_rounds':60,'sixth_clean_rounds':20}.items():
    if lock.get(key) != expected: fail('release lock R6 mismatch '+key)
if 'REVIEW-80-ROUNDS-RC6-R6.md' not in release_files: fail('RC6 R6 release ledger missing')
for key, expected in {'seventh_review_baseline':'9103310fc93d978b6e70661f024a079fc0971003','seventh_review_rounds':80,'seventh_defect_rounds':22,'seventh_clean_rounds':58}.items():
    if lock.get(key) != expected: fail('release lock R7 mismatch '+key)
if 'REVIEW-80-ROUNDS-RC6-R7.md' not in release_files: fail('RC6 R7 release ledger missing')
if not (root/'tests/eighty-round-audit-r7.py').is_file(): fail('RC6 R7 executable gate missing')
for key, expected in {'eighth_review_baseline':'f40228d29ffa92e8d27ea84f9b1c1ae4a63ff9b2','eighth_review_rounds':80,'eighth_defect_rounds':15,'eighth_clean_rounds':65}.items():
    if lock.get(key) != expected: fail('release lock R8 mismatch '+key)
if 'REVIEW-80-ROUNDS-RC6-R8.md' not in release_files: fail('RC6 R8 release ledger missing')
if not (root/'tests/eighty-round-audit-r8.py').is_file(): fail('RC6 R8 executable gate missing')
builder=(root/'tools/build-release.py').read_text(encoding='utf-8'); verifier=(root/'tools/verify-release.py').read_text(encoding='utf-8')
for token in ['generate_sbom',"release_candidate = 'RC6'", "version = '1.3.0'",'sabri:source-head','SBOM.spdx.json']:
    if token not in builder: fail('deterministic generated-SBOM builder missing '+token)
for token in ['generated SBOM','1.3.0-RC6','sabri:source-head','source/package parity mismatch']:
    if token not in verifier: fail('generated-SBOM verifier missing '+token)
trace=(root/'TRACEABILITY.md').read_text(encoding='utf-8')
for i in range(1,18):
    if f'F09-FR-{i:03d}' not in trace: fail(f'missing FR {i}')
for i in range(1,11):
    if f'F09-NFR-{i:03d}' not in trace: fail(f'missing NFR {i}')
for i in range(1,14):
    if f'DoD-{i:02d}' not in trace: fail(f'missing DoD {i}')
for i in range(1,25):
    if f'F09-AT-{i:02d}' not in trace: fail(f'missing advanced trust trace {i}')
for tok in ['F09-CEN-01','F09-CEN-02','CEN-SEARCH-001','AJ-03','AJ-40']:
    if tok not in trace: fail('latest-plan traceability missing '+tok)
for p in ['includes/class-gdo-advanced-trust.php','includes/class-gdo-advanced-trust-hardening.php','includes/class-gdo-advanced-trust-events.php','ADVANCED-TRUST-24.md','REVIEW-80-ROUNDS-RC6.md','REVIEW-80-ROUNDS-RC6-R2.md','REVIEW-80-ROUNDS-RC6-R3.md','REVIEW-80-ROUNDS-RC6-R4.md','REVIEW-80-ROUNDS-RC6-R5.md']:
    if p not in release_files: fail('RC6 release file missing '+p)
for p in root.rglob('*'):
    if not p.is_file() or '.git' in p.parts or 'dist' in p.parts: continue
    if p.suffix.lower() in {'.zip','.sql','.sqlite','.pem','.key','.p12','.pfx'} or p.name in {'.env','wp-config.php'}: fail('forbidden repository artifact '+str(p))
print('File 09 RC6 release-integrity checks passed.')
