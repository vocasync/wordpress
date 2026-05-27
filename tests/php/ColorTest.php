<?php
declare( strict_types=1 );
namespace VocaSync\Tests;

use PHPUnit\Framework\TestCase;
use VocaSync\Support\Color;

final class ColorTest extends TestCase {
	public function test_to_rgb_parses_and_rejects(): void {
		$this->assertSame( array( 255, 255, 255 ), Color::to_rgb( '#ffffff' ) );
		$this->assertSame( array( 0, 0, 0 ), Color::to_rgb( '000000' ) );
		$this->assertSame( array( 170, 187, 204 ), Color::to_rgb( '#abc' ) );
		$this->assertNull( Color::to_rgb( 'nope' ) );
	}
	public function test_contrast_text(): void {
		$this->assertSame( '#1e293b', Color::contrast_text( '#ffffff' ) );
		$this->assertSame( '#ffffff', Color::contrast_text( '#000000' ) );
		$this->assertSame( '#ffffff', Color::contrast_text( '#3b82f6' ) );
	}
	public function test_darken(): void {
		$this->assertSame( '#e0e0e0', Color::darken( '#ffffff', 0.12 ) );
		$this->assertSame( '#000000', Color::darken( '#000000', 0.5 ) );
	}
	public function test_mix(): void {
		$this->assertSame( '#808080', Color::mix( '#ffffff', '#000000', 0.5 ) );
		$this->assertSame( '#123456', Color::mix( '#123456', 'zzzzzz', 0.6 ) );
	}
}
