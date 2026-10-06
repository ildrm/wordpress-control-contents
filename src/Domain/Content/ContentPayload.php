<?php
declare(strict_types=1);
namespace UWCMP\Domain\Content;

final readonly class ContentPayload {
	/** @var list<ContentField> */
	public array $fields;
	/** @param array<mixed> $fields Untrusted fields validated before constructing the payload. */
	public function __construct( array $fields ) {
		if ( ! array_is_list( $fields ) || count( $fields ) < 1 || count( $fields ) > 32 ) {
			throw new \InvalidArgumentException( 'Provide 1–32 content fields.' );
		}
		$names = array();
		$bytes = 0;
		foreach ( $fields as $field ) {
			if ( ! $field instanceof ContentField ) {
				throw new \InvalidArgumentException( 'Invalid content field type.' );
			}
			if ( isset( $names[ $field->name ] ) ) {
				throw new \InvalidArgumentException( 'Duplicate field name.' );
			}
			$names[ $field->name ] = true;
			$bytes                += strlen( $field->text );
		}
		if ( $bytes > 524288 ) {
			throw new \InvalidArgumentException( 'Payload exceeds 512 KiB.' );
		}
		$this->fields = $fields;
	}
}
