# Implementation state

**Development checkpoint 0.1.0, not production-ready.** Updated 2026-10-06. The master request is not complete. No requirement was waived; see REQUIREMENTS.md and MASTER-SPECIFICATION.md.

## Repository baseline and current map
Initially README.md and GPLv2 LICENSE only; no app, dependencies, namespaces, schema, CI, tests, integration/build tooling or deployment baseline. Codebase-memory plus filesystem source fallback confirmed the initial empty application. No sound architecture was replaced.

Current runtime: universal-content-moderation.php, autoload.php, uninstall.php; src/Domain/{Content,Detection,Moderation,Policy}; src/Application/Services; src/Infrastructure/Database; src/Integrations/WordPress; src/Admin/{REST,Screens}; src/Privacy. Development tools are under tools/, tests/, Composer/npm manifests and CI. Runtime archives exclude development dependencies and tools. Required architecture/security/performance/accuracy/integrations/database/API/UX/privacy/testing/release documents and three ADRs exist.

## Completed coherent modules
- Guarded bootstrap and runtime PSR-4 loader; required intl/mbstring checks; administrator capabilities and activation/deactivation; no external processing/telemetry.
- Immutable ContentField (text/HTML), ContentPayload, Actor, ModerationContext, ModerationRequest, Policy, Rule, Evidence and result models; explicit supported action vocabulary.
- Unicode NFKC/case-fold pipeline, HTML visible-text extraction when configured, Persian/Arabic letters/digits/diacritics/tatweel, boundary-preserving ZWNJ; script labels; limited loss-aware evasion transformations.
- Sparse Aho–Corasick phrase/word/exact/partial/prefix/suffix matching; scoped term exceptions; Unicode boundaries; evidence/output/dictionary/state/memory-headroom limits. No per-term full content rescans in the indexed detector.
- Bounded nested AND/OR/NOT predicate engine; unknown-signal handling; deterministic priority/ID precedence; severity/confidence evidence; review-first enforcement and shadow mode.
- Strict JSON policy codec; immutable snapshot/checksum persistence; compare-and-swap active head and change audit transaction; per-request and optional version/checksum/site-aware compiled engine cache.
- Schema v1 custom InnoDB tables with advisory migration lock, table/column/index/engine verification, idempotent re-entry and missing-index recovery.
- Metadata-only audit, keyset pages, bounded 30-day retention, registered-user privacy export/erasure.
- Post/page/CPT publication and comment creation/update adapters; explicit REST approval reconciliation; synchronous low-level comment fallback; earlier error/spam preservation.
- Four native administrator screens: status, simple dictionary/review-policy editor, simulation playground and audit; protected REST check/events endpoints.
- Deterministic development ZIP, embedded file-integrity manifest and SHA-256 sidecar. Lifecycle tests cover activation/deactivation/default retained uninstall/explicit single-site deleted uninstall.

## Phase state
Phase 0 audit/ledger/architecture baseline is complete for the initially empty repository. Phases 1–4 contain the tested local vertical slice above, but full phase scope remains incomplete. Small portions of phases 6, 13 and 14 exist as preview UI, checks and packaging; those phases are NOT complete.

## Verification evidence
- PHPUnit: final suite 63 tests, 441 assertions, passing on PHP 8.2.34. Earlier suite passed on PHP 8.2.31 and host PHP 8.5.8. Final host rerun and static/coding checks are recorded below after completion.
- WP 6.9.4 / PHP 8.2.31 / MariaDB 11.4.13: 30 integration assertions and five browser tests pass.
- WP 7.1.2 / PHP 8.2.34 / MariaDB 11.4.13: 34 expanded integration assertions pass, including missing index recovery, a real competing DB migration lock and request cache reuse; five browser tests pass.
- Browser checks include CSRF, escaping, save/shadow simulation, four scoped axe WCAG A/AA checks and desktop RTL/focus smoke. This is not WCAG conformance or a full Persian-locale review.
- Synthetic evaluation: 20 sentinel cases pass; representative semantic accuracy and calibrated confidence remain unproven.
- Benchmarks: 32 combinations, 100–100,000 shared-prefix terms, 100 B–100 KiB input. Cold compile/memory measured separately from warm engine latency; no full WordPress request performance claim.
- Composer and npm audits reported no known dependency advisories on this run.
- Plugin Check 2.1.0 with runtime bootstrap: initial packaged scan found outdated tested header and direct-loader guard. Both addressed through current-release tests and ABSPATH-only runtime loader. Final packaged scan result is appended below.
- Coverage driver, remote CI execution, PHP 8.3/8.4, minimum WP 6.8, MySQL, persistent cache and Multisite matrix are unverified.

## Architectural decisions / assumptions
PHP 8.2+, WordPress 6.8+ intended floor, required intl/mbstring, GPL-2.0-or-later. Distribution header Universal Content Moderation avoids the directory checker's restricted WordPress name term; working product name is retained in project docs. Local-only and shadow by default. No destructive enforcement/AI-based penalty; unsupported actions route to pending. Unknown signals do not satisfy predicates. A request uses one stable policy snapshot per site; subsequent requests load the new version. Native UI avoids a framework until the full visual builder is implemented.

## Migrations and retention
Schema version 1 installed/re-entered on two disposable MariaDB stacks. Three tables: policy_versions, policy_head, events under the site's uwcmp_ prefix. No destructive upgrade migration exists. Versions preserve snapshots; event retention defaults to 30 days, 1,000 rows per batch, up to five batches per callback and scheduled continuations. Creator IDs/event references can be anonymized. Default uninstall retains data; explicit single-site opt-in removes owned data/capabilities. Network activation/deletion is refused.

## Important unresolved risks / release blockers
- Low-level comment insertion is already persisted before fallback: brief visibility/downstream observer gap. Later filters, status-only operations and direct SQL can bypass complete policy coverage.
- Tested synchronous paths now distinguish attempts/committed IDs and correlate repeated callbacks. Durable crash recovery and wider reentrant/concurrent coverage remain unimplemented; legacy ambiguous records cannot be reconstructed.
- Full queue/historical scans/retries/idempotency/recovery; inbox/assignment/notes/reports/appeals; reputation/strikes; visual rules/conflict preview/schedules/rollback/inheritance; all third-party adapters; AI/providers/budgets; media/OCR/files; analytics; Multisite/network/enterprise/cloud remain outstanding.
- Arbitrary regex, fuzzy/phonetic candidates, broad allowlist types, PII/secrets/URL/rate-limit detectors, language/slang/Finglish dictionaries and representative multilingual evaluation are outstanding.
- Detailed migration definitions/version upgrades/rollback and full installation matrix; cache serialization and true WordPress overhead; retention throughput; anonymous subject erasure; manual security/privacy/a11y review remain required.
- No production claim, Git commit/tag, publication or external deployment performed.

## Exact continuation task
ModerationCoordinator, typed AuditEvent and application storage contracts now exist. Continue with durable interrupted-submission recovery and concurrency/reentrancy coverage, then the remaining phase 1 foundation and phase 2 detection scope in master order. Preserve attempt/commit separation and existing passing behavior; do not claim distributed exactly-once processing from process-local tests.

Required validation commands: composer test; composer analyse; composer lint; composer audit; composer evaluate; php tools/benchmark.php --write. See TESTING.md for isolated WordPress installation, integration/E2E/lifecycle commands. Use the installed codebase-memory skill for structural exploration; reindex/check coverage if the graph generation predates these sources. Do not repeat phase 0 or replace the architecture.

## Final checkpoint check results

Host PHPUnit: 63 tests / 441 assertions pass. PHPStan level 6: pass. WPCS Core/Extra: zero errors or warnings. composer validate --strict: pass. Synthetic evaluation: pass. Final engine benchmark artifacts regenerated after resource-limit changes. Source graph reindexed: 691 nodes / 1,487 edges; coverage metadata reports no recorded gap for runtime src paths. This is best-effort graph coverage, not proof of semantic completeness.

Final packaged-runtime Plugin Check 2.1.0 (all default checks, runtime bootstrap, canonical slug): **Success: Checks complete. No errors found.** The readme tested-version format is 7.1, backed by WP 7.1.2 tests. Lifecycle/default retention/explicit single-site deletion also pass on WP 7.1.2. See verification-results.json for structured evidence. Build archive and integrity sidecar are under build/. The disposable localhost test stack is cleaned up at handoff; recreate it using TESTING.md. No further acceptance gate is implied by these results.

## Role-based review checkpoint

See REVIEW-FIXES.md and ADR 0004 for corrected defects, affected roles and remaining limits. Current verification: 75 unit tests / 471 assertions on host PHP 8.5.8 and container PHP 8.2.34; 34 base + 30 review integration assertions, 9 admin/schema/retention checks and 3 native shadow checks on WP 7.1.2 / MariaDB 11.4.13; six browser tests pass, including narrow RTL reflow and visible shadow proposals. PHPStan level 6 and WPCS pass. Composer/npm report no known advisories. The earlier 6.9.4 evidence predates these review fixes. The product remains an incomplete development preview; no requirements are waived.

Final review package: Plugin Check 2.1.0 with runtime bootstrap and all default checks reports no errors or warnings. Deactivation, retained uninstall, failed-deletion recovery indicators and successful opt-in retry pass. Final graph: 890 nodes / 2,238 edges; best-effort coverage reports no recorded issue for cited runtime paths. Preview archive and integrity sidecar rebuilt under build/. No commit, publication or external deployment performed.
