<?php

declare( strict_types=1 );

namespace VocaSync;

/**
 * Synthesis / alignment constants, mirrored from
 * `vocasync/dashboard/lib/constants/{synthesis,alignment}.ts`. The live
 * synthesis catalogue (voices, languages) is published at
 * `GET https://vocasync.io/api/v1/synthesis/info`; these are kept in sync with
 * it by hand.
 */
final class Constants {

	public const API_BASE = 'https://vocasync.io/api/v1';

	/**
	 * The VocaSync voice catalogue.
	 *
	 * @var list<string>
	 */
	public const VOICES = array(
		'linen',
		'basalt',
		'juniper',
		'drift',
		'quartz',
		'volt',
		'willow',
		'oak',
		'maple',
		'slate',
		'velvet',
		'hearth',
		'zest',
		'granite',
		'heath',
		'wren',
		'lark',
		'cairn',
		'fern',
		'breeze',
		'pearl',
		'beacon',
		'dawn',
		'summit',
		'timber',
		'hush',
		'blaze',
		'flint',
		'lune',
		'forge',
		'sol',
		'vento',
		'brisa',
		'taiga',
		'kumo',
		'bada',
		'jade',
		'neva',
		'zarya',
		'volga',
		'harbour',
		'meridian',
		'spark',
		'ember',
		'atlas',
	);

	public const DEFAULT_VOICE = 'slate';

	/**
	 * Voice names used by plugin versions before 1.1.0, mapped to the VocaSync
	 * voice that replaces them. They stay accepted everywhere a voice is read, so
	 * a site that saved `onyx` keeps working untouched.
	 *
	 * @var array<string,string>
	 */
	public const LEGACY_ALIASES = array(
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

	/** @var array<string,string> */
	public const VOICE_DESCRIPTIONS = array(
		'linen'    => 'A warm, mid-range narrator, unhurried and articulate; made for long articles.',
		'basalt'   => 'A deep, resonant baritone with measured pacing and quiet authority, like a classic audiobook reader.',
		'juniper'  => 'A friendly, upbeat podcast host; conversational and natural, with a smile in her voice.',
		'drift'    => 'A relaxed, soft and slightly raspy voice; easygoing, like a late-night radio host.',
		'quartz'   => 'A crisp, polished news-anchor voice; clear diction, confident and neutral.',
		'volt'     => 'Young, bright and enthusiastic; quick but clear, suited to tech explainers.',
		'willow'   => 'A calm, soothing voice, gentle and slow; suited to meditation and wellbeing content.',
		'oak'      => 'A wise, gravelly storyteller; warm and slow, with a rich, weathered texture.',
		'maple'    => 'A clear, patient teacher; friendly and encouraging, explains things step by step.',
		'slate'    => 'A mid-pitched, gender-neutral voice; calm, modern and precise.',
		'velvet'   => 'An elegant, poised and expressive narrator; ideal for literature.',
		'hearth'   => 'A warm, friendly and thoughtful narrator, like a radio presenter.',
		'zest'     => 'Brisk, articulate and lively; clear and conversational.',
		'granite'  => 'A deep, authoritative and cultivated voice; slow, deliberate, documentary style.',
		'heath'    => 'Cheerful, warm and down-to-earth.',
		'wren'     => 'A gentle, kindly storyteller, like someone reading to children at bedtime.',
		'lark'     => 'A warm, musical and engaging narrator.',
		'cairn'    => 'A deep, steady voice; warm and grounded, with slow pacing.',
		'fern'     => 'A melodic voice, gentle and expressive.',
		'breeze'   => 'Bright, friendly and casual; upbeat and easy to follow.',
		'pearl'    => 'A clear, crisp and friendly professional voice.',
		'beacon'   => 'A clear, approachable voice, like a public-radio host.',
		'dawn'     => 'A clear, warm and gentle narrator; articulate and professional.',
		'summit'   => 'A confident presenter with crisp diction; engaging and measured.',
		'timber'   => 'A rich, warm and deep narrator; expressive, with steady pacing.',
		'hush'     => 'Breathy and intimate, soft and close to the microphone, but still clear.',
		'blaze'    => 'A dynamic, punchy promo voice; bold, like a trailer or sports announcer.',
		'flint'    => 'Dry, witty and understated, with deadpan timing.',
		'lune'     => 'A warm, elegant and expressive narrator, designed in French.',
		'forge'    => 'A clear, steady and articulate narrator, designed in German.',
		'sol'      => 'A bright, friendly and lively narrator, designed in Spanish.',
		'vento'    => 'A warm, melodic and relaxed narrator, designed in Italian.',
		'brisa'    => 'A soft, friendly and natural narrator, designed in Portuguese.',
		'taiga'    => 'A deep, calm and authoritative narrator, designed in Russian.',
		'kumo'     => 'A gentle, clear and polite narrator, designed in Japanese.',
		'bada'     => 'A warm, friendly and natural narrator, designed in Korean.',
		'jade'     => 'A clear, warm and steady professional narrator, designed in Mandarin Chinese.',
		'neva'     => 'A warm, clear and articulate narrator, designed in Russian.',
		'zarya'    => 'Bright, friendly and lively, designed in Russian.',
		'volga'    => 'A calm, gentle narrator with soft, measured pacing, designed in Russian.',
		'harbour'  => 'A deep, warm and authoritative narrator with a rich low register; made for long-form articles.',
		'meridian' => 'A clear, warm and friendly narrator, like an experienced podcast host.',
		'spark'    => 'Bright, energetic and expressive; quick, but always clear.',
		'ember'    => 'A calm, gentle storyteller with a soft, slightly husky voice and slow pacing.',
		'atlas'    => 'A crisp, confident and precise voice, like a documentary narrator.',
		'alloy'    => 'Neutral and balanced',
		'ash'      => 'Soft and refined',
		'coral'    => 'Warm and friendly',
		'echo'     => 'Smooth and calm',
		'fable'    => 'Expressive and dramatic',
		'onyx'     => 'Deep and authoritative',
		'nova'     => 'Bright and energetic',
		'sage'     => 'Wise and measured',
		'shimmer'  => 'Clear and optimistic',
	);

	/** @var list<string> */
	public const QUALITIES       = array( 'sd', 'hd' );
	public const DEFAULT_QUALITY = 'sd';

	/** @var list<string> */
	public const FORMATS        = array( 'mp3', 'aac', 'opus', 'flac', 'wav' );
	public const DEFAULT_FORMAT = 'mp3';

	/** @var array<string,string> */
	public const FORMAT_MIME = array(
		'mp3'  => 'audio/mpeg',
		'aac'  => 'audio/aac',
		'opus' => 'audio/ogg',
		'flac' => 'audio/flac',
		'wav'  => 'audio/wav',
	);

	public const DEFAULT_LANGUAGE = 'en';

	/** Hard cap on synthesis input length (chars). */
	public const MAX_INPUT_CHARS = 200000;

	/**
	 * Synthesis ISO-639-1 -> alignment BCP-47 locale. Only these synthesis
	 * languages have an alignment model; unknown defaults to en-US.
	 *
	 * @var array<string,string>
	 */
	public const SYNTHESIS_TO_ALIGNMENT = array(
		'en' => 'en-US',
		'fr' => 'fr',
		'de' => 'de',
		'es' => 'es',
		'pt' => 'pt-PT',
		'sv' => 'sv',
		'cs' => 'cs',
		'pl' => 'pl',
		'tr' => 'tr',
		'ru' => 'ru',
		'uk' => 'uk',
		'ja' => 'ja',
		'zh' => 'zh-CN',
	);

	/**
	 * Languages the ASCII-Latin tokenizer can represent for highlighting.
	 * (Synthesis supports more, but Cyrillic/CJK strip to an empty transcript.)
	 *
	 * @var list<string>
	 */
	public const HIGHLIGHT_LANGUAGES = array( 'en', 'fr', 'de', 'es', 'pt', 'sv', 'cs', 'pl', 'tr' );

	/**
	 * Display labels for the languages we expose in the UI (the highlightable
	 * set). English is fully supported; the rest are experimental.
	 *
	 * @var array<string,string>
	 */
	public const LANGUAGE_LABELS = array(
		'en' => 'English',
		'fr' => 'Français (French)',
		'de' => 'Deutsch (German)',
		'es' => 'Español (Spanish)',
		'pt' => 'Português (Portuguese)',
		'sv' => 'Svenska (Swedish)',
		'cs' => 'Čeština (Czech)',
		'pl' => 'Polski (Polish)',
		'tr' => 'Türkçe (Turkish)',
	);

	/**
	 * Every voice name the plugin accepts: the catalogue plus the legacy names.
	 *
	 * @return list<string>
	 */
	public static function accepted_voices(): array {
		return array_merge( self::VOICES, array_keys( self::LEGACY_ALIASES ) );
	}

	/**
	 * The voice key that goes into the change-detection hash.
	 *
	 * Hashes were created while the plugin only knew the legacy names, so the
	 * replacement voices hash as the legacy name they stand in for. Without this
	 * every existing post would look changed: it would be re-synthesised (and
	 * re-billed) and its word highlighting would switch off.
	 */
	public static function hash_voice_key( string $voice ): string {
		$legacy = array_search( $voice, self::LEGACY_ALIASES, true );
		return false === $legacy ? $voice : (string) $legacy;
	}

	/** The voice name to put on the wire: legacy names become their replacement. */
	public static function send_voice( string $voice ): string {
		return self::LEGACY_ALIASES[ $voice ] ?? $voice;
	}

	/** Display label for a voice; legacy names show where they now point, e.g. "Onyx (→ Basalt)". */
	public static function voice_label( string $voice ): string {
		$label = ucfirst( $voice );
		if ( isset( self::LEGACY_ALIASES[ $voice ] ) ) {
			$label .= ' (→ ' . ucfirst( self::LEGACY_ALIASES[ $voice ] ) . ')';
		}
		return $label;
	}

	public static function to_alignment_locale( string $language ): string {
		return self::SYNTHESIS_TO_ALIGNMENT[ $language ] ?? 'en-US';
	}

	public static function format_mime( string $format ): string {
		return self::FORMAT_MIME[ $format ] ?? 'audio/mpeg';
	}
}
