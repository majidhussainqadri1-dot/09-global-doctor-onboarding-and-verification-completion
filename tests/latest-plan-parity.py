from pathlib import Path
import json, sys
root=Path(__file__).resolve().parents[1]
def text(p): return (root/p).read_text(encoding='utf-8')
def require(cond,msg):
    if not cond: print('FAIL:',msg,file=sys.stderr); raise SystemExit(1)
main=text('global-doctor-onboarding.php')
integrations=text('includes/class-gdo-integration-contracts.php')
notifications=text('includes/class-gdo-notifications.php')
plugin=text('includes/class-gdo-plugin.php')
trace=text('TRACEABILITY.md')
status=text('STATUS.md')
css=text('assets/css/onboarding.css').lower()
workflow=text('.github/workflows/file09-rc2-final.yml')

require('Plugin Name: Global Doctor Onboarding and Verification\n' in main,'canonical File09 title not exact')
require('Verification Completion' not in main,'obsolete Completion suffix still active')
for token in ['F09-CEN-01','F09-CEN-02','CEN-SEARCH-001','AJ-03','AJ-04','AJ-05','AJ-24','AJ-25','AJ-31','AJ-32','AJ-33','AJ-34','AJ-35','AJ-36','AJ-37','AJ-38','AJ-39','AJ-40']:
    require(token in trace,'latest governing trace missing '+token)
for token in ['gdo.file09.owner-contract','gdo.file21.publishing-eligibility','gdo.file23.publishing-dashboard-eligibility','gdo.file26.doctor-verification-projection','sabri_file26_connector_manifests','sabri_shell_page_contracts','authorization_rechecked','donor_rank_advantage','evidence_exposed','clinical_authorization']:
    require(token in integrations,'latest integration boundary missing '+token)
require("'status'             => 'contract_tested'" in integrations,'File26 private verification connector must not auto-activate')
require("'direct_table_meta_write'=> false" in integrations,'canonical-owner direct-write prohibition missing')
for token in ['sun_register_notification_producer','sun_ingest_domain_event','DoctorApplication.Submitted','DoctorVerification.Verified',"'schema_version'  => self::FILE19_SCHEMA",'minimized presentation data']:
    require(token in notifications,'File19 sun.event.v1 integration missing '+token)
require('wp_mail(' not in notifications,'parallel File09 mail transport forbidden')
require("function_exists('sun_ingest_domain_event')" in plugin,'dependency check does not recognize current File19 contract')
require('#146c43' in css or '#087a4e' in css,'green primary design token not present')
require('#ff8a1f' not in css,'obsolete orange primary token remains active')
for token in ['RC4','latest-plan-parity.py','global-doctor-onboarding-09-1.2.0-RC4.zip']:
    require(token in workflow,'RC4 exact-head workflow missing '+token)
low=status.lower()
for token in ['staging accepted: **false**','live deployed: **false**','operationally accepted: **false**']:
    require(token in low,'status honesty missing '+token)
lock=json.loads(text('RELEASE-LOCK.json'))
require(lock.get('release_candidate')=='RC4' and lock.get('staging_accepted') is False and lock.get('live_deployed') is False and lock.get('operationally_accepted') is False,'release lock status falsehood')
print('File 09 latest central + File09 plan parity gates passed.')
