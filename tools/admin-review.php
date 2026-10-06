<?php
if ( PHP_SAPI !== 'cli' || getenv( 'UWCMP_INTEGRATION_TEST' ) !== '1' ) {
	exit( 1 );
}
require '/var/www/html/wp-load.php';
use UWCMP\Admin\Screens\Admin;
use UWCMP\Domain\Detection\Term;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Policy\Condition;
use UWCMP\Domain\Policy\Policy;
use UWCMP\Domain\Policy\Rule;
use UWCMP\Infrastructure\Database\AuditStore;
use UWCMP\Infrastructure\Database\PolicyStore;
use UWCMP\Infrastructure\Database\Schema;

$admin = get_user_by( 'login', 'uwcmp_test_admin' );
wp_set_current_user( $admin->ID );
$wpdb->suppress_errors( true );
$store = new PolicyStore( $wpdb );
$screen = new Admin( $store, new AuditStore( $wpdb ) );
add_filter( 'wp_die_handler', static fn() => static function ( $message ): void { throw new RuntimeException( (string) $message ); } );
$version = $store->active()->version;
foreach ( array( array( 'terms' => array( 'badword' ) ), array( 'terms' => 'safe', 'shadow' => array( '1' ) ), array( 'terms' => 'safe', 'shadow' => 'unexpected' ) ) as $fields ) {
	$_POST = $fields + array( 'version' => (string) $version, '_wpnonce' => wp_create_nonce( 'uwcmp_save' ) );
	$_REQUEST = $_POST;
	try {
		$screen->save();
		throw new LogicException( 'Malformed form accepted.' );
	} catch ( RuntimeException $error ) {
		if ( ! str_contains( $error->getMessage(), 'could not be saved' ) || $store->active()->version !== $version ) {
			throw $error;
		}
	}
}
$rule = new Rule( 'review', Condition::from_array( array( 'field' => 'detection.count', 'op' => 'gt', 'value' => 0 ) ), Action::PENDING );
$advanced = new Policy( 0, array( new Term( 'scoped', 'badword', fields: array( 'body' ), exceptions: array( 'quoted badword' ) ) ), array( $rule ), false );
$version = $store->save( $advanced, $store->active()->version, $admin->ID );
$_POST = array( 'terms' => 'replacement', 'version' => (string) $version, '_wpnonce' => wp_create_nonce( 'uwcmp_save' ) );
$_REQUEST = $_POST;
try {
	$screen->save();
	throw new LogicException( 'Advanced policy replaced by simple form.' );
} catch ( RuntimeException $error ) {
	if ( ! str_contains( $error->getMessage(), 'could not be saved' ) || $store->active()->version !== $version ) {
		throw $error;
	}
}
ob_start();
$screen->policy();
$html = ob_get_clean();
if ( str_contains( $html, '<form' ) || ! str_contains( $html, 'advanced rules' ) ) {
	throw new RuntimeException( 'Advanced policy screen exposed replacement form.' );
}
$store->save( new Policy( 0, array( new Term( 'sentinel', 'badword' ) ), array( $rule ), false ), $version, $admin->ID );

$tests = apply_filters( 'site_status_tests', array() );
$health = $tests['direct']['uwcmp_schema']['test'];
if ( $health()['status'] !== 'good' ) {
	throw new RuntimeException( 'Healthy storage reported invalid.' );
}
$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX event_key, ADD KEY event_key (event_key)', $wpdb->prefix . 'uwcmp_events' ) );
try {
	( new Schema( $wpdb ) )->verify();
	throw new LogicException( 'Nonunique event key accepted.' );
} catch ( RuntimeException $error ) {
	if ( ! str_contains( $error->getMessage(), 'index definition' ) || $health()['status'] !== 'critical' ) {
		throw $error;
	}
} finally {
	$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX event_key, ADD UNIQUE KEY event_key (event_key)', $wpdb->prefix . 'uwcmp_events' ) );
}
// Exercise a backlog larger than one callback, without storing submitted content.
$wpdb->query( $wpdb->prepare( "INSERT INTO %i (event_key,kind,object_type,object_id,actor_id,policy_version,action,metadata,created_at) SELECT UUID(),'fixture','post','',0,0,'allow','{}','2000-01-01 00:00:00' FROM %i a CROSS JOIN %i b CROSS JOIN %i c LIMIT 5501", $wpdb->prefix . 'uwcmp_events', $wpdb->posts, $wpdb->posts, $wpdb->posts ) );
do_action( 'uwcmp_retention' );
if ( ! wp_next_scheduled( 'uwcmp_retention_batch' ) ) {
	throw new RuntimeException( 'Retention backlog did not schedule continuation.' );
}
do_action( 'uwcmp_retention_batch' );
if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}uwcmp_events WHERE created_at='2000-01-01 00:00:00'" ) !== 0 ) {
	throw new RuntimeException( 'Retention continuation did not drain backlog.' );
}
echo "Malformed forms, advanced-policy preservation, real schema health and bounded retention continuation passed (9 checks).\n";
