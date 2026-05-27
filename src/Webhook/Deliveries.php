<?php

declare( strict_types=1 );

namespace VocaSync\Webhook;

/**
 * Append-only log of received webhook deliveries, doubling as the idempotency
 * gate (dedup on VocaSync's stable `wevt_…` event id). Mirrors Valeon's
 * `vocasyncWebhookDeliveries` table.
 */
final class Deliveries {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vocasync_webhook_deliveries';
	}

	public function create_table(): void {
		global $wpdb;
		$table           = $this->table();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id VARCHAR(80) NOT NULL,
			event_type VARCHAR(64) NOT NULL DEFAULT '',
			post_id BIGINT UNSIGNED NULL,
			phase VARCHAR(32) NOT NULL DEFAULT '',
			outcome VARCHAR(32) NOT NULL DEFAULT '',
			signature_valid TINYINT(1) NOT NULL DEFAULT 0,
			received_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY post_id (post_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Record a delivery. Returns true when the event id was already seen
	 * (duplicate retry) so the caller can skip dispatch.
	 */
	public function record(
		string $event_id,
		string $event_type,
		?int $post_id,
		string $phase,
		string $outcome,
		bool $signature_valid
	): bool {
		global $wpdb;
		// INSERT IGNORE: a duplicate event_id (unique key) inserts 0 rows.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				// Table name is built from $wpdb->prefix + a constant; values are placeholders.
				"INSERT IGNORE INTO {$this->table()} (event_id, event_type, post_id, phase, outcome, signature_valid, received_at) VALUES (%s, %s, %d, %s, %s, %d, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$event_id,
				$event_type,
				$post_id ?? 0,
				$phase,
				$outcome,
				$signature_valid ? 1 : 0,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		return 0 === (int) $inserted;
	}
}
