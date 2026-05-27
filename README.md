# VocaSync for WordPress

Turn posts into narrated audio with word-level highlighting, powered by [VocaSync](https://vocasync.io).

VocaSync generates natural-sounding speech for your posts and synchronises a word-level
highlighting player with the audio. Add your VocaSync API key, click **Generate audio** on a
post, and a player appears that highlights each word as it is spoken.

> Synthesis and alignment run on VocaSync's servers (a paid service). You need a VocaSync
> account and API key from <https://vocasync.io>.

## Features

- **Per-post generation** with a one-click button, or automatic generation on publish.
- **Word-level highlighting** synchronised to the audio, with click-to-seek and autoscroll.
- **Flexible placement** — a theme-agnostic player is injected automatically, or you can place
  it precisely with the `vocasync/player` block or the `[vocasync]` shortcode.
- **Floating mini-player** that follows the reader down the page.
- **Player theming** — accent, highlight, surface, text, and border colours plus corner
  roundness, with an Auto/Light/Dark colour scheme, all from the settings screen.
- **Per-post overrides** for player position (top/bottom) and individual control visibility
  (speed, volume, mini-player, highlighting, click-to-seek).
- **Content-hash deduplication**, so unchanged posts are never re-billed.
- **Optional HMAC-signed webhooks** for job-completion notifications.
- Spoken-form expansion of numbers, currency, and percentages in English, plus experimental
  support for several Latin-script European languages.

## Requirements

- WordPress 6.2 or later
- PHP 8.0 or later
- A [VocaSync](https://vocasync.io) account and API key

## Installation

**From a release (recommended):** download `vocasync.zip` from the
[latest release](https://github.com/vocasync/wordpress/releases/latest), then in WordPress go to
**Plugins → Add New → Upload Plugin**, choose the ZIP, and activate.

**From WordPress.org:** once listed, search for "VocaSync" under **Plugins → Add New**. *(Pending review.)*

**From source:** see [Development](#development) below.

## Configuration

1. Go to **Settings → VocaSync**.
2. Paste your VocaSync **API key**.
3. (Optional) Add a **webhook signing secret** so VocaSync can notify your site when a job
   completes. This requires your site to be reachable over HTTPS.
4. Choose your default voice, language, and player appearance.

## Usage

- **Automatic:** the player is injected into single posts automatically. Enable
  *auto-generate on publish* to synthesise audio whenever a post goes live.
- **Manual:** edit a post and click **Generate audio** in the VocaSync panel.
- **Block:** insert the **VocaSync** block where you want the player to appear.
- **Shortcode:** add `[vocasync]` to a post's content for precise placement.

When the author places a block or shortcode, the automatic player is suppressed so the player
appears only once.

## Development

This plugin uses build-free front-end JavaScript (`wp.*` globals) and a PHP back end with
Composer-managed dependencies.

```bash
# PHP dependencies (and dev tooling)
composer install

# JS dev tooling (linting, local environment)
bun install

# Local WordPress environment (requires Docker)
bun run env:start          # http://localhost:8888  (wp-admin: admin / password)
bun run env:stop

# Tests
composer test              # PHPUnit (PHP unit tests)
bun run test:js            # node:test (tokenizer / highlight-core parity)

# Linting & static analysis
vendor/bin/phpcs           # WordPress Coding Standards
vendor/bin/phpstan analyse # static analysis
bun run lint:js            # @wordpress/scripts eslint
bun run format             # auto-format JS

# Build a distributable plugin ZIP (production deps only) -> build/vocasync.zip
bash scripts/build-zip.sh
```

## Language support

English is fully supported, including spoken-form expansion of numbers, currency, and
percentages. Several other Latin-script European languages (French, German, Spanish,
Portuguese, Swedish, Czech, Polish, Turkish) are supported experimentally. Other languages are
not yet supported for highlighting.

## External services

This plugin relies on the VocaSync API to synthesise speech and align it to your text, and does
not function without it. Post content, the chosen voice/language/format, and your API key are
sent to `https://vocasync.io/api/v1` when you generate audio. Audio is streamed directly from
VocaSync to the visitor's browser. See VocaSync's
[Terms of Service](https://vocasync.io/terms) and [Privacy Policy](https://vocasync.io/privacy).

## Contributing

Issues and pull requests are welcome. Please run the test, lint, and static-analysis commands
above before opening a PR.

## License

[GPLv2 or later](LICENSE).
