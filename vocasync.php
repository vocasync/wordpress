<?php
/**
 * Plugin Name:       VocaSync
 * Plugin URI:        https://vocasync.io
 * Description:        Turn posts into narrated audio with word-level highlighting, powered by VocaSync.
 * Version:           0.1.8
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Sayed Hamid Fatimi
 * Author URI:        https://github.com/sayedhfatimi
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vocasync
 * Domain Path:       /languages
 *
 * @package VocaSync
 */

declare( strict_types=1 );

namespace VocaSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VOCASYNC_VERSION', '0.1.8' );
define( 'VOCASYNC_PLUGIN_FILE', __FILE__ );
define( 'VOCASYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VOCASYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VOCASYNC_API_BASE', 'https://vocasync.io/api/v1' );

/**
 * Load the Composer autoloader. Without it the plugin cannot run, so surface a
 * clear admin notice instead of fataling.
 */
$vocasync_autoload = VOCASYNC_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! is_readable( $vocasync_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'VocaSync is missing its dependencies. Run "composer install" in the plugin directory.',
				'vocasync'
			);
			echo '</p></div>';
		}
	);
	return;
}
require $vocasync_autoload;

// Action Scheduler ships as a Composer library; its loader must be required
// directly (it self-registers on plugins_loaded).
$vocasync_action_scheduler = VOCASYNC_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if ( is_readable( $vocasync_action_scheduler ) ) {
	require_once $vocasync_action_scheduler;
}

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
