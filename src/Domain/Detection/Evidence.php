<?php
declare(strict_types=1);
namespace UWCMP\Domain\Detection;

final readonly class Evidence implements \JsonSerializable {
	public function __construct( public string $term_id, public string $category, public string $field, public int $severity, public float $confidence, public string $representation, public int $byte_offset, public int $byte_length ) {}
	public function jsonSerialize(): array {
		return get_object_vars( $this );
	}
}
