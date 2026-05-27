<?php

declare( strict_types=1 );

namespace VocaSync;

/**
 * Synthesis / alignment constants, mirrored from
 * `vocasync/dashboard/lib/constants/{synthesis,alignment}.ts`. There is no
 * list endpoint, so these are kept in sync by hand.
 */
final class Constants {

	public const API_BASE = 'https://vocasync.io/api/v1';

	/** @var list<string> */
	public const VOICES        = array( 'alloy', 'ash', 'coral', 'echo', 'fable', 'onyx', 'nova', 'sage', 'shimmer' );
	public const DEFAULT_VOICE = 'alloy';

	/** @var array<string,string> */
	public const VOICE_DESCRIPTIONS = array(
		'alloy'   => 'Neutral and balanced',
		'ash'     => 'Soft and refined',
		'coral'   => 'Warm and friendly',
		'echo'    => 'Smooth and calm',
		'fable'   => 'Expressive and dramatic',
		'onyx'    => 'Deep and authoritative',
		'nova'    => 'Bright and energetic',
		'sage'    => 'Wise and measured',
		'shimmer' => 'Clear and optimistic',
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
		'ko' => 'ko',
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

	public static function to_alignment_locale( string $language ): string {
		return self::SYNTHESIS_TO_ALIGNMENT[ $language ] ?? 'en-US';
	}

	public static function format_mime( string $format ): string {
		return self::FORMAT_MIME[ $format ] ?? 'audio/mpeg';
	}
}
