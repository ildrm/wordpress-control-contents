<?php
declare(strict_types=1);
namespace UWCMP\Domain\Content;

final readonly class ModerationContext {
	public function __construct( public string $content_type, public string $source, public Actor $actor = new Actor(), public int $site_id = 1, public string $object_id = '' ) {
		foreach ( array( $content_type, $source ) as $value ) {
			if ( ! preg_match( '/^[a-z][a-z0-9_.-]{0,63}$/D', $value ) ) {
				throw new \InvalidArgumentException( 'Invalid context identifier.' );
			}
		}
		if ( $site_id < 1 || strlen( $object_id ) > 64 ) {
			throw new \InvalidArgumentException( 'Invalid object scope.' );
		}
	}
}
