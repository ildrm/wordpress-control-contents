<?php
if ( PHP_SAPI !== 'cli' || getenv( 'UWCMP_INTEGRATION_TEST' ) !== '1' ) {
	exit( 1 );
}
require '/var/www/html/wp-load.php';
use UWCMP\Domain\Detection\Term;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Policy\Condition;
use UWCMP\Domain\Policy\Policy;
use UWCMP\Domain\Policy\Rule;
use UWCMP\Infrastructure\Database\PolicyStore;

$admin = get_user_by( 'login', 'uwcmp_test_admin' );
wp_set_current_user( $admin->ID );
$store = new PolicyStore( $wpdb );
$rule = new Rule( 'review', Condition::from_array( array( 'field' => 'detection.count', 'op' => 'gt', 'value' => 0 ) ), Action::PENDING );
$store->save( new Policy( 0, array( new Term( 'sentinel', 'badword' ) ), array( $rule ), true ), $store->active()->version, $admin->ID );
$post = wp_insert_post( array( 'post_title' => 'badword', 'post_status' => 'publish' ), true );
if ( ! is_int( $post ) || get_post_status( $post ) !== 'publish' ) {
	throw new RuntimeException( 'Shadow mode changed post status.' );
}
$comment = wp_insert_comment( array( 'comment_post_ID' => $post, 'comment_content' => 'badword', 'comment_approved' => 1 ) );
if ( (string) get_comment( $comment )->comment_approved !== '1' ) {
	throw new RuntimeException( 'Shadow mode changed comment status.' );
}
$row = $wpdb->get_row( $wpdb->prepare( "SELECT action,metadata FROM %i WHERE kind='decision' AND object_type='comment' AND object_id=%s", $wpdb->prefix . 'uwcmp_events', (string) $comment ), ARRAY_A );
$metadata = json_decode( $row['metadata'], true );
if ( $row['action'] !== 'allow' || $metadata['proposed_action'] !== 'pending' || ! $metadata['shadow'] ) {
	throw new RuntimeException( 'Shadow proposal was not audited correctly.' );
}
echo "Native shadow post/comment status and proposed audit decision passed (3 checks).\n";
