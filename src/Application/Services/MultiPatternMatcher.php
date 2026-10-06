<?php
declare(strict_types=1);
namespace UWCMP\Application\Services;

/** Sparse Aho–Corasick automaton; failure output links avoid quadratic output copying. */
final class MultiPatternMatcher {
	private array $next   = array( array() );
	private array $fail   = array( 0 );
	private array $output = array( array() );
	private array $suffix = array( 0 );
	/** @param array<int,string> $patterns */
	public function __construct( array $patterns, int $state_limit = 200000 ) {
		if ( $state_limit < 1 || $state_limit > 200000 ) {
			throw new \InvalidArgumentException( 'Invalid compiled dictionary state limit.' );
		}
		$limit       = trim( (string) ini_get( 'memory_limit' ) );
		$limit_bytes = null;
		if ( preg_match( '/^([0-9]+)([KMG]?)$/iD', $limit, $units ) ) {
			$limit_bytes = (float) $units[1] * match ( strtoupper( $units[2] ) ) {
				'K' => 1024, 'M' => 1048576, 'G' => 1073741824, default => 1,
			};
		}
		foreach ( $patterns as $id => $pattern ) {
			if ( $pattern === '' ) {
				throw new \InvalidArgumentException( 'Empty matcher pattern.' );
			}
			$state = 0;
			for ( $i = 0, $length = strlen( $pattern ); $i < $length; ++$i ) {
				$byte = ord( $pattern[ $i ] );
				if ( ! isset( $this->next[ $state ][ $byte ] ) ) {
					$new = count( $this->next );
					if ( $new >= $state_limit ) {
						throw new \InvalidArgumentException( 'Compiled dictionary exceeds the state safety limit.' );
					}
					if ( $new % 1024 === 0 && $limit_bytes !== null && memory_get_usage( true ) + 8388608 >= $limit_bytes ) {
						throw new \RuntimeException( 'Insufficient memory to compile dictionary safely.' );
					}
					$this->next[ $state ][ $byte ] = $new;
					$this->next[]                  = array();
					$this->fail[]                  = 0;
					$this->output[]                = array();
					$this->suffix[]                = 0;
				}
				$state = $this->next[ $state ][ $byte ];
			}
			$this->output[ $state ][] = array( $id, strlen( $pattern ) );
		}
		$queue = array_values( $this->next[0] );
		for ( $head = 0; isset( $queue[ $head ] ); ++$head ) {
			$state = $queue[ $head ];
			foreach ( $this->next[ $state ] as $byte => $child ) {
				$queue[] = $child;
				$f       = $this->fail[ $state ];
				while ( $f !== 0 && ! isset( $this->next[ $f ][ $byte ] ) ) {
					$f = $this->fail[ $f ];
				}
				$f                      = $this->next[ $f ][ $byte ] ?? 0;
				$this->fail[ $child ]   = $f;
				$this->suffix[ $child ] = $this->output[ $f ] !== array() ? $f : $this->suffix[ $f ];
			}
		}
	}
	/** @return \Generator<int,array{int,int,int}> */
	public function matches( string $text ): \Generator {
		$state = 0;
		for ( $i = 0, $length = strlen( $text ); $i < $length; ++$i ) {
			$byte = ord( $text[ $i ] );
			while ( $state !== 0 && ! isset( $this->next[ $state ][ $byte ] ) ) {
				$state = $this->fail[ $state ];
			}
			$state = $this->next[ $state ][ $byte ] ?? 0;
			$node  = $state;
			do {
				foreach ( $this->output[ $node ] as [ $id, $size ] ) {
					yield array( $id, $i - $size + 1, $size );
				}
				$node = $this->suffix[ $node ];
			} while ( $node !== 0 );
		}
	}
}
