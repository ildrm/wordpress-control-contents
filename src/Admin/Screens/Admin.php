<?php
declare(strict_types=1);
namespace UWCMP\Admin\Screens;

use UWCMP\Application\Services\PolicyCodec;
use UWCMP\Admin\REST\Controller;
use UWCMP\Domain\Detection\Term;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Policy\Condition;
use UWCMP\Domain\Policy\Policy;
use UWCMP\Domain\Policy\Rule;
use UWCMP\Infrastructure\Database\AuditStore;
use UWCMP\Infrastructure\Database\PolicyStore;

/** Native, server-rendered preview screens; full operations UI follows in later phases. */
final class Admin {
	public function __construct( private readonly PolicyStore $policies, private readonly AuditStore $audit ) {}
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_uwcmp_save', array( $this, 'save' ) );
	}
	public function menu(): void {
		add_menu_page( __( 'Content Moderation', 'universal-content-moderation' ), __( 'Moderation', 'universal-content-moderation' ), 'uwcmp_view_moderation', 'uwcmp', array( $this, 'dashboard' ), 'dashicons-shield' );
		add_submenu_page( 'uwcmp', __( 'Policy', 'universal-content-moderation' ), __( 'Policy', 'universal-content-moderation' ), 'uwcmp_edit_policies', 'uwcmp-policy', array( $this, 'policy' ) );
		add_submenu_page( 'uwcmp', __( 'Playground', 'universal-content-moderation' ), __( 'Playground', 'universal-content-moderation' ), 'uwcmp_edit_policies', 'uwcmp-playground', array( $this, 'playground' ) );
		add_submenu_page( 'uwcmp', __( 'Audit', 'universal-content-moderation' ), __( 'Audit', 'universal-content-moderation' ), 'uwcmp_view_audit', 'uwcmp-audit', array( $this, 'events' ) );
	}
	private function require_capability( string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access moderation.', 'universal-content-moderation' ), '', array( 'response' => 403 ) );
		}
	}
	public function dashboard(): void {
		$this->require_capability( 'uwcmp_view_moderation' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Content Moderation', 'universal-content-moderation' ) . '</h1><p>' . esc_html__( 'Development preview. Local processing only. No AI, cloud processing, or telemetry.', 'universal-content-moderation' ) . '</p>';
		try {
			$policy = $this->policies->active();
			echo '<p>' . esc_html( sprintf( /* translators: 1: policy version, 2: moderation mode. */ __( 'Active version: %1$d. Mode: %2$s.', 'universal-content-moderation' ), $policy->version, $policy->shadow ? __( 'Shadow: analysis without content changes', 'universal-content-moderation' ) : __( 'Review: matching submissions are held', 'universal-content-moderation' ) ) ) . '</p>';
		} catch ( \Throwable $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Policy storage is unavailable. Submissions require manual review until storage is restored.', 'universal-content-moderation' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Use WordPress Comments and Posts screens to review held content. Audit retention is 30 days. Network activation and the full moderation operations workflow are not yet available.', 'universal-content-moderation' ) . '</p></div>';
	}
	public function policy(): void {
		$this->require_capability( 'uwcmp_edit_policies' );
		try {
			$policy = $this->policies->active();
		} catch ( \Throwable $error ) {
			wp_die( esc_html__( 'Policy storage is unavailable.', 'universal-content-moderation' ) );
		}
		if ( ! $this->simple_policy( $policy ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Local review policy', 'universal-content-moderation' ) . '</h1><p role="status">' . esc_html__( 'This policy contains advanced rules, scopes or exceptions. Use the PHP API to edit it; the simple editor cannot replace it.', 'universal-content-moderation' ) . '</p></div>';
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Local review policy', 'universal-content-moderation' ) . '</h1><p>' . esc_html__( 'Edit a whole-word review policy here. Advanced policies are protected from replacement; manage them through the PHP API. Existing versions remain in storage. Test in shadow mode before enabling holds.', 'universal-content-moderation' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'uwcmp_save' );
		echo '<input type="hidden" name="action" value="uwcmp_save"><input type="hidden" name="version" value="' . esc_attr( (string) $policy->version ) . '"><p><label for="uwcmp-terms">' . esc_html__( 'Prohibited words and phrases, one per line (maximum 1,000)', 'universal-content-moderation' ) . '</label></p><textarea id="uwcmp-terms" name="terms" rows="12" cols="70" style="width:100%;max-width:50rem;box-sizing:border-box" maxlength="262144" aria-describedby="uwcmp-help">' . esc_textarea( implode( "\n", array_map( static fn( Term $term ): string => $term->text, $policy->terms ) ) ) . '</textarea><p id="uwcmp-help">' . esc_html__( 'Whole-word matching avoids substrings inside innocent words. Matching content is sent to review; it is never deleted. Phrase exceptions and advanced policies are available through the PHP API.', 'universal-content-moderation' ) . '</p><p><label><input type="checkbox" name="shadow" value="1" ' . checked( $policy->shadow, true, false ) . '> ' . esc_html__( 'Shadow mode: record proposed decisions without changing content', 'universal-content-moderation' ) . '</label></p>';
		submit_button( __( 'Save a new policy version', 'universal-content-moderation' ) );
		echo '</form></div>';
	}
	private function simple_policy( Policy $policy ): bool {
		if ( count( $policy->terms ) > 1000 || count( $policy->rules ) > 1 ) {
			return false;
		}
		foreach ( $policy->terms as $term ) {
			if ( $term->category !== 'custom' || $term->severity !== 50 || $term->mode !== 'word' || $term->fields !== array() || $term->content_types !== array() || $term->exceptions !== array() ) {
				return false;
			}
		}
		if ( $policy->rules === array() ) {
			return $policy->terms === array();
		}
		$rule      = $policy->rules[0];
		$condition = $rule->condition->jsonSerialize();
		return $rule->enabled && $rule->action === Action::PENDING && $rule->priority === 0 && ( $condition['field'] ?? null ) === 'detection.count' && ( $condition['op'] ?? null ) === 'gt' && ( ( $condition['value'] ?? null ) === 0 || ( $condition['value'] ?? null ) === 0.0 );
	}
	public function save(): void {
		$this->require_capability( 'uwcmp_edit_policies' );
		check_admin_referer( 'uwcmp_save' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below and by Term/PolicyCodec; preserve originals.
		$raw     = isset( $_POST['terms'] ) && is_string( $_POST['terms'] ) ? wp_unslash( $_POST['terms'] ) : '';
		$version = isset( $_POST['version'] ) && is_string( $_POST['version'] ) ? filter_var( wp_unslash( $_POST['version'] ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) : false;
		try {
			if ( ! isset( $_POST['terms'] ) || ! is_string( $_POST['terms'] ) || ( isset( $_POST['shadow'] ) && $_POST['shadow'] !== '1' ) ) {
				throw new \InvalidArgumentException( 'Malformed policy fields.' );
			}
			$active = $this->policies->active();
			if ( ! $this->simple_policy( $active ) ) {
				throw new \InvalidArgumentException( 'Advanced policies require the PHP API.' );
			}
			if ( strlen( $raw ) > 262144 || ! mb_check_encoding( $raw, 'UTF-8' ) || $version === false ) {
				throw new \InvalidArgumentException( 'Invalid policy input.' );
			}
			$split = preg_split( '/\R/u', $raw );
			if ( $split === false ) {
				throw new \InvalidArgumentException( 'Invalid dictionary input.' );
			}
			$lines = array_values( array_unique( array_filter( array_map( 'trim', $split ), static fn( string $line ): bool => $line !== '' ) ) );
			if ( count( $lines ) > 1000 ) {
				throw new \InvalidArgumentException( 'Dictionary limit exceeded.' );
			}
			$existing = array();
			foreach ( $active->terms as $term ) {
				$existing[ $term->text ] = $term;
			}
			$terms  = array_map( static fn( string $text ): Term => $existing[ $text ] ?? new Term( 'term-' . substr( hash( 'sha256', $text ), 0, 32 ), $text ), $lines );
			$rule   = new Rule(
				'review-matched-content',
				Condition::from_array(
					array(
						'field' => 'detection.count',
						'op'    => 'gt',
						'value' => 0,
					)
				),
				Action::PENDING
			);
			$policy = new Policy( 0, $terms, $active->rules === array() ? array( $rule ) : $active->rules, isset( $_POST['shadow'] ) && $_POST['shadow'] === '1' );
			$this->policies->save( $policy, $version, get_current_user_id() );
		} catch ( \Throwable $error ) {
			wp_die( esc_html__( 'Policy could not be saved. Check the input and reload the policy to avoid overwriting another editor’s changes.', 'universal-content-moderation' ), '', array( 'response' => 409 ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=uwcmp-policy' ) );
		exit;
	}
	public function playground(): void {
		$this->require_capability( 'uwcmp_edit_policies' );
		$result  = null;
		$content = '';
		if ( isset( $_POST['uwcmp_test'] ) ) {
			check_admin_referer( 'uwcmp_test' );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ContentField validates size and UTF-8; retain raw text for testing.
			$content = isset( $_POST['content'] ) && is_string( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
			$request = new \WP_REST_Request( 'POST' );
			$request->set_param( 'content', $content );
			$request->set_param( 'content_type', 'comment' );
			$result = ( new Controller( $this->policies, $this->audit ) )->check( $request );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Moderation playground', 'universal-content-moderation' ) . '</h1><p>' . esc_html__( 'Samples are processed locally and are not stored or enforced.', 'universal-content-moderation' ) . '</p><form method="post">';
		wp_nonce_field( 'uwcmp_test' );
		echo '<p><label for="uwcmp-sample">' . esc_html__( 'Sample comment', 'universal-content-moderation' ) . '</label></p><textarea id="uwcmp-sample" name="content" rows="6" cols="70" style="width:100%;max-width:50rem;box-sizing:border-box" maxlength="262144">' . esc_textarea( $content ) . '</textarea><input type="hidden" name="uwcmp_test" value="1">';
		submit_button( __( 'Test sample', 'universal-content-moderation' ) );
		echo '</form>';
		if ( $result instanceof \WP_Error ) {
			echo '<p role="alert">' . esc_html( $result->get_error_message() ) . '</p>';
		} elseif ( $result instanceof \WP_REST_Response ) {
			echo '<h2>' . esc_html__( 'Result', 'universal-content-moderation' ) . '</h2><pre dir="ltr" style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html( (string) wp_json_encode( $result->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';
		}
		echo '</div>';
	}
	public function events(): void {
		$this->require_capability( 'uwcmp_view_audit' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination; capability checked above.
		$before = isset( $_GET['before'] ) && is_string( $_GET['before'] ) ? absint( $_GET['before'] ) : 0;
		echo '<div class="wrap"><h1>' . esc_html__( 'Audit log', 'universal-content-moderation' ) . '</h1>';
		try {
			$rows = $this->audit->page( $before );
			echo '<div role="region" aria-label="' . esc_attr__( 'Moderation events', 'universal-content-moderation' ) . '" tabindex="0" style="max-width:100%;overflow-x:auto"><table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Recent moderation events', 'universal-content-moderation' ) . '</caption><thead><tr>';
			foreach ( array( 'ID', 'Type', 'Object', 'Decision', 'Policy', 'Time (UTC)' ) as $heading ) {
				echo '<th scope="col">' . esc_html( $heading ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$metadata = json_decode( (string) $row['metadata'], true, 16 );
				$decision = (string) $row['action'];
				if ( is_array( $metadata ) && ( $metadata['shadow'] ?? false ) === true && is_string( $metadata['proposed_action'] ?? null ) ) {
					/* translators: %s: proposed moderation action. */
					$decision = sprintf( __( 'Shadow proposal: %s', 'universal-content-moderation' ), $metadata['proposed_action'] );
				}
				if ( is_array( $metadata ) && ( $metadata['complete'] ?? true ) === false ) {
					/* translators: %s: moderation action. */
					$decision = sprintf( __( '%s (analysis incomplete)', 'universal-content-moderation' ), $decision );
				}
				echo '<tr>';
				foreach ( array( $row['id'], $row['kind'], $row['object_type'] . ':' . $row['object_id'], $decision, $row['policy_version'], $row['created_at'] ) as $value ) {
					echo '<td>' . esc_html( (string) $value ) . '</td>';
				}
				echo '</tr>';
			}
			if ( $rows === array() ) {
				echo '<tr><td colspan="6">' . esc_html__( 'No moderation events yet.', 'universal-content-moderation' ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
			if ( count( $rows ) === 50 ) {
				echo '<p><a href="' . esc_url(
					add_query_arg(
						array(
							'page'   => 'uwcmp-audit',
							'before' => end( $rows )['id'],
						),
						admin_url( 'admin.php' )
					)
				) . '">' . esc_html__( 'Older events', 'universal-content-moderation' ) . '</a></p>';
			}
		} catch ( \Throwable $error ) {
			echo '<p role="alert">' . esc_html__( 'Audit storage is unavailable.', 'universal-content-moderation' ) . '</p>';
		}
		echo '</div>';
	}
}
