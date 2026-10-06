<?php
declare(strict_types=1);
namespace UWCMP\Application\Services;

/** Local fragment extraction. Inline elements preserve words; block elements preserve boundaries. */
final class HtmlText {
	private const BLOCKS = array( 'address', 'article', 'aside', 'blockquote', 'br', 'dd', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'td', 'th', 'tr', 'ul' );
	public function extract( string $html ): array {
		// Convert each source entity once. This supports HTML5 names without decoding escaped markup twice.
		$html = preg_replace_callback(
			'/&(?:[A-Za-z][A-Za-z0-9]+|#[0-9]+|#x[0-9a-f]+);/i',
			static function ( array $capture ): string {
				$decoded = html_entity_decode( $capture[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( $decoded === $capture[0] ) {
					return $decoded;
				}
				$encoded = '';
				foreach ( mb_str_split( $decoded, 1, 'UTF-8' ) as $character ) {
					$encoded .= '&#' . mb_ord( $character, 'UTF-8' ) . ';';
				}
				return $encoded;
			},
			$html
		);
		if ( $html === null ) {
			throw new \RuntimeException( 'HTML entity extraction failed.' );
		}
		$html = preg_replace( '/<(?![A-Za-z!\/?])/u', '&lt;', $html );
		if ( $html === null ) {
			throw new \RuntimeException( 'HTML extraction failed.' );
		}
		$document = new \DOMDocument();
		if ( ! $document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING ) ) {
			throw new \RuntimeException( 'HTML extraction failed.' );
		}
		$text          = array();
		$outside_links = array();
		$links         = 0;
		$this->walk( $document, $text, $outside_links, $links, false, 0 );
		return array( implode( '', $text ), $links + self::count_urls( implode( '', $outside_links ) ) );
	}
	private function walk( \DOMNode $node, array &$text, array &$outside_links, int &$links, bool $in_link, int $depth ): void {
		if ( $depth > 128 ) {
			throw new \RuntimeException( 'HTML nesting limit reached.' );
		}
		if ( $node instanceof \DOMText ) {
			$text[] = $node->data;
			if ( ! $in_link ) {
				$outside_links[] = $node->data;
			}
			return;
		}
		if ( $node instanceof \DOMElement ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			$name = strtolower( $node->tagName );
			if ( in_array( $name, array( 'script', 'style', 'head', 'template' ), true ) ) {
				return;
			}
			$block = in_array( $name, self::BLOCKS, true );
			if ( $block ) {
				$text[]          = ' ';
				$outside_links[] = ' ';
			}
			if ( $name === 'a' && preg_match( '~^https?://~i', trim( $node->getAttribute( 'href' ) ) ) === 1 ) {
				++$links;
				$in_link         = true;
				$outside_links[] = ' ';
			}
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		foreach ( $node->childNodes as $child ) {
			$this->walk( $child, $text, $outside_links, $links, $in_link, $depth + 1 );
		}
		if ( isset( $block ) && $block ) {
			$text[]          = ' ';
			$outside_links[] = ' ';
		}
	}
	public static function count_urls( string $text ): int {
		$count = preg_match_all( '~https?://[^\s<>]+~iu', $text );
		if ( $count === false ) {
			throw new \RuntimeException( 'URL analysis failed.' );
		}
		return $count;
	}
}
