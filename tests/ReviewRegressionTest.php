<?php
declare(strict_types=1);
namespace UWCMP\Tests;

use PHPUnit\Framework\TestCase;
use UWCMP\Application\Services\Normalizer;
use UWCMP\Application\Services\TermDetector;
use UWCMP\Domain\Content\ContentPayload;
use UWCMP\Domain\Detection\Term;

final class ReviewRegressionTest extends TestCase {
	public function test_inline_markup_and_comments_cannot_split_a_visible_word(): void {
		$n = new Normalizer();
		foreach ( array( 'b<strong>ad</strong>word', 'b<!-- hidden -->adword', '<span title="a > b">badword</span>' ) as $html ) {
			self::assertSame( 'badword', $n->normalize( $html )->canonical );
		}
		self::assertSame( 'bad word', $n->normalize( 'bad<br>word' )->canonical );
		self::assertSame( '1 < 2 badword > 0', $n->normalize( '1 < 2 badword > 0' )->canonical );
		self::assertSame( 'badword', $n->normalize( '<p>b&zwj;adword</p>' )->canonical );
		self::assertSame( '&lt;script&gt;badword&lt;/script&gt;', $n->normalize( '<p>&amp;lt;script&amp;gt;badword&amp;lt;/script&amp;gt;</p>' )->canonical );
	}
	public function test_excessive_html_depth_requires_review_instead_of_truncating_analysis(): void {
		$this->expectException( \RuntimeException::class );
		( new Normalizer() )->normalize( str_repeat( '<div>', 300 ) . 'badword' . str_repeat( '</div>', 300 ) );
	}
	public function test_exceptions_do_not_authorize_obfuscated_text(): void {
		$n = new Normalizer();
		$d = new TermDetector( array( new Term( 'one', 'badword', exceptions: array( 'quoted badword' ) ) ), $n );
		self::assertCount( 1, $d->detect( $n->normalize( 'quoted b@dw0rd' ), 'body', 'comment' )->evidence );
		self::assertCount( 0, $d->detect( $n->normalize( 'quoted badword and h3llo' ), 'body', 'comment' )->evidence );
		self::assertCount( 1, $d->detect( $n->normalize( 'quoted badword and quoted b@dw0rd' ), 'body', 'comment' )->evidence );
		self::assertCount( 1, $d->detect( $n->normalize( "\u{0301}quoted badword" ), 'body', 'comment' )->evidence );
		self::assertCount( 0, $d->detect( $n->normalize( 'ک。quoted badword and h3llo' ), 'body', 'comment' )->evidence );
	}
	public function test_html_link_count_counts_an_anchor_once_and_excludes_hidden_urls(): void {
		$n = new Normalizer();
		$r = $n->normalize( '<a href="https://example.test">https://example.test</a><script>https://hidden.test</script> https://plain.test' );
		self::assertSame( 2, $r->link_count );
	}
	public function test_regex_failure_is_not_reported_as_complete_analysis(): void {
		$previous = ini_get( 'pcre.backtrack_limit' );
		try {
			ini_set( 'pcre.backtrack_limit', '0' );
			$this->expectException( \RuntimeException::class );
			( new Normalizer() )->normalize( 'hello world', false );
		} finally {
			ini_set( 'pcre.backtrack_limit', $previous );
		}
	}
	public function test_normalization_edit_budget_prevents_unbounded_span_storage(): void {
		$this->expectException( \RuntimeException::class );
		( new Normalizer() )->normalize( str_repeat( '@ ', 4097 ), false );
	}
	public function test_irrelevant_scopes_do_not_exhaust_detection_limits(): void {
		$n = new Normalizer();
		$d = new TermDetector( array( new Term( 'one', 'a', mode: 'partial', content_types: array( 'post' ) ) ), $n );
		$r = $d->detect( $n->normalize( str_repeat( 'a ', 5000 ) ), 'body', 'comment' );
		self::assertTrue( $r->complete );
		self::assertCount( 0, $r->evidence );
	}
	public function test_repeated_letters_can_preserve_a_dictionary_double_letter(): void {
		$n = new Normalizer();
		$d = new TermDetector( array( new Term( 'one', 'book' ) ), $n );
		self::assertCount( 1, $d->detect( $n->normalize( 'boooook' ), 'body', 'comment' )->evidence );
		self::assertCount( 0, $d->detect( $n->normalize( 'bok' ), 'body', 'comment' )->evidence );
	}
	public function test_model_rejects_wrong_field_type_cleanly(): void {
		$this->expectException( \InvalidArgumentException::class );
		new ContentPayload( array( 'body' ) );
	}
}
