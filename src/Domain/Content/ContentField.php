<?php
declare(strict_types=1);
namespace UWCMP\Domain\Content;

final readonly class ContentField {
	public function __construct( public string $name, public string $text, public string $format = 'text' ) {
		if ( ! in_array( $format, array( 'text', 'html' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid content format.' );
		}
		if ( ! preg_match( '/^[a-z][a-z0-9_.-]{0,63}$/D', $name ) ) {
			throw new \InvalidArgumentException( 'Invalid field name.' );
		}
		if ( strlen( $text ) > 262144 || ! mb_check_encoding( $text, 'UTF-8' ) ) {
			throw new \InvalidArgumentException( 'Content must be valid UTF-8 and at most 256 KiB per field.' );
		}
	}
}
