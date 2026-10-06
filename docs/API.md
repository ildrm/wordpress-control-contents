# API

The typed PHP API is usable for local checks; stable third-party SDK versioning is outstanding. No global singleton or vendor-specific dependencies are required.

```php
use UWCMP\Application\Services\Moderator;
use UWCMP\Domain\Content\{Actor, ContentField, ContentPayload, ModerationContext};
use UWCMP\Domain\Moderation\ModerationRequest;

// $policy is a validated UWCMP\Domain\Policy\Policy.
$result = (new Moderator($policy))->check(new ModerationRequest(
    new ContentPayload([new ContentField('body', $text, 'html')]),
    new ModerationContext('comment', 'custom.form', new Actor($user_id))
));
```

The pure API does not write audit or enforce. Integrators must enforce through a source-aware adapter, record appropriate audit, and preserve permission checks. ContentField allows valid UTF-8 at most 256 KiB; ContentPayload allows 1–32 unique fields and at most 512 KiB total. Normalized offsets cannot be used to mutate original HTML. No masking/redaction handler is currently supported.

| Endpoint | Method / permission | Input | Behavior |
|---|---|---|---|
| /wp-json/uwcmp/v1/check | POST; uwcmp_edit_policies | content UTF-8 string, max 256 KiB; content_type identifier, default comment | Side-effect-free sample result using active policy and authenticated actor. 400 validation; 503 unavailable. |
| /wp-json/uwcmp/v1/events | GET; uwcmp_view_audit | before >=0, limit 1–100, default 50 | Keyset page of metadata-only events. 503 storage unavailable. |

WordPress authenticates cookies/nonces or application passwords. A nonce is never authorization. There are no anonymous frontend endpoints, policy mutation REST routes, rate-limit store, moderation queue APIs or scan endpoints yet. Authentication/role limits are current access restrictions, not a substitute for future endpoint rate limiting.

PolicyCodec format 1 contains format, shadow, terms and rules. Conditions use group=and/or/not with children, or field/op/value. Current fields are content.type, source, site.id, actor.id/account_age/reputation/roles, content.length, links.count and detection.count/score/confidence/categories/fields/terms. Operators are eq/ne/gt/gte/lt/lte and list contains. Unknown fields are rejected. First enabled matching rule wins by descending priority and lexical ID. Unsupported enforcement is downgraded to pending. Conflicts are deterministically resolved; user-facing conflict analysis and inheritance are not implemented.

Hooks: uwcmp_result($result,$request) after analysis and attempt recording, before persistence; this is not a committed-object notification; uwcmp_failure($reason_identifier) on submission/retention failure. Hook handlers are trusted server code and must not log submitted text. Exceptions are isolated. Full SDK, source/field registration, action handler extensibility and WP-CLI commands remain outstanding.

ModerationCoordinator accepts PolicyProvider/AuditJournal contracts. prepare() creates an identified attempt; commit() acknowledges a saved object and observed status. AuditStore completion supports the native numeric WordPress IDs in this preview. Registered native authors and the current playground user have roles/account age supplied through the shared actor factory.
