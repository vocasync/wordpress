<?php

declare( strict_types=1 );

namespace VocaSync\Generation;

use VocaSync\Api\ApiException;
use VocaSync\Api\Client;
use VocaSync\Constants;
use VocaSync\Content\Extractor;
use VocaSync\Render\PlayerRenderer;
use VocaSync\Settings\Settings;
use VocaSync\Tokenizer\SpokenForm;
use WP_Post;

/**
 * Drives the two-job pipeline (synthesis → alignment) as an idempotent,
 * status-driven state machine, advanced by EITHER a webhook or the
 * reconciliation poll — mirroring Valeon's audioJobs flow.
 *
 *   generate() → enqueue synthesis → advance() (synthesis done) → submit
 *   alignment → advance() (alignment done) → write artifact.
 *
 * advance() inspects stored state + the authoritative VocaSync project status,
 * so duplicate webhooks and the poll converge safely. A per-post transient lock
 * prevents concurrent advances from double-submitting.
 */
final class Orchestrator {

	public const HOOK_SYNTHESIS = 'vocasync_run_synthesis';
	public const HOOK_ADVANCE   = 'vocasync_advance';
	public const HOOK_RECONCILE = 'vocasync_reconcile';

	private const GROUP       = 'vocasync';
	private const MAX_RUNTIME = 1800; // 30 min hard timeout for an in-flight job
	private const STALE_AFTER = 240;  // reconcile jobs quiet for > 4 min

	public function __construct(
		private Client $client,
		private Artifact $artifact,
		private Extractor $extractor,
		private Settings $settings
	) {}

	public function register(): void {
		add_action( self::HOOK_SYNTHESIS, array( $this, 'run_synthesis' ), 10, 1 );
		add_action( self::HOOK_ADVANCE, array( $this, 'advance' ), 10, 1 );
		add_action( self::HOOK_RECONCILE, array( $this, 'reconcile' ), 10, 0 );
		add_action( 'save_post', array( $this, 'maybe_auto_generate' ), 20, 2 );
	}

	// ── Entry point ─────────────────────────────────────────────────

	/**
	 * Begin (or skip) generation for a post.
	 *
	 * @return array{status:string, message?:string}
	 */
	public function generate( int $post_id, bool $force = false ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Post not found.', 'vocasync' ),
			);
		}
		if ( ! $this->settings->has_api_key() ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Add your VocaSync API key in Settings → VocaSync first.', 'vocasync' ),
			);
		}

		$params = $this->resolve_params( $post_id );
		if ( ! SpokenForm::is_supported_language( $params['language'] ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'This language is not yet supported for highlighting.', 'vocasync' ),
			);
		}

		$rendered = $this->rendered_content( $post );
		$speech   = $this->extractor->extract( $rendered, $params['mode'] );
		if ( '' === $speech['normalisedSpeechtext'] ) {
			return array(
				'status'  => 'error',
				'message' => __( 'No speakable text was found. The post may be empty, or written in a language that is not yet supported for highlighting.', 'vocasync' ),
			);
		}
		if ( mb_strlen( $speech['speechtext'] ) > Constants::MAX_INPUT_CHARS ) {
			return array(
				'status'  => 'error',
				'message' => __( 'This post is too long to synthesize.', 'vocasync' ),
			);
		}

		// Change detection keys on the spoken transcript (not raw markup), so
		// markup-only edits (e.g. adding the player block) don't invalidate the
		// audio. The render-time check re-extracts the transcript the same way.
		$hash = Extractor::content_hash(
			$speech['normalisedSpeechtext'],
			$params['voice'],
			$params['language'],
			$params['format']
		);

		if ( ! $force && $this->artifact->is_current( $post_id, $hash ) ) {
			return array( 'status' => 'unchanged' );
		}
		if ( $this->artifact->is_in_flight( $post_id ) ) {
			return array( 'status' => 'in_progress' );
		}

		$this->artifact->save_state(
			$post_id,
			array(
				'status'               => Artifact::STATUS_SYNTHESIZING,
				'phase'                => 'synthesis',
				'speechtext'           => $speech['speechtext'],
				'normalisedSpeechtext' => $speech['normalisedSpeechtext'],
				'contentHash'          => $hash,
				'voice'                => $params['voice'],
				'language'             => $params['language'],
				'format'               => $params['format'],
				'quality'              => $this->settings->default_quality(),
				'mode'                 => $params['mode'],
				'callbackToken'        => wp_generate_password( 40, false, false ),
				'startedAt'            => time(),
				'lastEventAt'          => time(),
				'attempts'             => 0,
				'error'                => null,
			)
		);

		$this->enqueue( self::HOOK_SYNTHESIS, $post_id );
		return array( 'status' => 'queued' );
	}

	// ── Action Scheduler handlers ───────────────────────────────────

	public function run_synthesis( int $post_id ): void {
		if ( ! $this->lock( $post_id ) ) {
			return;
		}
		try {
			$state = $this->artifact->get_state( $post_id );
			if ( Artifact::STATUS_SYNTHESIZING !== ( $state['status'] ?? '' ) || ! empty( $state['synthesisProjectUuid'] ) ) {
				return; // already submitted or no longer synthesizing
			}

			$webhook = $this->webhook_for( $post_id, (string) $state['callbackToken'], 'synthesis' );
			$resp    = $this->client->synthesize(
				array(
					'name'         => "wp-{$post_id}",
					'text'         => (string) $state['speechtext'],
					'voice'        => (string) $state['voice'],
					'quality'      => (string) $state['quality'],
					'language'     => (string) $state['language'],
					'outputFormat' => (string) $state['format'],
				),
				$webhook
			);
			$uuid    = (string) ( $resp['projectUuid'] ?? '' );
			if ( '' === $uuid ) {
				$this->fail( $post_id, __( 'Synthesis did not return a project id.', 'vocasync' ) );
				return;
			}
			$this->artifact->patch_state(
				$post_id,
				array(
					'synthesisProjectUuid' => $uuid,
					'lastEventAt'          => time(),
				)
			);
			$this->schedule_advance( $post_id, true );
		} catch ( ApiException $e ) {
			$this->fail( $post_id, $this->friendly_error( $e ) );
		} finally {
			$this->unlock( $post_id );
		}
	}

	/** Status-driven step. Safe to call from webhook receipt or the poll. */
	public function advance( int $post_id ): void {
		if ( ! $this->lock( $post_id ) ) {
			return;
		}
		try {
			$state  = $this->artifact->get_state( $post_id );
			$status = (string) ( $state['status'] ?? Artifact::STATUS_IDLE );

			if ( $this->timed_out( $state ) ) {
				$this->fail( $post_id, __( 'Audio generation timed out.', 'vocasync' ) );
				return;
			}

			if ( Artifact::STATUS_SYNTHESIZING === $status && ! empty( $state['synthesisProjectUuid'] ) ) {
				$this->advance_synthesis( $post_id, $state );
			} elseif ( Artifact::STATUS_ALIGNING === $status && ! empty( $state['alignmentProjectUuid'] ) ) {
				$this->advance_alignment( $post_id, $state );
			}
		} catch ( ApiException $e ) {
			$this->fail( $post_id, $this->friendly_error( $e ) );
		} finally {
			$this->unlock( $post_id );
		}
	}

	/** Reconciliation backstop: nudge in-flight jobs that have gone quiet. */
	public function reconcile(): void {
		foreach ( $this->artifact->in_flight_posts() as $post_id ) {
			$state = $this->artifact->get_state( $post_id );
			$last  = (int) ( $state['lastEventAt'] ?? 0 );
			if ( ( time() - $last ) >= self::STALE_AFTER ) {
				$this->enqueue( self::HOOK_ADVANCE, $post_id );
			}
		}
	}

	/** Called by the webhook receiver after signature + correlation pass. */
	public function on_webhook( int $post_id ): void {
		$this->artifact->touch_last_event( $post_id );
		$this->enqueue( self::HOOK_ADVANCE, $post_id );
	}

	/**
	 * Record live progress from a job.stage.* webhook. Guarded to in-flight
	 * statuses so a late event can't resurrect a finished/failed job.
	 *
	 * @param array<string,mixed>|null $chunk
	 */
	public function on_progress( int $post_id, ?string $stage, ?array $chunk ): void {
		$status = $this->artifact->status( $post_id );
		if ( Artifact::STATUS_SYNTHESIZING !== $status && Artifact::STATUS_ALIGNING !== $status ) {
			return;
		}
		$patch = array( 'lastEventAt' => time() );
		if ( null !== $stage ) {
			$patch['currentStage'] = $stage;
		}
		if ( null !== $chunk ) {
			$patch['chunkProgress'] = array(
				'total'     => isset( $chunk['total'] ) ? (int) $chunk['total'] : 0,
				'completed' => isset( $chunk['completed'] ) ? (int) $chunk['completed'] : 0,
				'label'     => isset( $chunk['label'] ) ? (string) $chunk['label'] : '',
			);
		}
		$this->artifact->patch_state( $post_id, $patch );
	}

	/**
	 * Whether the stored audio no longer matches the post's current spoken
	 * transcript. Used by the editor status (the render filter does its own,
	 * cheaper check against the content it already has).
	 */
	public function is_stale( int $post_id ): bool {
		$artifact = $this->artifact->get_artifact( $post_id );
		$post     = get_post( $post_id );
		if ( null === $artifact || ! $post instanceof WP_Post ) {
			return false;
		}
		$mode   = SpokenForm::mode_for_language( (string) ( $artifact['language'] ?? 'en' ) );
		$speech = $this->extractor->extract( $this->rendered_content( $post ), $mode );
		$hash   = Extractor::content_hash(
			$speech['normalisedSpeechtext'],
			(string) ( $artifact['voice'] ?? '' ),
			(string) ( $artifact['language'] ?? '' ),
			(string) ( $artifact['format'] ?? '' )
		);
		return ( $artifact['contentHash'] ?? '' ) !== $hash;
	}

	/**
	 * Char count + speakable flag for the post, cached in a transient keyed on
	 * the content + mode so status polling doesn't re-extract every time.
	 *
	 * @return array{charCount:int, speakable:bool}
	 */
	public function post_info( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'charCount' => 0,
				'speakable' => false,
			);
		}
		$params = $this->resolve_params( $post_id );
		$key    = 'vocasync_info_' . md5( (string) $post->post_content . '|' . $params['mode'] );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$info = $this->extractor->analyze( $this->rendered_content( $post ), $params['mode'] );
		set_transient( $key, $info, HOUR_IN_SECONDS );
		return $info;
	}

	// ── Transitions ─────────────────────────────────────────────────

	/** @param array<string,mixed> $state */
	private function advance_synthesis( int $post_id, array $state ): void {
		$uuid    = (string) $state['synthesisProjectUuid'];
		$project = $this->client->get_project( $uuid );
		$pstatus = (string) ( $project['status'] ?? '' );

		if ( 'failed' === $pstatus || 'canceled' === $pstatus ) {
			$this->fail( $post_id, (string) ( $project['error'] ?? __( 'Synthesis failed.', 'vocasync' ) ) );
			return;
		}
		if ( 'completed' !== $pstatus ) {
			$this->schedule_advance( $post_id, false );
			return;
		}

		// Synthesis complete → run the alignment job.
		$pk = (string) ( $state['synthesisPublishableKey'] ?? '' );
		if ( '' === $pk ) {
			$pk = $this->client->create_publishable_key( $uuid );
		}
		$audio_url = $this->client->stream_url( $uuid, 'synthesis' );
		$download  = $this->client->download_bytes( Client::with_publishable_key( $audio_url, $pk ) );

		$transcript = (string) $state['normalisedSpeechtext'];
		$locale     = Constants::to_alignment_locale( (string) $state['language'] );
		$format     = (string) $state['format'];

		$presign = $this->client->presign_alignment(
			array(
				'audioName'      => "synthesis.{$format}",
				'audioType'      => $download['content_type'],
				'audioSize'      => strlen( $download['bytes'] ),
				'transcriptSize' => strlen( $transcript ),
				'language'       => $locale,
			)
		);

		$this->client->upload_to_presigned( (string) $presign['audio']['uploadUrl'], $download['bytes'], $download['content_type'] );
		$this->client->upload_to_presigned( (string) $presign['transcript']['uploadUrl'], $transcript, 'text/plain' );

		$align_uuid = (string) $presign['projectUuid'];
		$webhook    = $this->webhook_for( $post_id, (string) $state['callbackToken'], 'alignment' );
		$this->client->submit_alignment(
			array(
				'projectUuid'             => $align_uuid,
				'audioFileKey'            => (string) $presign['audio']['key'],
				'transcriptFileKey'       => (string) $presign['transcript']['key'],
				'audioFileSizeBytes'      => strlen( $download['bytes'] ),
				'transcriptFileSizeBytes' => strlen( $transcript ),
				'language'                => $locale,
				'projectName'             => "wp-{$post_id}",
			),
			$webhook
		);

		$this->artifact->patch_state(
			$post_id,
			array(
				'status'                  => Artifact::STATUS_ALIGNING,
				'phase'                   => 'alignment',
				'synthesisPublishableKey' => $pk,
				'audioUrl'                => $audio_url,
				'alignmentProjectUuid'    => $align_uuid,
				'currentStage'            => null,
				'chunkProgress'           => null,
				'lastEventAt'             => time(),
				'attempts'                => 0,
			)
		);
		$this->schedule_advance( $post_id, true );
	}

	/** @param array<string,mixed> $state */
	private function advance_alignment( int $post_id, array $state ): void {
		$uuid    = (string) $state['alignmentProjectUuid'];
		$project = $this->client->get_project( $uuid );
		$pstatus = (string) ( $project['status'] ?? '' );

		if ( 'failed' === $pstatus || 'canceled' === $pstatus ) {
			$this->fail( $post_id, (string) ( $project['error'] ?? __( 'Alignment failed.', 'vocasync' ) ) );
			return;
		}
		if ( 'completed' !== $pstatus ) {
			$this->schedule_advance( $post_id, false );
			return;
		}

		$pk       = $this->client->create_publishable_key( $uuid );
		$stream   = Client::with_publishable_key( $this->client->stream_url( $uuid, 'alignment' ), $pk );
		$result   = $this->client->fetch_alignment_words( $stream );
		$words    = $result['words'];
		$last     = ! empty( $words ) ? (float) ( $words[ count( $words ) - 1 ]['end'] ?? 0 ) : 0.0;
		$duration = $result['duration'] > 0 ? $result['duration'] : $last;

		$existing = $this->artifact->get_artifact( $post_id );
		$now      = gmdate( 'c' );
		$this->artifact->set_artifact(
			$post_id,
			array(
				'contentHash'             => (string) $state['contentHash'],
				'voice'                   => (string) $state['voice'],
				'language'                => (string) $state['language'],
				'format'                  => (string) $state['format'],
				'synthesisProjectUuid'    => (string) $state['synthesisProjectUuid'],
				'synthesisPublishableKey' => (string) $state['synthesisPublishableKey'],
				'audioUrl'                => (string) $state['audioUrl'],
				'alignmentProjectUuid'    => $uuid,
				'alignmentPublishableKey' => $pk,
				'words'                   => $words,
				'duration'                => $duration,
				'createdAt'               => $existing['createdAt'] ?? $now,
				'updatedAt'               => $now,
			)
		);

		$this->artifact->save_state(
			$post_id,
			array(
				'status'      => Artifact::STATUS_DONE,
				'completedAt' => time(),
				'lastEventAt' => time(),
			)
		);
		delete_post_meta( $post_id, Artifact::META_TOKEN );
	}

	// ── Helpers ─────────────────────────────────────────────────────

	/**
	 * @return array{voice:string, language:string, format:string, mode:string, auto:bool, enabled:bool}
	 */
	public function resolve_params( int $post_id ): array {
		$o        = $this->artifact->get_overrides( $post_id );
		$language = isset( $o['language'] ) && in_array( $o['language'], Constants::HIGHLIGHT_LANGUAGES, true )
			? (string) $o['language']
			: $this->settings->default_language();
		$voice    = isset( $o['voice'] ) && in_array( $o['voice'], Constants::VOICES, true )
			? (string) $o['voice']
			: $this->settings->default_voice();
		$format   = isset( $o['format'] ) && in_array( $o['format'], Constants::FORMATS, true )
			? (string) $o['format']
			: $this->settings->default_format();

		return array(
			'voice'    => $voice,
			'language' => $language,
			'format'   => $format,
			'mode'     => SpokenForm::mode_for_language( $language ),
			'auto'     => isset( $o['auto'] ) ? (bool) $o['auto'] : $this->settings->auto_generate_default(),
			'enabled'  => isset( $o['enabled'] ) ? (bool) $o['enabled'] : true,
		);
	}

	public function maybe_auto_generate( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		$params = $this->resolve_params( $post_id );
		if ( ! $params['enabled'] || ! $params['auto'] ) {
			return;
		}
		if ( ! $this->settings->has_api_key() || ! SpokenForm::is_supported_language( $params['language'] ) ) {
			return;
		}
		// generate() dedups on content hash, so unchanged content is a no-op.
		$this->generate( $post_id, false );
	}

	private function rendered_content( WP_Post $post ): string {
		PlayerRenderer::$suppressed = true;
		// Re-run the core the_content filter to render shortcodes/blocks; $suppressed prevents our own re-injection.
		$html                       = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		PlayerRenderer::$suppressed = false;
		return is_string( $html ) ? $html : '';
	}

	/**
	 * @return array{url:string}|null Null when webhooks can't be delivered here.
	 */
	private function webhook_for( int $post_id, string $token, string $phase ): ?array {
		if ( ! $this->settings->webhooks_enabled() || ! wp_is_using_https() ) {
			return null;
		}
		$url = add_query_arg(
			array(
				'token' => rawurlencode( $token ),
				'phase' => $phase,
			),
			rest_url( 'vocasync/v1/webhook' )
		);
		return array(
			'url'    => $url,
			'events' => array( 'job.completed', 'job.failed', 'job.canceled', 'job.stage.started', 'job.stage.completed' ),
		);
	}

	private function fail( int $post_id, string $message ): void {
		$this->artifact->save_state(
			$post_id,
			array(
				'status'      => Artifact::STATUS_FAILED,
				'error'       => $message,
				'completedAt' => time(),
				'lastEventAt' => time(),
			)
		);
		delete_post_meta( $post_id, Artifact::META_TOKEN );
	}

	/** @param array<string,mixed> $state */
	private function timed_out( array $state ): bool {
		$started = (int) ( $state['startedAt'] ?? 0 );
		return $started > 0 && ( time() - $started ) > self::MAX_RUNTIME;
	}

	private function schedule_advance( int $post_id, bool $reset_attempts ): void {
		$state    = $this->artifact->get_state( $post_id );
		$attempts = $reset_attempts ? 0 : (int) ( $state['attempts'] ?? 0 ) + 1;
		$this->artifact->patch_state( $post_id, array( 'attempts' => $attempts ) );

		// Backoff: webhooks (if active) fire sooner; the poll is the backstop.
		$delays = array( 20, 30, 60, 120, 300 );
		$delay  = $delays[ min( $attempts, count( $delays ) - 1 ) ];
		if ( $this->settings->webhooks_enabled() && wp_is_using_https() ) {
			$delay = max( $delay, 120 ); // lean on the webhook; poll is just a safety net
		}
		$this->schedule_single( self::HOOK_ADVANCE, $post_id, $delay );
	}

	private function friendly_error( ApiException $e ): string {
		if ( $e->is_unauthorized() ) {
			return __( 'Your VocaSync API key was rejected. Check it in Settings → VocaSync.', 'vocasync' );
		}
		if ( $e->is_insufficient_balance() ) {
			return __( 'Your VocaSync balance is too low to generate this audio.', 'vocasync' );
		}
		if ( $e->is_rate_limited() ) {
			return __( 'VocaSync is rate-limiting requests; will retry shortly.', 'vocasync' );
		}
		return $e->getMessage();
	}

	// ── Action Scheduler / locking shims ────────────────────────────

	private function enqueue( string $hook, int $post_id ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( $hook, array( $post_id ), self::GROUP );
		} else {
			do_action( $hook, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- $hook is always a prefixed vocasync_* constant.
		}
	}

	private function schedule_single( string $hook, int $post_id, int $delay ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $delay, $hook, array( $post_id ), self::GROUP );
		}
	}

	private function lock( int $post_id ): bool {
		$key = "vocasync_lock_{$post_id}";
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, 120 );
		return true;
	}

	private function unlock( int $post_id ): void {
		delete_transient( "vocasync_lock_{$post_id}" );
	}
}
