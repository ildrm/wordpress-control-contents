# Release state

Version 0.1.0 is a development preview. The full master implementation request is not complete and this archive must not be released as a production moderation platform. docs/REQUIREMENTS.md retains every numbered requirement, with implementation/test/documentation tracked separately. IMPLEMENTATION-STATE.md specifies continuation work.

The repository's working product name remains Universal WordPress Content Moderation Platform. The distribution header uses Universal Content Moderation because the official directory checker flags WordPress as a restricted name term. No directory submission/publishing has occurred.

The deterministic package builder includes runtime PHP, source metadata, GPL license, readme, changelog and documentation; excludes hidden configuration, development tools/tests, vendor, node_modules, credentials and browser traces. Each archive includes an integrity manifest and has a separate SHA-256 sidecar. Rebuilding unchanged inputs uses fixed entry timestamps and sorted paths. This establishes artifact integrity, not a production release gate.

WordPress Plugin Check 2.1.0 was run with runtime bootstrap enabled. A repository scan correctly flagged non-distribution test tools/traces/configuration. A separate packaged-runtime scan is run with the canonical slug. Initial findings were direct-loader protection and an outdated tested-up-to header. The loader now requires ABSPATH, while pure tests use the development Composer loader. WP 7.1.2 integration/browser tests pass before updating the header. Final package scan status is recorded in IMPLEMENTATION-STATE.md.

| Acceptance gate | State |
|---|---|
| Complete scoped functionality | Not satisfied: full phases 1–14 incomplete |
| Representative accuracy / 99.5% hard-block precision | Not satisfied; review-only enforcement |
| WordPress request performance | Not satisfied; engine-only preliminary benchmarks |
| Security / Plugin Check | WPCS, dependency audits and packaged Plugin Check pass; comprehensive security review incomplete |
| Privacy | Local minimization/export/erasure tested; complete data-class/anonymous retention controls incomplete |
| Accessibility | Automated desktop/narrow RTL checks pass; manual/locale/full workflow audit incomplete |
| Reliability | Policy transaction tests pass; async queue/retries/recovery/idempotency incomplete |
| Integrations | Core slice tested on two stacks; third-party matrix absent |
| UX / SDK / CLI | Preview screens only; full workflows and stable developer contracts incomplete |
| Installation / upgrades / uninstall / rollback | Activation/schema re-entry/deactivation/default retention/single-site uninstall tested; older upgrade/rollback incomplete |

Archive build is a reviewable development artifact. No external deployment, publishing, Git commit, tag or release was performed by this implementation checkpoint.
