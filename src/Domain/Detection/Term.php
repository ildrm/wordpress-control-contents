<?php
declare(strict_types=1);
namespace UWCMP\Domain\Detection;

final readonly class Term {
	/** @param list<string> $fields @param list<string> $content_types @param list<string> $exceptions */
	public function __construct( public string $id, public string $text, public string $category = 'custom', public int $severity = 50, public string $mode = 'word', public array $fields = array(), public array $content_types = array(), public array $exceptions = array() ) {
		if ( ! preg_match( '/^[a-zA-Z0-9_.-]{1,64}$/D', $id ) || strlen( $text ) > 256 || trim( $text ) === '' || ! mb_check_encoding( $text, 'UTF-8' ) || ! in_array( $mode, array( 'word', 'partial', 'exact', 'prefix', 'suffix' ), true ) || $severity < 0 || $severity > 100 || ! preg_match( '/^[a-z][a-z0-9_-]{0,63}$/D', $category ) ) {
			throw new \InvalidArgumentException( 'Invalid dictionary term.' );
		}
		foreach ( array( $fields, $content_types ) as $scopes ) {
			if ( ! array_is_list( $scopes ) || count( $scopes ) > 64 ) {
				throw new \InvalidArgumentException( 'Invalid term scopes.' );
			}
			foreach ( $scopes as $scope ) {
				if ( ! is_string( $scope ) || ! preg_match( '/^[a-z][a-z0-9_.-]{0,63}$/D', $scope ) ) {
					throw new \InvalidArgumentException( 'Invalid term scope identifier.' );
				}
			}
		}
		if ( ! array_is_list( $exceptions ) ) {
			throw new \InvalidArgumentException( 'Invalid exception list.' );
		}
		foreach ( $exceptions as $scope ) {
			if ( ! is_string( $scope ) || strlen( $scope ) > 256 || ! mb_check_encoding( $scope, 'UTF-8' ) ) {
				throw new \InvalidArgumentException( 'Invalid term scope or exception.' );
			}
		}
		if ( count( $exceptions ) > 32 ) {
			throw new \InvalidArgumentException( 'Too many term exceptions.' );
		}
	}
}
