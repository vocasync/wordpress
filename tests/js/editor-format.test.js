const { test } = require( 'node:test' );
const assert = require( 'node:assert' );
const F = require( '../../assets/js/editor-format.js' );

test( 'formatDuration', () => {
	assert.strictEqual( F.formatDuration( 70.04 ), '1:10' );
	assert.strictEqual( F.formatDuration( 5 ), '0:05' );
	assert.strictEqual( F.formatDuration( 0 ), '0:00' );
	assert.strictEqual( F.formatDuration( -1 ), '0:00' );
} );

test( 'estimateLength (~14 chars/sec, min 1 min)', () => {
	assert.strictEqual( F.estimateLength( 10000 ), '~12 min' ); // round(10000/14/60)=12
	assert.strictEqual( F.estimateLength( 100 ), '~1 min' );    // clamps up to 1
	assert.strictEqual( F.estimateLength( 0 ), '' );
} );
