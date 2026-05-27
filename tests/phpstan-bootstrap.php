<?php
/**
 * PHPStan bootstrap. The plugin defines these constants at runtime via define();
 * PHPStan does not collect define()-d constants across files, so declare them
 * here for static analysis. Values are placeholders — only the names/types matter.
 *
 * @package VocaSync
 */

declare( strict_types=1 );

define( 'VOCASYNC_VERSION', '1.0.0' );
define( 'VOCASYNC_PLUGIN_FILE', dirname( __DIR__ ) . '/vocasync.php' );
define( 'VOCASYNC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'VOCASYNC_PLUGIN_URL', 'https://example.test/wp-content/plugins/vocasync/' );
define( 'VOCASYNC_API_BASE', 'https://vocasync.io/api/v1' );
