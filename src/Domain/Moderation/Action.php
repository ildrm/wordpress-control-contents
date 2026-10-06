<?php
declare(strict_types=1);
namespace UWCMP\Domain\Moderation;

enum Action: string {
	case ALLOW                = 'allow';
	case WARN                 = 'warn';
	case REQUIRE_EDIT         = 'require_edit';
	case MASK                 = 'mask';
	case REDACT               = 'redact';
	case PENDING              = 'pending';
	case QUARANTINE           = 'quarantine';
	case SPAM                 = 'spam';
	case REJECT               = 'reject';
	case TRASH                = 'trash';
	case UNPUBLISH            = 'unpublish';
	case ESCALATE             = 'escalate';
	case REQUIRE_CHALLENGE    = 'require_challenge';
	case TEMPORARILY_RESTRICT = 'temporarily_restrict';
	case SUSPEND              = 'suspend';
	case CUSTOM_ACTION        = 'custom_action';

	public function needs_review(): bool {
		return ! in_array( $this, array( self::ALLOW, self::WARN ), true );
	}
}
