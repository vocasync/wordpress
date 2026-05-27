/**
 * Settings page "Test connection" button. Calls the REST controller, which
 * validates the saved API key via GET /me and reports the balance.
 * @param wp
 */
( function ( wp ) {
	'use strict';
	const __ = wp && wp.i18n ? wp.i18n.__ : function ( s ) { return s; };
	const apiFetch = wp && wp.apiFetch;

	document.addEventListener( 'DOMContentLoaded', function () {
		const btn = document.getElementById( 'vocasync-test-connection' );
		if ( ! btn || ! apiFetch ) {
			return;
		}
		const out = document.getElementById( 'vocasync-test-result' );
		btn.addEventListener( 'click', function () {
			out.textContent = __( 'Testing…', 'vocasync' );
			btn.disabled = true;
			const field = document.getElementById( 'vocasync-api-key' );
			const apiKey = field ? field.value : '';
			apiFetch( { path: '/vocasync/v1/test-connection', method: 'POST', data: { api_key: apiKey } } )
				.then( function ( r ) {
					out.textContent = r.message || ( r.valid ? __( 'Connected.', 'vocasync' ) : __( 'Failed.', 'vocasync' ) );
					out.style.color = r.valid ? '#1a7f37' : '#b32d2e';
				} )
				.catch( function () {
					out.textContent = __( 'Connection test failed.', 'vocasync' );
					out.style.color = '#b32d2e';
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );

		const preview = document.getElementById( 'vocasync-preview' );
		if ( preview ) {
			const map = { accent: '--vocasync-acc', highlight: '--vocasync-hl', surface: '--vocasync-surf', text: '--vocasync-txt', border: '--vocasync-bd' };
			document.querySelectorAll( '[data-vocasync-token]' ).forEach( function ( input ) {
				input.addEventListener( 'input', function () {
					const token = input.getAttribute( 'data-vocasync-token' );
					if ( 'radius' === token ) {
						preview.style.setProperty( '--vocasync-rad', input.value + 'px' );
					} else if ( map[ token ] ) {
						preview.style.setProperty( map[ token ], input.value );
					}
				} );
			} );
		}
	} );
} )( window.wp );
