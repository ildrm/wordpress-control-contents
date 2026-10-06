# Code review and fixes — 2026-10-06

Reviewed the existing development preview through the specification's security, performance, detection, WordPress architecture, QA, UX, accessibility and moderator/operator roles. Privacy and reliability were examined across these roles. This review repairs the implemented slice; it does not complete the outstanding product modules.

## Findings addressed

| Priority | Role | Defect and resulting behavior |
|---|---|---|
| High | Detection / security | Replacing every HTML tag with a space let `b<strong>ad</strong>word` bypass detection. Local DOM extraction now preserves inline words, separates blocks, ignores inert/script/style content and handles quoted attributes and HTML5 entities once. Invalid `<` comparisons remain visible. Excessive nesting requires review. |
| High | Detection | An exception such as `quoted badword` also authorized `quoted b@dw0rd` in the lossy channel. Sparse source-span maps now check exceptions against canonical text. Allowlisted text remains exempt when unrelated text is obfuscated. |
| Medium | Detection / performance | Exception boundaries omitted combining marks and could inspect the wrong preceding Unicode character. Shared complete-codepoint checks and bounded phrase windows replace full-tail searches; source-span lookup uses binary search. |
| Medium | Detection / performance | Out-of-scope patterns could exhaust the candidate limit and send unrelated content to review. Matching now uses cached field/type subsets, with bounded cache size. |
| Medium | Detection | Collapsing repeated letters to one missed a dictionary word containing a double letter, such as `book` versus `boooook`. A second review-confidence representation preserves two letters. |
| Medium | Detection | HTML links were counted twice when the URL appeared in both href and label, and hidden script URLs were counted. HTTP(S) anchors count once; visible text URLs outside those anchors count separately. |
| High | Reliability / detection | Regex failures silently fell back to original text. Failures now raise controlled errors; valid submissions with analysis failures receive an auditable incomplete review result. Transformation edits, nesting, dictionary and exception storage are bounded. |
| Medium | Architecture / QA | Malformed model arrays produced warnings and TypeError rather than validation errors; invalid term scopes were accepted. Models now validate element types, list structure and bounded scope identifiers. |
| High | Security / WordPress | A failed site-prefix load left the previous site's policy cached under the new prefix. Cache state is cleared before loading; repeated failed loads keep failing instead of returning stale policy. The compiled-engine cache generation changed. |
| High | Reliability / database | Starting a policy transaction could implicitly commit the caller's existing transaction. Policy saves now require an independent transaction and leave an existing transaction rollbackable. Storage failure and optimistic conflict are distinguished. The guard follows the [MySQL transaction contract](https://dev.mysql.com/doc/refman/8.0/en/set-transaction.html). |
| High | WordPress / security | REST partial and status-only comment updates could apply explicit approval after the moderation filter. REST now checks the complete merged object and preserves the server hold. Native status-only approval also receives a synchronous transition fallback. |
| High | Reliability / operator | Pre-save records were labelled decisions, lacked new object IDs and duplicated standard comment callbacks. Typed AuditEvent and ModerationCoordinator now distinguish attempts from acknowledged decisions, bind final IDs/status and make repeated completion idempotent. Temporary server-generated comment tokens correlate core callbacks; identical real submissions remain separate. Failed insertions remain attempts. |
| High | Privacy / reliability | Late audit completion could restore object references after erasure. Completion now verifies the original actor association before updating an attempt. Audit completion failure requires review in active mode. |
| Medium | WordPress / reliability | Observer exceptions could change a clean result or crash error handling. Observer failures are isolated; low-level hold failures emit generic failure identifiers. |
| High | Reliability | Losing required PHP extensions disabled moderation hooks altogether. Hooks now remain registered for conservative failure handling, and notices/activation checks include the DOM dependency. Known shadow mode still preserves content status. |
| High | Security / UX | Array-valued or missing dictionary fields became an empty dictionary; malformed shadow values could switch mode. The form now rejects these inputs without saving. |
| High | Architecture / UX | The simple editor could remove advanced rules, scopes and exceptions. Advanced policies are protected in both screen and save handler. Compatible existing rule/term identities are preserved. |
| Medium | Accessibility / operator | Textareas and audit tables overflowed narrow screens; shadow proposals and incomplete analysis were unclear in the audit table. Textareas/results fit the viewport, tables have a labelled scroll region, and audit decisions identify shadow proposals and incomplete analysis. |
| Medium | Privacy | Privacy callbacks threw exceptions on database failure and omitted retained policy creator references. They now return WordPress errors and export bounded policy-authorship records as well as events. |
| Medium | Reliability / database | Schema health trusted a version marker and index names. It now checks real tables, InnoDB, index columns/order/uniqueness, policy integrity, extensions and the retention schedule. |
| Medium | Privacy / performance | Hourly deletion of only 1,000 events could not drain a larger backlog promptly. Each callback performs at most five batches, then schedules a continuation. Deactivation clears both schedules. WP-Cron availability remains necessary. |
| Medium | Reliability / privacy | Uninstall removed markers/capabilities despite failed table deletion. Opt-in deletion now fails visibly and preserves those recovery indicators; it also removes temporary correlation metadata. |
| Medium | WordPress / architecture | Registered-user account age was never supplied. A shared actor factory now supplies roles/account age to native moderation and simulation. Coordination depends on application contracts, keeping WordPress storage out of the application service. |

## Evidence

Five initial regression tests reproduced the original HTML, exception, scope, repeated-letter and model-validation defects before fixes. The final unit suite has 75 tests / 471 assertions on PHP 8.5.8 and PHP 8.2.34. PHPStan level 6 and WordPress Core/Extra coding checks pass.

WordPress 7.1.2 / PHP 8.2.34 / MariaDB 11.4.13: the original integration suite passes 34 assertions; review integration passes 30; admin/schema/retention checks pass 9; native shadow checks pass 3. Cases include repeated identical submissions, optional post-after-hooks disabled, failed insertions followed by drafts, native/REST approval, throwing observers, audit completion failure, late completion after erasure, repeated failed site loads, caller transaction rollback, nonunique indexes and a 5,501-row retention backlog.

Six Playwright tests pass: malformed form rejection, CSRF, shadow simulation, injection escaping, visible shadow proposals, four scoped axe A/AA checks, keyboard/desktop RTL smoke and all four screens at a 320-pixel RTL viewport. These are automated checks, not full accessibility conformance.

The 32-case synthetic engine benchmark is refreshed in benchmark-results.json. Pure engine latency excludes WordPress storage/hooks/audit/cache transfer. Native integration and lifecycle checks run only against disposable, explicitly marked test databases. See verification-results.json for final packaged-check and lifecycle evidence.

## Remaining limits

Low-level comment insertion, native comment approval and direct post publication are already persisted before their fallback checks; a brief visibility/downstream-observer gap remains. Later hooks or direct SQL can still bypass protection. Process-local correlation is not durable exactly-once processing: crash recovery, asynchronous jobs, concurrent/reentrant adapter coverage and cleanup of interrupted submissions require the planned recovery subsystem. Existing ambiguous legacy audit rows cannot be reliably reconstructed and are not relabelled as committed objects.

Representative multilingual accuracy, broader detection/integration coverage, the full moderation workflow, override/appeal/assignment UX, visual policies, queues, AI/media, network modules and release gates remain outstanding. DOM extraction handles the tested fragments; it is not a complete browser/CSS renderer. MySQL, minimum WP 6.8, persistent caches, older upgrades/rollback and complete manual security/privacy/accessibility reviews remain unverified. This remains a development preview.

Final packaged-runtime Plugin Check 2.1.0 reports no errors or warnings. Lifecycle checks pass, including failed deletion/retry and clearing both retention schedules. Composer/npm advisory checks report none. The disposable Docker stack is removed after verification.
