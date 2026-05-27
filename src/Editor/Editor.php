<?php

declare( strict_types=1 );

namespace VocaSync\Editor;

use VocaSync\Constants;
use VocaSync\Generation\Artifact;
use VocaSync\Settings\Settings;
use WP_Post;
use WP_Screen;

/**
 * Editor integration: the block-editor "VocaSync" document panel + player
 * block, a classic-editor meta box fallback, and the settings page
 * "Test connection" script. The Generate button, status polling, and per-post
 * overrides talk to the REST controller.
 */
final class Editor {

	public function __construct(
		private Settings $settings,
		private Artifact $artifact
	) {}

	public function register(): void {
		add_action( 'init', array( $this, 'register_scripts' ), 9 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor' ) );
		add_action( 'add_meta_boxes', array( $this, 'maybe_add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	public function register_scripts(): void {
		wp_register_script(
			'vocasync-editor-format',
			VOCASYNC_PLUGIN_URL . 'assets/js/editor-format.js',
			array(),
			VOCASYNC_VERSION,
			true
		);
		wp_register_style( 'vocasync-editor', VOCASYNC_PLUGIN_URL . 'assets/css/editor.css', array(), VOCASYNC_VERSION );
		wp_register_script(
			'vocasync-block',
			VOCASYNC_PLUGIN_URL . 'assets/js/editor.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-plugins', 'wp-edit-post', 'wp-data', 'wp-api-fetch', 'wp-i18n', 'vocasync-editor-format' ),
			VOCASYNC_VERSION,
			true
		);
	}

	public function enqueue_block_editor(): void {
		wp_enqueue_script( 'vocasync-block' );
		wp_enqueue_style( 'vocasync-editor' );
		wp_localize_script( 'vocasync-block', 'VocaSyncEditor', $this->editor_data() );
	}

	/** @return array<string,mixed> */
	private function editor_data(): array {
		$languages = array();
		foreach ( Constants::LANGUAGE_LABELS as $code => $label ) {
			$languages[] = array(
				'value'        => $code,
				'label'        => $label,
				'experimental' => 'en' !== $code,
			);
		}
		$voices = array();
		foreach ( Constants::VOICES as $voice ) {
			$voices[] = array(
				'value' => $voice,
				'label' => ucfirst( $voice ),
				'desc'  => Constants::VOICE_DESCRIPTIONS[ $voice ],
			);
		}
		$formats = array_map(
			static fn ( string $f ) => array(
				'value' => $f,
				'label' => strtoupper( $f ),
			),
			Constants::FORMATS
		);

		return array(
			'voices'        => $voices,
			'languages'     => $languages,
			'formats'       => $formats,
			'defaults'      => array(
				'voice'    => $this->settings->default_voice(),
				'language' => $this->settings->default_language(),
				'format'   => $this->settings->default_format(),
				'auto'     => $this->settings->auto_generate_default(),
				'position' => $this->settings->player_position(),
			),
			'controls'      => $this->settings->control_defaults(),
			'controlLabels' => array(
				'speed'     => __( 'Speed control', 'vocasync' ),
				'volume'    => __( 'Volume control', 'vocasync' ),
				'mini'      => __( 'Mini-player', 'vocasync' ),
				'highlight' => __( 'Highlighting', 'vocasync' ),
				'clickSeek' => __( 'Click-to-seek', 'vocasync' ),
			),
			'hasApiKey'     => $this->settings->has_api_key(),
		);
	}

	public function maybe_add_meta_box(): void {
		$screen = get_current_screen();
		if ( $screen instanceof WP_Screen && $screen->is_block_editor() ) {
			return; // the document panel covers the block editor
		}
		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			add_meta_box(
				'vocasync-meta-box',
				__( 'VocaSync Audio', 'vocasync' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side'
			);
		}
	}

	public function render_meta_box( WP_Post $post ): void {
		$status = $this->artifact->status( $post->ID );
		echo '<div id="vocasync-meta-box" data-post-id="' . esc_attr( (string) $post->ID ) . '">';
		echo '<p class="vocasync-meta-status">' . esc_html( $this->status_label( $status ) ) . '</p>';
		echo '<button type="button" class="button button-primary" id="vocasync-generate">' . esc_html__( 'Generate audio', 'vocasync' ) . '</button>';
		echo '</div>';
	}

	private function status_label( string $status ): string {
		switch ( $status ) {
			case Artifact::STATUS_DONE:
				return __( 'Audio ready.', 'vocasync' );
			case Artifact::STATUS_SYNTHESIZING:
			case Artifact::STATUS_ALIGNING:
				return __( 'Generating…', 'vocasync' );
			case Artifact::STATUS_FAILED:
				return __( 'Generation failed.', 'vocasync' );
			default:
				return __( 'No audio yet.', 'vocasync' );
		}
	}

	public function enqueue_admin( string $hook ): void {
		if ( 'settings_page_vocasync' === $hook ) {
			wp_enqueue_script( 'vocasync-settings', VOCASYNC_PLUGIN_URL . 'assets/js/settings.js', array( 'wp-api-fetch', 'wp-i18n' ), VOCASYNC_VERSION, true );
			return;
		}
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_script( 'vocasync-meta-box', VOCASYNC_PLUGIN_URL . 'assets/js/meta-box.js', array( 'wp-api-fetch', 'wp-i18n' ), VOCASYNC_VERSION, true );
		}
	}
}
