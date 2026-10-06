<?php
declare(strict_types=1);
namespace UWCMP\Domain\Policy;

use UWCMP\Domain\Detection\Term;

final readonly class Policy {
	/** @var list<Rule> */
	public array $rules;
	/** @param list<Term> $terms @param list<Rule> $rules */
	public function __construct( public int $version, public array $terms, array $rules, public bool $shadow = true ) {
		if ( ! array_is_list( $terms ) || ! array_is_list( $rules ) || $version < 0 || count( $rules ) > 256 ) {
			throw new \InvalidArgumentException( 'Invalid policy version or size.' );
		}
		$ids = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof Term ) {
				throw new \InvalidArgumentException( 'Invalid policy term type.' );
			}
		}
		foreach ( $rules as $rule ) {
			if ( ! $rule instanceof Rule ) {
				throw new \InvalidArgumentException( 'Invalid policy rule type.' );
			}
			if ( isset( $ids[ $rule->id ] ) ) {
				throw new \InvalidArgumentException( 'Duplicate policy rule ID.' );
			}
			$ids[ $rule->id ] = true;
		}
		usort( $rules, static fn( Rule $a, Rule $b ): int => ( $b->priority <=> $a->priority ) ?: strcmp( $a->id, $b->id ) );
		$this->rules = $rules;
	}
}
