/**
 * Pure formatting helpers for the editor panel (UMD: window.VocaSyncFormat in
 * the browser, require() in node tests).
 * @param root
 * @param factory
 */
( function ( root, factory ) {
	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory();
	} else {
		root.VocaSyncFormat = factory();
	}
} )( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function formatDuration( seconds ) {
		if ( ! seconds || ! isFinite( seconds ) || seconds < 0 ) {
			return '0:00';
		}
		const m = Math.floor( seconds / 60 );
		const s = Math.floor( seconds % 60 );
		return m + ':' + String( s ).padStart( 2, '0' );
	}

	// Rough spoken-length estimate at ~14 characters/second; never below 1 min.
	function estimateLength( charCount ) {
		if ( ! charCount || charCount <= 0 ) {
			return '';
		}
		const minutes = Math.max( 1, Math.round( charCount / 14 / 60 ) );
		return '~' + minutes + ' min';
	}

	return { formatDuration, estimateLength };
} );
