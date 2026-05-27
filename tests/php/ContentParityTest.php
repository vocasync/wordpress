<?php

declare( strict_types=1 );

namespace VocaSync\Tests;

use PHPUnit\Framework\TestCase;
use VocaSync\Content\Extractor;
use VocaSync\Content\WordWrapper;
use VocaSync\Tokenizer\SpokenForm;

/**
 * The render-time WordWrapper and the transcript Extractor must enumerate the
 * same word stream. We assert that the sum of `data-n` over the wrapped spans
 * equals the number of tokens in the alignment transcript, and that `data-i`
 * runs cumulatively — that is the token-parity invariant the player relies on.
 */
final class ContentParityTest extends TestCase {

	private const SAMPLE = <<<HTML
		<h2>The Beginning</h2>
		<p>I paid \$50 for 3 items at 10% off today. It's a great deal!</p>
		<ul><li>First point</li><li>Second point</li></ul>
		<p>Visit <a href="https://example.com">our site</a> for more.</p>
		<pre><code>const x = 42; // skipped</code></pre>
		<figure><img src="x.png" alt="alt skipped" /><figcaption>caption skipped</figcaption></figure>
		<blockquote>A quoted line with numbers like 1995.</blockquote>
		HTML;

	private function sum_data_n( string $wrapped ): int {
		preg_match_all( '/data-n="(\d+)"/', $wrapped, $m );
		return array_sum( array_map( 'intval', $m[1] ) );
	}

	/** @return list<int> */
	private function data_i_values( string $wrapped ): array {
		preg_match_all( '/data-i="(\d+)"/', $wrapped, $m );
		return array_map( 'intval', $m[1] );
	}

	private function transcript_token_count( string $normalised ): int {
		if ( '' === $normalised ) {
			return 0;
		}
		return count( preg_split( '/\s+/', $normalised, -1, PREG_SPLIT_NO_EMPTY ) );
	}

	public function test_data_n_sum_matches_transcript_tokens(): void {
		$mode      = SpokenForm::MODE_ENGLISH;
		$extracted = ( new Extractor() )->extract( self::SAMPLE, $mode );
		$wrapped   = ( new WordWrapper() )->wrap( self::SAMPLE, $mode );

		$this->assertNotSame( '', $extracted['normalisedSpeechtext'], 'transcript should not be empty' );

		$transcript_tokens = $this->transcript_token_count( $extracted['normalisedSpeechtext'] );
		$wrapped_tokens     = $this->sum_data_n( $wrapped );

		$this->assertSame(
			$transcript_tokens,
			$wrapped_tokens,
			'sum(data-n) must equal the transcript token count (token parity)'
		);
	}

	public function test_data_i_is_cumulative(): void {
		$wrapped = ( new WordWrapper() )->wrap( self::SAMPLE, SpokenForm::MODE_ENGLISH );

		// Re-derive the expected running index from the data-n sequence.
		preg_match_all( '/data-i="(\d+)"\s+data-n="(\d+)"/', $wrapped, $m, PREG_SET_ORDER );
		$this->assertNotEmpty( $m, 'expected wrapped spans' );

		$expected = 0;
		foreach ( $m as $span ) {
			$this->assertSame( $expected, (int) $span[1], 'data-i must be the running token index' );
			$expected += (int) $span[2];
		}
	}

	public function test_skip_set_is_not_wrapped(): void {
		$wrapped = ( new WordWrapper() )->wrap( self::SAMPLE, SpokenForm::MODE_ENGLISH );

		// Code, alt text, and figcaption content must not be wrapped or spoken.
		$this->assertStringContainsString( 'const x = 42', $wrapped, 'code content should remain' );
		$this->assertStringNotContainsString( 'caption skipped</span>', $wrapped );
		$this->assertDoesNotMatchRegularExpression(
			'/vocasync-word[^>]*>const/',
			$wrapped,
			'code must not be word-wrapped'
		);

		$extracted = ( new Extractor() )->extract( self::SAMPLE, SpokenForm::MODE_ENGLISH );
		$this->assertStringNotContainsString( 'skipped', $extracted['normalisedSpeechtext'], 'skipped content must not be spoken' );
		$this->assertStringNotContainsString( 'caption', $extracted['normalisedSpeechtext'] );
	}

	public function test_currency_expands_to_two_tokens(): void {
		$wrapped = ( new WordWrapper() )->wrap( '<p>It costs \$50 here.</p>', SpokenForm::MODE_ENGLISH );
		// "$50" is a single visible word that expands to two tokens ("fifty dollar").
		$this->assertMatchesRegularExpression(
			'/<span class="vocasync-word" data-i="\d+" data-n="2">\$50<\/span>/',
			$wrapped
		);
	}
}
