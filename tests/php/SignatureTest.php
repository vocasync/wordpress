<?php

declare( strict_types=1 );

namespace VocaSync\Tests;

use PHPUnit\Framework\TestCase;
use VocaSync\Webhook\Signature;

/**
 * Ported from `valeon-org/blog/convex/lib/__tests__/vocasyncWebhook.test.ts`.
 * Getting the key derivation wrong silently rejects every delivery, so the
 * exact scheme is pinned here.
 */
final class SignatureTest extends TestCase {

	private const SECRET = 'whsec_0123456789abcdef0123456789abcdef';
	private const BODY   = '{"id":"wevt_abc","type":"job.completed","data":{"job":{"id":"j1","status":"completed"}}}';

	public function test_valid_signature_passes(): void {
		$t      = 1_700_000_000;
		$header = Signature::sign( $t, self::BODY, self::SECRET );
		$this->assertTrue( Signature::verify( $header, self::BODY, self::SECRET, $t ) );
	}

	public function test_signs_over_t_dot_body_with_full_secret(): void {
		$t        = 1_700_000_000;
		$expected = hash_hmac( 'sha256', $t . '.' . self::BODY, self::SECRET );
		$this->assertSame( "t={$t},v1={$expected}", Signature::sign( $t, self::BODY, self::SECRET ) );
	}

	public function test_tampered_body_fails(): void {
		$t      = 1_700_000_000;
		$header = Signature::sign( $t, self::BODY, self::SECRET );
		$this->assertFalse( Signature::verify( $header, self::BODY . 'x', self::SECRET, $t ) );
	}

	public function test_tampered_signature_fails(): void {
		$t      = 1_700_000_000;
		$header = Signature::sign( $t, self::BODY, self::SECRET );
		$bad    = substr( $header, 0, -1 ) . ( str_ends_with( $header, '0' ) ? '1' : '0' );
		$this->assertFalse( Signature::verify( $bad, self::BODY, self::SECRET, $t ) );
	}

	public function test_stale_timestamp_fails(): void {
		$t      = 1_700_000_000;
		$header = Signature::sign( $t, self::BODY, self::SECRET );
		// Verify 6 minutes later → outside the ±5 min window.
		$this->assertFalse( Signature::verify( $header, self::BODY, self::SECRET, $t + 360 ) );
	}

	public function test_future_timestamp_within_window_passes(): void {
		$t      = 1_700_000_000;
		$header = Signature::sign( $t, self::BODY, self::SECRET );
		$this->assertTrue( Signature::verify( $header, self::BODY, self::SECRET, $t - 120 ) );
	}

	public function test_missing_header_fails(): void {
		$this->assertFalse( Signature::verify( null, self::BODY, self::SECRET ) );
		$this->assertFalse( Signature::verify( '', self::BODY, self::SECRET ) );
	}

	public function test_empty_secret_fails(): void {
		$t      = 1_700_000_000;
		$header = Signature::sign( $t, self::BODY, self::SECRET );
		$this->assertFalse( Signature::verify( $header, self::BODY, '', $t ) );
	}

	public function test_order_independent_header_parsing(): void {
		$t        = 1_700_000_000;
		$expected = hash_hmac( 'sha256', $t . '.' . self::BODY, self::SECRET );
		$reversed = "v1={$expected},t={$t}";
		$this->assertTrue( Signature::verify( $reversed, self::BODY, self::SECRET, $t ) );
	}

	public function test_uppercase_hex_signature_accepted(): void {
		$t        = 1_700_000_000;
		$expected = strtoupper( hash_hmac( 'sha256', $t . '.' . self::BODY, self::SECRET ) );
		$header   = "t={$t},v1={$expected}";
		$this->assertTrue( Signature::verify( $header, self::BODY, self::SECRET, $t ) );
	}
}
