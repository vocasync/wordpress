<?php

declare( strict_types=1 );

namespace VocaSync\Api;

use VocaSync\Constants;
use VocaSync\Settings\Settings;

/**
 * VocaSync REST client over wp_remote_*. Mirrors
 * `vocasync/astro/src/api/client.ts`: the explicit two-POST pipeline
 * (synthesis → presign → upload → alignment) plus publishable-key minting and
 * stream fetches. The API key is read from Settings on each call so it always
 * reflects current configuration.
 */
final class Client {

	public function __construct( private Settings $settings ) {}

	private function base(): string {
		return Constants::API_BASE;
	}

	/** @return array<string,string> */
	private function auth_headers(): array {
		return array( 'Authorization' => 'Bearer ' . $this->settings->get_api_key() );
	}

	public function stream_url( string $project_uuid, string $kind ): string {
		return "{$this->base()}/stream/{$project_uuid}/{$kind}";
	}

	public static function with_publishable_key( string $url, string $publishable_key ): string {
		return add_query_arg( 'pk', rawurlencode( $publishable_key ), $url );
	}

	// ────────────────────────────────────────────────────────────────
	// Synthesis
	// ────────────────────────────────────────────────────────────────

	/**
	 * Create a synthesis project. Sends multipart/form-data (matching the Astro
	 * client) so the route's request.formData() parses it reliably.
	 *
	 * @param array{name:string,text:string,voice:string,quality:string,language:string,outputFormat:string} $params
	 * @param array{url:string,events?:list<string>}|null                                                    $webhook
	 * @return array<string,mixed>
	 */
	public function synthesize( array $params, ?array $webhook = null ): array {
		$fields = array(
			'textType'     => 'text',
			'text'         => $params['text'],
			'voice'        => $params['voice'],
			'quality'      => $params['quality'],
			'language'     => $params['language'],
			'outputFormat' => $params['outputFormat'],
			'projectName'  => $params['name'],
		);
		if ( null !== $webhook ) {
			$fields['webhook'] = (string) wp_json_encode( $webhook );
		}

		$multipart = $this->build_multipart( $fields );
		$response  = wp_remote_post(
			"{$this->base()}/synthesis",
			array(
				'headers' => $this->auth_headers() + array( 'Content-Type' => $multipart['content_type'] ),
				'body'    => $multipart['body'],
				'timeout' => 30,
			)
		);
		return $this->decode( $response, 'synthesis' );
	}

	/**
	 * Build a multipart/form-data body from text fields.
	 *
	 * @param array<string,string> $fields
	 * @return array{body:string, content_type:string}
	 */
	private function build_multipart( array $fields ): array {
		$boundary = '----vocasync' . wp_generate_password( 24, false, false );
		$body     = '';
		foreach ( $fields as $name => $value ) {
			$body .= "--{$boundary}\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n";
			$body .= $value . "\r\n";
		}
		$body .= "--{$boundary}--\r\n";
		return array(
			'body'         => $body,
			'content_type' => "multipart/form-data; boundary={$boundary}",
		);
	}

	/**
	 * GET /projects/{uuid}.
	 *
	 * @return array<string,mixed>
	 */
	public function get_project( string $project_uuid ): array {
		return $this->json_request( 'GET', "/projects/{$project_uuid}" );
	}

	/** Mint a publishable key for a completed project (full key returned once). */
	public function create_publishable_key( string $project_uuid ): string {
		$data = $this->json_request( 'POST', "/projects/{$project_uuid}/publishable-key" );
		return (string) ( $data['publishableKey'] ?? '' );
	}

	/**
	 * Download bytes from a (publishable-key) stream URL.
	 *
	 * @return array{bytes:string, content_type:string}
	 */
	public function download_bytes( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 120,
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new ApiException( 'Audio download failed: ' . $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new ApiException( "Audio download failed: HTTP {$status}", $status, wp_remote_retrieve_body( $response ) );
		}
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		return array(
			'bytes'        => (string) wp_remote_retrieve_body( $response ),
			'content_type' => (string) ( $content_type ? $content_type : 'audio/mpeg' ),
		);
	}

	// ────────────────────────────────────────────────────────────────
	// Alignment
	// ────────────────────────────────────────────────────────────────

	/**
	 * Request presigned upload URLs for the alignment audio + transcript.
	 * Presign mints the alignment project UUID.
	 *
	 * @param array{audioName:string,audioType:string,audioSize:int,transcriptSize:int,language:string} $params
	 * @return array<string,mixed>
	 */
	public function presign_alignment( array $params ): array {
		return $this->json_request(
			'POST',
			'/alignment/presign',
			array(
				'audioFile'      => array(
					'name' => $params['audioName'],
					'type' => $params['audioType'],
					'size' => $params['audioSize'],
				),
				'transcriptFile' => array(
					'name' => 'transcript.txt',
					'type' => 'text/plain',
					'size' => $params['transcriptSize'],
				),
				'language'       => $params['language'],
			)
		);
	}

	/** PUT bytes to a presigned S3 URL. */
	public function upload_to_presigned( string $upload_url, string $bytes, string $content_type ): void {
		$response = wp_remote_request(
			$upload_url,
			array(
				'method'  => 'PUT',
				'headers' => array( 'Content-Type' => $content_type ),
				'body'    => $bytes,
				'timeout' => 120,
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new ApiException( 'S3 upload failed: ' . $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new ApiException( "S3 upload failed: HTTP {$status}", $status, wp_remote_retrieve_body( $response ) );
		}
	}

	/**
	 * Submit an alignment job for pre-uploaded files (JSON mode).
	 *
	 * @param array{projectUuid:string,audioFileKey:string,transcriptFileKey:string,audioFileSizeBytes:int,transcriptFileSizeBytes:int,language:string,projectName:string} $params
	 * @param array{url:string,events?:list<string>}|null                                                                                                                  $webhook
	 * @return array<string,mixed>
	 */
	public function submit_alignment( array $params, ?array $webhook = null ): array {
		$body = array(
			'projectUuid'             => $params['projectUuid'],
			'audioFileKey'            => $params['audioFileKey'],
			'transcriptFileKey'       => $params['transcriptFileKey'],
			'audioFileSizeBytes'      => $params['audioFileSizeBytes'],
			'transcriptFileSizeBytes' => $params['transcriptFileSizeBytes'],
			'language'                => $params['language'],
			'projectName'             => $params['projectName'],
		);
		if ( null !== $webhook ) {
			$body['webhook'] = $webhook;
		}
		return $this->json_request( 'POST', '/alignment', $body );
	}

	/**
	 * Fetch alignment word timings from a completed alignment stream URL.
	 *
	 * @return array{words:list<array{word:string,start:float,end:float}>, duration:float}
	 */
	public function fetch_alignment_words( string $alignment_stream_url ): array {
		$response = wp_remote_get( $alignment_stream_url, array( 'timeout' => 60 ) );
		if ( is_wp_error( $response ) ) {
			throw new ApiException( 'Alignment fetch failed: ' . $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new ApiException( "Alignment fetch failed: HTTP {$status}", $status, wp_remote_retrieve_body( $response ) );
		}
		$data  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$words = is_array( $data ) && isset( $data['words'] ) && is_array( $data['words'] ) ? $data['words'] : array();
		$dur   = is_array( $data ) && isset( $data['duration'] ) ? (float) $data['duration'] : 0.0;
		return array(
			'words'    => array_values( $words ),
			'duration' => $dur,
		);
	}

	// ────────────────────────────────────────────────────────────────
	// Account
	// ────────────────────────────────────────────────────────────────

	/**
	 * Validate the configured API key and return the prepaid balance in cents.
	 *
	 * @return array{valid:bool, balanceCents:int|null}
	 */
	public function validate_api_key(): array {
		return $this->validate_key( $this->settings->get_api_key() );
	}

	/**
	 * Validate an explicit API key (used by the settings "Test connection"
	 * button, which checks the value typed in the field before it is saved).
	 *
	 * @return array{valid:bool, balanceCents:int|null}
	 */
	public function validate_key( string $api_key ): array {
		if ( '' === $api_key ) {
			return array(
				'valid'        => false,
				'balanceCents' => null,
			);
		}
		$response = wp_remote_get(
			"{$this->base()}/me",
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new ApiException( 'GET /me failed: ' . $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $status ) {
			return array(
				'valid'        => false,
				'balanceCents' => null,
			);
		}
		if ( $status < 200 || $status >= 300 ) {
			throw new ApiException( "GET /me failed: HTTP {$status}", $status, wp_remote_retrieve_body( $response ) );
		}
		$data    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$balance = is_array( $data ) && isset( $data['plan']['balanceCents'] ) ? (int) $data['plan']['balanceCents'] : null;
		return array(
			'valid'        => true,
			'balanceCents' => $balance,
		);
	}

	// ────────────────────────────────────────────────────────────────
	// Internals
	// ────────────────────────────────────────────────────────────────

	/**
	 * @param mixed $body
	 * @return array<string,mixed>
	 */
	private function json_request( string $method, string $endpoint, mixed $body = null ): array {
		$args = array(
			'method'  => $method,
			'headers' => $this->auth_headers() + array( 'Content-Type' => 'application/json' ),
			'timeout' => 30,
		);
		if ( null !== $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}
		$response = wp_remote_request( "{$this->base()}{$endpoint}", $args );
		return $this->decode( $response, "{$method} {$endpoint}" );
	}

	/**
	 * @param array|\WP_Error $response
	 * @return array<string,mixed>
	 */
	private function decode( $response, string $context ): array {
		if ( is_wp_error( $response ) ) {
			throw new ApiException( "{$context} failed: " . $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new ApiException( "{$context} failed: HTTP {$status}", $status, $raw );
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}
}
