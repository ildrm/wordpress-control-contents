<?php
declare(strict_types=1);
namespace UWCMP\Application\Contracts;

use UWCMP\Application\Services\Moderator;
use UWCMP\Domain\Policy\Policy;

interface PolicyProvider {
	public function active(): Policy;
	public function engine(): Moderator;
}
