<?php
declare(strict_types=1);
namespace UWCMP\Domain\Policy;

/** Validated, bounded condition tree. Missing signals never satisfy predicates, including NOT. */
final readonly class Condition implements \JsonSerializable {
	private const FIELDS = array( 'content.type', 'source', 'site.id', 'actor.id', 'actor.account_age', 'actor.reputation', 'actor.roles', 'content.length', 'links.count', 'detection.count', 'detection.score', 'detection.confidence', 'detection.categories', 'detection.fields', 'detection.terms' );
	private function __construct( private array $expression ) {}
	public static function from_array( array $expression ): self {
		$nodes = 0;
		self::validate( $expression, 0, $nodes );
		return new self( $expression );
	}
	private static function validate( array $expression, int $depth, int &$nodes ): void {
		if ( $depth > 8 || ++$nodes > 128 ) {
			throw new \InvalidArgumentException( 'Condition nesting or node limit exceeded.' );
		}
		if ( isset( $expression['group'] ) ) {
			if ( array_diff( array_keys( $expression ), array( 'group', 'children' ) ) !== array() || ! in_array( $expression['group'], array( 'and', 'or', 'not' ), true ) || ! isset( $expression['children'] ) || ! is_array( $expression['children'] ) || ! array_is_list( $expression['children'] ) || count( $expression['children'] ) < 1 || ( $expression['group'] === 'not' && count( $expression['children'] ) !== 1 ) ) {
				throw new \InvalidArgumentException( 'Invalid condition group.' );
			}
			foreach ( $expression['children'] as $child ) {
				if ( ! is_array( $child ) ) {
					throw new \InvalidArgumentException( 'Condition child must be an object.' );
				}
				self::validate( $child, $depth + 1, $nodes );
			}
			return;
		}
		if ( count( $expression ) !== 3 || ! isset( $expression['field'], $expression['op'] ) || ! array_key_exists( 'value', $expression ) || ! in_array( $expression['field'], self::FIELDS, true ) || ! in_array( $expression['op'], array( 'eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'contains' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid predicate.' );
		}
		$field     = $expression['field'];
		$is_list   = in_array( $field, array( 'actor.roles', 'detection.categories', 'detection.fields', 'detection.terms' ), true );
		$is_string = in_array( $field, array( 'content.type', 'source' ), true );
		$value     = $expression['value'];
		if ( $is_list || $is_string ) {
			if ( ! is_string( $value ) || strlen( $value ) > 128 || ! mb_check_encoding( $value, 'UTF-8' ) || ( $is_list && $expression['op'] !== 'contains' ) || ( $is_string && ! in_array( $expression['op'], array( 'eq', 'ne' ), true ) ) ) {
				throw new \InvalidArgumentException( 'Invalid string predicate.' );
			}
		} elseif ( ( ! is_int( $value ) && ! is_float( $value ) ) || ! is_finite( (float) $value ) || $expression['op'] === 'contains' ) {
			throw new \InvalidArgumentException( 'Invalid numeric predicate.' );
		}
	}
	public function evaluate( array $facts ): bool {
		return $this->evaluate_node( $this->expression, $facts ) === true;
	}
	private function evaluate_node( array $node, array $facts ): ?bool {
		if ( isset( $node['group'] ) ) {
			$values = array_map( fn( array $child ): ?bool => $this->evaluate_node( $child, $facts ), $node['children'] );
			return match ( $node['group'] ) {
				'not' => $values[0] === null ? null : ! $values[0],
				'and' => in_array( false, $values, true ) ? false : ( in_array( null, $values, true ) ? null : true ),
				'or' => in_array( true, $values, true ) ? true : ( in_array( null, $values, true ) ? null : false ),
				default => throw new \LogicException( 'Unvalidated group.' ),
			};
		}
		$actual = $facts[ $node['field'] ] ?? null;
		if ( $actual === null ) {
			return null;
		}
		$value = $node['value'];
		if ( is_numeric( $actual ) && ! is_string( $actual ) && ( is_int( $value ) || is_float( $value ) ) ) {
			$actual = (float) $actual;
			$value  = (float) $value;
		}
		return match ( $node['op'] ) {
			'eq' => $actual === $value, 'ne' => $actual !== $value,
			'gt' => $actual > $value, 'gte' => $actual >= $value,
			'lt' => $actual < $value, 'lte' => $actual <= $value,
			'contains' => is_array( $actual ) && in_array( $value, $actual, true ),
			default => throw new \LogicException( 'Unvalidated predicate.' ),
		};
	}
	public function jsonSerialize(): array {
		return $this->expression;
	}
}
