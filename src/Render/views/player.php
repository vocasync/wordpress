<?php
/**
 * Player markup view. Returns a callable rendering the player for a post.
 * Ported from `@vocasync/astro` components/AudioPlayer.astro.
 *
 * @package VocaSync
 */

declare( strict_types=1 );

/**
 * @param int                  $post_id
 * @param string               $data_json  JSON-encoded {audioUrl, publishableKey, words, duration}
 * @param array<string,bool>   $opts       highlighting | mini_player | click_to_seek
 */
return static function ( int $post_id, string $data_json, array $opts ): string {
	$player_id = 'vocasync-player-' . $post_id;
	$audio_id  = $player_id . '-audio';
	$mini_id   = $player_id . '-mini';
	$label     = esc_attr__( 'Listen to this article', 'vocasync' );

	$highlighting = ! empty( $opts['highlighting'] ) ? 'true' : 'false';
	$mini         = ! empty( $opts['mini_player'] ) ? 'true' : 'false';
	$click_seek   = ! empty( $opts['click_to_seek'] ) ? 'true' : 'false';

	ob_start();
	?>
	<div class="vocasync-player" id="<?php echo esc_attr( $player_id ); ?>"
		data-enable-highlighting="<?php echo esc_attr( $highlighting ); ?>"
		data-enable-mini="<?php echo esc_attr( $mini ); ?>"
		data-enable-click-to-seek="<?php echo esc_attr( $click_seek ); ?>"
		data-trail-length="4"
		aria-label="<?php echo esc_attr( $label ); ?>"
		tabindex="0">
		<script type="application/json" class="vocasync-data"><?php echo $data_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG. ?></script>
		<audio id="<?php echo esc_attr( $audio_id ); ?>" preload="metadata"></audio>

		<div class="vocasync-player__loading" data-state="loading">
			<div class="vocasync-player__spinner"></div>
			<span><?php esc_html_e( 'Loading audio…', 'vocasync' ); ?></span>
		</div>

		<div class="vocasync-player__placeholder" data-state="no-audio" hidden>
			<span class="vocasync-player__placeholder-text"><?php esc_html_e( 'Audio not available for this article', 'vocasync' ); ?></span>
		</div>

		<div class="vocasync-player__controls" data-state="ready" hidden>
			<button type="button" class="vocasync-player__play-btn" aria-label="<?php esc_attr_e( 'Play', 'vocasync' ); ?>" data-action="play-pause" title="<?php esc_attr_e( 'Play/Pause (Space)', 'vocasync' ); ?>">
				<svg class="vocasync-icon vocasync-icon--play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 4l15 8-15 8V4z"/></svg>
				<svg class="vocasync-icon vocasync-icon--pause vocasync-hidden" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/></svg>
			</button>

			<div class="vocasync-player__progress-section">
				<div class="vocasync-player__time">
					<span data-time="current">0:00</span>
					<span class="vocasync-player__time-sep">/</span>
					<span data-time="duration">0:00</span>
				</div>
				<input type="range" class="vocasync-player__progress" min="0" max="100" value="0" step="0.1" aria-label="<?php esc_attr_e( 'Seek', 'vocasync' ); ?>" data-action="seek" />
			</div>

			<div class="vocasync-player__button-group">
				<?php if ( ! empty( $opts['highlighting'] ) ) : ?>
				<button type="button" class="vocasync-player__icon-btn" aria-label="<?php esc_attr_e( 'Toggle word highlighting', 'vocasync' ); ?>" data-action="toggle-highlighting" data-highlighting="on" title="<?php esc_attr_e( 'Toggle highlighting (H)', 'vocasync' ); ?>">
					<svg class="vocasync-icon vocasync-icon--highlight-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18"/><rect x="4" y="8" width="16" height="8" rx="1" fill="currentColor" opacity="0.3"/><path d="M8 12h8"/></svg>
					<svg class="vocasync-icon vocasync-icon--highlight-off vocasync-hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18" opacity="0.3"/><rect x="4" y="8" width="16" height="8" rx="1" opacity="0.3"/><path d="M4 4l16 16"/></svg>
				</button>
				<?php endif; ?>

				<?php if ( ! empty( $opts['speed'] ) ) : ?>
				<div class="vocasync-player__speed">
					<button type="button" class="vocasync-player__speed-btn" aria-label="<?php esc_attr_e( 'Playback speed', 'vocasync' ); ?>" data-action="speed"><span data-speed-label>1x</span></button>
					<div class="vocasync-player__speed-menu" hidden>
						<button type="button" data-speed="0.5">0.5x</button>
						<button type="button" data-speed="0.75">0.75x</button>
						<button type="button" data-speed="1" data-active>1x</button>
						<button type="button" data-speed="1.25">1.25x</button>
						<button type="button" data-speed="1.5">1.5x</button>
						<button type="button" data-speed="2">2x</button>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $opts['volume'] ) ) : ?>
				<div class="vocasync-player__volume">
					<button type="button" class="vocasync-player__icon-btn" aria-label="<?php esc_attr_e( 'Toggle mute', 'vocasync' ); ?>" data-action="mute" title="<?php esc_attr_e( 'Toggle mute (M)', 'vocasync' ); ?>">
						<svg class="vocasync-icon vocasync-icon--volume-high" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" fill="currentColor"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>
						<svg class="vocasync-icon vocasync-icon--volume-mute vocasync-hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" fill="currentColor" opacity="0.5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
					</button>
					<input type="range" class="vocasync-player__volume-slider" min="0" max="100" value="100" aria-label="<?php esc_attr_e( 'Volume', 'vocasync' ); ?>" data-action="volume" />
				</div>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( 'true' === $mini ) : ?>
		<div class="vocasync-mini-player" id="<?php echo esc_attr( $mini_id ); ?>" data-mini-player hidden>
			<button type="button" class="vocasync-mini-player__play-btn" aria-label="<?php esc_attr_e( 'Play', 'vocasync' ); ?>" data-action="play-pause">
				<svg class="vocasync-icon vocasync-icon--play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 4l15 8-15 8V4z"/></svg>
				<svg class="vocasync-icon vocasync-icon--pause vocasync-hidden" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/></svg>
			</button>
			<div class="vocasync-mini-player__progress"><div class="vocasync-mini-player__progress-bar" data-progress></div></div>
			<span class="vocasync-mini-player__time" data-time="current">0:00</span>
			<button type="button" class="vocasync-mini-player__close-btn" aria-label="<?php esc_attr_e( 'Close mini player', 'vocasync' ); ?>" data-action="close-mini">
				<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
			</button>
		</div>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
};
