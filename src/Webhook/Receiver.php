<?php

declare( strict_types=1 );

namespace VocaSync\Webhook;

use VocaSync\Generation\Artifact;
use VocaSync\Generation\Orchestrator;
use VocaSync\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public webhook receiver. Mirrors Valeon's `/vocasync-webhook`:
 *
 *   - Correlation rides in the callback URL (`?token=…&phase=…`); VocaSync's
 *     payload has no projectUuid.
 *   - The route is public, but gated by HMAC verification + timestamp freshness
 *     + event-id idempotency. The payload is treated as a trigger — the actual
 *     completion work re-checks authoritative state via the API in advance().
 *   - Returns 200 fast so VocaSync marks delivery successful; heavy work is
 *     enqueued.
 */
final class Receiver {

	/** Event types we act on (default subscription is terminal-only). */
	private const TERMINAL = array( 'job.completed', 'job.failed', 'job.canceled' );

	public function __construct(
		private Settings $settings,
		private Deliveries $deliveries,
		private Orchestrator $orchestrator
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route(): void {
		register_rest_route(
			'vocasync/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true', // security is the HMAC check below
			)
		);
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$raw    = (string) $request->get_body();
		$header = $request->get_header( 'X-Vocasync-Signature' );
		$secret = $this->settings->get_webhook_secret();

		$valid   = Signature::verify( $header, $raw, $secret );
		$payload = json_decode( $raw, true );
		$payload = is_array( $payload ) ? $payload : array();

		$token      = (string) $request->get_param( 'token' );
		$phase      = (string) $request->get_param( 'phase' );
		$event_id   = (string) ( $payload['id'] ?? '' );
		$event_type = (string) ( $payload['type'] ?? '' );

		$post_id = '' !== $token ? $this->orchestrator_resolve( $token ) : null;

		$outcome = $this->classify( $valid, $event_id, $post_id, $event_type );

		$dedup_key    = '' !== $event_id ? $event_id : 'nosig-' . time() . '-' . wp_rand();
		$is_duplicate = $this->deliveries->record( $dedup_key, $event_type, $post_id, $phase, $outcome, $valid );

		if ( ! $valid ) {
			return new WP_REST_Response( 'Invalid signature', 401 );
		}
		if ( $is_duplicate || null === $post_id ) {
			return new WP_REST_Response( 'OK', 200 ); // already handled, or uncorrelatable
		}

		if ( in_array( $event_type, self::TERMINAL, true ) ) {
			$this->orchestrator->on_webhook( $post_id );
		} elseif ( 'job.stage.started' === $event_type || 'job.stage.completed' === $event_type ) {
			$job   = isset( $payload['data']['job'] ) && is_array( $payload['data']['job'] ) ? $payload['data']['job'] : array();
			$stage = isset( $job['currentStage'] ) ? (string) $job['currentStage'] : ( '' !== $phase ? $phase : null );
			$chunk = isset( $job['chunkProgress'] ) && is_array( $job['chunkProgress'] ) ? $job['chunkProgress'] : null;
			$this->orchestrator->on_progress( $post_id, $stage, $chunk );
		}

		return new WP_REST_Response( 'OK', 200 );
	}

	private function orchestrator_resolve( string $token ): ?int {
		// Resolve token → post via the artifact store the orchestrator owns.
		return ( new Artifact() )->resolve_post_by_token( $token );
	}

	private function classify( bool $valid, string $event_id, ?int $post_id, string $event_type ): string {
		if ( ! $valid ) {
			return 'rejected_signature';
		}
		if ( '' === $event_id || null === $post_id ) {
			return 'ignored';
		}
		if ( in_array( $event_type, self::TERMINAL, true ) ) {
			return 'job.failed' === $event_type || 'job.canceled' === $event_type ? 'failed' : 'processed';
		}
		if ( 'job.stage.started' === $event_type || 'job.stage.completed' === $event_type ) {
			return 'progress';
		}
		return 'ignored';
	}
}
