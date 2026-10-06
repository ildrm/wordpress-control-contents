<?php
/**
 * Plugin Name: Universal Content Moderation
 * Description: Local-first content policy, deterministic detection, and moderation auditing. Development preview.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Requires Plugins:
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: universal-content-moderation
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/autoload.php';
register_activation_hook( __FILE__, array( UWCMP\Integrations\WordPress\Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( UWCMP\Integrations\WordPress\Lifecycle::class, 'deactivate' ) );
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! extension_loaded( 'mbstring' ) || ! extension_loaded( 'intl' ) || ! extension_loaded( 'dom' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Content Moderation requires the PHP mbstring, intl and dom extensions. Restore them to resume analysis.', 'universal-content-moderation' ) . '</p></div>';
				}
			);
		}
		( new UWCMP\Integrations\WordPress\Plugin() )->register();
	}
);
