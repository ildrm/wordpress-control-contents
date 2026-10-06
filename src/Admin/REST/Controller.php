<?php
declare(strict_types=1);
namespace UWCMP\Admin\REST;

use UWCMP\Integrations\WordPress\Actors;
use UWCMP\Domain\Content\ContentField;
use UWCMP\Domain\Content\ContentPayload;
use UWCMP\Domain\Content\ModerationContext;
use UWCMP\Domain\Moderation\ModerationRequest;
use UWCMP\Infrastructure\Database\AuditStore;
use UWCMP\Infrastructure\Database\PolicyStore;

final class Controller {
	public function __construct( private readonly PolicyStore $policies, private readonly AuditStore $audit ) {}
	public function register(): void {
		register_rest_route(
			'uwcmp/v1',
			'/check',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn(): bool => current_user_can( 'uwcmp_edit_policies' ),
				'callback'            => array( $this, 'check' ),
				'args'                => array(
					'content'      => array(
						'type'              => 'string',
						'required'          => true,
						'maxLength'         => 262144,
						'validate_callback' => static fn( mixed $value ): bool => is_string( $value ) && strlen( $value ) <= 262144 && mb_check_encoding( $value, 'UTF-8' ),
					),
					'content_type' => array(
						'type'    => 'string',
						'default' => 'comment',
						'pattern' => '^[a-z][a-z0-9_.-]{0,63}$',
					),
				),
			)
		);
		register_rest_route(
			'uwcmp/v1',
			'/events',
			array(
				'methods'             => 'GET',
				'permission_callback' => static fn(): bool => current_user_can( 'uwcmp_view_audit' ),
				'callback'            => array( $this, 'events' ),
				'args'                => array(
					'before' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
					'limit'  => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);
	}
	public function check( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			$user   = wp_get_current_user();
			$input  = new ModerationRequest( new ContentPayload( array( new ContentField( 'body', $request->get_param( 'content' ), 'html' ) ) ), new ModerationContext( $request->get_param( 'content_type' ), 'admin.playground', Actors::from_user_id( $user->ID ), get_current_blog_id() ) );
			$result = $this->policies->engine()->check( $input );
			return new \WP_REST_Response( $result->jsonSerialize() );
		} catch ( \InvalidArgumentException | \JsonException $error ) {
			return new \WP_Error( 'uwcmp_invalid_input', __( 'Invalid moderation input or policy.', 'universal-content-moderation' ), array( 'status' => 400 ) );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'uwcmp_unavailable', __( 'Moderation is unavailable. Check system health.', 'universal-content-moderation' ), array( 'status' => 503 ) );
		}
	}
	public function events( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			return new \WP_REST_Response( $this->audit->page( $request->get_param( 'before' ), $request->get_param( 'limit' ) ) );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'uwcmp_unavailable', __( 'Audit storage is unavailable.', 'universal-content-moderation' ), array( 'status' => 503 ) );
		}
	}
}
