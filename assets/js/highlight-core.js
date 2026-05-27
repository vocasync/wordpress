/**
 * Pure word-highlighting math, shared by the player and unit-tested in
 * isolation (no DOM). Ported from @vocasync/astro's AudioPlayer logic.
 *
 *   computeSpanTimings(items, words) — for each visible unit spanning alignment
 *     tokens [dataI, dataI+dataN), take min(start)..max(end); returns the units
 *     that have timings, sorted by start. `ref` is passed through opaquely
 *     (the DOM element in the browser; an id in tests).
 *   idxForTime(spanTimings, t) — binary search for the first span whose end > t.
 * @param root
 * @param factory
 */
( function ( root, factory ) {
	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory();
	} else {
		root.VocaSyncHighlight = factory();
	}
} )( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	const EPS = 0.0001;

	function computeSpanTimings( items, words ) {
		const out = [];
		for ( let i = 0; i < items.length; i++ ) {
			const it = items[ i ];
			const dataI = it.dataI;
			const dataN = it.dataN;
			if ( ! isFinite( dataI ) || ! isFinite( dataN ) || dataN < 1 ) {
				continue;
			}
			let minStart = Infinity;
			let maxEnd = -Infinity;
			for ( let k = dataI; k < dataI + dataN; k++ ) {
				const t = words[ k ];
				if ( ! t ) {
					continue;
				}
				if ( t.start < minStart ) {
					minStart = t.start;
				}
				if ( t.end > maxEnd ) {
					maxEnd = t.end;
				}
			}
			if ( isFinite( minStart ) && isFinite( maxEnd ) ) {
				if ( ! ( maxEnd >= minStart ) ) {
					maxEnd = minStart + EPS;
				}
				out.push( { start: minStart, end: maxEnd, ref: it.ref } );
			}
		}
		out.sort( function ( a, b ) {
			return a.start - b.start;
		} );
		return out;
	}

	function idxForTime( spanTimings, t ) {
		const n = spanTimings.length;
		if ( ! n ) {
			return -1;
		}
		let lo = 0;
		let hi = n - 1;
		while ( lo <= hi ) {
			const mid = ( lo + hi ) >> 1;
			if ( spanTimings[ mid ].end <= t ) {
				lo = mid + 1;
			} else {
				hi = mid - 1;
			}
		}
		return lo;
	}

	return { EPS, computeSpanTimings, idxForTime };
} );
