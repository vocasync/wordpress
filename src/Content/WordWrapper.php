<?php

declare( strict_types=1 );

namespace VocaSync\Content;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use VocaSync\Tokenizer\SpokenForm;

/**
 * Wrap each visible word in rendered post HTML with
 * `<span class="vocasync-word" data-i="N" data-n="K">…</span>`, mirroring
 * `vocasync/astro/src/rehype/audio-words.ts`.
 *
 *   data-i = the word's starting index in the alignment word stream.
 *   data-n = how many alignment tokens the visible word expands to.
 *
 * Tokenization is deterministic and uses the same SpokenForm primitives the
 * Extractor used to build the transcript, so indices line up token-for-token.
 */
final class WordWrapper {

	public const WORD_CLASS = 'vocasync-word';

	/**
	 * @return string The wrapped inner HTML, or the original on parse failure.
	 */
	public function wrap( string $html, string $mode = SpokenForm::MODE_ENGLISH ): string {
		$dom  = Tokenizer::load( $html );
		$root = $dom ? Tokenizer::root( $dom ) : null;
		if ( null === $dom || null === $root ) {
			return $html;
		}

		// Collect target text nodes first (mutating during traversal is unsafe).
		$text_nodes = array();
		$this->collect_text_nodes( $root, $text_nodes );

		$index = 0;
		foreach ( $text_nodes as $text_node ) {
			$index = $this->wrap_text_node( $dom, $text_node, $index, $mode );
		}

		return $this->inner_html( $dom, $root );
	}

	/**
	 * @param list<DOMText> $out
	 */
	private function collect_text_nodes( DOMNode $node, array &$out ): void {
		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			if ( $child instanceof DOMText ) {
				if ( '' !== ( $child->nodeValue ?? '' ) ) {
					$out[] = $child;
				}
				continue;
			}
			if ( $child instanceof DOMElement && ! Tokenizer::is_skippable( $child ) ) {
				$this->collect_text_nodes( $child, $out );
			}
		}
	}

	private function wrap_text_node( DOMDocument $dom, DOMText $text_node, int $index, string $mode ): int {
		$text    = $text_node->nodeValue ?? '';
		$matches = Tokenizer::match_words( $text );
		if ( empty( $matches ) ) {
			return $index;
		}

		$parent = $text_node->parentNode;
		if ( null === $parent ) {
			return $index;
		}
		$fragment = $dom->createDocumentFragment();
		$cursor   = 0;

		foreach ( $matches as $match ) {
			$word   = $match['word'];
			$offset = $match['offset'];

			if ( $offset > $cursor ) {
				$fragment->appendChild( $dom->createTextNode( substr( $text, $cursor, $offset - $cursor ) ) );
			}

			$count = SpokenForm::token_count( $word, $mode );
			if ( $count > 0 ) {
				$fragment->appendChild( $this->make_span( $dom, $word, $index, $count ) );
				$index += $count;
			} else {
				$fragment->appendChild( $dom->createTextNode( $word ) );
			}

			$cursor = $offset + strlen( $word );
		}

		if ( $cursor < strlen( $text ) ) {
			$fragment->appendChild( $dom->createTextNode( substr( $text, $cursor ) ) );
		}

		$parent->replaceChild( $fragment, $text_node );
		return $index;
	}

	private function make_span( DOMDocument $dom, string $word, int $data_i, int $data_n ): DOMElement {
		$span = $dom->createElement( 'span' );
		$span->setAttribute( 'class', self::WORD_CLASS );
		$span->setAttribute( 'data-i', (string) $data_i );
		$span->setAttribute( 'data-n', (string) $data_n );
		$span->appendChild( $dom->createTextNode( $word ) );
		return $span;
	}

	private function inner_html( DOMDocument $dom, DOMElement $root ): string {
		$html = '';
		foreach ( iterator_to_array( $root->childNodes ) as $child ) {
			$html .= $dom->saveHTML( $child );
		}
		return $html;
	}
}
