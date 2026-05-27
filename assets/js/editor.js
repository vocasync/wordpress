/**
 * VocaSync block-editor integration (build-free, uses wp.* globals):
 *   - registers the `vocasync/player` block (server-rendered placeholder)
 *   - adds a "VocaSync" document settings panel with a Generate button, live
 *     status polling, and per-post overrides, talking to the REST controller.
 * @param wp
 */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.element ) {
		return;
	}

	const el = wp.element.createElement;
	const useState = wp.element.useState;
	const useEffect = wp.element.useEffect;
	const __ = wp.i18n.__;
	const apiFetch = wp.apiFetch;
	const data = window.VocaSyncEditor || { voices: [], languages: [], formats: [], defaults: {} };

	if ( wp.blocks && wp.blocks.registerBlockType ) {
		wp.blocks.registerBlockType( 'vocasync/player', {
			apiVersion: 2,
			title: __( 'VocaSync Player', 'vocasync' ),
			description: __( 'Place the VocaSync audio player here.', 'vocasync' ),
			category: 'embed',
			icon: 'controls-volumeon',
			supports: { html: false, multiple: false, reusable: false },
			edit () {
				// useBlockProps wires selection + the block toolbar (incl. Remove);
				// without it the block can't be selected/removed in the canvas.
				const useBlockProps = ( wp.blockEditor && wp.blockEditor.useBlockProps ) || function ( p ) {
					return p || {};
				};
				// eslint-disable-next-line react-hooks/rules-of-hooks -- Gutenberg block `edit` component; useBlockProps is valid here.
				const blockProps = useBlockProps( {
					style: {
						padding: '1rem',
						border: '1px dashed #c3c4c7',
						borderRadius: '8px',
						textAlign: 'center',
						color: '#50575e',
					},
				} );
				return el(
					'div',
					blockProps,
					el( 'span', { className: 'dashicons dashicons-controls-volumeon', style: { marginRight: '6px' } } ),
					__( 'VocaSync audio player (rendered on the front end)', 'vocasync' )
				);
			},
			save () {
				return null;
			},
		} );
	}

	const F = window.VocaSyncFormat || { formatDuration () { return ''; }, estimateLength () { return ''; } };

	// state -> { cls, icon, title }
	function cardState( s ) {
		if ( 'synthesizing' === s.jobStatus || 'aligning' === s.jobStatus ) {
			return { cls: 'is-gen', icon: '◐', title: 'aligning' === s.jobStatus ? __( 'Aligning words…', 'vocasync' ) : __( 'Synthesizing speech…', 'vocasync' ) };
		}
		if ( 'failed' === s.jobStatus ) {
			return { cls: 'is-failed', icon: '✕', title: __( 'Generation failed', 'vocasync' ) };
		}
		if ( s.hasAudio && s.stale ) {
			return { cls: 'is-stale', icon: '▲', title: __( 'Out of date', 'vocasync' ) };
		}
		if ( s.hasAudio ) {
			return { cls: 'is-ready', icon: '●', title: __( 'Audio ready', 'vocasync' ) };
		}
		return { cls: 'is-idle', icon: '○', title: __( 'No audio yet', 'vocasync' ) };
	}

	function reasonText( reason ) {
		switch ( reason ) {
			case 'no_api_key':
				return __( 'Add your VocaSync API key in Settings → VocaSync.', 'vocasync' );
			case 'unsupported_language':
				return __( 'This language is not yet supported for highlighting.', 'vocasync' );
			case 'no_speakable_text':
				return __( 'No speakable text found — the post may be empty or in an unsupported language.', 'vocasync' );
			default:
				return '';
		}
	}

	function StatusCard( s ) {
		const st = cardState( s );
		let sub = '';
		if ( 'is-ready' === st.cls ) {
			sub = ( ( data.languages.find && data.languages.find( function ( l ) { return l.value === s.language; } ) ) || {} ).label || s.language;
			sub += ' · ' + ( ( s.overrides && s.overrides.voice ) || data.defaults.voice );
			if ( s.duration ) {
				sub += ' · ' + F.formatDuration( s.duration );
			}
		} else if ( 'is-stale' === st.cls ) {
			sub = __( 'The post text changed since this audio was made.', 'vocasync' );
		} else if ( 'is-failed' === st.cls ) {
			sub = s.error || '';
		}
		const kids = [ el( 'div', { key: 't', className: 'vocasync-card__title' }, st.icon + ' ' + st.title ) ];
		if ( sub ) {
			kids.push( el( 'div', { key: 's', className: 'vocasync-card__sub' }, sub ) );
		}
		if ( 'is-gen' === st.cls ) {
			const cp = s.chunkProgress;
			const pct = cp && cp.total > 0 ? Math.round( ( cp.completed / cp.total ) * 100 ) : null;
			kids.push(
				el(
					'div',
					{ key: 'p', className: 'vocasync-progress' + ( null === pct ? ' is-indeterminate' : '' ) },
					el( 'span', { style: null === pct ? {} : { width: pct + '%' } } )
				)
			);
		}
		return el( 'div', { className: 'vocasync-card ' + st.cls }, kids );
	}

	function Panel() {
		const postId = useState( wp.data.select( 'core/editor' ).getCurrentPostId() )[ 0 ];
		const statusState = useState( null );
		const status = statusState[ 0 ];
		const setStatus = statusState[ 1 ];
		const busyState = useState( false );
		const busy = busyState[ 0 ];
		const setBusy = busyState[ 1 ];

		function load() {
			if ( ! postId ) {
				return;
			}
			apiFetch( { path: '/vocasync/v1/status?post_id=' + postId } ).then( setStatus ).catch( function () {} );
		}
		useEffect( function () { load(); }, [ postId ] );
		useEffect( function () {
			if ( status && ( 'synthesizing' === status.jobStatus || 'aligning' === status.jobStatus ) ) {
				const t = setTimeout( load, 5000 );
				return function () { clearTimeout( t ); };
			}
		}, [ status ] );

		function generate() {
			setBusy( true );
			apiFetch( { path: '/vocasync/v1/generate', method: 'POST', data: { post_id: postId, force: true } } )
				.then( function ( r ) { setStatus( r ); setBusy( false ); } )
				.catch( function () { setBusy( false ); } );
		}
		function saveOverride( key, val ) {
			const ov = Object.assign( {}, ( status && status.overrides ) || {} );
			ov[ key ] = val;
			apiFetch( { path: '/vocasync/v1/overrides', method: 'POST', data: { post_id: postId, overrides: ov } } ).then( setStatus );
		}

		const kids = [];
		if ( ! status ) {
			kids.push( el( wp.components.Spinner, { key: 'spin' } ) );
			return el( wp.editPost.PluginDocumentSettingPanel, { name: 'vocasync', title: __( 'VocaSync', 'vocasync' ), className: 'vocasync-panel' }, kids );
		}

		const gen = 'synthesizing' === status.jobStatus || 'aligning' === status.jobStatus;
		kids.push( StatusCard( status ) ); // StatusCard is a plain function returning an element

		if ( ! gen && ! status.canGenerate && status.reason ) {
			kids.push( el( wp.components.Notice, { key: 'reason', status: 'no_api_key' === status.reason ? 'warning' : 'info', isDismissible: false }, reasonText( status.reason ) ) );
		}
		if ( 'error' === status.status && status.message ) {
			kids.push( el( wp.components.Notice, { key: 'genmsg', status: 'error', isDismissible: true }, status.message ) );
		} else if ( 'unchanged' === status.status ) {
			kids.push( el( wp.components.Notice, { key: 'unchanged', status: 'info', isDismissible: true }, __( 'Audio is already up to date.', 'vocasync' ) ) );
		}

		if ( status.charCount > 0 ) {
			let info = status.charCount.toLocaleString() + ' ' + __( 'characters', 'vocasync' );
			if ( status.hasAudio && status.duration ) {
				info += ' · ' + F.formatDuration( status.duration );
			} else {
				const est = F.estimateLength( status.charCount );
				if ( est ) {
					info += ' · ' + est;
				}
			}
			kids.push( el( 'div', { key: 'info', className: 'vocasync-info' }, info ) );
		}

		let label = status.hasAudio ? __( 'Regenerate audio', 'vocasync' ) : __( 'Generate audio', 'vocasync' );
		if ( 'failed' === status.jobStatus ) {
			label = __( 'Try again', 'vocasync' );
		}
		const primary = ! status.hasAudio || status.stale || 'failed' === status.jobStatus;
		kids.push(
			el( wp.components.Button, {
				key: 'gen',
				variant: primary ? 'primary' : 'secondary',
				isBusy: busy || gen,
				disabled: busy || gen || ! status.canGenerate,
				onClick: generate,
			}, gen ? __( 'Generating…', 'vocasync' ) : label )
		);

		const ov = status.overrides || {};
		kids.push(
			el(
				wp.components.PanelBody,
				{ key: 'settings', title: __( 'Settings', 'vocasync' ), initialOpen: false },
				el( wp.components.SelectControl, {
					key: 'voice',
					label: __( 'Voice', 'vocasync' ),
					value: ov.voice || data.defaults.voice,
					options: data.voices,
					help: ( ( data.voices.find && data.voices.find( function ( v ) { return v.value === ( ov.voice || data.defaults.voice ); } ) ) || {} ).desc || '',
					onChange ( v ) { saveOverride( 'voice', v ); },
				} ),
				el( wp.components.SelectControl, {
					key: 'lang',
					label: __( 'Language', 'vocasync' ),
					value: ov.language || data.defaults.language,
					options: data.languages.map( function ( l ) { return { value: l.value, label: l.experimental ? l.label + ' — experimental' : l.label }; } ),
					onChange ( v ) { saveOverride( 'language', v ); },
				} ),
				el( wp.components.SelectControl, {
					key: 'position',
					label: __( 'Player position', 'vocasync' ),
					value: ov.position || 'default',
					options: [
						{ value: 'default', label: __( 'Default', 'vocasync' ) },
						{ value: 'top', label: __( 'Top of content', 'vocasync' ) },
						{ value: 'bottom', label: __( 'Bottom of content', 'vocasync' ) },
					],
					onChange ( v ) { saveOverride( 'position', 'default' === v ? '' : v ); },
				} ),
				el( 'p', { key: 'controls-label', style: { textTransform: 'uppercase', fontSize: '11px', color: '#787c82', margin: '8px 0 0' } }, __( 'Player controls', 'vocasync' ) ),
				[ 'speed', 'volume', 'mini', 'highlight', 'clickSeek' ].map( function ( ctrl ) {
					return el( wp.components.ToggleControl, {
						key: 'ctrl-' + ctrl,
						label: ( data.controlLabels && data.controlLabels[ ctrl ] ) || ctrl,
						checked: undefined !== ov[ ctrl ] ? ov[ ctrl ] : !! ( data.controls && data.controls[ ctrl ] ),
						onChange ( v ) { saveOverride( ctrl, v ); },
					} );
				} ),
				el( wp.components.ToggleControl, {
					key: 'auto',
					label: __( 'Auto-generate on publish', 'vocasync' ),
					checked: undefined !== ov.auto ? ov.auto : data.defaults.auto,
					onChange ( v ) { saveOverride( 'auto', v ); },
				} ),
				el( wp.components.ToggleControl, {
					key: 'enabled',
					label: __( 'Enable audio for this post', 'vocasync' ),
					checked: undefined !== ov.enabled ? ov.enabled : true,
					onChange ( v ) { saveOverride( 'enabled', v ); },
				} )
			)
		);

		return el( wp.editPost.PluginDocumentSettingPanel, { name: 'vocasync', title: __( 'VocaSync', 'vocasync' ), className: 'vocasync-panel' }, kids );
	}

	if ( wp.plugins && wp.editPost && wp.editPost.PluginDocumentSettingPanel ) {
		wp.plugins.registerPlugin( 'vocasync', { render: Panel, icon: 'controls-volumeon' } );
	}
} )( window.wp );
