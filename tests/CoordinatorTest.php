<?php
declare(strict_types=1);
namespace UWCMP\Tests;

use PHPUnit\Framework\TestCase;
use UWCMP\Application\Contracts\AuditJournal;
use UWCMP\Application\Contracts\PolicyProvider;
use UWCMP\Application\Services\ModerationCoordinator;
use UWCMP\Application\Services\Moderator;
use UWCMP\Domain\Content\ContentField;
use UWCMP\Domain\Content\ContentPayload;
use UWCMP\Domain\Content\ModerationContext;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Moderation\ModerationRequest;
use UWCMP\Domain\Policy\Policy;
use UWCMP\Domain\Reporting\AuditEvent;

final class CoordinatorTest extends TestCase {
	private function request(): ModerationRequest {
		return new ModerationRequest( new ContentPayload( array( new ContentField( 'body', 'sensitive submission' ) ) ), new ModerationContext( 'post', 'test' ) );
	}
	private function failing_provider( bool $shadow ): PolicyProvider {
		$provider = $this->createMock( PolicyProvider::class );
		$provider->method( 'active' )->willReturn( new Policy( 4, array(), array(), $shadow ) );
		$provider->method( 'engine' )->willThrowException( new \RuntimeException( 'sensitive debug detail' ) );
		return $provider;
	}
	public function test_analysis_failure_is_auditable_without_wordpress_or_exception_details(): void {
		$journal = $this->createMock( AuditJournal::class );
		$journal->expects( self::once() )->method( 'begin' )->willReturnCallback( static fn( $request, $result ) => new AuditEvent( 'operation', $request->context, $result, array() ) );
		$event = ( new ModerationCoordinator( $this->failing_provider( false ), $journal ) )->prepare( $this->request() );
		self::assertSame( Action::PENDING, $event->result->action );
		self::assertFalse( $event->result->detection->complete );
		self::assertSame( 4, $event->result->policy_version );
		self::assertStringNotContainsString( 'sensitive', $event->result->reason );
	}
	public function test_shadow_failure_keeps_proposed_review_separate_from_actual_action(): void {
		$journal = $this->createMock( AuditJournal::class );
		$journal->method( 'begin' )->willReturnCallback( static fn( $request, $result ) => new AuditEvent( 'operation', $request->context, $result, array() ) );
		$result = ( new ModerationCoordinator( $this->failing_provider( true ), $journal ) )->prepare( $this->request() )->result;
		self::assertSame( Action::ALLOW, $result->action );
		self::assertSame( Action::PENDING, $result->proposed_action );
		self::assertFalse( $result->detection->complete );
	}
	public function test_audit_failure_is_not_misreported_as_an_audited_decision(): void {
		$policy = new Policy( 4, array(), array(), false );
		$provider = $this->createMock( PolicyProvider::class );
		$provider->method( 'active' )->willReturn( $policy );
		$provider->method( 'engine' )->willReturn( new Moderator( $policy ) );
		$journal = $this->createMock( AuditJournal::class );
		$journal->method( 'begin' )->willThrowException( new \RuntimeException( 'Audit unavailable' ) );
		$this->expectExceptionMessage( 'Audit unavailable' );
		( new ModerationCoordinator( $provider, $journal ) )->prepare( $this->request() );
	}
}
