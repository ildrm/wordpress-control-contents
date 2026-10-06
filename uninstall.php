<?php
/** Retain moderation records on uninstall unless explicitly opted in through configuration. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( is_multisite() || ! defined( 'UWCMP_DELETE_DATA_ON_UNINSTALL' ) || UWCMP_DELETE_DATA_ON_UNINSTALL !== true ) {
	return;
}
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicit opt-in removes only plugin correlation metadata.
if ( $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key=%s', $wpdb->commentmeta, '_uwcmp_operation' ) ) === false ) {
	wp_die( esc_html__( 'Moderation metadata could not be deleted. Restore database permissions and retry uninstall.', 'universal-content-moderation' ), '', array( 'response' => 500 ) );
}
foreach ( array( 'events', 'policy_versions', 'policy_head' ) as $uwcmp_suffix ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicit single-site opt-in uninstall removes owned tables only.
	if ( $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'uwcmp_' . $uwcmp_suffix ) ) === false ) {
		wp_die( esc_html__( 'Moderation data could not be completely deleted. Restore database permissions and retry uninstall.', 'universal-content-moderation' ), '', array( 'response' => 500 ) );
	}
}
delete_option( 'uwcmp_schema_version' );
require_once __DIR__ . '/autoload.php';
foreach ( wp_roles()->roles as $uwcmp_name => $uwcmp_definition ) {
	$uwcmp_moderation_role = get_role( $uwcmp_name );
	if ( $uwcmp_moderation_role !== null ) {
		foreach ( UWCMP\Integrations\WordPress\Lifecycle::CAPABILITIES as $uwcmp_capability ) {
			$uwcmp_moderation_role->remove_cap( $uwcmp_capability );
		}
	}
}
