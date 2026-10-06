<?php
declare(strict_types=1);
namespace UWCMP\Domain\Moderation;

use UWCMP\Domain\Content\ContentPayload;
use UWCMP\Domain\Content\ModerationContext;

final readonly class ModerationRequest {
	public function __construct( public ContentPayload $content, public ModerationContext $context ) {}
}
