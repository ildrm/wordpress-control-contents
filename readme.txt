=== Universal Content Moderation ===
Tags: moderation, content policy, comments, safety
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local-first content policy and moderation auditing. Development preview; production acceptance gates remain open.

== Description ==

This development preview implements an independent text moderation domain, Unicode and Persian/Arabic normalization, indexed dictionary matching, nested policy conditions, versioned policy snapshots, shadow mode, and privacy-minimized audit storage.

WordPress post publication and standard comment submissions can be held for review. The plugin never deletes content or automatically suspends users. Low-level direct comment insertion uses a synchronous post-insert fallback with a brief visibility gap; this remains a production release blocker.

Four native administrator screens provide status, a simple dictionary/review policy editor, a side-effect-free playground, and cursor-paginated audit history. The complete moderation platform specified for this project is still in development. Integrations, advanced workflow, AI, cloud, OCR and network policies are not implemented.

No content is sent externally. No telemetry is collected. PHP intl, mbstring and dom extensions are required. Activation defaults to shadow mode. Audit records omit raw text, email and IP addresses, and expire after 30 days through bounded hourly cleanup with backlog continuations. WordPress privacy export and erasure callbacks are provided for registered users.

== Installation ==

1. Install the development ZIP on an isolated development or staging WordPress site.
2. Activate the plugin on an individual site. Network activation is unavailable in this preview.
3. Open Moderation > Policy and enter dictionary terms, one per line.
4. Keep shadow mode enabled and test samples in Moderation > Playground.
5. After reviewing site-specific results, disable shadow mode to hold matching submissions for human review.

== Frequently Asked Questions ==

= Does this modify original content? =
Normalization only creates analysis representations. Original text is preserved. In review mode, matching public submissions are held in WordPress pending status.

= What happens if storage fails? =
Review mode holds submissions. If a shadow policy was successfully loaded, shadow mode preserves original status even if subsequent analysis or audit storage fails. An unavailable policy cannot establish shadow mode and requires review.

= What happens on uninstall? =
Data is retained by default. For single-site uninstall only, setting UWCMP_DELETE_DATA_ON_UNINSTALL to true in trusted configuration enables permanent removal of the plugin tables and capabilities. Back up the database first. Multisite data deletion is not implemented.

== Changelog ==

= 0.1.0 =
* Initial development preview of local moderation and WordPress adapters.
* Production accuracy, full scope and compatibility gates remain unsatisfied.
