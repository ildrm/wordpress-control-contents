<?php
declare(strict_types=1);
namespace UWCMP\Application\Services;

use UWCMP\Domain\Detection\DetectionResult;
use UWCMP\Domain\Detection\Evidence;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Moderation\ModerationRequest;
use UWCMP\Domain\Moderation\ModerationResult;
use UWCMP\Domain\Policy\Policy;

final class Moderator {
	private TermDetector $detector;
	public function __construct( private readonly Policy $policy, private readonly Normalizer $normalizer = new Normalizer() ) {
		$this->detector = new TermDetector( $policy->terms, $normalizer );
	}
	public function check( ModerationRequest $request ): ModerationResult {
		$start    = hrtime( true );
		$evidence = array();
		$complete = true;
		$length   = 0;
		$links    = 0;
		foreach ( $request->content->fields as $field ) {
			$normalized = $this->normalizer->normalize( $field->text, $field->format === 'html' );
			$result     = $this->detector->detect( $normalized, $field->name, $request->context->content_type );
			$evidence   = array_merge( $evidence, $result->evidence );
			$complete   = $complete && $result->complete;
			$length    += mb_strlen( $normalized->canonical, 'UTF-8' );
			$links     += $normalized->link_count;
		}
		$detection = new DetectionResult( $evidence, $complete );
		$actor     = $request->context->actor;
		$facts     = array(
			'content.type'         => $request->context->content_type,
			'source'               => $request->context->source,
			'site.id'              => $request->context->site_id,
			'actor.id'             => $actor->id,
			'actor.roles'          => $actor->roles,
			'actor.account_age'    => $actor->account_age_seconds,
			'actor.reputation'     => $actor->reputation,
			'content.length'       => $length,
			'links.count'          => $links,
			'detection.count'      => count( $evidence ),
			'detection.score'      => $detection->score(),
			'detection.confidence' => $detection->confidence(),
			'detection.categories' => array_values( array_unique( array_map( static fn( Evidence $e ): string => $e->category, $evidence ) ) ),
			'detection.fields'     => array_values( array_unique( array_map( static fn( Evidence $e ): string => $e->field, $evidence ) ) ),
			'detection.terms'      => array_values( array_unique( array_map( static fn( Evidence $e ): string => $e->term_id, $evidence ) ) ),
		);
		$action    = Action::ALLOW;
		$rule_id   = null;
		foreach ( $this->policy->rules as $rule ) {
			if ( $rule->enabled && $rule->condition->evaluate( $facts ) ) {
				$action  = $rule->action;
				$rule_id = $rule->id;
				break;
			}
		}
		$reason = $rule_id === null ? 'No policy rule matched.' : 'Policy rule matched.';
		// The entire preview is review-first: representative precision has not been established.
		if ( $action->needs_review() && $action !== Action::PENDING ) {
			$action = Action::PENDING;
			$reason = 'Requested enforcement requires review in this development preview.';
		}
		if ( ! $complete ) {
			$action = Action::PENDING;
			$reason = 'Detection limits reached; manual review required.';
		}
		return new ModerationResult( $this->policy->shadow ? Action::ALLOW : $action, $action, $detection, $this->policy->version, $rule_id, $this->policy->shadow, ( hrtime( true ) - $start ) / 1e6, $reason );
	}
}
