#!/usr/bin/env python3
from pathlib import Path
import argparse, hashlib, json, os, re, subprocess, zipfile
from datetime import datetime, timezone

ap = argparse.ArgumentParser()
ap.add_argument('--output', default='dist')
a = ap.parse_args()
root = Path(__file__).resolve().parents[1]
out = root / a.output
out.mkdir(exist_ok=True)
files = [x.strip() for x in (root / 'RELEASE-FILES.txt').read_text(encoding='utf-8').splitlines() if x.strip()]
slug = 'global-doctor-onboarding-09'
version = '1.3.0'
release_candidate = 'RC6'
name = f'global-doctor-onboarding-09-{version}-{release_candidate}.zip'
zpath = out / name

try:
    head = os.environ.get('EXPECTED_SHA') or os.environ.get('GITHUB_SHA') or subprocess.check_output(['git','rev-parse','HEAD'], cwd=root, text=True, stderr=subprocess.DEVNULL).strip()
except Exception:
    head = 'UNAVAILABLE'
try:
    epoch = int(os.environ.get('SOURCE_DATE_EPOCH') or subprocess.check_output(['git','log','-1','--format=%ct'], cwd=root, text=True, stderr=subprocess.DEVNULL).strip())
except Exception:
    epoch = 315532800
created = datetime.fromtimestamp(epoch, timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')

def sha256(data):
    return hashlib.sha256(data).hexdigest()

def spdx_id(rel):
    return 'SPDXRef-File-' + re.sub(r'[^A-Za-z0-9.-]+', '-', rel).strip('-')

def generate_sbom(source_bytes):
    package_id = 'SPDXRef-Package-File09'
    items = []
    relationships = [{'spdxElementId':'SPDXRef-DOCUMENT','relationshipType':'DESCRIBES','relatedSpdxElement':package_id}]
    for rel in files:
        if rel == 'SBOM.spdx.json':
            continue
        data = source_bytes[rel]
        fid = spdx_id(rel)
        items.append({
            'SPDXID':fid, 'fileName':'./' + rel,
            'checksums':[{'algorithm':'SHA256','checksumValue':sha256(data)}],
            'licenseConcluded':'NOASSERTION','licenseInfoInFiles':['NOASSERTION'],'copyrightText':'NOASSERTION',
        })
        relationships.append({'spdxElementId':package_id,'relationshipType':'CONTAINS','relatedSpdxElement':fid})
    document = {
        'SPDXID':'SPDXRef-DOCUMENT','spdxVersion':'SPDX-2.3','dataLicense':'CC0-1.0',
        'name':f'File 09 {release_candidate} exact-head SBOM',
        'documentNamespace':f'https://sabrihomeopathy.com/spdx/file09/{version}/{release_candidate.lower()}/{head}',
        'creationInfo':{'created':created,'creators':['Organization: Sabri Social Homeopathy Platform','Tool: File09-Deterministic-SBOM-6.0']},
        'annotations':[{'annotationDate':created,'annotationType':'OTHER','annotator':'Tool: File09-Deterministic-SBOM-6.0','comment':'Generated from the exact checked-out release allowlist. SBOM.spdx.json is excluded from its own checksum list to avoid self-reference.'}],
        'packages':[{
            'SPDXID':package_id,'name':slug,'versionInfo':f'{version}-{release_candidate}','downloadLocation':'NOASSERTION',
            'filesAnalyzed':True,'licenseConcluded':'NOASSERTION','licenseDeclared':'GPL-2.0-or-later','copyrightText':'NOASSERTION',
            'externalRefs':[{'referenceCategory':'OTHER','referenceType':'sabri:source-head','referenceLocator':head}],
        }],
        'files':items,'relationships':relationships,
    }
    return (json.dumps(document, indent=2, sort_keys=True) + '\n').encode('utf-8')

source_bytes = {}
for rel in files:
    p = root / rel
    if not p.is_file():
        raise SystemExit('missing release file ' + rel)
    if rel != 'SBOM.spdx.json':
        source_bytes[rel] = p.read_bytes()
source_bytes['SBOM.spdx.json'] = generate_sbom(source_bytes)

manifest = []
with zipfile.ZipFile(zpath, 'w', compression=zipfile.ZIP_STORED) as z:
    for rel in files:
        data = source_bytes[rel]
        manifest.append({'path':rel,'bytes':len(data),'sha256':sha256(data),'generated':rel == 'SBOM.spdx.json'})
        info = zipfile.ZipInfo(f'{slug}/{rel}', (1980,1,1,0,0,0))
        info.compress_type = zipfile.ZIP_STORED
        info.create_system = 3
        info.external_attr = (0o100644 & 0xffff) << 16
        z.writestr(info, data, compress_type=zipfile.ZIP_STORED)

digest = sha256(zpath.read_bytes())
(out / (name + '.sha256')).write_text(f'{digest}  {name}\n', encoding='utf-8', newline='')
(out / 'PACKAGE-MANIFEST.json').write_text(json.dumps({
    'package':name,'sha256':digest,'bytes':zpath.stat().st_size,'root':slug+'/',
    'version':version,'schema':6,'advanced_trust_schema':2,'advanced_trust_contract':'1.1.0',
    'release_candidate':release_candidate,'source_head':head,'review_rounds':80,'defect_rounds':49,'second_review_rounds':80,'second_defect_rounds':47,'second_clean_rounds':33,'third_review_rounds':80,'third_defect_rounds':13,'third_clean_rounds':67,'fourth_review_rounds':80,'fourth_defect_rounds':30,'fourth_clean_rounds':50,
    'staging_accepted':False,'live_deployed':False,'operationally_accepted':False,'files':manifest,
}, indent=2, sort_keys=True) + '\n', encoding='utf-8', newline='')
print(digest)
