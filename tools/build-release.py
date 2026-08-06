#!/usr/bin/env python3
from pathlib import Path
import argparse
import hashlib
import json
import os
import subprocess
import zipfile

ap = argparse.ArgumentParser()
ap.add_argument('--output', default='dist')
a = ap.parse_args()
root = Path(__file__).resolve().parents[1]
out = root / a.output
out.mkdir(exist_ok=True)
files = [x.strip() for x in (root / 'RELEASE-FILES.txt').read_text(encoding='utf-8').splitlines() if x.strip()]
slug = 'global-doctor-onboarding-09'
name = 'global-doctor-onboarding-09-1.2.0-RC1.zip'
zpath = out / name
try:
    head = os.environ.get('GITHUB_SHA') or subprocess.check_output(
        ['git', 'rev-parse', 'HEAD'], cwd=root, text=True, stderr=subprocess.DEVNULL
    ).strip()
except Exception:
    head = 'UNAVAILABLE'
manifest = []
with zipfile.ZipFile(zpath, 'w', compression=zipfile.ZIP_STORED) as z:
    for rel in files:
        p = root / rel
        if not p.is_file():
            raise SystemExit('missing release file ' + rel)
        data = p.read_bytes()
        manifest.append({'path': rel, 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest()})
        info = zipfile.ZipInfo(f'{slug}/{rel}', (1980, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_STORED
        info.create_system = 3
        info.external_attr = (0o100644 & 0xffff) << 16
        z.writestr(info, data, compress_type=zipfile.ZIP_STORED)
digest = hashlib.sha256(zpath.read_bytes()).hexdigest()
(out / (name + '.sha256')).write_text(f'{digest}  {name}\n', encoding='utf-8', newline='')
(out / 'PACKAGE-MANIFEST.json').write_text(
    json.dumps({
        'package': name,
        'sha256': digest,
        'bytes': zpath.stat().st_size,
        'root': slug + '/',
        'version': '1.2.0',
        'schema': 6,
        'source_head': head,
        'staging_accepted': False,
        'live_deployed': False,
        'operationally_accepted': False,
        'files': manifest,
    }, indent=2, sort_keys=True) + '\n',
    encoding='utf-8', newline=''
)
print(digest)
