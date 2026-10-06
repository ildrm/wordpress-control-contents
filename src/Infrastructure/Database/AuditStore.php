<?php
declare(strict_types=1);
namespace UWCMP\Infrastructure\Database;

use UWCMP\Domain\Moderation\ModerationRequest;
use UWCMP\Domain\Moderation\ModerationResult;
use UWCMP\Domain\Detection\Evidence;
use UWCMP\Domain\Reporting\AuditEvent;
use UWCMP\Application\Contracts\AuditJournal;

final class AuditStore implements AuditJournal {
	public function __construct( private readonly \wpdb $db ) {}
	public function begin( ModerationRequest $request, ModerationResult $result ): AuditEvent {
		// Store identifiers and signals only. No raw content, email, IP, or match excerpts.
		$metadata = array(
			'proposed_action' => $result->proposed_action->value,
			'shadow'          => $result->shadow,
			'complete'        => $result->detection->complete,
			'score'           => $result->detection->score(),
			'source'          => $request->context->source,
			'rule_id'         => $result->rule_id,
			'latency_ms'      => $result->latency_ms,
			'evidence'        => array_map(
				static fn( Evidence $e ): array => array(
					'term_id'    => $e->term_id,
					'category'   => $e->category,
					'field'      => $e->field,
					'confidence' => $e->confidence,
				),
				$result->detection->evidence
			),
		);
		$key      = $this->append( 'attempt', $request->context->content_type, '', $request->context->actor->id, $result->policy_version, $result->action->value, $metadata );
		return new AuditEvent( $key, $request->context, $result, $metadata );
	}
	public function commit( AuditEvent $event, string $object_id, string $status ): void {
		if ( ! preg_match( '/^[1-9][0-9]{0,19}$/D', $object_id ) ) {
			throw new \InvalidArgumentException( 'Invalid committed object ID.' );
		}
		$metadata = $event->metadata + array(
			'stored_status' => $status,
			'committed_at'  => gmdate( 'Y-m-d H:i:s' ),
			'hold_applied'  => $event->result->action->value === 'pending' && in_array( $status, array( 'pending', '0', 'spam', 'trash' ), true ),
		);
		$changed  = $this->db->query( $this->db->prepare( "UPDATE %i SET kind='decision',object_id=%s,metadata=%s WHERE event_key=%s AND kind='attempt' AND actor_id=%d", $this->db->prefix . 'uwcmp_events', $object_id, json_encode( $metadata, JSON_THROW_ON_ERROR ), $event->key, $event->context->actor->id ) );
		if ( $changed === 1 ) {
			return;
		}
		if ( $changed === 0 ) {
			$row = $this->db->get_row( $this->db->prepare( 'SELECT kind,object_id FROM %i WHERE event_key=%s', $this->db->prefix . 'uwcmp_events', $event->key ), ARRAY_A );
			if ( $this->db->last_error === '' && is_array( $row ) && $row['kind'] === 'decision' && $row['object_id'] === $object_id ) {
				return; // Repeated completion of the same operation is idempotent.
			}
		}
		throw new \RuntimeException( 'Audit completion failed.' );
	}
	public function append( string $kind, string $object_type, string $object_id, int $actor_id, int $version, string $action, array $metadata ): string {
		$key      = wp_generate_uuid4();
		$inserted = $this->db->insert(
			$this->db->prefix . 'uwcmp_events',
			array(
				'event_key'      => $key,
				'kind'           => $kind,
				'object_type'    => $object_type,
				'object_id'      => $object_id,
				'actor_id'       => $actor_id,
				'policy_version' => $version,
				'action'         => $action,
				'metadata'       => json_encode( $metadata, JSON_THROW_ON_ERROR ),
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		if ( $inserted === false ) {
			throw new \RuntimeException( 'Audit write failed.' );
		}
		return $key;
	}
	public function page( int $before = 0, int $limit = 50 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$table = $this->db->prefix . 'uwcmp_events';
		$sql   = $before > 0
			? $this->db->prepare( 'SELECT id,kind,object_type,object_id,actor_id,policy_version,action,metadata,created_at FROM %i WHERE id < %d ORDER BY id DESC LIMIT %d', $table, $before, $limit )
			: $this->db->prepare( 'SELECT id,kind,object_type,object_id,actor_id,policy_version,action,metadata,created_at FROM %i ORDER BY id DESC LIMIT %d', $table, $limit );
		$rows  = $this->db->get_results( $sql, ARRAY_A );
		if ( $this->db->last_error !== '' ) {
			throw new \RuntimeException( 'Audit read failed.' );
		}
		return $rows ?? array();
	}
	public function cleanup( int $retention_days = 30 ): int {
		if ( $retention_days < 1 || $retention_days > 3650 ) {
			throw new \InvalidArgumentException( 'Invalid retention.' );
		}
		$result = $this->db->query( $this->db->prepare( 'DELETE FROM %i WHERE created_at < %s ORDER BY id LIMIT 1000', $this->db->prefix . 'uwcmp_events', gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS ) ) );
		if ( $result === false ) {
			throw new \RuntimeException( 'Retention cleanup failed.' );
		}
		return (int) $result;
	}
}
