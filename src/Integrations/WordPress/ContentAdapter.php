<?php
declare(strict_types=1);
namespace UWCMP\Integrations\WordPress;

use UWCMP\Application\Services\ModerationCoordinator;
use UWCMP\Domain\Content\ContentField;
use UWCMP\Domain\Content\ContentPayload;
use UWCMP\Domain\Content\ModerationContext;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Moderation\ModerationRequest;
use UWCMP\Infrastructure\Database\AuditStore;
use UWCMP\Infrastructure\Database\PolicyStore;

final class ContentAdapter {
	private const TOKEN = '_uwcmp_operation';
	private ModerationCoordinator $coordinator;
	private array $pending            = array();
	private array $post_operations    = array();
	private array $comment_operations = array();
	private array $enforcing          = array();
	public function __construct( private readonly PolicyStore $policies, AuditStore $audit ) {
		$this->coordinator = new ModerationCoordinator( $policies, $audit );
	}
	public function register(): void {
		add_filter( 'wp_insert_post_empty_content', array( $this, 'begin_post' ), 99, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'post' ), 99, 2 );
		add_action( 'wp_insert_post', array( $this, 'inserted_post' ), 99, 3 );
		add_filter( 'preprocess_comment', array( $this, 'begin_comment' ), 99 );
		add_filter( 'pre_comment_approved', array( $this, 'comment_approval' ), 99, 2 );
		add_filter( 'wp_update_comment_data', array( $this, 'comment' ), 99, 2 );
		add_filter( 'rest_preprocess_comment', array( $this, 'rest_preprocess' ), 99, 2 );
		add_filter( 'rest_pre_insert_comment', array( $this, 'rest_comment' ), 99, 2 );
		add_action( 'wp_insert_comment', array( $this, 'inserted_comment' ), 99, 2 );
		add_action( 'edit_comment', array( $this, 'edited_comment' ), 99, 2 );
		add_action( 'transition_comment_status', array( $this, 'transitioned_comment' ), 99, 3 );
	}
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress hook signature.
	public function begin_post( bool $is_empty, array $data ): bool {
		if ( count( $this->post_operations ) >= 128 ) {
			$old = array_shift( $this->post_operations );
			unset( $this->pending[ $old['key'] ] );
		}
		$this->post_operations[] = array( 'key' => wp_generate_uuid4(), 'fingerprint' => null );
		return $is_empty;
	}
	private function post_fields( array $data, bool $slashed ): array {
		$fields = array();
		foreach ( array( 'post_title' => 'title', 'post_content' => 'body', 'post_excerpt' => 'excerpt', 'post_name' => 'slug' ) as $key => $name ) {
			$value           = (string) ( $data[ $key ] ?? '' );
			$fields[ $name ] = $slashed ? wp_unslash( $value ) : $value;
		}
		return $fields;
	}
	private function supported_post( array $data ): bool {
		return ! in_array( $data['post_type'] ?? '', array( 'revision', 'attachment', 'nav_menu_item' ), true ) && in_array( $data['post_status'] ?? '', array( 'publish', 'future', 'private' ), true );
	}
	public function post( array $data, array $postarr ): array {
		$slot = array_key_last( $this->post_operations );
		if ( $slot === null ) {
			$this->begin_post( false, $postarr );
			$slot = array_key_last( $this->post_operations );
		}
		$key = $this->post_operations[ $slot ]['key'];
		$id  = (int) ( $postarr['ID'] ?? 0 );
		if ( $this->supported_post( $data ) && $this->prepare( $key, $this->post_fields( $data, true ), (string) $data['post_type'], 'wordpress.post', (int) ( $data['post_author'] ?? 0 ), $id ) ) {
			$data['post_status'] = 'pending';
		}
		$this->post_operations[ $slot ]['fingerprint'] = $this->post_fingerprint( $data, true );
		return $data;
	}
	private function post_fingerprint( array $data, bool $slashed ): string {
		return $this->digest( array_merge( $this->post_fields( $data, $slashed ), array( '_type' => (string) $data['post_type'], '_author' => (string) (int) $data['post_author'], '_status' => (string) $data['post_status'], '_site' => (string) get_current_blog_id() ) ) );
	}
	private function digest( array $fields ): string {
		$state = hash_init( 'sha256' );
		foreach ( $fields as $name => $value ) {
			hash_update( $state, $name . ':' . strlen( $value ) . ':' );
			hash_update( $state, $value );
		}
		return hash_final( $state );
	}
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress hook signature.
	public function inserted_post( int $id, \WP_Post $post, bool $update ): void {
		if ( isset( $this->enforcing[ 'post:' . $id ] ) ) {
			return;
		}
		$fingerprint = $this->post_fingerprint( $post->to_array(), false );
		$key         = null;
		foreach ( array_reverse( $this->post_operations, true ) as $slot => $operation ) {
			if ( $operation['fingerprint'] === $fingerprint ) {
				$key = $operation['key'];
				unset( $this->post_operations[ $slot ] );
				break;
			}
		}
		if ( $key === null ) {
			if ( ! $this->supported_post( $post->to_array() ) ) {
				return;
			}
			$key = wp_generate_uuid4();
			$this->prepare( $key, $this->post_fields( $post->to_array(), false ), $post->post_type, 'wordpress.post', (int) $post->post_author, $id );
		}
		$operation = $this->pending[ $key ] ?? null;
		if ( $operation === null ) {
			return;
		}
		if ( $operation['hold'] && in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) ) {
			$this->enforcing[ 'post:' . $id ] = true;
			try {
				$changed = wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ), true );
				if ( is_wp_error( $changed ) || $changed === 0 ) {
					$this->failure( 'post_hold_failed' );
				}
			} finally {
				unset( $this->enforcing[ 'post:' . $id ] );
			}
		}
		$this->complete( $key, $id, (string) get_post_status( $id ) );
	}
	public function begin_comment( array $data ): array {
		// A temporary server-generated UUID connects repeated core filters to this insertion.
		if ( ! isset( $data['comment_meta'] ) || ! is_array( $data['comment_meta'] ) ) {
			$data['comment_meta'] = array();
		}
		$data['comment_meta'][ self::TOKEN ] = wp_generate_uuid4();
		return $data;
	}
	public function comment_approval( int|string|\WP_Error $approved, array $data ): int|string|\WP_Error {
		if ( $approved instanceof \WP_Error || in_array( (string) $approved, array( 'spam', 'trash' ), true ) ) {
			return $approved;
		}
		$key = $this->token( $data ) ?? wp_generate_uuid4();
		return $this->prepare_comment( $key, wp_unslash( $data ) ) ? 0 : $approved;
	}
	public function rest_preprocess( array $data, \WP_REST_Request $request ): array {
		$id = (int) $request->get_param( 'id' );
		if ( $id === 0 ) {
			return $this->begin_comment( $data );
		}
		$existing = get_comment( $id );
		if ( ! $existing ) {
			return $data;
		}
		// REST updates can contain only a status or one field. Evaluate the resulting complete object.
		$merged = array_merge( $existing->to_array(), $data );
		try {
			$hold = $this->policies->engine()->check( $this->request( $this->comment_fields( $merged ), 'comment', 'wordpress.comment', (int) $merged['user_id'], $id ) )->action === Action::PENDING;
		} catch ( \Throwable $error ) {
			$hold = $this->failure_hold();
		}
		if ( $hold && ! in_array( $request->get_param( 'status' ), array( 'spam', 'trash' ), true ) ) {
			$request->set_param( 'status', 'hold' );
			$data['comment_approved'] = 0; // Also routes status-only updates through the audited update hook.
		}
		return $data;
	}
	public function rest_comment( array|\WP_Error $prepared, \WP_REST_Request $request ): array|\WP_Error {
		if ( $prepared instanceof \WP_Error ) {
			return $prepared;
		}
		if ( ! in_array( (string) ( $prepared['comment_approved'] ?? '' ), array( 'spam', 'trash' ), true ) ) {
			$key = $this->token( $prepared ) ?? wp_generate_uuid4();
			if ( $this->prepare_comment( $key, $prepared ) ) {
				$prepared['comment_approved'] = 0;
				if ( ! in_array( $request->get_param( 'status' ), array( 'spam', 'trash' ), true ) ) {
					$request->set_param( 'status', 'hold' );
				}
			}
		}
		return $prepared;
	}
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress hook signature.
	public function comment( array|\WP_Error $data, array $original ): array|\WP_Error {
		if ( $data instanceof \WP_Error || in_array( (string) ( $data['comment_approved'] ?? '' ), array( 'spam', 'trash' ), true ) ) {
			return $data;
		}
		$key                             = wp_generate_uuid4();
		$id                              = (int) ( $data['comment_ID'] ?? 0 );
		$this->comment_operations[ $id ] = $key;
		if ( $this->prepare_comment( $key, $data ) ) {
			$data['comment_approved'] = 0;
		}
		return $data;
	}
	public function inserted_comment( int $id, \WP_Comment $comment ): void {
		$key = (string) get_comment_meta( $id, self::TOKEN, true );
		if ( ! isset( $this->pending[ $key ] ) ) {
			if ( in_array( (string) $comment->comment_approved, array( 'spam', 'trash' ), true ) ) {
				delete_comment_meta( $id, self::TOKEN );
				return;
			}
			$key = wp_generate_uuid4();
			$this->prepare_comment( $key, $comment->to_array() );
		}
		$this->finish_comment( $key, $id );
		delete_comment_meta( $id, self::TOKEN );
	}
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress hook signature.
	public function edited_comment( int $id, array $data ): void {
		$key = $this->comment_operations[ $id ] ?? null;
		unset( $this->comment_operations[ $id ] );
		if ( $key !== null ) {
			$this->finish_comment( $key, $id );
		}
	}
	public function transitioned_comment( string $status, string $previous, \WP_Comment $comment ): void {
		if ( $status !== 'approved' || $status === $previous ) {
			return;
		}
		$key = wp_generate_uuid4();
		$this->prepare_comment( $key, $comment->to_array() );
		$this->finish_comment( $key, (int) $comment->comment_ID );
	}
	private function finish_comment( string $key, int $id ): void {
		$comment = get_comment( $id );
		if ( ! $comment || ! isset( $this->pending[ $key ] ) ) {
			return;
		}
		if ( $this->pending[ $key ]['hold'] && (string) $comment->comment_approved === '1' ) {
			// Low-level insertion is already persisted here; its visibility gap remains a release blocker.
			if ( ! wp_set_comment_status( $id, 'hold' ) ) {
				$this->failure( 'comment_hold_failed' );
			}
		}
		$this->complete( $key, $id, (string) get_comment( $id )->comment_approved );
	}
	private function token( array $data ): ?string {
		$value = $data['comment_meta'][ self::TOKEN ] ?? null;
		return is_string( $value ) ? $value : null;
	}
	private function comment_fields( array $data ): array {
		return array( 'body' => (string) ( $data['comment_content'] ?? '' ), 'author' => (string) ( $data['comment_author'] ?? '' ), 'url' => (string) ( $data['comment_author_url'] ?? '' ) );
	}
	private function prepare_comment( string $key, array $data ): bool {
		return $this->prepare( $key, $this->comment_fields( $data ), 'comment', 'wordpress.comment', (int) ( $data['user_id'] ?? 0 ), (int) ( $data['comment_ID'] ?? 0 ) );
	}
	private function request( array $fields, string $type, string $source, int $actor_id, int $id ): ModerationRequest {
		$actor   = Actors::from_user_id( $actor_id );
		$content = array();
		foreach ( $fields as $name => $text ) {
			$content[] = new ContentField( $name, $text, in_array( $name, array( 'body', 'excerpt' ), true ) ? 'html' : 'text' );
		}
		return new ModerationRequest( new ContentPayload( $content ), new ModerationContext( $type, $source, $actor, get_current_blog_id(), $id === 0 ? '' : (string) $id ) );
	}
	private function prepare( string $key, array $fields, string $type, string $source, int $actor_id, int $id ): bool {
		$fingerprint = $this->digest( array_merge( $fields, array( '_type' => $type, '_actor' => (string) $actor_id, '_object' => (string) $id, '_site' => (string) get_current_blog_id() ) ) );
		if ( isset( $this->pending[ $key ] ) && $this->pending[ $key ]['fingerprint'] === $fingerprint ) {
			return $this->pending[ $key ]['hold'];
		}
		$event  = null;
		$shadow = false;
		try {
			$shadow = $this->policies->active()->shadow;
			if ( count( $this->pending ) >= 128 ) {
				unset( $this->pending[ array_key_first( $this->pending ) ] );
				throw new \RuntimeException( 'Too many unfinished submissions.' );
			}
			$request = $this->request( $fields, $type, $source, $actor_id, $id );
			$event   = $this->coordinator->prepare( $request );
			$hold    = $event->result->action === Action::PENDING;
			if ( ! $event->result->detection->complete ) {
				$this->failure( 'analysis_incomplete' );
			}
			try {
				do_action( 'uwcmp_result', $event->result, $request );
			} catch ( \Throwable $error ) {
				$this->failure( 'result_observer_failed' );
			}
		} catch ( \Throwable $error ) {
			$this->failure( 'submission_review_required' );
			$hold = ! $shadow;
		}
		$this->pending[ $key ] = array( 'event' => $event, 'hold' => $hold, 'fingerprint' => $fingerprint, 'shadow' => $shadow );
		return $hold;
	}
	private function complete( string $key, int $id, string $status ): void {
		$operation = $this->pending[ $key ];
		unset( $this->pending[ $key ] );
		if ( $operation['event'] !== null ) {
			try {
				$this->coordinator->commit( $operation['event'], (string) $id, $status );
			} catch ( \Throwable $error ) {
				$this->failure( 'audit_completion_failed' );
				if ( ! $operation['shadow'] && in_array( $status, array( 'publish', 'future', 'private', '1' ), true ) ) {
					if ( $operation['event']->context->content_type === 'comment' ) {
						wp_set_comment_status( $id, 'hold' );
					} else {
						$this->enforcing[ 'post:' . $id ] = true;
						try {
							wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ), true );
						} finally {
							unset( $this->enforcing[ 'post:' . $id ] );
						}
					}
				}
			}
		}
	}
	private function failure_hold(): bool {
		$this->failure( 'submission_review_required' );
		try {
			return ! $this->policies->active()->shadow;
		} catch ( \Throwable $error ) {
			return true;
		}
	}
	private function failure( string $code ): void {
		try {
			do_action( 'uwcmp_failure', $code );
		} catch ( \Throwable $error ) {
			// Observers cannot turn a handled failure into a WordPress fatal error.
			return;
		}
	}
}
