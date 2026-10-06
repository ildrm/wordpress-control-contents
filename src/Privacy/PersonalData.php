<?php
declare(strict_types=1);
namespace UWCMP\Privacy;

final class PersonalData {
	public function __construct( private readonly \wpdb $db ) {}
	public function register(): void {
		add_filter(
			'wp_privacy_personal_data_exporters',
			function ( array $exporters ): array {
				$exporters['uwcmp'] = array(
					'exporter_friendly_name' => __( 'Moderation events', 'universal-content-moderation' ),
					'callback'               => array( $this, 'export' ),
				);
				return $exporters;
			}
		);
		add_filter(
			'wp_privacy_personal_data_erasers',
			function ( array $erasers ): array {
				$erasers['uwcmp'] = array(
					'eraser_friendly_name' => __( 'Moderation events', 'universal-content-moderation' ),
					'callback'             => array( $this, 'erase' ),
				);
				return $erasers;
			}
		);
	}
	public function export( string $email, int $page = 1 ): array|\WP_Error {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT id,object_type,object_id,action,created_at FROM %i WHERE actor_id=%d ORDER BY id LIMIT 100 OFFSET %d', $this->db->prefix . 'uwcmp_events', $user->ID, max( 0, $page - 1 ) * 100 ), ARRAY_A );
		if ( $this->database_failed() ) {
			return new \WP_Error( 'uwcmp_export_failed', __( 'Moderation personal data could not be exported. Restore storage and retry.', 'universal-content-moderation' ) );
		}
		$versions = $this->db->get_results( $this->db->prepare( 'SELECT id,created_at FROM %i WHERE created_by=%d ORDER BY id LIMIT 100 OFFSET %d', $this->db->prefix . 'uwcmp_policy_versions', $user->ID, max( 0, $page - 1 ) * 100 ), ARRAY_A );
		if ( $this->database_failed() ) {
			return new \WP_Error( 'uwcmp_export_failed', __( 'Moderation personal data could not be exported. Restore storage and retry.', 'universal-content-moderation' ) );
		}
		$data = array();
		foreach ( $rows ?? array() as $row ) {
			$data[] = array(
				'group_id'    => 'uwcmp',
				'group_label' => __( 'Moderation events', 'universal-content-moderation' ),
				'item_id'     => 'uwcmp-' . $row['id'],
				'data'        => array(
					array(
						'name'  => 'Object',
						'value' => $row['object_type'] . ':' . $row['object_id'],
					),
					array(
						'name'  => 'Decision',
						'value' => $row['action'],
					),
					array(
						'name'  => 'Time (UTC)',
						'value' => $row['created_at'],
					),
				),
			);
		}
		foreach ( $versions ?? array() as $version ) {
			$data[] = array(
				'group_id'    => 'uwcmp-policy-authorship',
				'group_label' => __( 'Moderation policy authorship', 'universal-content-moderation' ),
				'item_id'     => 'uwcmp-policy-' . $version['id'],
				'data'        => array(
					array( 'name' => __( 'Policy version', 'universal-content-moderation' ), 'value' => (string) $version['id'] ),
					array( 'name' => __( 'Time (UTC)', 'universal-content-moderation' ), 'value' => $version['created_at'] ),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => count( $rows ?? array() ) < 100 && count( $versions ?? array() ) < 100,
		);
	}
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Erasure drains 100 rows per call; WordPress passes a page.
	public function erase( string $email, int $page = 1 ): array|\WP_Error {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}
		$removed  = $this->db->query( $this->db->prepare( "UPDATE %i SET actor_id=0,object_id='',metadata='{}' WHERE actor_id=%d LIMIT 100", $this->db->prefix . 'uwcmp_events', $user->ID ) );
		$versions = $this->db->query( $this->db->prepare( 'UPDATE %i SET created_by=0 WHERE created_by=%d LIMIT 100', $this->db->prefix . 'uwcmp_policy_versions', $user->ID ) );
		if ( $removed === false || $versions === false ) {
			return new \WP_Error( 'uwcmp_erasure_failed', __( 'Moderation personal data could not be fully erased. Restore storage and retry.', 'universal-content-moderation' ) );
		}
		return array(
			'items_removed'  => $removed > 0 || $versions > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $removed < 100 && $versions < 100,
		);
	}
	/** @phpstan-impure Queries on the shared database connection mutate its error state. */
	private function database_failed(): bool {
		return $this->db->last_error !== '';
	}
}
