<?php

declare( strict_types=1 );

namespace VocaSync\Settings;

use VocaSync\Constants;

/**
 * Plugin settings: option storage, typed accessors, and the admin settings
 * page. Secrets (API key, webhook signing secret) may be provided via
 * wp-config constants, which take precedence over the stored option and keep
 * them out of the database.
 */
final class Settings {

	public const OPTION     = 'vocasync_settings';
	private const GROUP     = 'vocasync';
	private const PAGE_SLUG = 'vocasync';

	/** @var array<string,mixed>|null */
	private ?array $cache = null;

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/** @return array<string,mixed> */
	public function defaults(): array {
		return array(
			'api_key'        => '',
			'webhook_secret' => '',
			'voice'          => Constants::DEFAULT_VOICE,
			'quality'        => Constants::DEFAULT_QUALITY,
			'format'         => Constants::DEFAULT_FORMAT,
			'language'       => Constants::DEFAULT_LANGUAGE,
			'position'       => 'top',
			'highlighting'   => true,
			'click_to_seek'  => true,
			'mini_player'    => true,
			'auto_generate'  => false,
			'theme_enabled'  => false,
			'accent'         => '#3b82f6',
			'highlight'      => '#10b981',
			'surface'        => '#f8fafc',
			'text'           => '#1e293b',
			'border'         => '#e2e8f0',
			'radius'         => 12,
			'scheme'         => 'auto',
			'show_speed'     => true,
			'show_volume'    => true,
		);
	}

	/** @return array<string,mixed> */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, array() );
			$this->cache = array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
		}
		return $this->cache;
	}

	public function get( string $key, mixed $fallback = null ): mixed {
		$all = $this->all();
		return $all[ $key ] ?? $fallback;
	}

	public function get_api_key(): string {
		if ( defined( 'VOCASYNC_API_KEY' ) && is_string( VOCASYNC_API_KEY ) && '' !== VOCASYNC_API_KEY ) {
			return VOCASYNC_API_KEY;
		}
		return (string) $this->get( 'api_key', '' );
	}

	public function get_webhook_secret(): string {
		if ( defined( 'VOCASYNC_WEBHOOK_SECRET' ) && is_string( VOCASYNC_WEBHOOK_SECRET ) && '' !== VOCASYNC_WEBHOOK_SECRET ) {
			return VOCASYNC_WEBHOOK_SECRET;
		}
		return (string) $this->get( 'webhook_secret', '' );
	}

	public function has_api_key(): bool {
		return '' !== $this->get_api_key();
	}

	/** Webhooks are usable only when a signing secret is configured. */
	public function webhooks_enabled(): bool {
		return '' !== $this->get_webhook_secret();
	}

	public function default_voice(): string {
		return (string) $this->get( 'voice', Constants::DEFAULT_VOICE );
	}

	public function default_quality(): string {
		return (string) $this->get( 'quality', Constants::DEFAULT_QUALITY );
	}

	public function default_format(): string {
		return (string) $this->get( 'format', Constants::DEFAULT_FORMAT );
	}

	public function default_language(): string {
		return (string) $this->get( 'language', Constants::DEFAULT_LANGUAGE );
	}

	public function player_position(): string {
		return 'bottom' === $this->get( 'position' ) ? 'bottom' : 'top';
	}

	public function highlighting_enabled(): bool {
		return (bool) $this->get( 'highlighting', true );
	}

	public function click_to_seek_enabled(): bool {
		return (bool) $this->get( 'click_to_seek', true );
	}

	public function mini_player_enabled(): bool {
		return (bool) $this->get( 'mini_player', true );
	}

	public function has_custom_theme(): bool {
		return (bool) $this->get( 'theme_enabled', false );
	}

	/** @return array{accent:string,highlight:string,surface:string,text:string,border:string,radius:int,scheme:string} */
	public function theme(): array {
		return array(
			'accent'    => (string) $this->get( 'accent', '#3b82f6' ),
			'highlight' => (string) $this->get( 'highlight', '#10b981' ),
			'surface'   => (string) $this->get( 'surface', '#f8fafc' ),
			'text'      => (string) $this->get( 'text', '#1e293b' ),
			'border'    => (string) $this->get( 'border', '#e2e8f0' ),
			'radius'    => (int) $this->get( 'radius', 12 ),
			'scheme'    => (string) $this->get( 'scheme', 'auto' ),
		);
	}

	/** Global default visibility per control. @return array<string,bool> */
	public function control_defaults(): array {
		return array(
			'speed'     => (bool) $this->get( 'show_speed', true ),
			'volume'    => (bool) $this->get( 'show_volume', true ),
			'mini'      => $this->mini_player_enabled(),
			'highlight' => $this->highlighting_enabled(),
			'clickSeek' => $this->click_to_seek_enabled(),
		);
	}

	public function auto_generate_default(): bool {
		return (bool) $this->get( 'auto_generate', false );
	}

	// ────────────────────────────────────────────────────────────────
	// Admin page
	// ────────────────────────────────────────────────────────────────

	public function add_menu(): void {
		add_options_page(
			__( 'VocaSync', 'vocasync' ),
			__( 'VocaSync', 'vocasync' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_setting(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
			)
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = $this->defaults();
		$clean    = array();

		$clean['api_key']        = isset( $input['api_key'] ) ? trim( sanitize_text_field( (string) $input['api_key'] ) ) : '';
		$clean['webhook_secret'] = isset( $input['webhook_secret'] ) ? trim( sanitize_text_field( (string) $input['webhook_secret'] ) ) : '';

		$clean['voice']    = $this->pick( $input['voice'] ?? '', Constants::VOICES, $defaults['voice'] );
		$clean['quality']  = $this->pick( $input['quality'] ?? '', Constants::QUALITIES, $defaults['quality'] );
		$clean['format']   = $this->pick( $input['format'] ?? '', Constants::FORMATS, $defaults['format'] );
		$clean['language'] = $this->pick( $input['language'] ?? '', Constants::HIGHLIGHT_LANGUAGES, $defaults['language'] );
		$clean['position'] = $this->pick( $input['position'] ?? '', array( 'top', 'bottom' ), $defaults['position'] );

		$clean['highlighting']  = ! empty( $input['highlighting'] );
		$clean['click_to_seek'] = ! empty( $input['click_to_seek'] );
		$clean['mini_player']   = ! empty( $input['mini_player'] );
		$clean['auto_generate'] = ! empty( $input['auto_generate'] );

		$clean['theme_enabled'] = ! empty( $input['theme_enabled'] );
		foreach ( array( 'accent', 'highlight', 'surface', 'text', 'border' ) as $color ) {
			$val             = isset( $input[ $color ] ) ? sanitize_hex_color( (string) $input[ $color ] ) : null;
			$clean[ $color ] = $val ? $val : $defaults[ $color ];
		}
		$clean['radius']      = isset( $input['radius'] ) ? max( 0, min( 24, (int) $input['radius'] ) ) : $defaults['radius'];
		$clean['scheme']      = $this->pick( $input['scheme'] ?? '', array( 'auto', 'light', 'dark' ), $defaults['scheme'] );
		$clean['show_speed']  = ! empty( $input['show_speed'] );
		$clean['show_volume'] = ! empty( $input['show_volume'] );

		return $clean;
	}

	/**
	 * @param mixed        $value
	 * @param list<string> $allowed
	 */
	private function pick( mixed $value, array $allowed, string $fallback ): string {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		( require VOCASYNC_PLUGIN_DIR . 'src/Settings/views/settings-page.php' )( $this );
	}
}
