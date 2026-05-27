<?php

declare( strict_types=1 );

namespace VocaSync\Render;

use VocaSync\Content\Extractor;
use VocaSync\Content\WordWrapper;
use VocaSync\Generation\Artifact;
use VocaSync\Settings\Settings;
use VocaSync\Tokenizer\SpokenForm;
use WP_Post;

/**
 * Renders the player into post content and wraps the body words for
 * highlighting. The player auto-inserts at the configured position, unless the
 * post uses the VocaSync block or [vocasync] shortcode (which place it
 * explicitly). Audio streams from VocaSync via the stored publishable key.
 */
final class PlayerRenderer {

	/** Set true to bypass rendering (used while extracting content for hashing). */
	public static bool $suppressed = false;

	private const HANDLE = 'vocasync-player';

	/** Post IDs whose player has already been emitted this request (dedupe). */
	private array $rendered = array();

	public function __construct(
		private Artifact $artifact,
		private Settings $settings,
		private WordWrapper $wrapper,
		private Extractor $extractor
	) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_shortcode( 'vocasync', array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_assets(): void {
		wp_register_style( self::HANDLE, VOCASYNC_PLUGIN_URL . 'assets/css/player.css', array(), VOCASYNC_VERSION );
		wp_register_script( 'vocasync-highlight-core', VOCASYNC_PLUGIN_URL . 'assets/js/highlight-core.js', array(), VOCASYNC_VERSION, true );
		wp_register_script( self::HANDLE, VOCASYNC_PLUGIN_URL . 'assets/js/player.js', array( 'vocasync-highlight-core' ), VOCASYNC_VERSION, true );
		if ( $this->settings->has_custom_theme() ) {
			wp_add_inline_style( self::HANDLE, Theme::build_css( $this->settings->theme() ) );
		}
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type(
			'vocasync/player',
			array(
				'render_callback' => array( $this, 'render_block' ),
				'editor_script'   => 'vocasync-block',
				'supports'        => array( 'html' => false ),
			)
		);
	}

	/**
	 * Main the_content filter: wrap words + auto-insert the player.
	 */
	public function filter_content( string $content ): string {
		if ( self::$suppressed || is_admin() || ! is_singular() ) {
			return $content;
		}
		// Only wrap the primary queried post's content. Using the queried-object
		// id (rather than in_the_loop()/is_main_query()) works in both classic
		// loops and block themes (FSE), where those are unreliable inside the
		// core/post-content block.
		$post_id = (int) get_the_ID();
		if ( ! $post_id || get_queried_object_id() !== $post_id ) {
			return $content;
		}

		$render = $this->prepare( $post_id, $content );
		if ( null === $render ) {
			return $content;
		}

		$body = $render['highlight'] ? $this->wrapper->wrap( $content, $render['mode'] ) : $content;

		// If the author placed a block/shortcode, the player is already in $body.
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post && $this->has_explicit_placement( $post ) ) {
			return $body;
		}

		$player = $this->player_markup( $post_id, $render['artifact'] );
		return 'bottom' === $this->resolve_position( $post_id ) ? $body . $player : $player . $body;
	}

	/** [vocasync] shortcode — renders the player at its location. */
	public function shortcode(): string {
		$post_id = (int) get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		$artifact = $this->artifact->get_artifact( $post_id );
		if ( null === $artifact || ! $this->is_enabled( $post_id ) ) {
			return '';
		}
		return $this->player_markup( $post_id, $artifact );
	}

	/** Server render for the vocasync/player block. */
	public function render_block(): string {
		return $this->shortcode();
	}

	// ── Internals ───────────────────────────────────────────────────

	/**
	 * @return array{artifact:array<string,mixed>, mode:string, highlight:bool}|null
	 */
	private function prepare( int $post_id, string $content ): ?array {
		$artifact = $this->artifact->get_artifact( $post_id );
		if ( null === $artifact || empty( $artifact['words'] ) || empty( $artifact['audioUrl'] ) ) {
			return null;
		}
		if ( ! $this->is_enabled( $post_id ) ) {
			return null;
		}

		$mode = SpokenForm::mode_for_language( (string) ( $artifact['language'] ?? 'en' ) );

		// Staleness keys on the spoken TRANSCRIPT (extracted from the rendered
		// body, skipping the player/code/images), not raw markup — so adding the
		// player block or other non-speech edits don't disable highlighting.
		$speech = $this->extractor->extract( $content, $mode );
		$hash   = Extractor::content_hash(
			$speech['normalisedSpeechtext'],
			(string) ( $artifact['voice'] ?? '' ),
			(string) ( $artifact['language'] ?? '' ),
			(string) ( $artifact['format'] ?? '' )
		);
		$stale  = ( $artifact['contentHash'] ?? '' ) !== $hash;

		return array(
			'artifact'  => $artifact,
			'mode'      => $mode,
			'highlight' => $this->resolve_controls( $post_id )['highlight'] && ! $stale,
		);
	}

	private function is_enabled( int $post_id ): bool {
		$o = $this->artifact->get_overrides( $post_id );
		return ! isset( $o['enabled'] ) || (bool) $o['enabled'];
	}

	/**
	 * Effective control visibility: global defaults overlaid with per-post
	 * overrides. @return array<string,bool>
	 */
	private function resolve_controls( int $post_id ): array {
		$controls = $this->settings->control_defaults();
		$o        = $this->artifact->get_overrides( $post_id );
		foreach ( array_keys( $controls ) as $key ) {
			if ( isset( $o[ $key ] ) ) {
				$controls[ $key ] = (bool) $o[ $key ];
			}
		}
		return $controls;
	}

	private function resolve_position( int $post_id ): string {
		$o = $this->artifact->get_overrides( $post_id );
		if ( isset( $o['position'] ) && in_array( $o['position'], array( 'top', 'bottom' ), true ) ) {
			return (string) $o['position'];
		}
		return $this->settings->player_position();
	}

	private function has_explicit_placement( WP_Post $post ): bool {
		if ( function_exists( 'has_block' ) && has_block( 'vocasync/player', $post ) ) {
			return true;
		}
		return has_shortcode( (string) $post->post_content, 'vocasync' );
	}

	/**
	 * @param array<string,mixed> $artifact
	 */
	private function player_markup( int $post_id, array $artifact ): string {
		// One player per post per request: if a block + shortcode (or multiple
		// shortcodes) are present, only the first emits — avoids duplicate
		// element IDs and competing highlight loops.
		if ( ! empty( $this->rendered[ $post_id ] ) ) {
			return '';
		}
		$this->rendered[ $post_id ] = true;

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		$data = wp_json_encode(
			array(
				'audioUrl'       => (string) ( $artifact['audioUrl'] ?? '' ),
				'publishableKey' => (string) ( $artifact['synthesisPublishableKey'] ?? '' ),
				'words'          => $artifact['words'] ?? array(),
				'duration'       => (float) ( $artifact['duration'] ?? 0 ),
			),
			JSON_HEX_TAG | JSON_HEX_AMP // safe to embed in a <script> tag
		);

		$controls = $this->resolve_controls( $post_id );
		$view     = require VOCASYNC_PLUGIN_DIR . 'src/Render/views/player.php';
		return $view(
			$post_id,
			(string) $data,
			array(
				'highlighting'  => $controls['highlight'],
				'mini_player'   => $controls['mini'],
				'click_to_seek' => $controls['clickSeek'],
				'speed'         => $controls['speed'],
				'volume'        => $controls['volume'],
			)
		);
	}
}
