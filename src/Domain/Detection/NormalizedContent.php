<?php
declare(strict_types=1);
namespace UWCMP\Domain\Detection;

final readonly class NormalizedContent {
	public function __construct( public string $canonical, public string $evasion, public string $script, private array $evasion_maps = array(), public ?string $evasion_double = null, private array $double_maps = array(), public int $link_count = 0 ) {}
	/** @return array{int,int} Canonical byte offset and length for an evidence span. */
	public function canonical_span( string $representation, int $offset, int $length ): array {
		$maps = $representation === 'evasion_double' ? $this->double_maps : $this->evasion_maps;
		$end  = $offset + $length;
		foreach ( array_reverse( $maps ) as $edits ) {
			$offset = $this->boundary( $offset, $edits, false );
			$end    = $this->boundary( $end, $edits, true );
		}
		return array( $offset, $end - $offset );
	}
	private function boundary( int $position, array $edits, bool $end ): int {
		$low      = 0;
		$high     = count( $edits ) - 1;
		$selected = -1;
		while ( $low <= $high ) {
			$middle = intdiv( $low + $high, 2 );
			if ( $edits[ $middle ][0] <= $position ) {
				$selected = $middle;
				$low      = $middle + 1;
			} else {
				$high = $middle - 1;
			}
		}
		if ( $selected < 0 ) {
			return $position;
		}
		[ $target, $target_length, $source, $source_length ] = $edits[ $selected ];
		if ( $position === $target ) {
			return $source;
		}
		if ( $position < $target + $target_length ) {
			return $end ? $source + $source_length : $source;
		}
		return $position + $source + $source_length - $target - $target_length;
	}
}
