from pathlib import Path
import sys
root=Path(__file__).resolve().parents[1]
def fail(m): print('FAIL:',m,file=sys.stderr); raise SystemExit(1)
def text(p): return (root/p).read_text(encoding='utf-8')
member=text('includes/class-gdo-membership-adapter.php')
if 'identity_documents_current' not in member or 'approved_membership_types' not in member or 'age_years >= $minimum_age' not in member: fail('current adult professional identity/eligibility control missing')
api=text('includes/class-gdo-api.php')
if "array( 'draft','more_information','resubmitted' )" in api: fail('submitted snapshot exposed as editable')
js=text('assets/js/onboarding.js')
for token in ["document.querySelector('[data-gdo-autosave-status]')",'synchronizeRowVersion','input[name="row_version"]',':invalid','reportValidity']:
    if token not in js: fail('wizard race/accessibility fix missing '+token)
evidence=text('includes/class-gdo-evidence.php')
for token in ['FOR UPDATE','pending_review','more_information','rejected','gdo_evidence_review_conflict','credential_review_recorded_purpose']:
    if token not in evidence: fail('evidence atomicity/purpose control missing '+token)
admin=text('includes/class-gdo-admin.php')
for token in ['gdo_assign_appeal','assigned_reviewer_id IS NULL','gdo_appeal_assignment_conflict','$assigned_to_actor']:
    if token not in admin: fail('independent appeal assignment control missing '+token)
contract=text('includes/class-gdo-integration-contracts.php')
for token in ['gdo.file03.doctor-profile-eligibility','gdo.file07.directory-eligibility','gdo.file08.clinic-eligibility','fail_closed','file00_claim_not_current']:
    if token not in contract: fail('cross-file contract missing '+token)
for p in ['BRANCH-MARKER.tmp','noop2','noop3','noop4','noop5','.github/workflows/apply-file09-final-source.yml','.github/workflows/apply-file09-final-source-v2.yml','.github/workflows/apply-file09-final-source-v3.yml','.github/workflows/rc2-source-export.yml','.github/workflows/rc2-source-export-v2.yml']:
    if (root/p).exists(): fail('obsolete or temporary artifact remains '+p)

workflows={p.name for p in (root/'.github/workflows').glob('*.yml')}
if workflows!={'file09-rc2-final.yml'}: fail('authoritative workflow set is not singular: '+repr(sorted(workflows)))

print('File 09 RC2 adversarial invariants passed.')
