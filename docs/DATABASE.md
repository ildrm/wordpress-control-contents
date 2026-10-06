# Database

Schema version 1 creates per-site InnoDB tables using the trusted WordPress table prefix and dbDelta. No high-volume records are stored in options. The sole schema-version option is non-autoloaded. Activation may be retried after partial table creation; no migration drops data. Migration uses a database/site-scoped advisory lock and verifies required table names, column names, index column order/uniqueness and InnoDB engines before advancing the marker. Missing-index recovery and competing-connection exclusion are tested. Detailed column definitions, automatic upgrade dispatch and older-version rollback testing remain outstanding.

| Table | Key data | Indexes | Lifecycle |
|---|---|---|---|
| uwcmp_policy_versions | bigint ID; JSON payload; SHA-256 checksum; creator; UTC date | Primary ID | Immutable snapshots; no automatic pruning yet. Privacy erasure anonymizes creator. |
| uwcmp_policy_head | singleton ID=1; active_version | Primary ID | Atomic compare-and-swap selects active snapshot. |
| uwcmp_events | bigint ID; UUID event key; kind; object type/ID; actor; policy version; action; metadata; UTC date | Primary ID; unique event key; timeline; actor/time; retention; action/ID | Metadata-only, default 30-day retention, 1,000 rows per batch; at most five per callback plus scheduled continuations. |

Policy save runs snapshot insertion, head update and audit insertion in one transaction. A stale expected version rolls back the snapshot and audit; tests verify no orphan snapshots after a conflict. Active snapshots are checked against their checksums before parsing. MySQL/MariaDB InnoDB is required; SQLite is not a supported transaction/migration backend.

Pre-persistence analysis creates an attempt without a committed object ID. WordPress acknowledgement changes that UUID to a decision with final ID/status. Repeated completion is idempotent for the same binding. Failed insertions remain attempts; genuine identical submissions remain separate. Legacy ambiguous rows remain unchanged. Durable crash recovery and broader reentrant/concurrent coverage remain required; see ADR 0004.

Deactivation removes both retention schedules. Uninstall retains data/capabilities by default for recovery. Trusted single-site configuration UWCMP_DELETE_DATA_ON_UNINSTALL=true explicitly deletes owned tables, temporary comment correlation metadata, marker and capabilities. This is irreversible; backup and rollback procedures are required. Multisite deletion is refused. Install, schema re-entry, index recovery, deactivation, default retention and explicit single-site uninstall have been tested on disposable databases. Deletion errors preserve recovery markers/capabilities and stop uninstall. Failed deletion/retry has been tested. Older-version upgrade/rollback remains untested.
