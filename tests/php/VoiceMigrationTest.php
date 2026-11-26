<?php
declare( strict_types=1 );
namespace VocaSync\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use VocaSync\Api\Client;
use VocaSync\Constants;
use VocaSync\Content\Extractor;
use VocaSync\Content\WordWrapper;
use VocaSync\Generation\Artifact;
use VocaSync\Generation\Orchestrator;
use VocaSync\Render\PlayerRenderer;
use VocaSync\Rest\Controller;
use VocaSync\Settings\Settings;
use VocaSync\Tokenizer\SpokenForm;
use WP_Post;
use WP_REST_Request;

/**
 * The move from 9 legacy voice names to the VocaSync catalogue must not change
 * a single content hash on an existing site: a changed hash re-synthesises (and
 * re-bills) the post and switches word highlighting off.
 */
final class VoiceMigrationTest extends TestCase {

	private const TEXT = 'The quick brown fox jumps over the lazy dog.';

	private const ALIASES = array(
		'alloy'   => 'slate',
		'ash'     => 'atlas',
		'coral'   => 'juniper',
		'echo'    => 'beacon',
		'fable'   => 'hearth',
		'onyx'    => 'basalt',
		'nova'    => 'spark',
		'sage'    => 'willow',
		'shimmer' => 'linen',
	);

	protected function setUp(): void {
		vocasync_test_reset();
	}

	/** The v1.0.0 recipe, written out independently of the code under test. */
	private function v1_hash( string $text, string $voice, string $language = 'en', string $format = 'mp3' ): string {
		return hash( 'sha256', (string) json_encode( array( $text, $voice, $language, $format ) ) );
	}

	// ── Catalogue shape ─────────────────────────────────────────────

	public function test_catalogue_has_45_voices_and_every_alias_target_is_in_it(): void {
		$this->assertCount( 45, Constants::VOICES );
		$this->assertCount( 45, array_unique( Constants::VOICES ) );
		foreach ( self::ALIASES as $old => $new ) {
			$this->assertContains( $new, Constants::VOICES );
			$this->assertSame( $new, Constants::LEGACY_ALIASES[ $old ] );
		}
		$this->assertCount( 9, Constants::LEGACY_ALIASES );
	}

	public function test_accepted_voices_is_catalogue_plus_legacy_names(): void {
		$accepted = Constants::accepted_voices();
		$this->assertCount( 54, $accepted );
		foreach ( array_keys( self::ALIASES ) as $old ) {
			$this->assertContains( $old, $accepted );
		}
		foreach ( Constants::VOICES as $voice ) {
			$this->assertContains( $voice, $accepted );
		}
	}

	public function test_descriptions_cover_all_54_names(): void {
		foreach ( Constants::accepted_voices() as $voice ) {
			$this->assertArrayHasKey( $voice, Constants::VOICE_DESCRIPTIONS );
			$this->assertNotSame( '', Constants::VOICE_DESCRIPTIONS[ $voice ] );
		}
	}

	public function test_no_provider_names_in_voice_copy(): void {
		$blob = strtolower( implode( ' ', Constants::VOICE_DESCRIPTIONS ) . ' ' . implode( ' ', array_map( array( Constants::class, 'voice_label' ), Constants::accepted_voices() ) ) );
		foreach ( array( 'openai', 'gemini', 'google', 'qwen', 'replicate' ) as $banned ) {
			$this->assertStringNotContainsString( $banned, $blob );
		}
	}

	public function test_legacy_label_shows_the_target(): void {
		$this->assertSame( 'Onyx (→ Basalt)', Constants::voice_label( 'onyx' ) );
		$this->assertSame( 'Basalt', Constants::voice_label( 'basalt' ) );
	}

	public function test_default_voice_is_slate_and_ko_is_dropped(): void {
		$this->assertSame( 'slate', Constants::DEFAULT_VOICE );
		$this->assertArrayNotHasKey( 'ko', Constants::SYNTHESIS_TO_ALIGNMENT );
	}

	// ── Hash stability ──────────────────────────────────────────────

	public function test_hash_voice_key_maps_targets_back_to_legacy_and_leaves_the_rest(): void {
		foreach ( self::ALIASES as $old => $new ) {
			$this->assertSame( $old, Constants::hash_voice_key( $new ) );
			$this->assertSame( $old, Constants::hash_voice_key( $old ) );
		}
		$this->assertSame( 'drift', Constants::hash_voice_key( 'drift' ) );
	}

	public function test_unsaved_default_hashes_exactly_as_v1_alloy(): void {
		// No option stored: Settings merges defaults at read time.
		$settings = new Settings();
		$this->assertSame( 'slate', $settings->default_voice() );
		$new = Extractor::content_hash( self::TEXT, $settings->default_voice(), 'en', 'mp3' );
		$this->assertSame( $this->v1_hash( self::TEXT, 'alloy' ), $new );
	}

	public function test_every_legacy_and_target_name_hashes_as_the_legacy_name(): void {
		foreach ( self::ALIASES as $old => $new ) {
			$expected = $this->v1_hash( self::TEXT, $old );
			$this->assertSame( $expected, Extractor::content_hash( self::TEXT, $old, 'en', 'mp3' ), $old );
			$this->assertSame( $expected, Extractor::content_hash( self::TEXT, $new, 'en', 'mp3' ), $new );
		}
	}

	public function test_saved_onyx_equals_basalt(): void {
		$this->assertSame(
			Extractor::content_hash( self::TEXT, 'onyx', 'en', 'mp3' ),
			Extractor::content_hash( self::TEXT, 'basalt', 'en', 'mp3' )
		);
	}

	public function test_voices_outside_the_alias_set_keep_their_own_key(): void {
		$this->assertSame( $this->v1_hash( self::TEXT, 'drift' ), Extractor::content_hash( self::TEXT, 'drift', 'en', 'mp3' ) );
	}

	private function html(): string {
		return '<p>' . self::TEXT . '</p>';
	}

	public function test_is_stale_is_false_for_a_basalt_artifact_hashed_under_onyx(): void {
		$extractor = new Extractor();
		$speech    = $extractor->extract( $this->html(), SpokenForm::MODE_ENGLISH );
		$v1        = $this->v1_hash( $speech['normalisedSpeechtext'], 'onyx' );

		$GLOBALS['vocasync_test']['posts'][7]  = new WP_Post( 7, $this->html() );
		$GLOBALS['vocasync_test']['meta'][7][ Artifact::META_ARTIFACT ] = array(
			'voice'       => 'basalt',
			'language'    => 'en',
			'format'      => 'mp3',
			'contentHash' => $v1,
			'words'       => array( array( 'w' => 'x' ) ),
		);

		$settings     = new Settings();
		$orchestrator = new Orchestrator( new Client( $settings ), new Artifact(), $extractor, $settings );
		$this->assertFalse( $orchestrator->is_stale( 7 ) );
	}

	public function test_renderer_keeps_highlighting_for_a_basalt_artifact_hashed_under_onyx(): void {
		$extractor = new Extractor();
		$speech    = $extractor->extract( $this->html(), SpokenForm::MODE_ENGLISH );
		$v1        = $this->v1_hash( $speech['normalisedSpeechtext'], 'onyx' );

		$GLOBALS['vocasync_test']['meta'][7][ Artifact::META_ARTIFACT ] = array(
			'voice'       => 'basalt',
			'language'    => 'en',
			'format'      => 'mp3',
			'contentHash' => $v1,
			'words'       => array( array( 'w' => 'x' ) ),
			'audioUrl'    => 'http://example.test/a.mp3',
		);

		$settings = new Settings();
		$renderer = new PlayerRenderer( new Artifact(), $settings, new WordWrapper(), $extractor );
		$prepare  = new ReflectionMethod( $renderer, 'prepare' );
		$result = $prepare->invoke( $renderer, 7, $this->html() );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['highlight'] );
	}

	public function test_renderer_still_turns_highlighting_off_when_the_text_really_changed(): void {
		$extractor = new Extractor();
		$GLOBALS['vocasync_test']['meta'][7][ Artifact::META_ARTIFACT ] = array(
			'voice'       => 'basalt',
			'language'    => 'en',
			'format'      => 'mp3',
			'contentHash' => $this->v1_hash( 'some other text', 'onyx' ),
			'words'       => array( array( 'w' => 'x' ) ),
			'audioUrl'    => 'http://example.test/a.mp3',
		);
		$renderer = new PlayerRenderer( new Artifact(), new Settings(), new WordWrapper(), $extractor );
		$prepare  = new ReflectionMethod( $renderer, 'prepare' );
		$result = $prepare->invoke( $renderer, 7, $this->html() );
		$this->assertFalse( $result['highlight'] );
	}

	// ── Settings, REST, params ──────────────────────────────────────

	public function test_sanitize_keeps_a_saved_legacy_voice(): void {
		$clean = ( new Settings() )->sanitize( array( 'voice' => 'onyx' ) );
		$this->assertSame( 'onyx', $clean['voice'] );
	}

	public function test_sanitize_keeps_a_catalogue_voice_and_defaults_unknowns_to_slate(): void {
		$settings = new Settings();
		$this->assertSame( 'drift', $settings->sanitize( array( 'voice' => 'drift' ) )['voice'] );
		$this->assertSame( 'slate', $settings->sanitize( array( 'voice' => 'nonsense' ) )['voice'] );
	}

	public function test_settings_view_marks_a_saved_onyx_as_selected_and_labels_it(): void {
		$GLOBALS['vocasync_test']['options'][ Settings::OPTION ] = array( 'voice' => 'onyx' );
		$settings = new Settings();

		ob_start();
		( require dirname( __DIR__, 2 ) . '/src/Settings/views/settings-page.php' )( $settings );
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/<option value="onyx"\s+selected=\'selected\'>\s*Onyx \(→ Basalt\)/u', $html );
		$this->assertSame( 1, substr_count( $html, "selected='selected'>\n\t\t\t\t\t\t\t\t\tOnyx" ) + substr_count( $html, 'value="onyx" selected' ) );
		$this->assertStringContainsString( 'value="basalt"', $html );
		$this->assertStringContainsString( 'value="drift"', $html );
	}

	public function test_rest_override_nova_is_kept(): void {
		$settings   = new Settings();
		$artifact   = new Artifact();
		$client     = new Client( $settings );
		$orch       = new Orchestrator( $client, $artifact, new Extractor(), $settings );
		$controller = new Controller( $orch, $artifact, $settings, $client );

		$controller->save_overrides( new WP_REST_Request( array( 'post_id' => 9, 'overrides' => array( 'voice' => 'nova' ) ) ) );
		$this->assertSame( 'nova', $artifact->get_overrides( 9 )['voice'] );

		$controller->save_overrides( new WP_REST_Request( array( 'post_id' => 9, 'overrides' => array( 'voice' => 'spark' ) ) ) );
		$this->assertSame( 'spark', $artifact->get_overrides( 9 )['voice'] );

		$controller->save_overrides( new WP_REST_Request( array( 'post_id' => 9, 'overrides' => array( 'voice' => 'bogus' ) ) ) );
		$this->assertArrayNotHasKey( 'voice', $artifact->get_overrides( 9 ) );
	}

	public function test_resolve_params_keeps_a_legacy_override(): void {
		$GLOBALS['vocasync_test']['meta'][9][ Artifact::META_OVERRIDES ] = array( 'voice' => 'nova' );
		$settings = new Settings();
		$orch     = new Orchestrator( new Client( $settings ), new Artifact(), new Extractor(), $settings );
		$this->assertSame( 'nova', $orch->resolve_params( 9 )['voice'] );
	}

	public function test_resolve_params_falls_back_to_the_default_voice(): void {
		$settings = new Settings();
		$orch     = new Orchestrator( new Client( $settings ), new Artifact(), new Extractor(), $settings );
		$this->assertSame( 'slate', $orch->resolve_params( 9 )['voice'] );
	}

	// ── Outgoing request ────────────────────────────────────────────

	/** @return array<string,string> */
	private function sent_fields( string $voice ): array {
		$GLOBALS['vocasync_test']['http'] = array();
		( new Client( new Settings() ) )->synthesize(
			array(
				'name'         => 'Post',
				'text'         => self::TEXT,
				'voice'        => $voice,
				'quality'      => 'hd',
				'language'     => 'en',
				'outputFormat' => 'mp3',
			)
		);
		$body = $GLOBALS['vocasync_test']['http'][0]['args']['body'];
		preg_match_all( '/name="([^"]+)"\r\n\r\n(.*?)\r\n/s', $body, $m, PREG_SET_ORDER );
		$fields = array();
		foreach ( $m as $row ) {
			$fields[ $row[1] ] = $row[2];
		}
		return $fields;
	}

	public function test_the_wire_voice_is_the_new_name_and_quality_is_still_sent(): void {
		$fields = $this->sent_fields( 'onyx' );
		$this->assertSame( 'basalt', $fields['voice'] );
		$this->assertSame( 'hd', $fields['quality'] );

		$this->assertSame( 'basalt', $this->sent_fields( 'basalt' )['voice'] );
		$this->assertSame( 'drift', $this->sent_fields( 'drift' )['voice'] );
	}

	public function test_send_voice_maps_every_alias(): void {
		foreach ( self::ALIASES as $old => $new ) {
			$this->assertSame( $new, Constants::send_voice( $old ) );
			$this->assertSame( $new, Constants::send_voice( $new ) );
		}
	}
}
