# Testing

Install locked development dependencies using composer install and npm ci --ignore-scripts. Runtime archives contain no vendor/node_modules or test tools. Run composer test, composer analyse, composer lint, composer audit, composer evaluate, composer benchmark and composer package. PHPStan uses --debug for serial execution because sandboxed local worker socket creation is unavailable. Analysis level 6 uses official WordPress stubs; iterable-value typing is not yet enforced for adapter arrays and should be strengthened later.

## Recorded checks, 2026-10-06

- PHPUnit: 63 tests, 441 assertions, passing on host PHP 8.5.8; the final expanded suite also passes on PHP 8.2.34; the original 55-test suite passed on PHP 8.2.31. Critical matcher includes 300 reproducible Unicode/overlap comparisons with a naive reference, boundaries, evasion, ZWNJ, scoped exceptions, malformed/oversized input and output explosion. Policy tests cover nested conditions, unknown signals under NOT, priority, disabled rules, shadow/review behavior, immutable codec roundtrip and malformed documents.
- WordPress integration: 30 assertions on WP 6.9.4 / PHP 8.2.31 / MariaDB 11.4.13; expanded suite on WP 7.1.2 / PHP 8.2.34, including index recovery, cache reuse and migration lock exclusion. Final expanded assertion count is recorded in IMPLEMENTATION-STATE.md. See tools/integration.php. Tests install schema twice, reject stale policy saves with rollback, moderate posts/CPTs/comments/REST, preserve spam, handle prior WP_Error, verify access and simulation isolation, audit minimization/pagination, privacy erasure, retention and checksum failure.
- Playwright/Chrome: five tests passing on WP 6.9.4 and WP 7.1.2, including CSRF, injection escaping, four screens and scoped axe A/AA/desktop RTL smoke checks. Browser version is available from the locally installed Chrome, not pinned to the CI browser binary. No manual screen-reader audit is claimed.
- WPCS Core/Extra: zero findings with documented conventions in ADR 0001.
- PHPStan level 6: zero findings in the last recorded run; rerun after any change.
- Composer audit: no reported advisories or abandoned packages on the recorded run. npm install audit: no vulnerabilities in six packages.
- Synthetic evaluation: 20 sentinel cases, all pass; not representative accuracy.
- Benchmark: 32 engine cases recorded; compilation and memory separate, WordPress overhead unmeasured.
- Packaged runtime Plugin Check 2.1.0: all default checks with runtime bootstrap, no reported errors; tested header uses 7.1 based on the 7.1.2 stack.
- Lifecycle: deactivation, default retained uninstall and explicit single-site deleted uninstall pass on both stacks.
- Coverage: no coverage driver measured in this environment; requested percentage gates are unproven.
- CI workflow added, but remote GitHub execution has not been observed.

## Isolated WordPress environment

```sh
docker compose -f tools/compose.yaml up -d --build
docker compose -f tools/compose.yaml exec -T wordpress php /var/www/html/wp-content/plugins/universal-content-moderation/tools/install-test.php
docker compose -f tools/compose.yaml exec -T wordpress php /var/www/html/wp-content/plugins/universal-content-moderation/tools/integration.php
docker compose -f tools/compose.yaml exec -T -w /var/www/html/wp-content/plugins/universal-content-moderation wordpress php vendor/bin/phpunit --do-not-cache-result
UWCMP_BROWSER_CHANNEL=chrome npm run test:e2e
```

Alternatively install Playwright Chromium and omit the Chrome environment override. Only localhost port 8876 is published. Database uses disposable tmpfs and development-only credentials. The installer/tools require the explicit UWCMP_INTEGRATION_TEST=1 environment marker and CLI. After testing: docker compose -f tools/compose.yaml down -v. Never point these tools at an existing site/database.

Remaining matrix: WP 6.8 minimum, PHP 8.3/8.4, MySQL, persistent cache on/off, Multisite, integrations, older-version migrations/upgrades/rollback, network failures, high-volume concurrency, comprehensive E2E and manual WCAG/RTL. CI matrix configuration is not proof of completed compatibility testing.

## Review verification, 2026-10-06

The current review suite has 75 unit tests / 471 assertions. On WordPress 7.1.2, run tools/integration.php (34 assertions), tools/review-integration.php (30), tools/admin-review.php (9 checks), then tools/shadow-review.php (3 checks) before the six browser tests. All tools require the disposable marked container. Run tools/lifecycle-test.php last because it deactivates/uninstalls the plugin and deletes owned test data; it also verifies failed deletion preserves recovery indicators. Historical 6.9.4 results above predate these review changes. See REVIEW-FIXES.md and verification-results.json for final evidence.

Packaged scan uses official Plugin Check 2.1.0: run WP-CLI with --require=/var/www/html/wp-content/plugins/plugin-check/cli.php, plugin check uwcmp-package-check --slug=universal-content-moderation --format=json against the extracted local archive. No AI-based analysis is enabled.
