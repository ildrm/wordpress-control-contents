<?php
declare(strict_types=1);
namespace UWCMP\Domain\Policy;

use UWCMP\Domain\Moderation\Action;

final readonly class Rule {
	public function __construct( public string $id, public Condition $condition, public Action $action, public int $priority = 0, public bool $enabled = true ) {
		if ( ! preg_match( '/^[a-zA-Z0-9_.-]{1,64}$/D', $id ) || $priority < -10000 || $priority > 10000 ) {
			throw new \InvalidArgumentException( 'Invalid rule ID or priority.' );
		}
	}
}
