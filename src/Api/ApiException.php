<?php

declare( strict_types=1 );

namespace VocaSync\Api;

use Exception;

/**
 * Raised when a VocaSync API request fails (transport error or non-2xx).
 */
final class ApiException extends Exception {

	public function __construct(
		string $message,
		private int $status_code = 0,
		private string $response_body = ''
	) {
		parent::__construct( $message );
	}

	public function status_code(): int {
		return $this->status_code;
	}

	public function response_body(): string {
		return $this->response_body;
	}

	public function is_unauthorized(): bool {
		return 401 === $this->status_code;
	}

	public function is_insufficient_balance(): bool {
		return 402 === $this->status_code;
	}

	public function is_rate_limited(): bool {
		return 429 === $this->status_code;
	}
}
