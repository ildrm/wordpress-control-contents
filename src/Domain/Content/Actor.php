<?php
declare(strict_types=1);
namespace UWCMP\Domain\Content;

final readonly class Actor {
	/** @param list<string> $roles */
	public function __construct( public int $id = 0, public array $roles = array(), public ?int $account_age_seconds = null, public ?float $reputation = null ) {
		if ( $id < 0 || ( $account_age_seconds !== null && $account_age_seconds < 0 ) || ( $reputation !== null && ( ! is_finite( $reputation ) || $reputation < 0 || $reputation > 100 ) ) ) {
			throw new \InvalidArgumentException( 'Invalid actor signals.' );
		}
		if ( ! array_is_list( $roles ) || count( $roles ) > 64 ) {
			throw new \InvalidArgumentException( 'Invalid role list.' );
		}
		foreach ( $roles as $role ) {
			if ( ! is_string( $role ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $role ) ) {
				throw new \InvalidArgumentException( 'Invalid role identifier.' );
			}
		}
	}
}
