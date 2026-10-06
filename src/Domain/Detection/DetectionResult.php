<?php
declare(strict_types=1);
namespace UWCMP\Domain\Detection;

final readonly class DetectionResult {
	/** @param list<Evidence> $evidence */
	public function __construct( public array $evidence, public bool $complete = true ) {}
	public function confidence(): float {
		return $this->evidence === array() ? 0.0 : max( array_map( static fn( Evidence $e ): float => $e->confidence, $this->evidence ) );
	}
	public function score(): float {
		return $this->evidence === array() ? 0.0 : max( array_map( static fn( Evidence $e ): float => $e->severity * $e->confidence, $this->evidence ) );
	}
}
