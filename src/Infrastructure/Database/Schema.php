<?php
declare(strict_types=1);
namespace UWCMP\Infrastructure\Database;

final class Schema {
	public const VERSION = 1;
	public function __construct( private readonly \wpdb $db ) {}
	public function migrate(): void {
		$database = (string) $this->db->get_var( 'SELECT DATABASE()' );
		$lock     = 'uwcmp-schema-' . substr( hash( 'sha256', $database . ':' . $this->db->prefix ), 0, 40 );
		if ( (string) $this->db->get_var( $this->db->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) !== '1' ) {
			throw new \RuntimeException( 'Another migration is running or migration locking is unavailable.' );
		}
		try {
			$this->apply();
		} finally {
			$this->db->get_var( $this->db->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}
	private function apply(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$prefix = $this->db->prefix;
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/D', $prefix ) ) {
			throw new \RuntimeException( 'Invalid database prefix.' );
		}
		$collate = $this->db->get_charset_collate();
		$queries = array(
			"CREATE TABLE {$prefix}uwcmp_policy_versions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			payload longtext NOT NULL,
			checksum char(64) NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
			) ENGINE=InnoDB $collate;",
			"CREATE TABLE {$prefix}uwcmp_policy_head (
			id tinyint unsigned NOT NULL,
			active_version bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
			) ENGINE=InnoDB $collate;",
			"CREATE TABLE {$prefix}uwcmp_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_key char(36) NOT NULL,
			kind varchar(32) NOT NULL,
			object_type varchar(64) NOT NULL,
			object_id varchar(64) NOT NULL DEFAULT '',
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			policy_version bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(32) NOT NULL,
			metadata longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_key (event_key),
			KEY timeline (object_type,object_id,id),
			KEY actor_time (actor_id,id),
			KEY retention (created_at,id),
			KEY action_id (action,id)
			) ENGINE=InnoDB $collate;",
		);
		foreach ( $queries as $query ) {
			dbDelta( $query );
		}
		$this->verify();
		if ( $this->db->query( $this->db->prepare( 'INSERT IGNORE INTO %i (id, active_version) VALUES (1, 0)', $prefix . 'uwcmp_policy_head' ) ) === false ) {
			throw new \RuntimeException( 'Database initialization failed.' );
		}
		update_option( 'uwcmp_schema_version', self::VERSION, false );
	}
	public function verify(): void {
		$prefix = $this->db->prefix;
		foreach ( array(
			'policy_versions' => array( 'id', 'payload', 'checksum', 'created_by', 'created_at' ),
			'policy_head'     => array( 'id', 'active_version' ),
			'events'          => array( 'id', 'event_key', 'kind', 'object_type', 'object_id', 'actor_id', 'policy_version', 'action', 'metadata', 'created_at' ),
		) as $suffix => $required_columns ) {
			$table = $prefix . 'uwcmp_' . $suffix;
			if ( $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $this->db->esc_like( $table ) ) ) !== $table ) {
				throw new \RuntimeException( 'Database schema creation failed.' );
			}
			$columns = $this->db->get_col( $this->db->prepare( 'SHOW COLUMNS FROM %i', $table ) );
			if ( array_diff( $required_columns, $columns ) !== array() ) {
				throw new \RuntimeException( 'Database schema columns are incomplete.' );
			}
			$engine = $this->db->get_var( $this->db->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
			if ( ! is_string( $engine ) || strcasecmp( $engine, 'InnoDB' ) !== 0 ) {
				throw new \RuntimeException( 'Moderation tables require InnoDB transactions.' );
			}
			$indexes     = $this->db->get_results( $this->db->prepare( 'SHOW INDEX FROM %i', $table ), ARRAY_A );
			$definitions = array();
			foreach ( $indexes ?? array() as $index ) {
				$definitions[ $index['Key_name'] ][ (int) $index['Seq_in_index'] ] = array( $index['Column_name'], (int) $index['Non_unique'], $index['Sub_part'] );
			}
			$required_indexes = array( 'PRIMARY' => array( 'id' ) );
			if ( $suffix === 'events' ) {
				$required_indexes += array( 'event_key' => array( 'event_key' ), 'timeline' => array( 'object_type', 'object_id', 'id' ), 'actor_time' => array( 'actor_id', 'id' ), 'retention' => array( 'created_at', 'id' ), 'action_id' => array( 'action', 'id' ) );
			}
			foreach ( $required_indexes as $name => $fields ) {
				$expected = array();
				foreach ( $fields as $position => $field ) {
					$expected[ $position + 1 ] = array( $field, in_array( $name, array( 'PRIMARY', 'event_key' ), true ) ? 0 : 1, null );
				}
				$actual = $definitions[ $name ] ?? array();
				ksort( $actual );
				if ( $actual !== $expected ) {
					throw new \RuntimeException( 'Database schema index definition is invalid.' );
				}
			}
		}
	}
}
