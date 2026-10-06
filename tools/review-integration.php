<?php
declare(strict_types=1);
if ( PHP_SAPI !== 'cli' || getenv( 'UWCMP_INTEGRATION_TEST' ) !== '1' ) {
	exit( 1 );
}
require '/var/www/html/wp-load.php';
use UWCMP\Domain\Detection\Term;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Policy\Condition;
use UWCMP\Domain\Policy\Policy;
use UWCMP\Domain\Policy\Rule;
use UWCMP\Infrastructure\Database\AuditStore;
use UWCMP\Infrastructure\Database\PolicyStore;
use UWCMP\Privacy\PersonalData;

$assertions = 0;
function review_verify( bool $condition, string $label ): void {
	global $assertions;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAILED: ' . $label );
	}
	++$assertions;
}
function decisions( string $type, int $id ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE kind='decision' AND object_type=%s AND object_id=%s", $wpdb->prefix . 'uwcmp_events', $type, (string) $id ), ARRAY_A );
}
$admin = get_user_by( 'login', 'uwcmp_test_admin' );
wp_set_current_user( $admin->ID );
$wpdb->suppress_errors( true );
$store = new PolicyStore( $wpdb );
$rule = new Rule( 'review', Condition::from_array( array( 'field' => 'detection.count', 'op' => 'gt', 'value' => 0 ) ), Action::PENDING );
$policy = new Policy( 0, array( new Term( 'sentinel', 'badword' ) ), array( $rule ), false );
$store->save( $policy, $store->active()->version, $admin->ID );
$store->active();
$prefix = $wpdb->prefix;
$event_start = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$prefix}uwcmp_events" );
try {
	$wpdb->prefix = 'missing_review_site_';
	for ( $i = 0; $i < 2; ++$i ) {
		try {
			$store->active();
			throw new LogicException( 'Stale policy leaked across sites.' );
		} catch ( RuntimeException $error ) {
			review_verify( str_contains( $error->getMessage(), 'unavailable' ), 'repeated failed site load rejects stale cache' );
		}
	}
} finally {
	$wpdb->prefix = $prefix;
}
$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}uwcmp_policy_versions" );
$wpdb->query( 'START TRANSACTION' );
$wpdb->insert( $prefix . 'uwcmp_policy_versions', array( 'payload' => '{}', 'checksum' => str_repeat( '0', 64 ), 'created_by' => 0, 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );
try {
	$store->save( $policy, $store->active()->version, $admin->ID );
	throw new LogicException( 'Nested transaction accepted.' );
} catch ( RuntimeException $error ) {
	review_verify( str_contains( $error->getMessage(), 'independent transaction' ), 'nested save rejected without committing caller transaction' );
} finally {
	$wpdb->query( 'ROLLBACK' );
}
review_verify( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}uwcmp_policy_versions" ) === $before, 'caller transaction remains rollbackable' );

$bad = wp_insert_post( array( 'post_title' => 'b<strong>ad</strong>word', 'post_status' => 'publish', 'post_content' => 'badword' ), true, false );
review_verify( get_post_status( $bad ) === 'pending', 'held without optional after-insert hooks' );
review_verify( count( decisions( 'post', $bad ) ) === 1, 'post committed ID bound once' );
$deep = wp_insert_post( array( 'post_title' => 'Deep HTML fixture', 'post_content' => str_repeat( '<div>', 300 ) . 'badword' . str_repeat( '</div>', 300 ), 'post_status' => 'publish' ), true );
$deep_events = decisions( 'post', $deep );
review_verify( get_post_status( $deep ) === 'pending' && count( $deep_events ) === 1 && json_decode( $deep_events[0]['metadata'], true )['complete'] === false, 'analysis failure is held and audited as incomplete' );
$target = wp_insert_post( array( 'post_title' => 'Review comments', 'post_status' => 'publish', 'comment_status' => 'open' ), true );
update_option( 'comment_moderation', 0 );
update_option( 'comment_previously_approved', 0 );
$comment = wp_new_comment( wp_slash( array( 'comment_post_ID' => $target, 'comment_content' => "Writer's badword", 'comment_author' => 'Writer', 'comment_author_email' => 'review@example.test', 'comment_author_url' => '', 'comment_author_IP' => '192.0.2.8' ) ), true );
review_verify( is_int( $comment ) && get_comment( $comment )->comment_content === "Writer's badword", 'comment input slashing preserved' );
review_verify( count( decisions( 'comment', $comment ) ) === 1, 'repeated standard comment filters produce one decision' );
review_verify( get_comment_meta( $comment, '_uwcmp_operation', true ) === '', 'temporary operation token removed' );
$first = wp_insert_comment( array( 'comment_post_ID' => $target, 'comment_content' => 'badword', 'comment_approved' => 1 ) );
$second = wp_insert_comment( array( 'comment_post_ID' => $target, 'comment_content' => 'badword', 'comment_approved' => 1 ) );
review_verify( $first !== $second && count( decisions( 'comment', $first ) ) === 1 && count( decisions( 'comment', $second ) ) === 1, 'genuine identical submissions retained separately' );
wp_set_comment_status( $first, 'approve' );
review_verify( (string) get_comment( $first )->comment_approved === '0', 'native status-only approval is held by transition fallback' );
review_verify( count( decisions( 'comment', $first ) ) === 2, 'native approval operation audited once' );

$server = rest_get_server();
$create = new WP_REST_Request( 'POST', '/wp/v2/comments' );
$create->set_body_params( array( 'post' => $target, 'content' => 'badword', 'status' => 'approved' ) );
$response = $server->dispatch( $create );
review_verify( $response->get_status() === 201, 'REST fixture inserted' );
$id = $response->get_data()['id'];
review_verify( count( decisions( 'comment', $id ) ) === 1, 'REST create produces one committed decision' );
$update = new WP_REST_Request( 'POST', '/wp/v2/comments/' . $id );
$update->set_body_params( array( 'status' => 'approved' ) );
$response = $server->dispatch( $update );
review_verify( $response->get_status() === 200 && (string) get_comment( $id )->comment_approved === '0', 'REST status-only approval cannot bypass moderation' );
review_verify( count( decisions( 'comment', $id ) ) === 2, 'REST status-only update audited once' );
$update->set_body_params( array( 'content' => 'b<strong>ad</strong>word', 'status' => 'approved' ) );
$response = $server->dispatch( $update );
review_verify( $response->get_status() === 200 && (string) get_comment( $id )->comment_approved === '0', 'REST partial content update cannot override hold' );

$observer = static function (): void { throw new RuntimeException( 'Observer fixture' ); };
add_action( 'uwcmp_result', $observer );
add_action( 'uwcmp_failure', $observer );
$clean = wp_insert_post( array( 'post_title' => 'Observer test', 'post_status' => 'publish' ), true );
review_verify( is_int( $clean ) && get_post_status( $clean ) === 'publish', 'throwing observers do not alter decision or crash request' );
remove_action( 'uwcmp_result', $observer );
remove_action( 'uwcmp_failure', $observer );

$attempts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}uwcmp_events WHERE kind='attempt'" );
$reject = static function ( array $data ): array { $data['missing_fixture_column'] = 'fail'; return $data; };
add_filter( 'wp_insert_post_data', $reject, 101 );
$failed = wp_insert_post( array( 'post_title' => 'Failed submission', 'post_status' => 'publish' ), true );
remove_filter( 'wp_insert_post_data', $reject, 101 );
review_verify( is_wp_error( $failed ), 'database failure fixture reached persistence' );
review_verify( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}uwcmp_events WHERE kind='attempt'" ) === $attempts + 1, 'failed insertion remains an uncommitted attempt' );
$draft = wp_insert_post( array( 'post_title' => 'Failed submission', 'post_status' => 'draft' ), true );
review_verify( decisions( 'post', $draft ) === array(), 'failed attempt cannot attach to a later draft' );

$audit = new AuditStore( $wpdb );
$request = new UWCMP\Domain\Moderation\ModerationRequest( new UWCMP\Domain\Content\ContentPayload( array( new UWCMP\Domain\Content\ContentField( 'body', 'safe' ) ) ), new UWCMP\Domain\Content\ModerationContext( 'post', 'test', new UWCMP\Domain\Content\Actor( $admin->ID ) ) );
$event = $audit->begin( $request, $store->engine()->check( $request ) );
$audit->commit( $event, (string) $clean, 'publish' );
$count = count( decisions( 'post', $clean ) );
$audit->commit( $event, (string) $clean, 'publish' );
review_verify( count( decisions( 'post', $clean ) ) === $count, 'repeated completion is idempotent' );
try {
	$audit->commit( $event, (string) $target, 'publish' );
	throw new LogicException( 'Event rebound to another object.' );
} catch ( RuntimeException $error ) {
	review_verify( str_contains( $error->getMessage(), 'completion failed' ), 'event cannot bind a second object' );
}

$erased_event = $audit->begin( $request, $store->engine()->check( $request ) );
$wpdb->query( $wpdb->prepare( "UPDATE %i SET actor_id=0,metadata='{}',object_id='' WHERE event_key=%s", $prefix . 'uwcmp_events', $erased_event->key ) );
try {
	$audit->commit( $erased_event, (string) $clean, 'publish' );
	throw new LogicException( 'Erased references resurrected.' );
} catch ( RuntimeException $error ) {
	review_verify( str_contains( $error->getMessage(), 'completion failed' ), 'concurrent erasure cannot be undone by late completion' );
}
$reject_completion = static fn( string $query ): string => str_contains( $query, "SET kind='decision'" ) ? 'UPDATE missing_review_table SET kind=1' : $query;
add_filter( 'query', $reject_completion );
$unaudited = wp_insert_post( array( 'post_title' => 'Audit failure fixture', 'post_status' => 'publish' ), true );
remove_filter( 'query', $reject_completion );
review_verify( is_int( $unaudited ) && get_post_status( $unaudited ) === 'pending', 'completion storage failure requires manual review' );
$actor = UWCMP\Integrations\WordPress\Actors::from_user_id( $admin->ID );
review_verify( $actor->account_age_seconds !== null && in_array( 'administrator', $actor->roles, true ), 'native and simulation actors have real roles and account age' );
$privacy = new PersonalData( $wpdb );
try {
	$wpdb->prefix = 'missing_review_site_';
	review_verify( is_wp_error( $privacy->export( $admin->user_email ) ), 'export failure returns WordPress error' );
	review_verify( is_wp_error( $privacy->erase( $admin->user_email ) ), 'erasure failure returns WordPress error' );
} finally {
	$wpdb->prefix = $prefix;
}
review_verify( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}uwcmp_events WHERE id>{$event_start} AND kind='decision' AND object_id=''" ) === 0, 'all new committed decisions have object IDs' );
echo json_encode( array( 'review_assertions' => $assertions, 'result' => 'passed' ), JSON_PRETTY_PRINT ) . "\n";
