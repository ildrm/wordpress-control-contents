<?php
/** Destructive lifecycle checks on the disposable, explicitly marked test database only. */
if ( PHP_SAPI !== 'cli' || getenv( 'UWCMP_INTEGRATION_TEST' ) !== '1' ) {
	exit( 1 );
}
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$uwcmp_plugin = 'universal-content-moderation/universal-content-moderation.php';
deactivate_plugins( $uwcmp_plugin );
if ( wp_next_scheduled( 'uwcmp_retention' ) || wp_next_scheduled( 'uwcmp_retention_batch' ) || (int) get_option( 'uwcmp_schema_version' ) !== 1 ) {
	throw new RuntimeException( 'Deactivation failed schedule/data invariant.' );
}
define( 'WP_UNINSTALL_PLUGIN', $uwcmp_plugin );
require '/var/www/html/wp-content/plugins/universal-content-moderation/uninstall.php';
if ( (int) get_option( 'uwcmp_schema_version' ) !== 1 ) {
	throw new RuntimeException( 'Default uninstall unexpectedly removed data.' );
}
define( 'UWCMP_DELETE_DATA_ON_UNINSTALL', true );

$wpdb->suppress_errors( true );
$reject_drop = static fn( string $query ): string => str_starts_with( $query, 'DROP TABLE' ) ? 'DROP TABLE missing_database.uwcmp_events' : $query;
$die_handler = static fn() => static function ( $message ): void { throw new RuntimeException( (string) $message ); };
add_filter( 'query', $reject_drop );
add_filter( 'wp_die_handler', $die_handler );
try {
	require '/var/www/html/wp-content/plugins/universal-content-moderation/uninstall.php';
	throw new LogicException( 'Failed deletion was reported as successful.' );
} catch ( RuntimeException $error ) {
	if ( ! str_contains( $error->getMessage(), 'could not be completely deleted' ) || (int) get_option( 'uwcmp_schema_version' ) !== 1 || ! get_role( 'administrator' )->has_cap( 'uwcmp_edit_policies' ) ) {
		throw $error;
	}
} finally {
	remove_filter( 'query', $reject_drop );
	remove_filter( 'wp_die_handler', $die_handler );
}
require '/var/www/html/wp-content/plugins/universal-content-moderation/uninstall.php';
foreach ( [ 'events', 'policy_versions', 'policy_head' ] as $uwcmp_suffix ) {
	$uwcmp_table = $wpdb->prefix . 'uwcmp_' . $uwcmp_suffix;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $uwcmp_table ) ) ) !== null ) {
		throw new RuntimeException( 'Opt-in uninstall left an owned table.' );
	}
}
if ( get_option( 'uwcmp_schema_version', false ) !== false || get_role( 'administrator' )->has_cap( 'uwcmp_edit_policies' ) ) {
	throw new RuntimeException( 'Opt-in uninstall left option/capability.' );
}
echo "Deactivation, default data retention and explicit single-site uninstall passed.\n";
