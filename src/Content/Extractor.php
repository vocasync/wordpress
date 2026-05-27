<?php

declare( strict_types=1 );

namespace VocaSync\Content;

use DOMElement;
use DOMNode;
use DOMText;
use VocaSync\Tokenizer\SpokenForm;

/**
 * Build the speech document from rendered post HTML, mirroring
 * `vocasync/astro/src/core/speech-builder.ts`:
 *
 *   - Walk visible text in document order, skipping the same elements the
 *     WordWrapper skips (so the word streams agree → token parity).
 *   - Insert heading (`# `), list-item (`. `), and block-break markers that
 *     `strip_for_alignment` removes, so they aid synthesis chunking without
 *     becoming alignment tokens.
 *   - speechtext -> /synthesis; normalisedSpeechtext -> alignment transcript.
 */
final class Extractor {

	/**
	 * @return array{speechtext:string, normalisedSpeechtext:string}
	 */
	public function extract( string $html, string $mode = SpokenForm::MODE_ENGLISH ): array {
		$dom  = Tokenizer::load( $html );
		$root = $dom ? Tokenizer::root( $dom ) : null;
		if ( null === $root ) {
			return array(
				'speechtext'           => '',
				'normalisedSpeechtext' => '',
			);
		}

		$parts = array();
		foreach ( iterator_to_array( $root->childNodes ) as $child ) {
			$this->visit( $child, $parts );
		}

		$plain = implode( ' ', $parts );
		$plain = preg_replace( '/[ \t]+/', ' ', $plain ) ?? $plain;
		$plain = preg_replace( '/\n\s*\n/', "\n\n", $plain ) ?? $plain;
		$plain = preg_replace( '/\s*\.\s*\./', '.', $plain ) ?? $plain;
		$plain = trim( $plain );

		return SpokenForm::pair( $plain, $mode );
	}

	/**
	 * Cheap content facts for the editor: synthesis character count and whether
	 * the body yields any alignment tokens (false for empty or non-Latin scripts).
	 *
	 * @return array{charCount:int, speakable:bool}
	 */
	public function analyze( string $html, string $mode = SpokenForm::MODE_ENGLISH ): array {
		$speech    = $this->extract( $html, $mode );
		$speakable = '' !== $speech['normalisedSpeechtext'];
		return array(
			'charCount' => $speakable ? ( function_exists( 'mb_strlen' ) ? mb_strlen( $speech['speechtext'] ) : strlen( $speech['speechtext'] ) ) : 0,
			'speakable' => $speakable,
		);
	}

	/**
	 * @param list<string> $parts
	 */
	private function visit( DOMNode $node, array &$parts ): void {
		if ( $node instanceof DOMText ) {
			$parts[] = $node->nodeValue ?? '';
			return;
		}
		if ( ! $node instanceof DOMElement ) {
			return;
		}
		if ( Tokenizer::is_skippable( $node ) ) {
			return;
		}

		$tag = strtolower( $node->tagName );
		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$parts[] = "\n# ";
				$this->visit_children( $node, $parts );
				$parts[] = "\n";
				break;
			case 'p':
				$this->visit_children( $node, $parts );
				$parts[] = "\n";
				break;
			case 'li':
				$this->visit_children( $node, $parts );
				$parts[] = '. ';
				break;
			case 'blockquote':
				$this->visit_children( $node, $parts );
				$parts[] = "\n";
				break;
			case 'br':
				$parts[] = ' ';
				break;
			case 'hr':
				$parts[] = "\n\n";
				break;
			default:
				$this->visit_children( $node, $parts );
				break;
		}
	}

	/**
	 * @param list<string> $parts
	 */
	private function visit_children( DOMElement $element, array &$parts ): void {
		foreach ( iterator_to_array( $element->childNodes ) as $child ) {
			$this->visit( $child, $parts );
		}
	}

	/**
	 * Change-detection hash: sha256(JSON([normalisedSpeechtext, voice, language,
	 * format])). Keys on the spoken transcript (the article body words, with the
	 * player/code/images skipped) so markup-only edits — including adding the
	 * VocaSync player block — don't invalidate the audio. Mirrors the Astro recipe.
	 */
	public static function content_hash(
		string $normalised_speechtext,
		string $voice,
		string $language,
		string $format
	): string {
		$payload = (string) wp_json_encode( array( $normalised_speechtext, $voice, $language, $format ) );
		return hash( 'sha256', $payload );
	}
}
