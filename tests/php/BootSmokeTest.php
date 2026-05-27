<?php

declare( strict_types=1 );

namespace VocaSync\Tests;

use PHPUnit\Framework\TestCase;
use VocaSync\Plugin;

/**
 * Exercises the real Plugin::boot() wiring (every service constructor +
 * register() call) against the minimal WP stubs in bootstrap.php, catching
 * dependency/constructor mismatches the pure-unit tests don't.
 */
final class BootSmokeTest extends TestCase {

	public function test_boot_wires_all_services_without_error(): void {
		$plugin = Plugin::instance();
		$plugin->boot();

		$this->assertInstanceOf( Plugin::class, $plugin );
		$this->assertInstanceOf( \VocaSync\Settings\Settings::class, $plugin->settings() );
		$this->assertInstanceOf( \VocaSync\Generation\Orchestrator::class, $plugin->orchestrator() );
	}

	public function test_boot_is_idempotent(): void {
		// Calling boot() again must be a no-op (no duplicate wiring / errors).
		Plugin::instance()->boot();
		$this->assertInstanceOf( Plugin::class, Plugin::instance() );
	}
}
