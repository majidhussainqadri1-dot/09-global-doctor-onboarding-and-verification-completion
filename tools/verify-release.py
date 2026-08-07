#!/usr/bin/env python3
from pathlib import Path
import hashlib
import sys
import zipfile
repo_root = Path(__file__).resolve().parents[1]
zpath = Path(sys.argv[1]).resolve()
release = Path(sys.argv[2]).resolve()
package_root = 'global-doctor-onboarding-09/'
release_files = [x.strip() for x in release.read_text(encoding='utf-8').splitlines() if x.strip()]
expected = [package_root + rel for rel in release_files]
with zipfile.ZipFile(zpath) as z:
    names = z.namelist()
    if names != expected: raise SystemExit('entry order/allowlist mismatch')
    if z.testzip(): raise SystemExit('corrupt archive')
    if any('..' in Path(n).parts or not n.startswith(package_root) for n in names): raise SystemExit('unsafe path')
    if any(i.compress_type != zipfile.ZIP_STORED for i in z.infolist()): raise SystemExit('package must use deterministic stored entries')
    for info, rel in zip(z.infolist(), release_files):
        if info.date_time != (1980,1,1,0,0,0): raise SystemExit('non-deterministic timestamp: '+info.filename)
        source=repo_root/rel
        if hashlib.sha256(z.read(info.filename)).hexdigest()!=hashlib.sha256(source.read_bytes()).hexdigest(): raise SystemExit('source/package parity mismatch: '+rel)
print('Package verification passed:',len(expected),'entries')
