<?php
declare(strict_types=1);
namespace UWCMP\Tests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UWCMP\Application\Services\MultiPatternMatcher;
use UWCMP\Application\Services\Normalizer;
use UWCMP\Application\Services\TermDetector;
use UWCMP\Domain\Detection\Term;

final class DetectionTest extends TestCase {
	public static function normalization_cases(): array {
		return [
			[ '  BADWORD &amp; hello ', 'badword & hello' ],
			[ '<p>Hello</p><p>world</p>', 'hello world' ],
			[ '<script>badword</script>safe', 'safe' ],
			[ 'ＢＡＤＷＯＲＤ', 'badword' ],
			[ 'كِتاب يكي ١۲۳', 'کتاب یکی 123' ],
			[ "می\u{200C}روم", 'می روم' ],
			[ "hello\u{200B}world", 'hello world' ],
			[ "b\u{200D}adword", 'badword' ],
			[ "\u{202E}كــتاب\u{202C}", 'کتاب' ],
			[ 'ﻛﺘﺎﺏ', 'کتاب' ],
			[ "Cafe\u{0301}", 'café' ],
			[ 'Straße', 'strasse' ],
		];
	}
	#[DataProvider( 'normalization_cases' )]
	public function test_normalization_and_idempotence( string $input, string $expected ): void {
		$normalizer = new Normalizer();
		self::assertSame( $expected, $normalizer->normalize( $input )->canonical );
		self::assertSame( $expected, $normalizer->normalize( $expected )->canonical );
	}
	public static function matching_cases(): array {
		return [
			[ 'badword', true, 1.0 ], [ 'BADWORD!', true, 1.0 ],
			[ 'notbadword', false, 0.0 ], [ 'badwords', false, 0.0 ],
			[ 'badword_suffix', false, 0.0 ], [ 'ébadword', false, 0.0 ],
			[ 'badwordی', false, 0.0 ], [ 'سلام badword دنیا', true, 1.0 ],
			[ 'b a d w o r d', true, 0.7 ], [ 'b.a.d.w.o.r.d', true, 0.7 ],
			[ 'b-a-d-w-o-r-d', true, 0.7 ], [ 'b@dw0rd', true, 0.7 ],
			[ 'b4dword', true, 0.7 ], [ 'baaaaadword', true, 0.7 ],
			[ 'bаdword', true, 0.7 ], [ 'ＢＡＤＷＯＲＤ', true, 1.0 ],
			[ 'safe day', false, 0.0 ], [ 'badword&#33;', true, 1.0 ],
		];
	}
	#[DataProvider( 'matching_cases' )]
	public function test_matching_boundaries_and_evasion( string $input, bool $match, float $confidence ): void {
		$n = new Normalizer();
		$d = new TermDetector( [ new Term( 'test', 'badword' ) ], $n );
		$r = $d->detect( $n->normalize( $input ), 'body', 'comment' );
		self::assertSame( $match, $r->evidence !== [] );
		self::assertSame( $confidence, $r->confidence() );
		self::assertTrue( $r->complete );
	}
	public function test_persian_boundaries(): void {
		$n = new Normalizer();
		$d = new TermDetector( [ new Term( 'persian', 'کتاب' ) ], $n );
		self::assertCount( 1, $d->detect( $n->normalize( 'كِتاب' ), 'body', 'comment' )->evidence );
		self::assertCount( 0, $d->detect( $n->normalize( 'کتابخانه' ), 'body', 'comment' )->evidence );
	}
	public function test_phrase_exceptions_are_local_and_rule_specific(): void {
		$n = new Normalizer();
		$d = new TermDetector( [ new Term( 'one', 'badword', exceptions: [ 'quoted badword' ] ), new Term( 'two', 'scam' ) ], $n );
		self::assertCount( 0, $d->detect( $n->normalize( 'quoted badword' ), 'body', 'comment' )->evidence );
		self::assertCount( 2, $d->detect( $n->normalize( 'quoted badword; badword scam' ), 'body', 'comment' )->evidence );
		self::assertCount( 1, $d->detect( $n->normalize( 'unquoted badword' ), 'body', 'comment' )->evidence );
	}
	public function test_scope_and_modes(): void {
		$n = new Normalizer();
		foreach ( [ 'exact' => [ 'bad', 'bad!' ], 'prefix' => [ 'badly', 'notbad' ], 'suffix' => [ 'notbad', 'badly' ], 'partial' => [ 'notbadly', 'good' ] ] as $mode => [ $positive, $negative ] ) {
			$d = new TermDetector( [ new Term( 'one', 'bad', mode: $mode, fields: [ 'title' ], content_types: [ 'post' ] ) ], $n );
			self::assertCount( 1, $d->detect( $n->normalize( $positive ), 'title', 'post' )->evidence );
			self::assertCount( 0, $d->detect( $n->normalize( $negative ), 'title', 'post' )->evidence );
			self::assertCount( 0, $d->detect( $n->normalize( $positive ), 'body', 'post' )->evidence );
			self::assertCount( 0, $d->detect( $n->normalize( $positive ), 'title', 'comment' )->evidence );
		}
	}
	public function test_suffix_links_and_overlapping_patterns(): void {
		$m = new MultiPatternMatcher( [ 'he', 'she', 'hers', 'his' ] );
		self::assertSame( [ [ 1, 1, 3 ], [ 0, 2, 2 ], [ 2, 2, 4 ] ], iterator_to_array( $m->matches( 'ushers' ), false ) );
	}
	public function test_matcher_matches_naive_reference_on_seeded_inputs(): void {
		mt_srand( 173 );
		for ( $run = 0; $run < 300; ++$run ) {
			$terms = [];
			for ( $t = 0; $t < 12; ++$t ) {
				$terms[] = $this->random_word( mt_rand( 1, 6 ) );
			}
			$text = $this->random_word( 80 );
			$expected = [];
			foreach ( $terms as $id => $term ) {
				$offset = 0;
				while ( ( $p = strpos( $text, $term, $offset ) ) !== false ) {
					$expected[] = [ $id, $p, strlen( $term ) ];
					$offset = $p + 1;
				}
			}
			$actual = iterator_to_array( ( new MultiPatternMatcher( $terms ) )->matches( $text ), false );
			sort( $actual );
			sort( $expected );
			self::assertSame( $expected, $actual );
		}
	}
	private function random_word( int $length ): string {
		$result = '';
		$alphabet = [ 'a', 'b', 'c', 'ی', 'ک' ];
		for ( $i = 0; $i < $length; ++$i ) {
			$result .= $alphabet[ mt_rand( 0, 4 ) ];
		}
		return $result;
	}
	public function test_output_explosion_is_incomplete(): void {
		$n = new Normalizer();
		$d = new TermDetector( [ new Term( 'one', 'a', mode: 'partial' ) ], $n );
		self::assertFalse( $d->detect( $n->normalize( str_repeat( 'a', 5000 ) ), 'body', 'comment' )->complete );
	}
	public function test_malformed_utf8_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new Normalizer() )->normalize( "\xC3\x28" );
	}
	public function test_oversized_content_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new Normalizer() )->normalize( str_repeat( 'a', 262145 ) );
	}
	public function test_html_entities_remain_visible_literal_text(): void {
		$n = new Normalizer();
		$d = new TermDetector( [ new Term( 'one', 'badword' ) ], $n );
		self::assertCount( 1, $d->detect( $n->normalize( '&lt;script&gt;badword&lt;/script&gt;' ), 'body', 'comment' )->evidence );
		self::assertSame( '<script>badword</script>', $n->normalize( '<script>badword</script>', false )->canonical );
		self::assertSame( '', $n->normalize( '<script>badword</script>', true )->canonical );
	}
	public function test_compiled_state_budget_is_enforced(): void {
		$this->expectException( \InvalidArgumentException::class );
		new MultiPatternMatcher( [ 'hello' ], 3 );
	}
	public function test_dictionary_byte_budget_is_enforced(): void {
		$terms = [];
		for ( $i = 0; $i < 8200; ++$i ) {
			$terms[] = new Term( 't-' . $i, str_repeat( 'x', 256 ) );
		}
		$this->expectException( \InvalidArgumentException::class );
		new TermDetector( $terms, new Normalizer() );
	}
}
