<?php

declare( strict_types=1 );

namespace VocaSync\Content;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Shared HTML tokenization primitives used by both the transcript Extractor and
 * the render-time WordWrapper, so they enumerate the same visible words in the
 * same order — the basis of token parity.
 *
 * Mirrors `vocasync/astro/src/rehype/audio-words.ts`:
 *   - WORD_PATTERN matches a visible "word" (letters/digits/currency/percent,
 *     with intra-word apostrophes). Comma-grouped numbers (1,000,000) split —
 *     a known limitation shared with the Astro plugin.
 *   - Skip set: never emit speech / wrap words inside these.
 */
final class Tokenizer {

	public const WORD_PATTERN = '/[\p{L}\p{N}\p{Sc}%]+(?:[\x{0027}\x{2019}][\p{L}\p{N}\p{Sc}%]+)*/u';

	/** @var array<string,true> */
	private const SKIP_TAGS = array(
		'pre'           => true,
		'code'          => true,
		'script'        => true,
		'style'         => true,
		'textarea'      => true,
		'svg'           => true,
		'math'          => true,
		'mjx-container' => true,
		'img'           => true,
		'figcaption'    => true,
	);

	/** @var array<string,true> */
	private const SKIP_CLASSES = array(
		'katex'                => true,
		'katex-mathml'         => true,
		'katex-html'           => true,
		'vocasync-player'      => true,
		'vocasync-mini-player' => true,
	);

	public const ROOT_ID = 'vocasync-root';

	/**
	 * Load post HTML into a DOMDocument wrapped in a known root element, so
	 * extraction and serialization operate on a stable subtree. UTF-8 safe.
	 */
	public static function load( string $html ): ?DOMDocument {
		if ( '' === trim( $html ) ) {
			return null;
		}
		$dom      = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$wrapped  = '<?xml encoding="utf-8"?><div id="' . self::ROOT_ID . '">' . $html . '</div>';
		$dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $dom;
	}

	public static function root( DOMDocument $dom ): ?DOMElement {
		$root = $dom->getElementById( self::ROOT_ID );
		if ( $root instanceof DOMElement ) {
			return $root;
		}
		// getElementById can miss without a DTD; fall back to a direct lookup.
		foreach ( $dom->getElementsByTagName( 'div' ) as $div ) {
			if ( self::ROOT_ID === $div->getAttribute( 'id' ) ) {
				return $div;
			}
		}
		return null;
	}

	public static function is_skippable( DOMElement $element ): bool {
		if ( isset( self::SKIP_TAGS[ strtolower( $element->tagName ) ] ) ) {
			return true;
		}
		$class = $element->getAttribute( 'class' );
		if ( '' === $class ) {
			return false;
		}
		$classes = preg_split( '/\s+/', $class );
		if ( false === $classes ) {
			$classes = array();
		}
		foreach ( $classes as $cls ) {
			if ( isset( self::SKIP_CLASSES[ $cls ] ) ) {
				return true;
			}
		}
		return false;
	}

	/** True if any ancestor (inclusive) of $node is a skippable element. */
	public static function is_under_skip( DOMNode $node ): bool {
		for ( $current = $node; $current instanceof DOMNode; $current = $current->parentNode ) {
			if ( $current instanceof DOMElement && self::is_skippable( $current ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Find visible-word matches in a string.
	 *
	 * @return list<array{word:string, offset:int}> byte offsets into $text
	 */
	public static function match_words( string $text ): array {
		if ( '' === $text ) {
			return array();
		}
		$matches = array();
		if ( preg_match_all( self::WORD_PATTERN, $text, $found, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $found[0] as $m ) {
				$matches[] = array(
					'word'   => $m[0],
					'offset' => (int) $m[1],
				);
			}
		}
		return $matches;
	}
}
