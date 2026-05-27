<?php
/**
 * Settings page view. Returns a callable invoked with the Settings instance.
 *
 * @package VocaSync
 */

declare( strict_types=1 );

use VocaSync\Constants;
use VocaSync\Settings\Settings;

return static function ( Settings $settings ): void {
	$option             = Settings::OPTION;
	$api_via_constant   = defined( 'VOCASYNC_API_KEY' ) && '' !== (string) constant( 'VOCASYNC_API_KEY' );
	$whsec_via_constant = defined( 'VOCASYNC_WEBHOOK_SECRET' ) && '' !== (string) constant( 'VOCASYNC_WEBHOOK_SECRET' );
	$webhook_url        = rest_url( 'vocasync/v1/webhook' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'VocaSync', 'vocasync' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %s: vocasync.io link */
				esc_html__( 'Generate narrated audio for your posts with word-level highlighting. Get an API key at %s.', 'vocasync' ),
				'<a href="https://vocasync.io" target="_blank" rel="noopener">vocasync.io</a>' // phpcs:ignore
			);
			?>
		</p>

		<form action="options.php" method="post">
			<?php settings_fields( 'vocasync' ); ?>

			<h2 class="title"><?php esc_html_e( 'API credentials', 'vocasync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="vocasync-api-key"><?php esc_html_e( 'API key', 'vocasync' ); ?></label></th>
					<td>
						<?php if ( $api_via_constant ) : ?>
							<p class="description"><?php esc_html_e( 'Set via the VOCASYNC_API_KEY constant in wp-config.php.', 'vocasync' ); ?></p>
						<?php else : ?>
							<input type="password" autocomplete="off" class="regular-text" id="vocasync-api-key"
								name="<?php echo esc_attr( "{$option}[api_key]" ); ?>"
								value="<?php echo esc_attr( (string) $settings->get( 'api_key', '' ) ); ?>" />
							<button type="button" class="button" id="vocasync-test-connection"><?php esc_html_e( 'Test connection', 'vocasync' ); ?></button>
							<span id="vocasync-test-result" aria-live="polite"></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="vocasync-webhook-secret"><?php esc_html_e( 'Webhook signing secret', 'vocasync' ); ?></label></th>
					<td>
						<?php if ( $whsec_via_constant ) : ?>
							<p class="description"><?php esc_html_e( 'Set via the VOCASYNC_WEBHOOK_SECRET constant in wp-config.php.', 'vocasync' ); ?></p>
						<?php else : ?>
							<input type="password" autocomplete="off" class="regular-text" id="vocasync-webhook-secret"
								name="<?php echo esc_attr( "{$option}[webhook_secret]" ); ?>"
								value="<?php echo esc_attr( (string) $settings->get( 'webhook_secret', '' ) ); ?>" />
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'The whsec_… secret shown once when you created your API key. Required for instant completion via webhooks; without it, the plugin falls back to polling.', 'vocasync' ); ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Webhook receiver URL (set automatically per job):', 'vocasync' ); ?>
							<code><?php echo esc_html( $webhook_url ); ?></code>
						</p>
						<?php if ( ! wp_is_using_https() ) : ?>
							<p class="description" style="color:#b32d2e;">
								<?php esc_html_e( 'This site is not served over HTTPS, so VocaSync cannot deliver webhooks here. Generation will use polling instead.', 'vocasync' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Defaults', 'vocasync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="vocasync-voice"><?php esc_html_e( 'Voice', 'vocasync' ); ?></label></th>
					<td>
						<select id="vocasync-voice" name="<?php echo esc_attr( "{$option}[voice]" ); ?>">
							<?php foreach ( Constants::VOICES as $voice ) : ?>
								<option value="<?php echo esc_attr( $voice ); ?>" <?php selected( $settings->default_voice(), $voice ); ?>>
									<?php echo esc_html( ucfirst( $voice ) . ' — ' . Constants::VOICE_DESCRIPTIONS[ $voice ] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="vocasync-language"><?php esc_html_e( 'Language', 'vocasync' ); ?></label></th>
					<td>
						<select id="vocasync-language" name="<?php echo esc_attr( "{$option}[language]" ); ?>">
							<?php foreach ( Constants::LANGUAGE_LABELS as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $settings->default_language(), $code ); ?>>
									<?php echo esc_html( 'en' === $code ? $label : $label . ' — experimental' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'English is fully supported. Other Latin-script languages are experimental. Cyrillic and CJK languages are not yet supported for highlighting.', 'vocasync' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="vocasync-quality"><?php esc_html_e( 'Quality', 'vocasync' ); ?></label></th>
					<td>
						<select id="vocasync-quality" name="<?php echo esc_attr( "{$option}[quality]" ); ?>">
							<option value="sd" <?php selected( $settings->default_quality(), 'sd' ); ?>><?php esc_html_e( 'Standard', 'vocasync' ); ?></option>
							<option value="hd" <?php selected( $settings->default_quality(), 'hd' ); ?>><?php esc_html_e( 'High (premium)', 'vocasync' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="vocasync-format"><?php esc_html_e( 'Audio format', 'vocasync' ); ?></label></th>
					<td>
						<select id="vocasync-format" name="<?php echo esc_attr( "{$option}[format]" ); ?>">
							<?php foreach ( Constants::FORMATS as $format ) : ?>
								<option value="<?php echo esc_attr( $format ); ?>" <?php selected( $settings->default_format(), $format ); ?>><?php echo esc_html( strtoupper( $format ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Player position', 'vocasync' ); ?></th>
					<td>
						<label><input type="radio" name="<?php echo esc_attr( "{$option}[position]" ); ?>" value="top" <?php checked( $settings->player_position(), 'top' ); ?> /> <?php esc_html_e( 'Top of content', 'vocasync' ); ?></label><br />
						<label><input type="radio" name="<?php echo esc_attr( "{$option}[position]" ); ?>" value="bottom" <?php checked( $settings->player_position(), 'bottom' ); ?> /> <?php esc_html_e( 'Bottom of content', 'vocasync' ); ?></label>
						<p class="description"><?php esc_html_e( 'Ignored when you place a VocaSync block or [vocasync] shortcode in the post.', 'vocasync' ); ?></p>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Player features', 'vocasync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Features', 'vocasync' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( "{$option}[highlighting]" ); ?>" value="1" <?php checked( $settings->highlighting_enabled() ); ?> /> <?php esc_html_e( 'Word-level highlighting', 'vocasync' ); ?></label><br />
						<label><input type="checkbox" name="<?php echo esc_attr( "{$option}[click_to_seek]" ); ?>" value="1" <?php checked( $settings->click_to_seek_enabled() ); ?> /> <?php esc_html_e( 'Click a word to seek', 'vocasync' ); ?></label><br />
						<label><input type="checkbox" name="<?php echo esc_attr( "{$option}[mini_player]" ); ?>" value="1" <?php checked( $settings->mini_player_enabled() ); ?> /> <?php esc_html_e( 'Floating mini-player', 'vocasync' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-generate', 'vocasync' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( "{$option}[auto_generate]" ); ?>" value="1" <?php checked( $settings->auto_generate_default() ); ?> /> <?php esc_html_e( 'Generate audio automatically when a post is published or updated', 'vocasync' ); ?></label>
						<p class="description"><?php esc_html_e( 'Default for new posts; can be overridden per post. Unchanged content is never re-billed.', 'vocasync' ); ?></p>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Player appearance', 'vocasync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable custom theme', 'vocasync' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( "{$option}[theme_enabled]" ); ?>" value="1" <?php checked( (bool) $settings->get( 'theme_enabled', false ) ); ?> /> <?php esc_html_e( 'Override the default player colors site-wide', 'vocasync' ); ?></label></td>
				</tr>
				<?php
				$color_fields = array(
					'accent'    => __( 'Accent', 'vocasync' ),
					'highlight' => __( 'Highlight', 'vocasync' ),
					'surface'   => __( 'Surface', 'vocasync' ),
					'text'      => __( 'Text', 'vocasync' ),
					'border'    => __( 'Border', 'vocasync' ),
				);
				foreach ( $color_fields as $key => $label ) :
					?>
					<tr>
						<th scope="row"><label for="vocasync-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="color" id="vocasync-<?php echo esc_attr( $key ); ?>" data-vocasync-token="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( "{$option}[{$key}]" ); ?>" value="<?php echo esc_attr( (string) $settings->get( $key ) ); ?>" /></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="vocasync-radius"><?php esc_html_e( 'Roundness', 'vocasync' ); ?></label></th>
					<td><input type="range" id="vocasync-radius" data-vocasync-token="radius" min="0" max="24" name="<?php echo esc_attr( "{$option}[radius]" ); ?>" value="<?php echo esc_attr( (string) $settings->get( 'radius', 12 ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="vocasync-scheme"><?php esc_html_e( 'Color scheme', 'vocasync' ); ?></label></th>
					<td>
						<select id="vocasync-scheme" name="<?php echo esc_attr( "{$option}[scheme]" ); ?>">
							<option value="auto" <?php selected( $settings->get( 'scheme', 'auto' ), 'auto' ); ?>><?php esc_html_e( 'Auto (follow visitor system)', 'vocasync' ); ?></option>
							<option value="light" <?php selected( $settings->get( 'scheme', 'auto' ), 'light' ); ?>><?php esc_html_e( 'Always light', 'vocasync' ); ?></option>
							<option value="dark" <?php selected( $settings->get( 'scheme', 'auto' ), 'dark' ); ?>><?php esc_html_e( 'Always dark', 'vocasync' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default controls', 'vocasync' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( "{$option}[show_speed]" ); ?>" value="1" <?php checked( (bool) $settings->get( 'show_speed', true ) ); ?> /> <?php esc_html_e( 'Speed control', 'vocasync' ); ?></label><br />
						<label><input type="checkbox" name="<?php echo esc_attr( "{$option}[show_volume]" ); ?>" value="1" <?php checked( (bool) $settings->get( 'show_volume', true ) ); ?> /> <?php esc_html_e( 'Volume control', 'vocasync' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Preview', 'vocasync' ); ?></th>
					<td>
						<div id="vocasync-preview" style="--vocasync-acc:<?php echo esc_attr( (string) $settings->get( 'accent' ) ); ?>;--vocasync-hl:<?php echo esc_attr( (string) $settings->get( 'highlight' ) ); ?>;--vocasync-surf:<?php echo esc_attr( (string) $settings->get( 'surface' ) ); ?>;--vocasync-txt:<?php echo esc_attr( (string) $settings->get( 'text' ) ); ?>;--vocasync-bd:<?php echo esc_attr( (string) $settings->get( 'border' ) ); ?>;--vocasync-rad:<?php echo esc_attr( (string) $settings->get( 'radius', 12 ) ); ?>px;max-width:420px;">
							<p style="color:var(--vocasync-txt);"><?php esc_html_e( 'Shall I tell you', 'vocasync' ); ?> <span style="background:var(--vocasync-hl);opacity:.85;border-radius:3px;padding:0 2px;"><?php esc_html_e( 'a story', 'vocasync' ); ?></span>?</p>
							<div style="display:flex;align-items:center;gap:12px;background:var(--vocasync-surf);border:1px solid var(--vocasync-bd);border-radius:var(--vocasync-rad);padding:12px;">
								<span style="width:36px;height:36px;border-radius:999px;background:var(--vocasync-acc);display:inline-flex;align-items:center;justify-content:center;color:#fff;">▶</span>
								<span style="flex:1;height:6px;border-radius:999px;background:var(--vocasync-bd);"></span>
							</div>
						</div>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
};
