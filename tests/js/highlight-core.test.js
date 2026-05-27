/**
 * Unit tests for the pure highlight math. Run with: node --test tests/js
 */
const { test } = require( 'node:test' );
const assert = require( 'node:assert' );
const HL = require( '../../assets/js/highlight-core.js' );

const words = [
	{ start: 0.0, end: 0.4 }, // 0
	{ start: 0.4, end: 0.9 }, // 1
	{ start: 0.9, end: 1.5 }, // 2
	{ start: 1.5, end: 2.0 }, // 3
];

test( 'computeSpanTimings: one token per visible word (data-n=1)', () => {
	const items = [
		{ dataI: 0, dataN: 1, ref: 'a' },
		{ dataI: 1, dataN: 1, ref: 'b' },
	];
	const out = HL.computeSpanTimings( items, words );
	assert.deepStrictEqual( out, [
		{ start: 0.0, end: 0.4, ref: 'a' },
		{ start: 0.4, end: 0.9, ref: 'b' },
	] );
} );

test( 'computeSpanTimings: multi-token unit ($50 -> data-n=2) spans min..max', () => {
	const items = [ { dataI: 1, dataN: 2, ref: 'price' } ];
	const out = HL.computeSpanTimings( items, words );
	assert.strictEqual( out.length, 1 );
	assert.strictEqual( out[ 0 ].start, 0.4 ); // min(words[1].start, words[2].start)
	assert.strictEqual( out[ 0 ].end, 1.5 ); // max(words[1].end, words[2].end)
	assert.strictEqual( out[ 0 ].ref, 'price' );
} );

test( 'computeSpanTimings: skips units with no timings and sorts by start', () => {
	const items = [
		{ dataI: 3, dataN: 1, ref: 'late' },
		{ dataI: 99, dataN: 1, ref: 'missing' }, // no word -> dropped
		{ dataI: 0, dataN: 1, ref: 'early' },
	];
	const out = HL.computeSpanTimings( items, words );
	assert.deepStrictEqual( out.map( ( s ) => s.ref ), [ 'early', 'late' ] );
} );

test( 'computeSpanTimings: ignores invalid data-n', () => {
	const items = [
		{ dataI: 0, dataN: 0, ref: 'zero' },
		{ dataI: NaN, dataN: 1, ref: 'nan' },
	];
	assert.deepStrictEqual( HL.computeSpanTimings( items, words ), [] );
} );

test( 'idxForTime: binary search finds first span whose end > t', () => {
	const spanTimings = HL.computeSpanTimings(
		[
			{ dataI: 0, dataN: 1, ref: 'a' },
			{ dataI: 1, dataN: 1, ref: 'b' },
			{ dataI: 2, dataN: 1, ref: 'c' },
			{ dataI: 3, dataN: 1, ref: 'd' },
		],
		words
	);
	assert.strictEqual( idxRef( spanTimings, 0.0 ), 'a' ); // within a
	assert.strictEqual( idxRef( spanTimings, 0.5 ), 'b' ); // within b
	assert.strictEqual( idxRef( spanTimings, 1.2 ), 'c' ); // within c
	assert.strictEqual( idxRef( spanTimings, 1.6 ), 'd' ); // within d
	// At a boundary (t === end of a), the active candidate is the next span.
	assert.strictEqual( idxRef( spanTimings, 0.4 ), 'b' );
} );

test( 'idxForTime: past the end returns out-of-range index', () => {
	const spanTimings = HL.computeSpanTimings( [ { dataI: 0, dataN: 1, ref: 'a' } ], words );
	assert.strictEqual( HL.idxForTime( spanTimings, 99 ), spanTimings.length );
} );

test( 'idxForTime: empty timings returns -1', () => {
	assert.strictEqual( HL.idxForTime( [], 1 ), -1 );
} );

function idxRef( spanTimings, t ) {
	const i = HL.idxForTime( spanTimings, t );
	return spanTimings[ i ] ? spanTimings[ i ].ref : null;
}
