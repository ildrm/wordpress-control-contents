# Security

This is a development threat-model and control record, not a completed security certification.

Untrusted inputs include submitted text, REST parameters, dictionary lines and structured policy documents. Domain validation bounds UTF-8 input, fields, tree nodes/depth, evidence and dictionary size. Policy format rejects unknown properties and incorrect scalar/list types; optional values cannot silently accept null. Arbitrary regex, file extraction, remote URLs and provider credentials are not exposed.

Admin forms require capabilities and WordPress CSRF nonces. REST /check requires uwcmp_edit_policies; /events requires uwcmp_view_audit. REST authentication is delegated to WordPress. Caller-supplied actor IDs are not accepted by these endpoints. WP-CLI or trusted PHP APIs remain privileged server code. Audit and settings screens escape content contextually. The playground preserves raw input for analysis and escapes it on output. Executable development tools reject non-CLI access and are omitted from archives.

SQL values and table identifiers use wpdb preparation, including %i. Table prefixes are validated during migration. Custom tables are owned by this plugin. Policy changes use an InnoDB transaction containing immutable snapshot insertion, compare-and-swap head update and audit insertion. A conflicting editor or write failure rolls back. SHA-256 checksums detect accidental snapshot corruption; they are not authentication against an attacker with database write access.

Storage/analysis failures hold submissions in review mode. A successfully loaded shadow policy preserves original status on later failures; an unavailable policy cannot establish shadow mode and requires review. Errors exposed to administrators/REST clients exclude raw SQL, submitted text, secrets and internal exception details. uwcmp_failure emits only a bounded reason identifier. There is no remote HTTP, credential storage, telemetry or external processor in this slice.

| Threat | Current control / remaining work |
|---|---|
| Stored/reflected XSS | Contextual escaping; browser injection regression. Full UI/security audit outstanding. |
| SQL injection | Prepared identifiers/values; no user-selectable SQL. Database access review performed. |
| CSRF | Nonce plus capability; browser test rejects missing nonce with 403. |
| Broken access control / IDOR | Protected endpoints, separate audit capability; no object mutation API yet. Expanded role matrix outstanding. |
| Race/lost update | Atomic policy head compare-and-swap; stale editor rollback test. Parallel transaction stress test outstanding. |
| Replay / duplicate processing | Unique UUIDs, acknowledged object binding, idempotent completion and standard comment callback correlation. Durable recovery/distributed exactly-once processing remain outstanding. |
| Resource exhaustion | Size/tree/output limits; no arbitrary regex. Worst-case memory/generative fuzz coverage incomplete. |
| SSRF / webhooks / credentials | Features absent. Required controls must precede provider/webhook implementation. |
| Upload/archive abuse | Public upload analysis absent. Package test extractor accepts only a trusted locally built archive. |
| PII/log leakage | Metadata-only audit and bounded retention; registered-user erasure tested. Anonymous subject erasure outstanding. |
| Other-plugin bypass | REST explicit approval guard tested. Direct comment insertion has post-insert visibility gap; later filters/direct SQL can bypass policies. |

WPCS checks pass with documented PSR-4/modern PHP conventions. Plugin Check and production acceptance statuses are recorded in RELEASE.md. No claim that all high/critical vulnerabilities have been ruled out is made.
