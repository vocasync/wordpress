=== VocaSync ===
Contributors: sayedhfatimi
Tags: text-to-speech, tts, audio, accessibility, narration
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn posts into narrated audio with word-level highlighting, powered by VocaSync.

== Description ==

VocaSync generates natural-sounding speech for your posts and synchronises a
word-level highlighting player with the audio. Add your VocaSync API key, click
"Generate audio" on a post, and a player appears that highlights each word as it
is spoken.

* Per-post generation with a one-click button, or auto-generate on publish.
* Word-level highlighting, click-to-seek, autoscroll, and a floating mini-player.
* Theme-agnostic — the player is injected automatically, or place it precisely
  with the VocaSync block or the [vocasync] shortcode.
* Audio streams directly from VocaSync; no local storage required.

Synthesis and alignment run on VocaSync's servers (a paid service). You need a
VocaSync account and API key from https://vocasync.io.

= Language support =

English is fully supported, including spoken-form expansion of numbers, currency,
and percentages. Several other Latin-script European languages
(French, German, Spanish, Portuguese, Swedish, Czech, Polish, Turkish) are
supported experimentally. Other languages are not yet supported for highlighting.

== External services ==

This plugin relies on the VocaSync API (a third-party service) to synthesize
speech and align it to your text. The plugin does not function without it, and
you must supply your own VocaSync API key.

What is sent, and when:

* When you generate audio for a post (by clicking "Generate audio", or on
  publish if auto-generate is enabled), the plugin sends that post's text
  content, the chosen voice/language/format, and your API key to the VocaSync
  API at https://vocasync.io/api/v1. VocaSync returns synthesized audio (served
  as a stream from VocaSync) and word-level timing data.
* If a webhook signing secret is configured and your site is reachable over
  HTTPS, the plugin registers a callback URL so VocaSync can notify your site
  when a job completes. No post data is included in these notifications.
* On the front end, the audio is streamed directly from VocaSync to the
  visitor's browser using a publishable key.

This service is operated by VocaSync (https://vocasync.io). Please review their
Terms of Service and Privacy Policy before use:

* Terms of Service: https://vocasync.io/terms
* Privacy Policy: https://vocasync.io/privacy

== Installation ==

1. Install and activate the plugin.
2. Go to Settings → VocaSync and paste your API key (and webhook signing secret).
3. Edit a post and click "Generate audio" in the VocaSync panel.

== Frequently Asked Questions ==

= Do I need a VocaSync account? =

Yes. Synthesis and alignment are performed by VocaSync's API, which requires an
account and API key from https://vocasync.io.

= Does generation cost money? =

VocaSync bills per character synthesized and per second of audio aligned.
Unchanged posts are never re-billed (content-hash deduplication).

== Screenshots ==

1. The VocaSync settings screen: API key, webhook signing secret, default voice/language, and player appearance.
2. The VocaSync panel in the post editor: generate audio, track progress, and set per-post player overrides.
3. The front-end player with word-level highlighting as the audio plays.
4. Inserting the VocaSync block to place the player exactly where you want it.

== Changelog ==

= 0.1.8 =
* Player theming: customize accent, highlight, surface, text, and border colors plus corner roundness from Settings, with Auto/Light/Dark color scheme.
* Per-post overrides for player position (top/bottom) and individual control visibility (speed, volume, mini-player, highlighting, click-to-seek).

= 0.1.0 =
* Initial development release.
