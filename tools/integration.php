<?php
declare(strict_types=1);
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
if ( getenv( 'UWCMP_INTEGRATION_TEST' ) !== '1' ) {
	exit( "Run only in the marked integration-test container.\n" );
}
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
use UWCMP\Application\Services\Moderator;
use UWCMP\Domain\Detection\Term;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Policy\Condition;
use UWCMP\Domain\Policy\Policy;
use UWCMP\Domain\Policy\Rule;
use UWCMP\Infrastructure\Database\AuditStore;
use UWCMP\Infrastructure\Database\PolicyStore;
use UWCMP\Infrastructure\Database\Schema;
use UWCMP\Privacy\PersonalData;

$assertions = 0;
function verify( bool $condition, string $label ): void {
	global $assertions;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAILED: ' . $label );
	}
	++$assertions;
}
$admin = get_user_by( 'login', 'uwcmp_test_admin' );
wp_set_current_user( $admin->ID );
verify( current_user_can( 'uwcmp_edit_policies' ), 'activation capabilities' );
( new Schema( $wpdb ) )->migrate();
( new Schema( $wpdb ) )->migrate();
verify( (int) get_option( 'uwcmp_schema_version' ) === 1, 'idempotent schema migration' );
$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX action_id', $wpdb->prefix . 'uwcmp_events' ) );
( new Schema( $wpdb ) )->migrate();
verify( in_array( 'action_id', $wpdb->get_col( $wpdb->prepare( 'SHOW INDEX FROM %i', $wpdb->prefix . 'uwcmp_events' ), 2 ), true ), 'missing index recovered' );
$competitor = new wpdb( getenv( 'WORDPRESS_DB_USER' ), getenv( 'WORDPRESS_DB_PASSWORD' ), getenv( 'WORDPRESS_DB_NAME' ), getenv( 'WORDPRESS_DB_HOST' ) );
$migration_lock = 'uwcmp-schema-' . substr( hash( 'sha256', $wpdb->get_var( 'SELECT DATABASE()' ) . ':' . $wpdb->prefix ), 0, 40 );
verify( (string) $competitor->get_var( $competitor->prepare( 'SELECT GET_LOCK(%s,0)', $migration_lock ) ) === '1', 'competing migration lock acquired' );
try {
	( new Schema( $wpdb ) )->migrate();
	throw new RuntimeException( 'Concurrent migration unexpectedly accepted.' );
} catch ( RuntimeException $error ) {
	verify( str_contains( $error->getMessage(), 'Another migration' ), 'concurrent migration excluded' );
} finally {
	$competitor->get_var( $competitor->prepare( 'SELECT RELEASE_LOCK(%s)', $migration_lock ) );
	$competitor->close();
}
( new Schema( $wpdb ) )->migrate();
$store = new PolicyStore( $wpdb );
$rule = new Rule( 'review', Condition::from_array( [ 'field' => 'detection.count', 'op' => 'gt', 'value' => 0 ] ), Action::PENDING );
$policy = new Policy( 0, [ new Term( 'sentinel', 'badword' ) ], [ $rule ], false );
$previous = $store->active()->version;
$version = $store->save( $policy, $previous, $admin->ID );
verify( $store->active()->version === $version, 'active policy version' );
verify( $store->engine() === $store->engine(), 'request compiled engine reused' );
$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}uwcmp_policy_versions" );
try {
	$store->save( $policy, $previous, $admin->ID );
	throw new RuntimeException( 'Missing optimistic concurrency protection.' );
} catch ( RuntimeException $error ) {
	verify( str_contains( $error->getMessage(), 'concurrently' ), 'concurrent editor rejected' );
}
verify( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}uwcmp_policy_versions" ) === $count, 'failed save transaction rolled back' );

$clean = wp_insert_post( [ 'post_title' => 'Helpful article', 'post_content' => "Writer's original text", 'post_status' => 'publish' ], true );
verify( is_int( $clean ) && get_post_status( $clean ) === 'publish', 'clean post published' );
verify( get_post_field( 'post_content', $clean ) === "Writer's original text", 'original text retained' );
$bad = wp_insert_post( [ 'post_title' => 'badword', 'post_status' => 'publish' ], true );
verify( is_int( $bad ) && get_post_status( $bad ) === 'pending', 'post publication held' );
wp_update_post( [ 'ID' => $clean, 'post_content' => 'badword', 'post_status' => 'publish' ] );
verify( get_post_status( $clean ) === 'pending', 'published post edit held' );
$draft = wp_insert_post( [ 'post_title' => 'badword', 'post_status' => 'draft' ] );
verify( get_post_status( $draft ) === 'draft', 'draft status retained' );
register_post_type( 'uwcmp_fixture', [ 'public' => true ] );
$cpt = wp_insert_post( [ 'post_type' => 'uwcmp_fixture', 'post_title' => 'badword', 'post_status' => 'publish' ] );
verify( get_post_status( $cpt ) === 'pending', 'custom post type publication held' );

update_option( 'comment_moderation', 0 );
update_option( 'comment_previously_approved', 0 );
$comment_post = wp_insert_post( [ 'post_title' => 'Comment target', 'post_status' => 'publish', 'comment_status' => 'open' ] );
$comment = wp_new_comment( wp_slash( [ 'comment_post_ID' => $comment_post, 'comment_content' => 'badword', 'comment_author' => 'Test writer', 'comment_author_email' => 'writer@example.test', 'comment_author_url' => '', 'comment_author_IP' => '192.0.2.4' ] ), true );
verify( is_int( $comment ) && (string) get_comment( $comment )->comment_approved === '0', 'normal comment held' );
$direct = wp_insert_comment( [ 'comment_post_ID' => $comment_post, 'comment_content' => 'badword', 'comment_approved' => 1 ] );
verify( (string) get_comment( $direct )->comment_approved === '0', 'low-level insertion held by synchronous fallback' );
$spam = wp_insert_comment( [ 'comment_post_ID' => $comment_post, 'comment_content' => 'badword', 'comment_approved' => 'spam' ] );
verify( get_comment( $spam )->comment_approved === 'spam', 'existing spam decision preserved' );
$clean_comment = wp_insert_comment( [ 'comment_post_ID' => $comment_post, 'comment_content' => 'civil discussion', 'comment_approved' => 1 ] );
wp_update_comment( [ 'comment_ID' => $clean_comment, 'comment_content' => 'badword', 'comment_approved' => 1 ] );
verify( (string) get_comment( $clean_comment )->comment_approved === '0', 'comment update held' );
add_filter( 'wp_update_comment_data', static fn() => new WP_Error( 'fixture', 'Earlier rejection' ), 1 );
$error = wp_update_comment( [ 'comment_ID' => $clean_comment, 'comment_content' => 'clean' ], true );
verify( is_wp_error( $error ) && $error->get_error_code() === 'fixture', 'prior error passed through' );
remove_all_filters( 'wp_update_comment_data', 1 );

$server = rest_get_server();
$subscriber = wp_create_user( 'fixture_' . wp_generate_password( 8, false ), 'test-only', 'fixture-' . wp_generate_uuid4() . '@example.test' );
wp_set_current_user( $subscriber );
$request = new WP_REST_Request( 'POST', '/uwcmp/v1/check' );
$request->set_param( 'content', 'badword' );
verify( $server->dispatch( $request )->get_status() === 403, 'REST simulation capability denied' );
wp_set_current_user( $admin->ID );
$before_events = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}uwcmp_events" );
$response = $server->dispatch( $request );
verify( $response->get_status() === 200 && $response->get_data()['action'] === 'pending', 'authorized REST simulation' );
verify( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}uwcmp_events" ) === $before_events, 'simulation has no audit side effects' );
$request->set_param( 'content', str_repeat( 'a', 262145 ) );
verify( $server->dispatch( $request )->get_status() === 400, 'oversized REST content rejected' );
$post_request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
$post_request->set_body_params( [ 'title' => 'badword', 'status' => 'publish' ] );
$response = $server->dispatch( $post_request );
verify( $response->get_status() === 201 && get_post_status( $response->get_data()['id'] ) === 'pending', 'REST post cannot bypass hold' );
$comment_request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
$comment_request->set_body_params( [ 'post' => $comment_post, 'content' => 'badword', 'status' => 'approved' ] );
$response = $server->dispatch( $comment_request );
verify( $response->get_status() === 201 && (string) get_comment( $response->get_data()['id'] )->comment_approved === '0', 'REST explicit approval cannot bypass fallback: ' . json_encode( $response->get_data() ) );

$audit = new AuditStore( $wpdb );
$events = $audit->page( 0, 5 );
verify( count( $events ) === 5, 'audit page bounded' );
$older = $audit->page( (int) end( $events )['id'], 5 );
verify( $older !== [] && (int) $older[0]['id'] < (int) end( $events )['id'], 'keyset pagination' );
$all_metadata = implode( '', $wpdb->get_col( "SELECT metadata FROM {$wpdb->prefix}uwcmp_events" ) );
verify( ! str_contains( $all_metadata, 'badword' ) && ! str_contains( $all_metadata, '192.0.2.4' ) && ! str_contains( $all_metadata, 'writer@example.test' ), 'raw text and PII excluded from audit' );
$privacy = new PersonalData( $wpdb );
verify( $privacy->export( $admin->user_email )['data'] !== [], 'personal data exporter returns authored audit' );
$erased = $privacy->erase( $admin->user_email );
verify( $erased['items_removed'] && $erased['done'], 'privacy erasure removes references' );
verify( $privacy->export( $admin->user_email )['data'] === [], 'erased audit no longer linked to user' );
$audit->append( 'fixture', 'post', '', 0, $version, 'allow', [] );
$wpdb->query( "UPDATE {$wpdb->prefix}uwcmp_events SET created_at='2000-01-01 00:00:00' ORDER BY id DESC LIMIT 1" );
verify( $audit->cleanup() === 1, 'bounded retention removes expired audit' );

// Integrity failure must hold publication and never expose raw SQL in the result.
$wpdb->query( $wpdb->prepare( 'UPDATE %i SET checksum=%s WHERE id=%d', $wpdb->prefix . 'uwcmp_policy_versions', str_repeat( '0', 64 ), $version ) );
try {
	( new PolicyStore( $wpdb ) )->active();
	throw new RuntimeException( 'Corrupt policy accepted.' );
} catch ( RuntimeException $error ) {
	verify( str_contains( $error->getMessage(), 'integrity' ), 'policy checksum corruption rejected' );
}
$wpdb->query( $wpdb->prepare( 'UPDATE %i SET checksum=SHA2(payload,256) WHERE id=%d', $wpdb->prefix . 'uwcmp_policy_versions', $version ) );
echo json_encode( [ 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'database' => $wpdb->db_version(), 'assertions' => $assertions, 'result' => 'passed' ], JSON_PRETTY_PRINT ) . "\n";
