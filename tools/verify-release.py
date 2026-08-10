#!/usr/bin/env python3
from pathlib import Path
import hashlib, json, subprocess, sys, zipfile
repo_root = Path(__file__).resolve().parents[1]
zpath = Path(sys.argv[1]).resolve()
release = Path(sys.argv[2]).resolve()
package_root = 'global-doctor-onboarding-09/'
release_files = [x.strip() for x in release.read_text(encoding='utf-8').splitlines() if x.strip()]
expected = [package_root + rel for rel in release_files]

def digest(data): return hashlib.sha256(data).hexdigest()

with zipfile.ZipFile(zpath) as z:
    names = z.namelist()
    if names != expected: raise SystemExit('entry order/allowlist mismatch')
    if z.testzip(): raise SystemExit('corrupt archive')
    if any('..' in Path(n).parts or not n.startswith(package_root) for n in names): raise SystemExit('unsafe path')
    if any(i.compress_type != zipfile.ZIP_STORED for i in z.infolist()): raise SystemExit('package must use deterministic stored entries')
    for info, rel in zip(z.infolist(), release_files):
        if info.date_time != (1980,1,1,0,0,0): raise SystemExit('non-deterministic timestamp: '+info.filename)
        if rel == 'SBOM.spdx.json':
            continue
        source = repo_root / rel
        if digest(z.read(info.filename)) != digest(source.read_bytes()): raise SystemExit('source/package parity mismatch: '+rel)

    sbom = json.loads(z.read(package_root + 'SBOM.spdx.json').decode('utf-8'))
    if sbom.get('spdxVersion') != 'SPDX-2.3': raise SystemExit('invalid generated SBOM')
    package = (sbom.get('packages') or [{}])[0]
    if package.get('versionInfo') != '1.3.0-RC5' or package.get('filesAnalyzed') is not True: raise SystemExit('generated SBOM identity mismatch')
    declared = {}
    for item in sbom.get('files', []):
        rel = item.get('fileName','').removeprefix('./')
        values = {c.get('checksumValue') for c in item.get('checksums', []) if c.get('algorithm') == 'SHA256'}
        if not rel or not (repo_root / rel).is_file(): raise SystemExit('generated SBOM path missing: '+rel)
        actual = digest((repo_root / rel).read_bytes())
        if actual not in values: raise SystemExit('generated SBOM checksum mismatch: '+rel)
        declared[rel] = actual
    if set(declared) != set(release_files) - {'SBOM.spdx.json'}: raise SystemExit('generated SBOM coverage mismatch')
    try:
        head = subprocess.check_output(['git','rev-parse','HEAD'], cwd=repo_root, text=True, stderr=subprocess.DEVNULL).strip()
    except Exception:
        head = ''
    refs = package.get('externalRefs') or []
    locators = {x.get('referenceLocator') for x in refs if x.get('referenceType') == 'sabri:source-head'}
    if head and head not in locators: raise SystemExit('generated SBOM is not bound to exact source head')
print('Package verification passed:', len(expected), 'entries; generated SBOM exact-head coverage passed')
