<?php
declare(strict_types=1);
namespace UWCMP\Application\Contracts;

use UWCMP\Domain\Moderation\ModerationRequest;
use UWCMP\Domain\Moderation\ModerationResult;
use UWCMP\Domain\Reporting\AuditEvent;

interface AuditJournal {
	public function begin( ModerationRequest $request, ModerationResult $result ): AuditEvent;
	public function commit( AuditEvent $event, string $object_id, string $status ): void;
}
