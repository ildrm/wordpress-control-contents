<?php
declare(strict_types=1);
namespace UWCMP\Domain\Reporting;

use UWCMP\Domain\Content\ModerationContext;
use UWCMP\Domain\Moderation\ModerationResult;

/** An identified attempt, containing signals but no submitted text. */
final readonly class AuditEvent {
	public function __construct( public string $key, public ModerationContext $context, public ModerationResult $result, public array $metadata ) {}
}
