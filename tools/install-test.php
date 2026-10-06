<?php
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
/** Installs only the explicitly marked isolated test database. */
if ( getenv( 'UWCMP_INTEGRATION_TEST' ) !== '1' ) {
	exit( "Run only in the marked integration-test container.\n" );
}
define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST'] = 'localhost:8876';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_blog_installed() ) {
	wp_install( 'Moderation development tests', 'uwcmp_test_admin', 'admin@example.test', true, '', 'local-test-password-only' );
	update_option( 'siteurl', 'http://localhost:8876' );
	update_option( 'home', 'http://localhost:8876' );
}
$result = activate_plugin( 'universal-content-moderation/universal-content-moderation.php' );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() );
	exit( 1 );
}
echo "Isolated WordPress installed; plugin activated.\n";
