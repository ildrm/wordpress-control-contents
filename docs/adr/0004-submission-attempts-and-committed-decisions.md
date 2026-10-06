# ADR 0004: distinguish attempts from committed moderation decisions

Status: accepted for the development preview, 2026-10-06.

Moderation filters run before WordPress persists content and can run repeatedly for the same submission. An analysis result alone cannot establish that a content object exists, its final ID, or its stored status.

Use a typed AuditEvent and an application ModerationCoordinator depending on PolicyProvider/AuditJournal contracts. Prepare writes an `attempt` without a claimed committed object ID. WordPress acknowledgement hooks complete that same UUID as a `decision`, binding the final object ID and observed status. Repeated completion is accepted only for the same binding. Completion cannot restore an erased actor's references.

Standard comment callbacks are correlated with a temporary server-generated UUID carried through comment metadata and removed after synchronous completion. Separate identical submissions get separate UUIDs. Post operations are correlated in request memory, including inserts whose optional later hooks are disabled. Failed saves remain attempts. Valid submissions whose analysis fails receive an incomplete review result; audit write failures remain observable failures, with active-mode holds.

No schema change is needed: the existing event key, kind, object ID and metadata support this distinction. Earlier ambiguous records are retained unchanged because their final object identity cannot be inferred safely. This approach establishes the tested synchronous paths; it does not provide durable crash recovery, distributed exactly-once processing or protection against every later third-party hook.
