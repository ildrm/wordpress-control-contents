<?php
declare(strict_types=1);
namespace UWCMP\Tests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UWCMP\Application\Services\Moderator;
use UWCMP\Application\Services\PolicyCodec;
use UWCMP\Domain\Content\Actor;
use UWCMP\Domain\Content\ContentField;
use UWCMP\Domain\Content\ContentPayload;
use UWCMP\Domain\Content\ModerationContext;
use UWCMP\Domain\Detection\Term;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Moderation\ModerationRequest;
use UWCMP\Domain\Policy\Condition;
use UWCMP\Domain\Policy\Policy;
use UWCMP\Domain\Policy\Rule;

final class PolicyTest extends TestCase {
	private function request( string $text, ?float $reputation = null ): ModerationRequest {
		return new ModerationRequest( new ContentPayload( [ new ContentField( 'body', $text ) ] ), new ModerationContext( 'comment', 'wordpress.comment', new Actor( reputation: $reputation ) ) );
	}
	public function test_nested_policy_and_unknown_signals(): void {
		$condition = Condition::from_array( [ 'group' => 'and', 'children' => [
			[ 'field' => 'content.type', 'op' => 'eq', 'value' => 'comment' ],
			[ 'group' => 'or', 'children' => [ [ 'field' => 'links.count', 'op' => 'gt', 'value' => 1 ], [ 'field' => 'actor.reputation', 'op' => 'lt', 'value' => 30 ] ] ],
		] ] );
		$m = new Moderator( new Policy( 3, [], [ new Rule( 'risk', $condition, Action::PENDING ) ], false ) );
		self::assertSame( Action::ALLOW, $m->check( $this->request( 'hello' ) )->action );
		self::assertSame( Action::PENDING, $m->check( $this->request( 'https://a.test https://b.test' ) )->action );
		self::assertSame( Action::PENDING, $m->check( $this->request( 'hello', 10 ) )->action );
	}
	public function test_unknown_under_not_does_not_match(): void {
		$c = Condition::from_array( [ 'group' => 'not', 'children' => [ [ 'field' => 'actor.reputation', 'op' => 'gt', 'value' => 90 ] ] ] );
		self::assertFalse( $c->evaluate( [] ) );
		self::assertTrue( $c->evaluate( [ 'actor.reputation' => 20 ] ) );
	}
	public function test_shadow_and_no_hard_rejection(): void {
		$c = Condition::from_array( [ 'field' => 'detection.count', 'op' => 'gt', 'value' => 0 ] );
		$r = new Rule( 'block', $c, Action::REJECT );
		foreach ( [ true, false ] as $shadow ) {
			$m = new Moderator( new Policy( 7, [ new Term( 'bad', 'badword' ) ], [ $r ], $shadow ) );
			$result = $m->check( $this->request( 'b@dw0rd' ) );
			self::assertSame( $shadow ? Action::ALLOW : Action::PENDING, $result->action );
			self::assertSame( Action::PENDING, $result->proposed_action );
			self::assertSame( 7, $result->policy_version );
			self::assertSame( 'block', $result->rule_id );
		}
	}
	public function test_priority_then_lexical_id_resolves_conflicts(): void {
		$c = Condition::from_array( [ 'field' => 'content.length', 'op' => 'gt', 'value' => 0 ] );
		$p = new Policy( 1, [], [ new Rule( 'z', $c, Action::PENDING, 10 ), new Rule( 'low', $c, Action::ALLOW, 0 ), new Rule( 'a', $c, Action::WARN, 10 ) ], false );
		self::assertSame( Action::WARN, ( new Moderator( $p ) )->check( $this->request( 'x' ) )->action );
	}
	public function test_disabled_rule_is_skipped(): void {
		$c = Condition::from_array( [ 'field' => 'content.length', 'op' => 'gt', 'value' => 0 ] );
		$p = new Policy( 1, [], [ new Rule( 'one', $c, Action::PENDING, enabled: false ) ], false );
		self::assertSame( Action::ALLOW, ( new Moderator( $p ) )->check( $this->request( 'x' ) )->action );
	}
	public function test_incomplete_analysis_requires_review_even_without_matching_policy(): void {
		$p = new Policy( 1, [ new Term( 'one', 'a', mode: 'partial' ) ], [], false );
		self::assertSame( Action::PENDING, ( new Moderator( $p ) )->check( $this->request( str_repeat( 'a', 5000 ) ) )->action );
	}
	public function test_codec_round_trip(): void {
		$c = new PolicyCodec();
		$p = new Policy( 0, [ new Term( 'one', 'كتاب', exceptions: [ 'quoted كتاب' ] ) ], [ new Rule( 'r', Condition::from_array( [ 'field' => 'detection.count', 'op' => 'gt', 'value' => 0 ] ), Action::PENDING ) ] );
		$decoded = $c->decode( $c->encode( $p ), 9 );
		self::assertSame( $c->encode( $p ), $c->encode( $decoded ) );
		self::assertSame( 9, $decoded->version );
	}
	public static function invalid_conditions(): array {
		return [
			[ [] ], [ [ 'group' => 'not', 'children' => [] ] ],
			[ [ 'field' => 'actor.reputation', 'op' => 'lt', 'value' => '30' ] ],
			[ [ 'field' => 'actor.id', 'op' => 'eq', 'value' => true ] ],
			[ [ 'field' => 'actor.roles', 'op' => 'eq', 'value' => 'admin' ] ],
			[ [ 'field' => 'unknown', 'op' => 'eq', 'value' => 0 ] ],
			[ [ 'field' => 'content.type', 'op' => 'eq', 'value' => 'post', 'extra' => 1 ] ],
		];
	}
	#[DataProvider( 'invalid_conditions' )]
	public function test_invalid_condition_is_rejected( array $expression ): void {
		$this->expectException( \InvalidArgumentException::class );
		Condition::from_array( $expression );
	}
	public function test_depth_is_bounded(): void {
		$c = [ 'field' => 'content.length', 'op' => 'gt', 'value' => 1 ];
		for ( $i = 0; $i < 10; ++$i ) {
			$c = [ 'group' => 'not', 'children' => [ $c ] ];
		}
		$this->expectException( \InvalidArgumentException::class );
		Condition::from_array( $c );
	}
	public function test_duplicate_fields_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new ContentPayload( [ new ContentField( 'body', 'x' ), new ContentField( 'body', 'y' ) ] );
	}
	public function test_duplicate_terms_rejected_during_compile(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Moderator( new Policy( 1, [ new Term( 'same', 'a' ), new Term( 'same', 'b' ) ], [] ) );
	}
	public static function malformed_policies(): array {
		return [
			[ '{' ],
			[ '{"format":1,"shadow":"true","terms":[],"rules":[]}' ],
			[ '{"format":1,"shadow":true,"terms":[{"id":"a","text":"x","severity":null}],"rules":[]}' ],
			[ '{"format":1,"shadow":true,"terms":[{"id":"a","text":"x","exceptions":[4]}],"rules":[]}' ],
			[ '{"format":1,"shadow":true,"terms":[],"rules":[],"secret":"unexpected"}' ],
		];
	}
	#[DataProvider( 'malformed_policies' )]
	public function test_malformed_policy_rejected( string $json ): void {
		try {
			( new PolicyCodec() )->decode( $json, 0 );
			self::fail( 'Malformed policy accepted.' );
		} catch ( \InvalidArgumentException|\JsonException $expected ) {
			self::assertNotEmpty( $expected->getMessage() );
		}
	}
}
