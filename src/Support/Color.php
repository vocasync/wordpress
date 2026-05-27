<?php

declare( strict_types=1 );

namespace VocaSync\Support;

/**
 * Small, dependency-free colour helpers for deriving player theme tokens.
 * All hex in/out is `#rrggbb` lowercase.
 */
final class Color {

	/** @return array{0:int,1:int,2:int}|null */
	public static function to_rgb( string $hex ): ?array {
		$hex = ltrim( trim( $hex ), '#' );
		if ( 3 === strlen( $hex ) && ctype_xdigit( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return null;
		}
		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	public static function to_hex( int $r, int $g, int $b ): string {
		$clamp = static fn ( int $v ): int => max( 0, min( 255, $v ) );
		return sprintf( '#%02x%02x%02x', $clamp( $r ), $clamp( $g ), $clamp( $b ) );
	}

	/** Perceived luminance 0..1 (sRGB-weighted). */
	public static function luminance( string $hex ): float {
		$rgb = self::to_rgb( $hex );
		if ( null === $rgb ) {
			return 0.0;
		}
		return ( 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2] ) / 255;
	}

	/** Dark text on light backgrounds, white on dark. */
	public static function contrast_text( string $hex ): string {
		return self::luminance( $hex ) > 0.6 ? '#1e293b' : '#ffffff';
	}

	/** Darken toward black by $pct (0..1). */
	public static function darken( string $hex, float $pct ): string {
		$rgb = self::to_rgb( $hex );
		if ( null === $rgb ) {
			return $hex;
		}
		$f = max( 0.0, 1.0 - $pct );
		return self::to_hex( (int) round( $rgb[0] * $f ), (int) round( $rgb[1] * $f ), (int) round( $rgb[2] * $f ) );
	}

	/** Mix $a and $b; $weight is the share of $a (0..1). Falls back to $a on bad input. */
	public static function mix( string $a, string $b, float $weight ): string {
		$ra = self::to_rgb( $a );
		$rb = self::to_rgb( $b );
		if ( null === $ra ) {
			return $a;
		}
		if ( null === $rb ) {
			return self::to_hex( $ra[0], $ra[1], $ra[2] );
		}
		$w = max( 0.0, min( 1.0, $weight ) );
		return self::to_hex(
			(int) round( $ra[0] * $w + $rb[0] * ( 1 - $w ) ),
			(int) round( $ra[1] * $w + $rb[1] * ( 1 - $w ) ),
			(int) round( $ra[2] * $w + $rb[2] * ( 1 - $w ) )
		);
	}
}
