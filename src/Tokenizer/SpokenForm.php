<?php

declare( strict_types=1 );

namespace VocaSync\Tokenizer;

use Normalizer;

/**
 * Spoken-form normalization primitives — a faithful PHP port of
 * `vocasync/astro/src/core/spoken-form.ts` (itself ported from the Valeon
 * blog's audio pipeline).
 *
 * Token parity is the core invariant: the alignment transcript IS
 * `strip(expand(text))`, and the rehype-equivalent word wrapper counts tokens
 * with the same `token_count`, so `data-i`/`data-n` line up with the alignment
 * word stream.
 *
 * Language modes:
 *   - english: full spoken-form expansion (numbers/currency/percent -> English
 *     words). MUST match the TS `expandSpokenForms` byte-for-byte.
 *   - latin:   no expansion (English number words would be wrong); the text is
 *     only NFKC-normalized + whitespace-collapsed. Experimental.
 *   - unsupported: scripts the ASCII-Latin tokenizer cannot represent (Cyrillic,
 *     CJK) — generation is gated off for these.
 *
 * `strip()` is language-agnostic and always runs (it produces the alignment
 * tokens and drives `token_count`).
 */
final class SpokenForm {

	public const MODE_ENGLISH     = 'english';
	public const MODE_LATIN       = 'latin';
	public const MODE_UNSUPPORTED = 'unsupported';

	/** Synthesis ISO-639-1 codes the Latin tokenizer can represent (besides en). */
	private const LATIN_LANGUAGES = array( 'fr', 'de', 'es', 'pt', 'sv', 'cs', 'pl', 'tr' );

	/** Currency symbol -> spoken word. Order mirrors the TS source. */
	private const CURRENCY_SYMBOL_WORDS = array(
		'£' => 'pound',
		'$' => 'dollar',
		'€' => 'euro',
		'¥' => 'yen',
		'₹' => 'rupee',
		'₽' => 'ruble',
		'₩' => 'won',
		'₪' => 'shekel',
		'₺' => 'lira',
		'฿' => 'baht',
		'₫' => 'dong',
		'₴' => 'hryvnia',
		'₦' => 'naira',
		'₱' => 'peso',
		'₲' => 'guarani',
		'₡' => 'colon',
		'₭' => 'kip',
		'₮' => 'tugrik',
		'₿' => 'bitcoin',
		'₣' => 'franc',
		'¢' => 'cent',
		'₤' => 'lira',
	);

	private const DIGIT_WORDS = array(
		'zero',
		'one',
		'two',
		'three',
		'four',
		'five',
		'six',
		'seven',
		'eight',
		'nine',
	);

	private const SMALL_WORDS = array(
		'zero',
		'one',
		'two',
		'three',
		'four',
		'five',
		'six',
		'seven',
		'eight',
		'nine',
		'ten',
		'eleven',
		'twelve',
		'thirteen',
		'fourteen',
		'fifteen',
		'sixteen',
		'seventeen',
		'eighteen',
		'nineteen',
	);

	private const TENS_WORDS = array(
		'',
		'',
		'twenty',
		'thirty',
		'forty',
		'fifty',
		'sixty',
		'seventy',
		'eighty',
		'ninety',
	);

	private const SCALE_WORDS = array(
		'',
		'thousand',
		'million',
		'billion',
		'trillion',
		'quadrillion',
		'quintillion',
		'sextillion',
	);

	/** Resolve the tokenizer mode for a synthesis language code (ISO 639-1). */
	public static function mode_for_language( string $language ): string {
		$lang = strtolower( trim( $language ) );
		if ( 'en' === $lang ) {
			return self::MODE_ENGLISH;
		}
		if ( in_array( $lang, self::LATIN_LANGUAGES, true ) ) {
			return self::MODE_LATIN;
		}
		return self::MODE_UNSUPPORTED;
	}

	public static function is_supported_language( string $language ): bool {
		return self::MODE_UNSUPPORTED !== self::mode_for_language( $language );
	}

	private static function nfkc( string $text ): string {
		$normalized = Normalizer::normalize( $text, Normalizer::FORM_KC );
		return false === $normalized ? $text : $normalized;
	}

	private static function nfkd( string $text ): string {
		$normalized = Normalizer::normalize( $text, Normalizer::FORM_KD );
		return false === $normalized ? $text : $normalized;
	}

	// ────────────────────────────────────────────────────────────────
	// Number → words
	// ────────────────────────────────────────────────────────────────

	private static function three_digit_words( int $value ): string {
		$parts = array();
		$v     = $value;
		if ( $v >= 100 ) {
			$parts[] = self::SMALL_WORDS[ intdiv( $v, 100 ) ];
			$parts[] = 'hundred';
			$v      %= 100;
		}
		if ( $v >= 20 ) {
			$parts[] = self::TENS_WORDS[ intdiv( $v, 10 ) ];
			$v      %= 10;
			if ( $v > 0 ) {
				$parts[] = self::SMALL_WORDS[ $v ];
			}
		} elseif ( $v > 0 || 0 === count( $parts ) ) {
			$parts[] = self::SMALL_WORDS[ $v ];
		}
		return trim( implode( ' ', $parts ) );
	}

	/**
	 * Convert an integer (given as a decimal digit string, no sign) to words.
	 * Operates on the string to support arbitrary magnitude (matches the TS
	 * BigInt path without 64-bit overflow).
	 */
	private static function integer_to_words( string $digits ): string {
		$digits = ltrim( $digits, '0' );
		if ( '' === $digits ) {
			return 'zero';
		}

		// Split into 3-digit groups, least-significant first.
		$groups = array();
		for ( $end = strlen( $digits ); $end > 0; $end -= 3 ) {
			$start    = max( 0, $end - 3 );
			$groups[] = (int) substr( $digits, $start, $end - $start );
		}

		$parts = array();
		for ( $scale = count( $groups ) - 1; $scale >= 0; $scale-- ) {
			$chunk = $groups[ $scale ];
			if ( $chunk <= 0 ) {
				continue;
			}
			$words      = self::three_digit_words( $chunk );
			$scale_word = self::SCALE_WORDS[ $scale ] ?? '';
			$parts[]    = '' !== $scale_word ? trim( "$words $scale_word" ) : $words;
		}
		return trim( implode( ' ', $parts ) );
	}

	private static function expand_number_token( string $token ): string {
		$working  = $token;
		$result   = array();
		$negative = false;
		if ( str_starts_with( $working, '-' ) ) {
			$negative = true;
			$working  = substr( $working, 1 );
		}
		if ( '' === $working ) {
			$working = '0';
		}
		$dot      = strpos( $working, '.' );
		$int_part = false === $dot ? $working : substr( $working, 0, $dot );
		$dec_part = false === $dot ? '' : substr( $working, $dot + 1 );

		if ( $negative ) {
			$result[] = 'minus';
		}
		$result[] = self::integer_to_words( $int_part );

		if ( '' !== $dec_part ) {
			$result[] = 'point';
			$len      = strlen( $dec_part );
			for ( $i = 0; $i < $len; $i++ ) {
				$digit = $dec_part[ $i ];
				if ( ctype_digit( $digit ) ) {
					$result[] = self::DIGIT_WORDS[ (int) $digit ];
				}
			}
		}
		return implode( ' ', $result );
	}

	private static function expand_numbers( string $input ): string {
		$out = preg_replace_callback(
			'/-?\d+(\.\d+)?/',
			static fn( array $m ): string => self::expand_number_token( $m[0] ),
			$input
		);
		return null === $out ? $input : $out;
	}

	private static function currency_symbol_class(): string {
		return '[' . implode( '', array_keys( self::CURRENCY_SYMBOL_WORDS ) ) . ']';
	}

	private static function expand_currency_symbols( string $input ): string {
		$range = self::currency_symbol_class();

		// Symbol-before-number: "$50" -> "50 dollar".
		$text = preg_replace_callback(
			'/(' . $range . ')\s*([0-9]+(?:[.,][0-9]+)*)/u',
			static function ( array $m ): string {
				$word = self::CURRENCY_SYMBOL_WORDS[ $m[1] ] ?? null;
				return null !== $word ? "{$m[2]} {$word}" : "{$m[1]}{$m[2]}";
			},
			$input
		);
		$text = null === $text ? $input : $text;

		// Number-before-symbol: "50$" -> "50 dollar".
		$text = preg_replace_callback(
			'/([0-9]+(?:[.,][0-9]+)*)\s*(' . $range . ')/u',
			static function ( array $m ): string {
				$word = self::CURRENCY_SYMBOL_WORDS[ $m[2] ] ?? null;
				return null !== $word ? "{$m[1]} {$word}" : "{$m[1]}{$m[2]}";
			},
			$text
		);
		$text = null === $text ? $input : $text;

		// Standalone symbol.
		$out = preg_replace_callback(
			'/' . $range . '/u',
			static function ( array $m ): string {
				$word = self::CURRENCY_SYMBOL_WORDS[ $m[0] ] ?? null;
				return null !== $word ? " {$word} " : ' ';
			},
			$text
		);
		return null === $out ? $text : $out;
	}

	private static function expand_percents( string $input ): string {
		$out = preg_replace( '/([0-9]+(?:\.[0-9]+)?)\s*%/', '$1 percent', $input );
		return null === $out ? $input : $out;
	}

	/**
	 * Expand currency symbols, numbers, and percentages into spoken word forms.
	 * Preserves punctuation/casing. This is the `speechtext` for English.
	 */
	public static function expand_spoken_forms( string $input ): string {
		$text = self::nfkc( $input );
		$text = self::expand_percents( $text );
		$text = self::expand_currency_symbols( $text );
		$text = preg_replace( '/(?<=\d),(?=\d)/', '', $text ) ?? $text; // thousand separators
		$text = self::expand_numbers( $text );
		return preg_replace( '/[ \t]+/', ' ', $text ) ?? $text;
	}

	/**
	 * Mode-aware "speechtext" producer. English fully expands; Latin only
	 * NFKC-normalizes + collapses (no English number words); unsupported is
	 * treated like Latin (callers should gate generation off beforehand).
	 */
	public static function expand( string $input, string $mode ): string {
		if ( self::MODE_ENGLISH === $mode ) {
			return self::expand_spoken_forms( $input );
		}
		$text = self::nfkc( $input );
		return preg_replace( '/[ \t]+/', ' ', $text ) ?? $text;
	}

	// ────────────────────────────────────────────────────────────────
	// Alignment form
	// ────────────────────────────────────────────────────────────────

	private static function strip_emoji( string $text ): string {
		// Older PCRE builds (e.g. PHP 8.0) lack \p{Extended_Pictographic}; detect
		// support once (suppressing the probe warning) and fall back to explicit
		// emoji blocks otherwise.
		static $supported = null;
		if ( null === $supported ) {
			$supported = false !== @preg_match( '/\p{Extended_Pictographic}/u', '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( $supported ) {
			$out = preg_replace( '/\p{Extended_Pictographic}/u', ' ', $text );
			if ( null !== $out ) {
				return $out;
			}
		}
		$fallback = preg_replace(
			'/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{1F1E6}-\x{1F1FF}]/u',
			' ',
			$text
		);
		return null === $fallback ? $text : $fallback;
	}

	/**
	 * Strip a speechtext-form string into the alignment form: lowercase,
	 * punctuation removed, whitespace collapsed, contractions preserved.
	 * Language-agnostic; non-Latin scripts collapse to empty (by design).
	 */
	public static function strip_for_alignment( string $input ): string {
		$text = self::nfkc( $input );

		// Control (C0/C1) + DEL + NBSP -> space.
		$text = preg_replace( '/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{00A0}]/u', ' ', $text ) ?? $text;
		// Zero-width / BiDi formatting -> removed.
		$text = preg_replace( '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}]/u', '', $text ) ?? $text;

		// Quote / dash normalisation.
		$text = preg_replace( '/[\x{201C}\x{201D}\x{201E}\x{201F}]/u', '"', $text ) ?? $text;
		$text = preg_replace( "/[\x{2018}\x{2019}\x{201A}\x{201B}]/u", "'", $text ) ?? $text;
		$text = preg_replace( '/\x{2026}/u', ' ', $text ) ?? $text;
		$text = preg_replace( '/[\x{002D}\x{2010}\x{2011}\x{2043}\x{2013}\x{2014}\x{2212}]/u', ' ', $text ) ?? $text;

		// Emoji, URLs, emails, @handles.
		$text = self::strip_emoji( $text );
		$text = preg_replace( '#https?://\S+#i', ' ', $text ) ?? $text;
		$text = preg_replace( '/\bwww\.[^\s\/]+\b/i', ' ', $text ) ?? $text;
		$text = preg_replace( '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/i', ' ', $text ) ?? $text;
		$text = preg_replace( '/@\w+/', ' ', $text ) ?? $text;

		// NFKD -> strip combining marks -> NFKC.
		$text = self::nfkd( $text );
		$text = preg_replace( '/\p{M}/u', '', $text ) ?? $text;
		$text = self::nfkc( $text );

		// Strip stray apostrophes that aren't part of a contraction.
		$text = preg_replace( "/(^|[^A-Za-z0-9])'(?=[^A-Za-z0-9]|$)/u", '$1', $text ) ?? $text;
		$text = preg_replace( "/(^|[^A-Za-z0-9])'(?=[A-Za-z0-9])/u", '$1', $text ) ?? $text;

		// Everything that's not a contraction-letter, digit, or whitespace -> space.
		$text = preg_replace( "/[^A-Za-z0-9'\s]/u", ' ', $text ) ?? $text;
		$text = preg_replace( '/\s+/', ' ', $text ) ?? $text;
		$text = trim( $text );

		return strtolower( $text );
	}

	// ────────────────────────────────────────────────────────────────
	// Public composites
	// ────────────────────────────────────────────────────────────────

	/**
	 * Produce both the speechtext (for /synthesis) and normalisedSpeechtext
	 * (the alignment transcript) from a plain-text input.
	 *
	 * @return array{speechtext:string, normalisedSpeechtext:string}
	 */
	public static function pair( string $plain_text, string $mode = self::MODE_ENGLISH ): array {
		$speechtext = self::expand( $plain_text, $mode );
		return array(
			'speechtext'           => $speechtext,
			'normalisedSpeechtext' => self::strip_for_alignment( $speechtext ),
		);
	}

	/**
	 * How many alignment tokens a visible string expands to. Drives `data-n`.
	 * "$50" -> 2, "1995" -> 6, ordinary words -> 1, non-Latin -> 0.
	 */
	public static function token_count( string $visible, string $mode = self::MODE_ENGLISH ): int {
		$normalised = self::strip_for_alignment( self::expand( $visible, $mode ) );
		if ( '' === $normalised ) {
			return 0;
		}
		$tokens = preg_split( '/\s+/', $normalised, -1, PREG_SPLIT_NO_EMPTY );
		return false === $tokens ? 0 : count( $tokens );
	}
}
