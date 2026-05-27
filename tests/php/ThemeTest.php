<?php
declare( strict_types=1 );
namespace VocaSync\Tests;

use PHPUnit\Framework\TestCase;
use VocaSync\Render\Theme;

final class ThemeTest extends TestCase {
	private function theme( string $scheme ): array {
		return array(
			'accent'    => '#7c3aed',
			'highlight' => '#f59e0b',
			'surface'   => '#ffffff',
			'text'      => '#1e293b',
			'border'    => '#e5e7eb',
			'radius'    => 14,
			'scheme'    => $scheme,
		);
	}
	public function test_light_has_no_media_block(): void {
		$css = Theme::build_css( $this->theme( 'light' ) );
		$this->assertStringContainsString( '--vocasync-primary:#7c3aed', $css );
		$this->assertStringContainsString( '--vocasync-highlight:#f59e0b', $css );
		$this->assertStringContainsString( '--vocasync-player-radius:14px', $css );
		$this->assertStringNotContainsString( '@media', $css );
	}
	public function test_auto_has_dark_media_with_dark_surface(): void {
		$css = Theme::build_css( $this->theme( 'auto' ) );
		$this->assertStringContainsString( '--vocasync-surface:#ffffff', $css );
		$this->assertStringContainsString( '@media (prefers-color-scheme: dark)', $css );
		$this->assertStringContainsString( '--vocasync-surface:#1e293b', $css );
		$this->assertStringContainsString( '--vocasync-primary:#7c3aed', $css );
	}
	public function test_dark_uses_dark_surface_without_media(): void {
		$css = Theme::build_css( $this->theme( 'dark' ) );
		$this->assertStringNotContainsString( '@media', $css );
		$this->assertStringContainsString( '--vocasync-surface:#1e293b', $css );
		$this->assertStringContainsString( '--vocasync-primary:#7c3aed', $css );
	}
}
