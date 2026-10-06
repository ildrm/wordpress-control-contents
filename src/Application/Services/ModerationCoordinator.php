<?php
declare(strict_types=1);
namespace UWCMP\Application\Services;

use UWCMP\Domain\Moderation\ModerationRequest;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Moderation\ModerationResult;
use UWCMP\Domain\Detection\DetectionResult;
use UWCMP\Domain\Reporting\AuditEvent;
use UWCMP\Application\Contracts\AuditJournal;
use UWCMP\Application\Contracts\PolicyProvider;

/** Keeps analysis and persistence acknowledgement separate. */
final class ModerationCoordinator {
	public function __construct( private readonly PolicyProvider $policies, private readonly AuditJournal $audit ) {}
	public function prepare( ModerationRequest $request ): AuditEvent {
		$policy = $this->policies->active();
		$start  = hrtime( true );
		try {
			$result = $this->policies->engine()->check( $request );
		} catch ( \Throwable $error ) {
			// A valid submission whose analysis fails still needs a traceable review decision.
			$result = new ModerationResult( $policy->shadow ? Action::ALLOW : Action::PENDING, Action::PENDING, new DetectionResult( array(), false ), $policy->version, null, $policy->shadow, ( hrtime( true ) - $start ) / 1e6, 'Analysis failed; manual review required.' );
		}
		return $this->audit->begin( $request, $result );
	}
	public function commit( AuditEvent $event, string $object_id, string $status ): void {
		$this->audit->commit( $event, $object_id, $status );
	}
}
