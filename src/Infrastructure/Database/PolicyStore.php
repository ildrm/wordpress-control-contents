<?php
declare(strict_types=1);
namespace UWCMP\Infrastructure\Database;

use UWCMP\Application\Services\PolicyCodec;
use UWCMP\Application\Services\Moderator;
use UWCMP\Domain\Policy\Policy;
use UWCMP\Application\Contracts\PolicyProvider;

final class PolicyStore implements PolicyProvider {
	private ?Policy $cached           = null;
	private string $cached_prefix     = '';
	private ?Moderator $cached_engine = null;
	private string $checksum          = '';
	public function __construct( private readonly \wpdb $db, private readonly PolicyCodec $codec = new PolicyCodec() ) {}
	public function active(): Policy {
		if ( $this->cached !== null && $this->cached_prefix === $this->db->prefix ) {
			return $this->cached;
		}
		$this->cached_prefix = $this->db->prefix;
		$this->cached_engine = null;
		$this->cached        = null;
		$this->checksum      = '';
		$row                 = $this->db->get_row( $this->db->prepare( 'SELECT h.active_version,v.payload,v.checksum FROM %i h LEFT JOIN %i v ON v.id=h.active_version WHERE h.id=1', $this->db->prefix . 'uwcmp_policy_head', $this->db->prefix . 'uwcmp_policy_versions' ), ARRAY_A );
		if ( ! is_array( $row ) || $this->db->last_error !== '' ) {
			throw new \RuntimeException( 'Policy storage unavailable.' );
		}
		if ( (int) $row['active_version'] === 0 ) {
			$this->checksum = 'empty';
			$this->cached   = new Policy( 0, array(), array() );
			return $this->cached;
		}
		if ( ! is_string( $row['payload'] ) || ! hash_equals( (string) $row['checksum'], hash( 'sha256', $row['payload'] ) ) ) {
			throw new \RuntimeException( 'Policy integrity check failed.' );
		}
		$this->checksum = $row['checksum'];
		$this->cached   = $this->codec->decode( $row['payload'], (int) $row['active_version'], false );
		return $this->cached;
	}
	public function engine(): Moderator {
		$policy = $this->active();
		if ( $this->cached_engine !== null ) {
			return $this->cached_engine;
		}
		$key    = 'engine-v3-' . hash( 'sha256', $this->db->prefix . ':' . $policy->version . ':' . $this->checksum );
		$engine = wp_cache_get( $key, 'uwcmp' );
		if ( $engine instanceof Moderator ) {
			$this->cached_engine = $engine;
		} else {
			$this->cached_engine = new Moderator( $policy );
			wp_cache_set( $key, $this->cached_engine, 'uwcmp', HOUR_IN_SECONDS );
		}
		return $this->cached_engine;
	}
	public function save( Policy $policy, int $expected_version, int $actor_id ): int {
		$payload = $this->codec->encode( $policy );
		$this->codec->decode( $payload, 0 );
		// Both MySQL and MariaDB reject this inside an existing transaction, without committing it.
		$suppressed = $this->db->suppress_errors( true );
		try {
			$independent = $this->db->query( 'SET TRANSACTION READ WRITE' ) !== false;
		} finally {
			$this->db->suppress_errors( $suppressed );
		}
		if ( ! $independent ) {
			throw new \RuntimeException( 'Policy saves require an independent transaction.' );
		}
		if ( $this->db->query( 'START TRANSACTION' ) === false ) {
			throw new \RuntimeException( 'Unable to start policy transaction.' );
		}
		try {
			if ( $this->db->insert(
				$this->db->prefix . 'uwcmp_policy_versions',
				array(
					'payload'    => $payload,
					'checksum'   => hash( 'sha256', $payload ),
					'created_by' => $actor_id,
					'created_at' => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%s', '%s', '%d', '%s' )
			) === false ) {
				throw new \RuntimeException( 'Unable to write policy snapshot.' );
			}
			$version = (int) $this->db->insert_id;
			$changed = $this->db->query( $this->db->prepare( 'UPDATE %i SET active_version=%d WHERE id=1 AND active_version=%d', $this->db->prefix . 'uwcmp_policy_head', $version, $expected_version ) );
			if ( $changed === false ) {
				throw new \RuntimeException( 'Unable to update policy head.' );
			}
			if ( $changed !== 1 ) {
				throw new \RuntimeException( 'Policy changed concurrently. Reload before saving.' );
			}
			( new AuditStore( $this->db ) )->append(
				'policy_changed',
				'policy',
				'1',
				$actor_id,
				$version,
				'activate',
				array(
					'previous_version' => $expected_version,
					'new_version'      => $version,
				)
			);
			if ( $this->db->query( 'COMMIT' ) === false ) {
				throw new \RuntimeException( 'Unable to commit policy.' );
			}
			$this->cached        = null;
			$this->cached_engine = null;
			return $version;
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' );
			throw $error;
		}
	}
}
