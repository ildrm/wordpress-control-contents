# Risk register

| Risk | Consequence | Current mitigation / next step |
|---|---|---|
| R1: missing representative labels | Unknown semantic FPR/FNR | Review-only decisions; procure/annotate multilingual validation corpus before hard blocking. |
| R2: low-level comment post-insert gap | Brief visibility or downstream observer exposure | Standard submissions prechecked; REST explicit-status guard; redesign SDK interception/coverage before release. |
| R3: later hooks/direct DB writes | Policy bypass | Document limits; integration matrix and adapter contract enforcement. |
| R4: cold compilation/cache memory | Request overhead or memory exhaustion | Bounded admin format; versioned optional object cache; measure real workloads/caches. |
| R5: attempts/duplicate audit/object binding | Misleading timeline/analytics | Metadata only; Synchronous identity/binding/idempotent completion now tested; durable crash recovery and wider concurrent/reentrant coverage remain. |
| R6: migration races/incomplete verification | Schema marker drift | Advisory lock plus required columns/index order/uniqueness/InnoDB checks; detailed column definitions and upgrade recovery remain. |
| R7: incomplete high-volume retention | Privacy retention lag | Bounded cleanup; Bounded continuations drain tested backlogs; queue, scheduler-independent guarantees and configuration remain. |
| R8: missing operations/AI/media/integrations | Product requirements unfulfilled | Keep 165-section ledger; implement in master phase order. |
| R9: untested latest/minimum WP and integrations | Incompatibility | Current single stack explicitly recorded; broaden matrix before claims. |
| R10: partial a11y/RTL | Inaccessible workflows | Native controls/axe desktop and 320-pixel RTL smoke; human screen-reader/reflow/real locale audits. |
| R11: limited privacy subjects/data classes | Erasure/export gaps | Raw PII never audited; anonymous object-based erasure and full retention policy required. |
| R12: no complete observability/idempotent queue | Silent degraded work or duplicated processing | Failure reason hook; implement health/metrics/queue and failure workflows before production. |
