# Integration coverage

| Surface | Current implementation | Verification / limits |
|---|---|---|
| Posts/pages/CPT publication | title/body/excerpt/slug, wp_insert_post_data, publish/future/private to pending on policy match | Direct PHP insert/edit, CPT and REST post tests pass on WP 6.9.4; expanded suite also passes on WP 7.1.2. Gutenberg/Classic real editor, scheduled publication and XML-RPC not separately tested. |
| Standard comments | body/author/URL, pre_comment_approved | Normal submission holds tested; existing spam/trash/pending is preserved. Email/IP/agent are not analyzed. |
| Comment updates | wp_update_comment_data | Hold and earlier WP_Error passthrough tested. Status-only low-level operations are not fully intercepted. |
| REST comments | rest_pre_insert_comment reconciles later explicit approval | Explicit approval regression passes. Core permission checks remain authoritative. |
| Direct wp_insert_comment | synchronous wp_insert_comment fallback | Hold tested, but content is already stored before fallback. Brief visibility/observer gap remains a release blocker. |
| Native admin | dashboard, simple policy editor, playground, audit | Six browser tests pass; full operations UI outstanding. |
| PHP integration | typed Moderator::check | WordPress-independent PHPUnit tests pass. Stable SDK compatibility contract is not yet frozen. |
| REST simulation | protected /uwcmp/v1/check | Capability, oversized input and side-effect-free behavior tested. |
| Third-party ecosystems | None claimed | WooCommerce/HPOS, bbPress, BuddyPress/BuddyBoss, ACF, forms, LMS/membership/marketplace adapters outstanding. |
| Taxonomies/users/metadata/media | None implemented | Universal coverage is not yet achieved. |
| Multisite | individual-site tables, request cache scoped by prefix; network activation refused | Network policies, new-site provisioning, switch-to-blog matrix and network uninstall outstanding. |

Other plugins running later filters or writing direct SQL can bypass this preview. Anti-spam statuses are preserved, but Akismet/CleanTalk compatibility has not been tested. Do not treat the generic WordPress comment adapter as verified WooCommerce review support.

The review adds merged-object REST update checks, native status-only comment approval fallback, acknowledgement audit IDs and process-local callback correlation. Low-level/native status fallbacks occur after persistence and retain their visibility/observer gap. See REVIEW-FIXES.md.
