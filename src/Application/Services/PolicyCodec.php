<?php
declare(strict_types=1);
namespace UWCMP\Application\Services;

use UWCMP\Domain\Detection\Term;
use UWCMP\Domain\Moderation\Action;
use UWCMP\Domain\Policy\Condition;
use UWCMP\Domain\Policy\Policy;
use UWCMP\Domain\Policy\Rule;

/** Strict portable policy format, without secrets or PHP serialization. */
final class PolicyCodec {
	public function decode( string $json, int $version, bool $compile = true ): Policy {
		if ( strlen( $json ) > 1048576 ) {
			throw new \InvalidArgumentException( 'Policy exceeds 1 MiB.' );
		}
		$data = json_decode( $json, true, 32, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) || array_diff( array_keys( $data ), array( 'format', 'shadow', 'terms', 'rules' ) ) !== array() || ( $data['format'] ?? null ) !== 1 || ! is_bool( $data['shadow'] ?? null ) || ! is_array( $data['terms'] ?? null ) || ! is_array( $data['rules'] ?? null ) || ! array_is_list( $data['terms'] ) || ! array_is_list( $data['rules'] ) || count( $data['terms'] ) > 10000 || count( $data['rules'] ) > 256 ) {
			throw new \InvalidArgumentException( 'Invalid policy document.' );
		}
		$terms = array();
		foreach ( $data['terms'] as $term ) {
			if ( ! is_array( $term ) || array_diff( array_keys( $term ), array( 'id', 'text', 'category', 'severity', 'mode', 'fields', 'content_types', 'exceptions' ) ) !== array() || ! is_string( $term['id'] ?? null ) || ! is_string( $term['text'] ?? null ) ) {
				throw new \InvalidArgumentException( 'Invalid term document.' );
			}
			foreach ( array( 'category', 'mode' ) as $key ) {
				if ( array_key_exists( $key, $term ) && ! is_string( $term[ $key ] ) ) {
					throw new \InvalidArgumentException( 'Invalid term attribute.' );
				}
			}
			if ( array_key_exists( 'severity', $term ) && ! is_int( $term['severity'] ) ) {
				throw new \InvalidArgumentException( 'Severity must be an integer.' );
			}
			foreach ( array( 'fields', 'content_types', 'exceptions' ) as $key ) {
				if ( array_key_exists( $key, $term ) && ( ! is_array( $term[ $key ] ) || ! array_is_list( $term[ $key ] ) ) ) {
					throw new \InvalidArgumentException( 'Invalid term scope.' );
				}
			}
			$terms[] = new Term( $term['id'], $term['text'], $term['category'] ?? 'custom', $term['severity'] ?? 50, $term['mode'] ?? 'word', $term['fields'] ?? array(), $term['content_types'] ?? array(), $term['exceptions'] ?? array() );
		}
		$rules = array();
		foreach ( $data['rules'] as $rule ) {
			if ( ! is_array( $rule ) || array_diff( array_keys( $rule ), array( 'id', 'condition', 'action', 'priority', 'enabled' ) ) !== array() || ! is_string( $rule['id'] ?? null ) || ! is_array( $rule['condition'] ?? null ) || ! is_string( $rule['action'] ?? null ) || Action::tryFrom( $rule['action'] ) === null || ( array_key_exists( 'priority', $rule ) && ! is_int( $rule['priority'] ) ) || ( array_key_exists( 'enabled', $rule ) && ! is_bool( $rule['enabled'] ) ) ) {
				throw new \InvalidArgumentException( 'Invalid rule document.' );
			}
			$rules[] = new Rule( $rule['id'], Condition::from_array( $rule['condition'] ), Action::from( $rule['action'] ), $rule['priority'] ?? 0, $rule['enabled'] ?? true );
		}
		$policy = new Policy( $version, $terms, $rules, $data['shadow'] );
		// Compile during validation so malformed dictionaries cannot become active.
		if ( $compile ) {
			new TermDetector( $policy->terms, new Normalizer() );
		}
		return $policy;
	}
	public function encode( Policy $policy ): string {
		return json_encode(
			array(
				'format' => 1,
				'shadow' => $policy->shadow,
				'terms'  => array_map( static fn( Term $term ): array => get_object_vars( $term ), $policy->terms ),
				'rules'  => array_map(
					static fn( Rule $rule ): array => array(
						'id'        => $rule->id,
						'condition' => $rule->condition,
						'action'    => $rule->action->value,
						'priority'  => $rule->priority,
						'enabled'   => $rule->enabled,
					),
					$policy->rules
				),
			),
			JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		);
	}
}
