# ADR 0003 — Transactional snapshots and minimized audit

Accepted for development preview, 2026-10-06.

Use per-site InnoDB custom tables for snapshots, active head and events. Insert a version, compare the expected active head and append the change audit in one transaction. Conflicts roll back rather than silently overwriting. Cache keys include prefix/version/checksum/engine format; each executing request uses a stable snapshot.

Audit stores bounded identifiers/signals without raw text, email/IP or snippets. This avoids collecting PII unnecessarily but limits moderator evidence review; secure optional excerpts and configurable retention require future implementation. Events currently describe attempts and may have empty object IDs or duplicates. Exactly-once events and final object binding remain release blockers, not implied guarantees.
