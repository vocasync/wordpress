<?php

declare( strict_types=1 );

namespace VocaSync\Render;

use VocaSync\Support\Color;

/**
 * Builds the CSS-variable override block for a custom player theme. Pure: a
 * validated theme array in, a CSS string out. Accent + highlight carry across
 * light/dark; surface/text/border swap to a built-in dark palette under dark.
 */
final class Theme {

	private const DARK = array(
		'surface'    => '#1e293b',
		'elevated'   => '#334155',
		'border'     => '#475569',
		'text'       => '#f1f5f9',
		'text_muted' => '#94a3b8',
		'text_faint' => '#64748b',
	);

	/**
	 * @param array{accent:string,highlight:string,surface:string,text:string,border:string,radius:int,scheme:string} $t
	 */
	public static function build_css( array $t ): string {
		$accent    = $t['accent'];
		$highlight = $t['highlight'];

		$brand = array(
			'--vocasync-primary'         => $accent,
			'--vocasync-primary-hover'   => Color::darken( $accent, 0.12 ),
			'--vocasync-primary-content' => Color::contrast_text( $accent ),
			'--vocasync-border-focus'    => $accent,
			'--vocasync-highlight'       => $highlight,
			'--vocasync-highlight-text'  => Color::contrast_text( $highlight ),
			'--vocasync-player-radius'   => ( (int) $t['radius'] ) . 'px',
		);

		$light = array(
			'--vocasync-surface'          => $t['surface'],
			'--vocasync-surface-elevated' => '#ffffff',
			'--vocasync-border'           => $t['border'],
			'--vocasync-text'             => $t['text'],
			'--vocasync-text-muted'       => Color::mix( $t['text'], $t['surface'], 0.6 ),
			'--vocasync-text-faint'       => Color::mix( $t['text'], $t['surface'], 0.4 ),
		);

		$dark = array(
			'--vocasync-surface'          => self::DARK['surface'],
			'--vocasync-surface-elevated' => self::DARK['elevated'],
			'--vocasync-border'           => self::DARK['border'],
			'--vocasync-text'             => self::DARK['text'],
			'--vocasync-text-muted'       => self::DARK['text_muted'],
			'--vocasync-text-faint'       => self::DARK['text_faint'],
		);

		$scheme = in_array( $t['scheme'], array( 'auto', 'light', 'dark' ), true ) ? $t['scheme'] : 'auto';

		if ( 'dark' === $scheme ) {
			return ':root{' . self::vars( $brand + $dark ) . '}';
		}
		$css = ':root{' . self::vars( $brand + $light ) . '}';
		if ( 'auto' === $scheme ) {
			$css .= '@media (prefers-color-scheme: dark){:root{' . self::vars( $dark ) . '}}';
		}
		return $css;
	}

	/** @param array<string,string> $vars */
	private static function vars( array $vars ): string {
		$out = '';
		foreach ( $vars as $name => $value ) {
			$out .= $name . ':' . $value . ';';
		}
		return $out;
	}
}
