<?php
declare( strict_types=1 );
namespace VocaSync\Tests;

use PHPUnit\Framework\TestCase;
use VocaSync\Content\Extractor;
use VocaSync\Tokenizer\SpokenForm;

final class ExtractorAnalyzeTest extends TestCase {
	public function test_counts_chars_and_detects_speakable(): void {
		$r = ( new Extractor() )->analyze( '<p>It costs $50 today.</p>', SpokenForm::MODE_ENGLISH );
		$this->assertTrue( $r['speakable'] );
		$this->assertGreaterThan( 0, $r['charCount'] );
	}
	public function test_non_latin_is_not_speakable(): void {
		$r = ( new Extractor() )->analyze( '<p>こんにちは世界</p>', SpokenForm::MODE_ENGLISH );
		$this->assertFalse( $r['speakable'] );
	}
	public function test_empty_is_not_speakable(): void {
		$r = ( new Extractor() )->analyze( '<p>   </p>', SpokenForm::MODE_ENGLISH );
		$this->assertFalse( $r['speakable'] );
		$this->assertSame( 0, $r['charCount'] );
	}
}
