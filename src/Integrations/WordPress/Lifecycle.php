<?php
declare(strict_types=1);
namespace UWCMP\Integrations\WordPress;

use UWCMP\Infrastructure\Database\Schema;

final class Lifecycle {
	public const CAPABILITIES = array( 'uwcmp_view_moderation', 'uwcmp_moderate', 'uwcmp_view_sensitive', 'uwcmp_edit_policies', 'uwcmp_manage_integrations', 'uwcmp_suspend_users', 'uwcmp_export', 'uwcmp_manage_providers', 'uwcmp_view_audit', 'uwcmp_administer' );
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Network activation is unavailable in this development preview. Activate on individual sites.', 'universal-content-moderation' ) );
		}
		if ( ! extension_loaded( 'intl' ) || ! extension_loaded( 'mbstring' ) || ! extension_loaded( 'dom' ) ) {
			wp_die( esc_html__( 'PHP intl, mbstring and dom are required.', 'universal-content-moderation' ) );
		}
		global $wpdb;
		try {
			( new Schema( $wpdb ) )->migrate();
		} catch ( \Throwable $error ) {
			wp_die( esc_html__( 'Moderation database initialization failed. Check database permissions and retry activation.', 'universal-content-moderation' ) );
		}
		$role = get_role( 'administrator' );
		if ( $role !== null ) {
			foreach ( self::CAPABILITIES as $capability ) {
				$role->add_cap( $capability );
			}
		}
		if ( ! wp_next_scheduled( 'uwcmp_retention' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'uwcmp_retention' );
		}
	}
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'uwcmp_retention' );
		wp_clear_scheduled_hook( 'uwcmp_retention_batch' );
	}
}
