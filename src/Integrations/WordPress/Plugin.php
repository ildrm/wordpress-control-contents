<?php
declare(strict_types=1);
namespace UWCMP\Integrations\WordPress;

use UWCMP\Admin\REST\Controller;
use UWCMP\Admin\Screens\Admin;
use UWCMP\Infrastructure\Database\AuditStore;
use UWCMP\Infrastructure\Database\PolicyStore;
use UWCMP\Infrastructure\Database\Schema;
use UWCMP\Privacy\PersonalData;

final class Plugin {
	public function register(): void {
		global $wpdb;
		$policies = new PolicyStore( $wpdb );
		$audit    = new AuditStore( $wpdb );
		( new ContentAdapter( $policies, $audit ) )->register();
		( new PersonalData( $wpdb ) )->register();
		add_action( 'rest_api_init', array( new Controller( $policies, $audit ), 'register' ) );
		if ( is_admin() ) {
			( new Admin( $policies, $audit ) )->register();
		}
		$retention = static function () use ( $audit ): void {
			try {
				for ( $batch = 0; $batch < 5; ++$batch ) {
					if ( $audit->cleanup() < 1000 ) {
						return;
					}
				}
				if ( ! wp_next_scheduled( 'uwcmp_retention_batch' ) ) {
					wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'uwcmp_retention_batch' );
				}
			} catch ( \Throwable $error ) {
				try {
					do_action( 'uwcmp_failure', 'retention_failed' );
				} catch ( \Throwable $observer_error ) {
					return;
				}
			}
		};
		add_action( 'uwcmp_retention', $retention );
		add_action( 'uwcmp_retention_batch', $retention );
		add_filter(
			'site_status_tests',
			static function ( array $tests ) use ( $policies ): array {
				$tests['direct']['uwcmp_schema'] = array(
					'label' => __( 'Moderation schema', 'universal-content-moderation' ),
					'test'  => static function () use ( $policies ): array {
								global $wpdb;
						try {
							( new Schema( $wpdb ) )->verify();
							$policies->active();
							$ok = extension_loaded( 'intl' ) && extension_loaded( 'mbstring' ) && extension_loaded( 'dom' ) && (int) get_option( 'uwcmp_schema_version', 0 ) === Schema::VERSION && wp_next_scheduled( 'uwcmp_retention' ) !== false;
						} catch ( \Throwable $error ) {
							$ok = false;
						}
								return array(
									'label'       => $ok ? __( 'Moderation storage and retention checks passed', 'universal-content-moderation' ) : __( 'Moderation storage or retention requires attention', 'universal-content-moderation' ),
									'status'      => $ok ? 'good' : 'critical',
									'badge'       => array(
										'label' => __( 'Moderation', 'universal-content-moderation' ),
										'color' => 'blue',
									),
									'description' => '<p>' . esc_html__( 'This check verifies required tables, index columns and uniqueness, transactional storage, policy integrity, PHP extensions and the retention schedule.', 'universal-content-moderation' ) . '</p>',
									'actions'     => '',
									'test'        => 'uwcmp_schema',
								);
					},
				);
				return $tests;
			}
		);
	}
}
