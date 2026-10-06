<?php
declare(strict_types=1);
namespace UWCMP\Domain\Moderation;

use UWCMP\Domain\Detection\DetectionResult;

final readonly class ModerationResult implements \JsonSerializable {
	public function __construct( public Action $action, public Action $proposed_action, public DetectionResult $detection, public int $policy_version, public ?string $rule_id, public bool $shadow, public float $latency_ms, public string $reason ) {}
	public function jsonSerialize(): array {
		return array(
			'action'          => $this->action->value,
			'proposed_action' => $this->proposed_action->value,
			'policy_version'  => $this->policy_version,
			'rule_id'         => $this->rule_id,
			'shadow'          => $this->shadow,
			'score'           => $this->detection->score(),
			'confidence'      => $this->detection->confidence(),
			'complete'        => $this->detection->complete,
			'evidence'        => $this->detection->evidence,
			'latency_ms'      => $this->latency_ms,
			'reason'          => $this->reason,
		);
	}
}
