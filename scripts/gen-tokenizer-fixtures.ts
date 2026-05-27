/**
 * Generate golden tokenizer fixtures from the canonical TypeScript source
 * (`@vocasync/astro`). The PHP port (`src/Tokenizer/SpokenForm.php`) is asserted
 * byte-for-byte against these in `tests/php/SpokenFormParityTest.php`, so any
 * drift in the TS tokenizer is caught the next time fixtures are regenerated.
 *
 * Run with:  bun run scripts/gen-tokenizer-fixtures.ts
 */

import { mkdirSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import {
	expandSpokenForms,
	spokenTokenCount,
	stripForAlignment,
} from "../../astro/src/core/spoken-form.ts";

const inputs: string[] = [
	// Plain numbers
	"0",
	"7",
	"42",
	"100",
	"1995",
	"1000000",
	"-5",
	"3.14",
	"0.5",
	"007",
	"1,000",
	"1,000,000",
	"12345678901234567890",
	// Currency
	"$50",
	"€100",
	"50$",
	"£20",
	"¥1000",
	"$0",
	"it costs $1,000.50 today",
	"price: $5 and €3",
	"₿1 is a lot",
	// Percent
	"50%",
	"3.5%",
	"100 %",
	"up 12% today",
	// Mixed sentences
	"I paid $50 for 3 items at 10% off.",
	"The year 1995 was great.",
	"She has 2 cats and 1 dog.",
	"Buy 2 for $10.99 (50% off!) — visit https://x.io",
	// Contractions + apostrophes
	"don't can't it's I'm we'll",
	"'quoted' text",
	"rock 'n' roll",
	// Quotes / dashes / ellipsis
	"“Hello” — world",
	"it's a “test”…",
	"em—dash and en–dash and minus −5",
	// Emoji
	"hello 😀 world",
	"🎉 party time",
	// URLs / emails / handles
	"visit https://example.com/path?q=1 now",
	"email me at a.b+x@example.co.uk please",
	"ping @user here",
	"www.test.org is a link",
	// Diacritics
	"café résumé naïve Zürich",
	// Edge / empty / punctuation
	"",
	"   ",
	"...",
	"!!!",
	"a-b-c",
	// Non-Latin (should strip to empty)
	"你好世界",
	"Привет мир",
	"こんにちは",
];

interface Fixture {
	input: string;
	expand: string;
	strip: string;
	normalised: string;
	tokenCount: number;
}

const fixtures: Fixture[] = inputs.map((input) => ({
	input,
	expand: expandSpokenForms(input),
	strip: stripForAlignment(input),
	normalised: stripForAlignment(expandSpokenForms(input)),
	tokenCount: spokenTokenCount(input),
}));

const outDir = join(import.meta.dir, "..", "tests", "fixtures", "tokenizer");
mkdirSync(outDir, { recursive: true });
const outFile = join(outDir, "english.json");
writeFileSync(outFile, `${JSON.stringify(fixtures, null, 2)}\n`, "utf8");

console.log(`Wrote ${fixtures.length} fixtures to ${outFile}`);
