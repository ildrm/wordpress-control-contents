<?php
declare(strict_types=1);
namespace UWCMP\Application\Services;

use UWCMP\Domain\Detection\DetectionResult;
use UWCMP\Domain\Detection\Evidence;
use UWCMP\Domain\Detection\NormalizedContent;
use UWCMP\Domain\Detection\Term;

final class TermDetector {
	private MultiPatternMatcher $matcher;
	private array $exceptions      = array();
	private array $patterns        = array();
	private array $scoped_matchers = array();
	private bool $has_scopes       = false;
	/** @param list<Term> $terms */
	public function __construct( private readonly array $terms, Normalizer $normalizer ) {
		if ( count( $terms ) > 100000 ) {
			throw new \InvalidArgumentException( 'Dictionary exceeds 100,000 terms.' );
		}
		$patterns        = array();
		$ids             = array();
		$pattern_bytes   = 0;
		$exception_bytes = 0;
		foreach ( $terms as $i => $term ) {
			if ( ! $term instanceof Term ) {
				throw new \InvalidArgumentException( 'Invalid dictionary term type.' );
			}
			$this->has_scopes = $this->has_scopes || $term->fields !== array() || $term->content_types !== array();
			if ( isset( $ids[ $term->id ] ) ) {
				throw new \InvalidArgumentException( 'Duplicate term ID.' );
			}
			$ids[ $term->id ] = true;
			$patterns[ $i ]   = $normalizer->normalize( $term->text, false )->canonical;
			$pattern_bytes   += strlen( $patterns[ $i ] );
			if ( $pattern_bytes > 2097152 ) {
				throw new \InvalidArgumentException( 'Normalized dictionary exceeds 2 MiB.' );
			}
			if ( $patterns[ $i ] === '' ) {
				throw new \InvalidArgumentException( 'Term normalizes to empty text.' );
			}
			$this->exceptions[ $i ] = array_map( static fn( string $exception ): string => $normalizer->normalize( $exception, false )->canonical, $term->exceptions );
			foreach ( $this->exceptions[ $i ] as $exception ) {
				$exception_bytes += strlen( $exception );
				if ( $exception === '' || $exception_bytes > 2097152 ) {
					throw new \InvalidArgumentException( 'Invalid or oversized normalized exceptions.' );
				}
			}
		}
		$this->matcher  = new MultiPatternMatcher( $patterns );
		$this->patterns = $patterns;
	}
	public function detect( NormalizedContent $content, string $field, string $content_type ): DetectionResult {
		$evidence   = array();
		$seen       = array();
		$candidates = 0;
		$channels   = array( 'canonical' => $content->canonical );
		if ( $content->canonical !== $content->evasion ) {
			$channels['evasion'] = $content->evasion;
		}
		if ( $content->evasion_double !== null && ! in_array( $content->evasion_double, $channels, true ) ) {
			$channels['evasion_double'] = $content->evasion_double;
		}
		$matcher = $this->scoped_matcher( $field, $content_type );
		foreach ( $channels as $representation => $text ) {
			foreach ( $matcher->matches( $text ) as [ $id, $offset, $length ] ) {
				if ( ++$candidates > 4096 || count( $evidence ) >= 128 ) {
					return new DetectionResult( $evidence, false );
				}
				$term = $this->terms[ $id ];
				if ( isset( $seen[ $id ] ) || ( $term->fields !== array() && ! in_array( $field, $term->fields, true ) ) || ( $term->content_types !== array() && ! in_array( $content_type, $term->content_types, true ) ) ) {
					continue;
				}
				$left    = $this->boundary( $text, $offset, true );
				$end     = $offset + $length;
				$right   = $this->boundary( $text, $end, false );
				$matches = match ( $term->mode ) {
					'word' => $left && $right,
					'prefix' => $left,
					'suffix' => $right,
					'exact' => $offset === 0 && $end === strlen( $text ),
					default => true,
				};
				[ $original_offset, $original_length ] = $representation === 'canonical' ? array( $offset, $length ) : $content->canonical_span( $representation, $offset, $length );
				if ( ! $matches || $this->excepted( $content->canonical, $id, $original_offset, $original_length ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$evidence[]  = new Evidence( $term->id, $term->category, $field, $term->severity, $representation === 'canonical' ? 1.0 : 0.7, $representation, $offset, $length );
			}
		}
		return new DetectionResult( $evidence );
	}
	private function scoped_matcher( string $field, string $type ): MultiPatternMatcher {
		if ( ! $this->has_scopes ) {
			return $this->matcher;
		}
		$key = $field . ':' . $type;
		if ( ! isset( $this->scoped_matchers[ $key ] ) ) {
			$patterns = array();
			foreach ( $this->terms as $id => $term ) {
				if ( ( $term->fields === array() || in_array( $field, $term->fields, true ) ) && ( $term->content_types === array() || in_array( $type, $term->content_types, true ) ) ) {
					$patterns[ $id ] = $this->patterns[ $id ];
				}
			}
			if ( count( $this->scoped_matchers ) >= 2 ) {
				$this->scoped_matchers = array();
			}
			$this->scoped_matchers[ $key ] = new MultiPatternMatcher( $patterns );
		}
		return $this->scoped_matchers[ $key ];
	}
	private function excepted( string $text, int $id, int $offset, int $length ): bool {
		foreach ( $this->exceptions[ $id ] as $phrase ) {
			if ( $phrase === '' ) {
				continue;
			}
			$from     = max( 0, $offset - strlen( $phrase ) );
			$window   = substr( $text, $from, strlen( $phrase ) * 2 + $length );
			$position = strpos( $window, $phrase );
			$position = $position === false ? false : $position + $from;
			while ( $position !== false && $position <= $offset ) {
				$from  = $position + 1;
				$left  = $this->boundary( $text, $position, true );
				$right = $this->boundary( $text, $position + strlen( $phrase ), false );
				if ( $position + strlen( $phrase ) >= $offset + $length && $left && $right ) {
					return true;
				}
				$next     = strpos( $window, $phrase, $from - max( 0, $offset - strlen( $phrase ) ) );
				$position = $next === false ? false : $next + max( 0, $offset - strlen( $phrase ) );
			}
		}
		return false;
	}
	private function boundary( string $text, int $offset, bool $left ): bool {
		if ( ( $left && $offset === 0 ) || ( ! $left && $offset === strlen( $text ) ) ) {
			return true;
		}
		if ( $left ) {
			$start = $offset - 1;
			while ( $start > 0 && ( ord( $text[ $start ] ) & 0xC0 ) === 0x80 ) {
				--$start;
			}
			$character = substr( $text, $start, $offset - $start );
		} else {
			$character = mb_strcut( $text, $offset, 4, 'UTF-8' );
		}
		$word = preg_match( '/^[\p{L}\p{M}\p{N}_]/u', $character );
		if ( $word === false ) {
			throw new \RuntimeException( 'Boundary analysis failed.' );
		}
		return $word === 0;
	}
}
