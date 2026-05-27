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
