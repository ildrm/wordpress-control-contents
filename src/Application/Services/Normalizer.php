<?php
declare(strict_types=1);
namespace UWCMP\Application\Services;

use UWCMP\Domain\Detection\NormalizedContent;

final class Normalizer {
	public function normalize( string $text, bool $html = true ): NormalizedContent {
		if ( strlen( $text ) > 262144 || ! mb_check_encoding( $text, 'UTF-8' ) ) {
			throw new \InvalidArgumentException( 'Invalid UTF-8 or oversized input.' );
		}
		// Parse before decoding entities: escaped markup remains visible literal text.
		if ( $html && str_contains( $text, '<' ) ) {
			[ $text, $links ] = ( new HtmlText() )->extract( $text );
		} else {
			$text  = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$links = HtmlText::count_urls( $text );
		}
		$text = \Normalizer::normalize( $text, \Normalizer::FORM_KC );
		if ( $text === false ) {
			throw new \InvalidArgumentException( 'Unicode normalization failed.' );
		}
		$text   = mb_convert_case( $text, MB_CASE_FOLD, 'UTF-8' );
		$text   = strtr(
			$text,
			array(
				'ي'        => 'ی',
				'ى'        => 'ی',
				'ك'        => 'ک',
				'٠'        => '0',
				'١'        => '1',
				'٢'        => '2',
				'٣'        => '3',
				'٤'        => '4',
				'٥'        => '5',
				'٦'        => '6',
				'٧'        => '7',
				'٨'        => '8',
				'٩'        => '9',
				'۰'        => '0',
				'۱'        => '1',
				'۲'        => '2',
				'۳'        => '3',
				'۴'        => '4',
				'۵'        => '5',
				'۶'        => '6',
				'۷'        => '7',
				'۸'        => '8',
				'۹'        => '9',
				"\u{200C}" => ' ',
				"\u{200B}" => ' ',
			)
		);
		$text   = self::replace( '/[\x{0640}\x{064B}-\x{065F}\x{0670}\p{Cf}]/u', '', $text );
		$text   = trim( self::replace( '/[\p{Z}\s]+/u', ' ', $text ) );
		$script = preg_match( '/\p{Arabic}/u', $text ) ? ( preg_match( '/\p{Latin}/u', $text ) ? 'mixed' : 'arabic' ) : ( preg_match( '/\p{Latin}/u', $text ) ? 'latin' : 'other' );
		// Lossy representations are evidence for review, never high-confidence hard blocking.
		$translations = array(
			'а' => 'a',
			'е' => 'e',
			'о' => 'o',
			'р' => 'p',
			'с' => 'c',
			'х' => 'x',
			'і' => 'i',
			'α' => 'a',
			'ο' => 'o',
			'@' => 'a',
			'4' => 'a',
			'0' => 'o',
			'3' => 'e',
			'1' => 'i',
			'$' => 's',
		);
		$maps         = array();
		$base         = $this->transform( $text, '/[аеорсхіαο@4031$]/u', static fn( string $matched ): string => strtr( $matched, $translations ), $maps );
		$base         = $this->transform( $base, '/(?<![\p{L}\p{M}\p{N}])(?:\p{L}[\s._-]){3,}\p{L}(?![\p{L}\p{M}\p{N}])/u', static fn( string $matched ): string => self::replace( '/[\s._-]/u', '', $matched ), $maps );
		$double_maps  = $maps;
		$evasion      = $this->transform( $base, '/(\p{L})\1{2,}/u', static fn( string $matched ): string => mb_substr( $matched, 0, 1, 'UTF-8' ), $maps );
		$double       = $this->transform( $base, '/(\p{L})\1{2,}/u', static fn( string $matched ): string => str_repeat( mb_substr( $matched, 0, 1, 'UTF-8' ), 2 ), $double_maps );
		return new NormalizedContent( $text, $evasion, $script, $maps, $double, $double_maps, $links );
	}
	private static function replace( string $pattern, string $replacement, string $text ): string {
		$result = preg_replace( $pattern, $replacement, $text );
		if ( $result === null ) {
			throw new \RuntimeException( 'Text normalization failed.' );
		}
		return $result;
	}
	/** Keep sparse byte-span edits so exceptions can be checked against canonical text. */
	private function transform( string $text, string $pattern, callable $replace, array &$maps ): string {
		$edits  = array();
		$delta  = 0;
		$result = preg_replace_callback(
			$pattern,
			function ( array $capture ) use ( $replace, &$edits, &$delta ): string {
				return $this->capture_edit( $capture, $replace, $edits, $delta ); },
			$text,
			-1,
			$count,
			PREG_OFFSET_CAPTURE
		);
		if ( $result === null ) {
			throw new \RuntimeException( 'Evasion normalization failed.' );
		}
		if ( $edits !== array() ) {
			$maps[] = $edits;
		}
		return $result;
	}
	private function capture_edit( array $capture, callable $replace, array &$edits, int &$delta ): string {
		if ( ! isset( $capture[0] ) || ! is_array( $capture[0] ) || ! is_string( $capture[0][0] ) || ! is_int( $capture[0][1] ) ) {
			throw new \RuntimeException( 'Invalid normalization span.' );
		}
		[ $matched, $offset ] = $capture[0];
		$replacement          = $replace( $matched );
		if ( count( $edits ) >= 4096 ) {
			throw new \RuntimeException( 'Normalization edit limit reached.' );
		}
		$edits[] = array( $offset + $delta, strlen( $replacement ), $offset, strlen( $matched ) );
		$delta  += strlen( $replacement ) - strlen( $matched );
		return $replacement;
	}
}
