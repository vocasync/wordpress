<?php

declare( strict_types=1 );

namespace VocaSync\Rest;

use VocaSync\Api\ApiException;
use VocaSync\Api\Client;
use VocaSync\Constants;
use VocaSync\Generation\Artifact;
use VocaSync\Generation\Orchestrator;
use VocaSync\Settings\Settings;
use VocaSync\Tokenizer\SpokenForm;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Editor-facing REST routes: trigger generation, poll status, save per-post
 * overrides, and test the API connection. All are nonce + capability gated
 * (distinct from the public webhook route).
 */
final class Controller {

	private const NS = 'vocasync/v1';

	public function __construct(
		private Orchestrator $orchestrator,
		private Artifact $artifact,
		private Settings $settings,
		private Client $client
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'force'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/overrides',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_overrides' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
		register_rest_route(
			self::NS,
			'/test-connection',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => static fn () => current_user_can( 'manage_options' ),
			)
		);
	}

	public function can_edit( WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'post_id' );
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	public function generate( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$force   = (bool) $request->get_param( 'force' );
		$result  = $this->orchestrator->generate( $post_id, $force );
		return rest_ensure_response( array_merge( $result, $this->status_payload( $post_id ) ) );
	}

	public function status( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		return rest_ensure_response( $this->status_payload( $post_id ) );
	}

	public function save_overrides( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$input   = $request->get_param( 'overrides' );
		$input   = is_array( $input ) ? $input : array();

		$overrides = array();
		if ( isset( $input['voice'] ) && in_array( $input['voice'], Constants::VOICES, true ) ) {
			$overrides['voice'] = (string) $input['voice'];
		}
		if ( isset( $input['language'] ) && in_array( $input['language'], Constants::HIGHLIGHT_LANGUAGES, true ) ) {
			$overrides['language'] = (string) $input['language'];
		}
		if ( isset( $input['format'] ) && in_array( $input['format'], Constants::FORMATS, true ) ) {
			$overrides['format'] = (string) $input['format'];
		}
		if ( isset( $input['auto'] ) ) {
			$overrides['auto'] = (bool) $input['auto'];
		}
		if ( isset( $input['enabled'] ) ) {
			$overrides['enabled'] = (bool) $input['enabled'];
		}
		if ( isset( $input['position'] ) && in_array( $input['position'], array( 'top', 'bottom' ), true ) ) {
			$overrides['position'] = (string) $input['position'];
		}
		foreach ( array( 'speed', 'volume', 'mini', 'highlight', 'clickSeek' ) as $control ) {
			if ( isset( $input[ $control ] ) ) {
				$overrides[ $control ] = (bool) $input[ $control ];
			}
		}

		$this->artifact->set_overrides( $post_id, $overrides );
		return rest_ensure_response( $this->status_payload( $post_id ) );
	}

	public function test_connection( WP_REST_Request $request ): WP_REST_Response {
		// Prefer the key typed in the field (may be unsaved); fall back to the
		// stored/constant key.
		$key = trim( (string) $request->get_param( 'api_key' ) );
		if ( '' === $key ) {
			$key = $this->settings->get_api_key();
		}
		if ( '' === $key ) {
			return rest_ensure_response(
				array(
					'valid'   => false,
					'message' => __( 'Enter an API key first.', 'vocasync' ),
				)
			);
		}
		try {
			$result = $this->client->validate_key( $key );
			$msg    = $result['valid']
				? sprintf(
					/* translators: %s: formatted balance */
					__( 'Connected. Balance: %s.', 'vocasync' ),
					null !== $result['balanceCents'] ? '$' . number_format( $result['balanceCents'] / 100, 2 ) : __( 'unknown', 'vocasync' )
				)
				: __( 'The API key was rejected.', 'vocasync' );
			return rest_ensure_response(
				array(
					'valid'   => $result['valid'],
					'message' => $msg,
				)
			);
		} catch ( ApiException $e ) {
			return rest_ensure_response(
				array(
					'valid'   => false,
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function status_payload( int $post_id ): array {
		$artifact  = $this->artifact->get_artifact( $post_id );
		$state     = $this->artifact->get_state( $post_id );
		$params    = $this->orchestrator->resolve_params( $post_id );
		$info      = $this->orchestrator->post_info( $post_id );
		$supported = SpokenForm::is_supported_language( $params['language'] );

		$reason = null;
		if ( ! $this->settings->has_api_key() ) {
			$reason = 'no_api_key';
		} elseif ( ! $supported ) {
			$reason = 'unsupported_language';
		} elseif ( ! $info['speakable'] ) {
			$reason = 'no_speakable_text';
		}

		return array(
			'jobStatus'     => (string) ( $state['status'] ?? Artifact::STATUS_IDLE ),
			'error'         => $state['error'] ?? null,
			'stage'         => $state['currentStage'] ?? null,
			'chunkProgress' => $state['chunkProgress'] ?? null,
			'hasAudio'      => null !== $artifact,
			'stale'         => null !== $artifact && $this->orchestrator->is_stale( $post_id ),
			'duration'      => null !== $artifact ? (float) ( $artifact['duration'] ?? 0 ) : 0,
			'charCount'     => (int) $info['charCount'],
			'speakable'     => (bool) $info['speakable'],
			'supported'     => $supported,
			'hasApiKey'     => $this->settings->has_api_key(),
			'webhooks'      => $this->settings->webhooks_enabled() && wp_is_using_https(),
			'canGenerate'   => null === $reason,
			'reason'        => $reason,
			'overrides'     => $this->artifact->get_overrides( $post_id ),
			'language'      => $params['language'],
		);
	}
}
