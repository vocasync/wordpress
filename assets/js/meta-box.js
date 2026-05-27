/**
 * Classic-editor meta box: Generate button + status polling via the REST
 * controller. (The block editor uses the document panel instead.)
 * @param wp
 */
( function ( wp ) {
	'use strict';
	const __ = wp && wp.i18n ? wp.i18n.__ : function ( s ) { return s; };
	const apiFetch = wp && wp.apiFetch;

	document.addEventListener( 'DOMContentLoaded', function () {
		const box = document.getElementById( 'vocasync-meta-box' );
		if ( ! box || ! apiFetch ) {
			return;
		}
		const postId = parseInt( box.getAttribute( 'data-post-id' ) || '0', 10 );
		const btn = document.getElementById( 'vocasync-generate' );
		const statusEl = box.querySelector( '.vocasync-meta-status' );
		let pollTimer = null;

		function label( s ) {
			if ( 'synthesizing' === s.jobStatus ) { return __( 'Synthesizing speech…', 'vocasync' ); }
			if ( 'aligning' === s.jobStatus ) { return __( 'Aligning words…', 'vocasync' ); }
			if ( 'failed' === s.jobStatus ) { return s.error || __( 'Generation failed.', 'vocasync' ); }
			if ( s.hasAudio && s.stale ) { return __( 'Audio is out of date — regenerate to update.', 'vocasync' ); }
			if ( s.hasAudio ) { return __( 'Audio ready.', 'vocasync' ); }
			if ( s.reason === 'no_speakable_text' ) { return __( 'No speakable text found in this post.', 'vocasync' ); }
			if ( s.reason === 'unsupported_language' ) { return __( 'This language is not yet supported.', 'vocasync' ); }
			if ( s.reason === 'no_api_key' ) { return __( 'Add your API key in Settings → VocaSync.', 'vocasync' ); }
			return __( 'No audio yet.', 'vocasync' );
		}

		function apply( s ) {
			// Generate/overrides responses include a top-level status; surface
			// rejection/no-op feedback that isn't a persisted job error.
			if ( 'error' === s.status && s.message ) {
				if ( statusEl ) {
					statusEl.textContent = s.message;
				}
				if ( btn ) {
					btn.disabled = ! s.hasApiKey;
				}
				return;
			}
			if ( 'unchanged' === s.status ) {
				if ( statusEl ) {
					statusEl.textContent = __( 'Audio is already up to date.', 'vocasync' );
				}
				if ( btn ) {
					btn.disabled = false;
				}
				return;
			}
			if ( statusEl ) {
				statusEl.textContent = label( s );
			}
			const inFlight = 'synthesizing' === s.jobStatus || 'aligning' === s.jobStatus;
			if ( btn ) {
				btn.disabled = inFlight || ! s.canGenerate;
			}
			if ( inFlight ) {
				pollTimer = setTimeout( load, 5000 );
			}
		}

		function load() {
			apiFetch( { path: '/vocasync/v1/status?post_id=' + postId } ).then( apply ).catch( function () {} );
		}

		if ( btn ) {
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				if ( statusEl ) {
					statusEl.textContent = __( 'Starting…', 'vocasync' );
				}
				if ( pollTimer ) {
					clearTimeout( pollTimer );
				}
				apiFetch( { path: '/vocasync/v1/generate', method: 'POST', data: { post_id: postId, force: true } } )
					.then( apply )
					.catch( function () {
						if ( statusEl ) {
							statusEl.textContent = __( 'Could not start generation.', 'vocasync' );
						}
						btn.disabled = false;
					} );
			} );
		}

		load();
	} );
} )( window.wp );
