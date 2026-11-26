<?php
/**
 * Unit-test bootstrap. Loads the Composer autoloader and shims the handful of
 * WordPress functions used by otherwise-pure classes, so the parity tests run
 * without a full WordPress install.
 *
 * @package VocaSync
 */

declare( strict_types=1 );

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'VOCASYNC_PLUGIN_FILE' ) ) {
	define( 'VOCASYNC_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/vocasync.php' );
	define( 'VOCASYNC_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
	define( 'VOCASYNC_PLUGIN_URL', 'http://example.test/wp-content/plugins/vocasync/' );
	define( 'VOCASYNC_VERSION', '0.1.0-test' );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
		return json_encode( $data, $options, $depth );
	}
}

// Minimal global WordPress stubs so the boot-wiring smoke test can run the real
// register() paths in isolation. Pure-logic tests don't call these.
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ): bool {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ): bool {
		return true;
	}
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( ...$args ): bool {
		return true;
	}
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( ...$args ): bool {
		return true;
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( $file );
	}
}

// ── In-memory WordPress state stubs ─────────────────────────────────────────
// Every plugin class is `final`, so tests cannot mock them. Instead these tiny
// stubs back the real classes with plain arrays. Tests call vocasync_test_reset()
// in setUp() to start from a clean slate.

/**
 * Reset the in-memory options, post meta, posts and captured HTTP calls.
 */
function vocasync_test_reset(): void {
	$GLOBALS['vocasync_test'] = array(
		'options' => array(),
		'meta'    => array(),
		'posts'   => array(),
		'http'    => array(),
	);
}
vocasync_test_reset();

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID                = 0;
		public string $post_content   = '';
		public string $post_status    = 'publish';
		public string $post_title     = '';

		public function __construct( int $id = 0, string $content = '' ) {
			$this->ID           = $id;
			$this->post_content = $content;
		}
	}
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @param array<string,mixed> $params */
		public function __construct( private array $params = array() ) {}

		public function get_param( string $key ): mixed {
			return $this->params[ $key ] ?? null;
		}
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public function __construct( public mixed $data = null ) {}
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, mixed $default_value = false ): mixed {
		return $GLOBALS['vocasync_test']['options'][ $name ] ?? $default_value;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
		return $GLOBALS['vocasync_test']['meta'][ $post_id ][ $key ] ?? ( $single ? '' : array() );
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $key, mixed $value ): bool {
		$GLOBALS['vocasync_test']['meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $key ): bool {
		unset( $GLOBALS['vocasync_test']['meta'][ $post_id ][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( int $post_id ): ?WP_Post {
		return $GLOBALS['vocasync_test']['posts'][ $post_id ] ?? null;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		return $value;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( string $color ): ?string {
		return 1 === preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ? $color : null;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( mixed $data ): WP_REST_Response {
		return new WP_REST_Response( $data );
	}
}

// HTTP: capture the outgoing request instead of sending it.
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( string $url, array $args = array() ): array {
		$GLOBALS['vocasync_test']['http'][] = array(
			'url'  => $url,
			'args' => $args,
		);
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{}',
		);
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return false;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( array $response ): int {
		return (int) ( $response['response']['code'] ?? 0 );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( array $response ): string {
		return (string) ( $response['body'] ?? '' );
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( int $length = 12, bool $special = true, bool $extra = false ): string {
		return str_repeat( 'x', $length );
	}
}

// Settings-page view helpers.
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( $text );
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo esc_html( $text );
	}
}
if ( ! function_exists( 'selected' ) ) {
	function selected( mixed $selected, mixed $current = true, bool $display = true ): string {
		$out = (string) $selected === (string) $current ? " selected='selected'" : '';
		if ( $display ) {
			echo $out; // phpcs:ignore
		}
		return $out;
	}
}
if ( ! function_exists( 'checked' ) ) {
	function checked( mixed $checked, mixed $current = true, bool $display = true ): string {
		$out = (string) $checked === (string) $current ? " checked='checked'" : '';
		if ( $display ) {
			echo $out; // phpcs:ignore
		}
		return $out;
	}
}
if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( string $group ): void {}
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button(): void {}
}
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string {
		return 'http://example.test/wp-json/' . $path;
	}
}
if ( ! function_exists( 'wp_is_using_https' ) ) {
	function wp_is_using_https(): bool {
		return true;
	}
}
