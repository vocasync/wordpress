<?php

declare( strict_types=1 );

namespace VocaSync\Generation;

/**
 * Per-post persistence: the completed audio artifact, the in-flight job state
 * machine, per-post overrides, and the small queryable mirror metas the
 * reconciliation sweep and webhook receiver use to find/resolve jobs.
 *
 * Status lifecycle: idle → synthesizing → aligning → done | failed.
 */
final class Artifact {

	public const META_ARTIFACT   = '_vocasync_artifact';
	public const META_STATE      = '_vocasync_state';
	public const META_STATUS     = '_vocasync_status';      // queryable mirror
	public const META_TOKEN      = '_vocasync_cb_token';    // queryable per-job callback token
	public const META_LAST_EVENT = '_vocasync_last_event';  // queryable int (epoch)
	public const META_OVERRIDES  = '_vocasync_overrides';

	public const STATUS_IDLE         = 'idle';
	public const STATUS_SYNTHESIZING = 'synthesizing';
	public const STATUS_ALIGNING     = 'aligning';
	public const STATUS_DONE         = 'done';
	public const STATUS_FAILED       = 'failed';

	// ── Artifact ────────────────────────────────────────────────────

	/** @return array<string,mixed>|null */
	public function get_artifact( int $post_id ): ?array {
		$stored = get_post_meta( $post_id, self::META_ARTIFACT, true );
		return is_array( $stored ) && ! empty( $stored ) ? $stored : null;
	}

	/** @param array<string,mixed> $artifact */
	public function set_artifact( int $post_id, array $artifact ): void {
		update_post_meta( $post_id, self::META_ARTIFACT, $artifact );
	}

	public function delete_artifact( int $post_id ): void {
		delete_post_meta( $post_id, self::META_ARTIFACT );
	}

	/** A completed artifact whose hash matches the current content. */
	public function is_current( int $post_id, string $content_hash ): bool {
		$artifact = $this->get_artifact( $post_id );
		return null !== $artifact
			&& ! empty( $artifact['words'] )
			&& ( $artifact['contentHash'] ?? '' ) === $content_hash;
	}

	// ── Job state ───────────────────────────────────────────────────

	/** @return array<string,mixed> */
	public function get_state( int $post_id ): array {
		$state = get_post_meta( $post_id, self::META_STATE, true );
		if ( ! is_array( $state ) || empty( $state ) ) {
			return array( 'status' => self::STATUS_IDLE );
		}
		return $state;
	}

	public function status( int $post_id ): string {
		$status = get_post_meta( $post_id, self::META_STATUS, true );
		return is_string( $status ) && '' !== $status ? $status : self::STATUS_IDLE;
	}

	public function is_in_flight( int $post_id ): bool {
		return in_array( $this->status( $post_id ), array( self::STATUS_SYNTHESIZING, self::STATUS_ALIGNING ), true );
	}

	/** @param array<string,mixed> $state */
	public function save_state( int $post_id, array $state ): void {
		update_post_meta( $post_id, self::META_STATE, $state );
		update_post_meta( $post_id, self::META_STATUS, (string) ( $state['status'] ?? self::STATUS_IDLE ) );
		update_post_meta( $post_id, self::META_LAST_EVENT, (int) ( $state['lastEventAt'] ?? time() ) );
		if ( ! empty( $state['callbackToken'] ) ) {
			update_post_meta( $post_id, self::META_TOKEN, (string) $state['callbackToken'] );
		}
	}

	/**
	 * Merge a partial update into the job state and persist.
	 *
	 * @param array<string,mixed> $partial
	 * @return array<string,mixed> the new state
	 */
	public function patch_state( int $post_id, array $partial ): array {
		$state = array_merge( $this->get_state( $post_id ), $partial );
		$this->save_state( $post_id, $state );
		return $state;
	}

	public function touch_last_event( int $post_id ): void {
		$now = time();
		update_post_meta( $post_id, self::META_LAST_EVENT, $now );
		$state                = $this->get_state( $post_id );
		$state['lastEventAt'] = $now;
		update_post_meta( $post_id, self::META_STATE, $state );
	}

	public function clear_state( int $post_id ): void {
		delete_post_meta( $post_id, self::META_STATE );
		delete_post_meta( $post_id, self::META_STATUS );
		delete_post_meta( $post_id, self::META_TOKEN );
		delete_post_meta( $post_id, self::META_LAST_EVENT );
	}

	public function resolve_post_by_token( string $token ): ?int {
		if ( '' === $token ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => self::META_TOKEN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $token,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => true,
			)
		);
		return ! empty( $posts ) ? (int) $posts[0] : null;
	}

	/** @return list<int> post IDs with an in-flight job. */
	public function in_flight_posts(): array {
		$posts = get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => 'any',
				'numberposts'      => 100,
				'fields'           => 'ids',
				'meta_key'         => self::META_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => array( self::STATUS_SYNTHESIZING, self::STATUS_ALIGNING ),
				'meta_compare'     => 'IN',
				'suppress_filters' => true,
			)
		);
		return array_map( 'intval', $posts );
	}

	// ── Per-post overrides ──────────────────────────────────────────

	/** @return array<string,mixed> */
	public function get_overrides( int $post_id ): array {
		$o = get_post_meta( $post_id, self::META_OVERRIDES, true );
		return is_array( $o ) ? $o : array();
	}

	/** @param array<string,mixed> $overrides */
	public function set_overrides( int $post_id, array $overrides ): void {
		update_post_meta( $post_id, self::META_OVERRIDES, $overrides );
	}
}
