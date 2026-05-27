<?php

declare( strict_types=1 );

namespace VocaSync\Webhook;

/**
 * VocaSync webhook signature verification — a PHP port of
 * `valeon-org/blog/convex/lib/vocasyncWebhook.ts`. VocaSync signs deliveries
 * Stripe-style:
 *
 *   Header:  X-Vocasync-Signature: t=<unixSeconds>,v1=<hexHmac>
 *   Signed:  "{t}.{rawBody}"   (t in seconds)
 *   HMAC:    SHA-256, lowercase hex
 *   Key:     the FULL whsec_… string as UTF-8 bytes (not prefix-stripped/decoded)
 *
 * The caller must read the RAW request body before any JSON parsing.
 */
final class Signature {

	private const TOLERANCE_SECONDS = 300; // ±5 min replay window

	/**
	 * @return array{0:int,1:string}|null Parsed {t, v1}, order-independent.
	 */
	public static function parse_header( string $header ): ?array {
		$t  = null;
		$v1 = null;
		foreach ( explode( ',', $header ) as $part ) {
			$eq = strpos( $part, '=' );
			if ( false === $eq ) {
				continue;
			}
			$key   = trim( substr( $part, 0, $eq ) );
			$value = trim( substr( $part, $eq + 1 ) );
			if ( 't' === $key && is_numeric( $value ) ) {
				$t = (int) $value;
			} elseif ( 'v1' === $key ) {
				$v1 = $value;
			}
		}
		if ( null === $t || null === $v1 || '' === $v1 ) {
			return null;
		}
		return array( $t, $v1 );
	}

	public static function verify( ?string $header, string $raw_body, string $secret, ?int $now = null ): bool {
		if ( null === $header || '' === $header || '' === $secret ) {
			return false;
		}
		$parsed = self::parse_header( $header );
		if ( null === $parsed ) {
			return false;
		}
		[ $t, $v1 ] = $parsed;

		$now = $now ?? time();
		if ( abs( $now - $t ) > self::TOLERANCE_SECONDS ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $t . '.' . $raw_body, $secret );
		return hash_equals( $expected, strtolower( $v1 ) );
	}

	/** Helper for tests / internal signing parity checks. */
	public static function sign( int $timestamp, string $raw_body, string $secret ): string {
		return 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );
	}
}
