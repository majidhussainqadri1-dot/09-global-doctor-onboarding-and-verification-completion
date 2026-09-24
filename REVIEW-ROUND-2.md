# Fresh Review/Fix Round 2 — Adversarial and Failure Review

Date: 2026-08-06

Findings corrected:

1. Reviewer scope had to be rechecked at evidence review, recommendation, finalization, risk and quality actions.
2. Self-review/conflict and recommender/finalizer separation had to remain server-side at action time.
3. Outbox needed processing locks, event UUID dedupe, bounded retry, dead-letter timestamps and reasoned replay.
4. Claims needed opaque File 00 subject/version binding, deterministic canonical signing and fail-closed current context.
5. Evidence duplication needed human-review signals rather than automatic rejection.
6. Public status needed File 00 acknowledgment and current validity/claim state.
7. Operations needed safe degraded mode, stale-claim/critical-risk health and bounded repair.
8. UI needed green-centered accessible tokens, logical RTL properties, 320px reflow, visible focus and reduced motion.
9. CI needed explicit FR/NFR traceability, secret/archive checks, PHP 7.4/8.3, JavaScript syntax and deterministic packaging.
10. Release documents needed to distinguish code/package/CI from staging/live/operational acceptance.

All corrections require two complete exact-tree test passes before the release candidate is published.

Additional adversarial findings corrected during the final pass:

11. Duplicate-risk detection originally prevented submission before any reviewer could resolve a false positive; submission now continues to human review while verification remains blocked until resolution.
12. A repeated initial submit could fail instead of returning the already-authoritative immutable submission; same-hash submitted/resubmitted retries are now idempotent.
13. A resubmitted application could still be edited or receive new uploads; it is now immutable until reviewer reassignment.
14. Privacy erasure could anonymize a recommended/suspended/appeal-pending record without reaching a terminal user-controlled state; withdrawal paths and appeal closure were corrected.
15. Stale claim reconciliation could mint a new claim version on every run; it now retries the existing outbox event, requires operator replay for dead letters, and reconstructs only a genuinely missing event.
16. Purpose-bound credential access was audited but did not atomically queue an applicant notification; consumption now rechecks scope and commits one-time use plus notification together.
