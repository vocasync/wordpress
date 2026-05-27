/**
 * VocaSync player — vanilla JS, no dependencies. Ported from the
 * `@vocasync/astro` AudioPlayer component. Initializes every `.vocasync-player`
 * on the page; each reads its alignment data from a child
 * `<script type="application/json" class="vocasync-data">` element.
 */
( function () {
	'use strict';

	const HL = window.VocaSyncHighlight || {};
	const EPS = HL.EPS || 0.0001;
	const PARAGRAPH_SELECTOR = 'p, li, blockquote, h1, h2, h3, h4, h5, h6, td, th';

	function formatTime( seconds ) {
		if ( ! seconds || ! isFinite( seconds ) ) {
			return '0:00';
		}
		const m = Math.floor( seconds / 60 );
		const s = Math.floor( seconds % 60 );
		return m + ':' + String( s ).padStart( 2, '0' );
	}

	function readData( player ) {
		const script = player.querySelector( '.vocasync-data' );
		if ( ! script ) {
			return null;
		}
		try {
			return JSON.parse( script.textContent || '{}' );
		} catch ( e ) {
			return null;
		}
	}

	function initPlayer( player ) {
		const audio = player.querySelector( 'audio' );
		if ( ! player || ! audio ) {
			return;
		}
		const miniPlayer = player.querySelector( '[data-mini-player]' );

		const enableHighlighting = player.dataset.enableHighlighting !== 'false';
		const enableMini = player.dataset.enableMini !== 'false';
		const enableClickToSeek = player.dataset.enableClickToSeek === 'true';
		const trailLength = parseInt( player.dataset.trailLength || '4', 10 );
		const articleSel = player.dataset.articleSelector || '';
		let wordRoot = articleSel ? document.querySelector( articleSel ) : document;
		if ( ! wordRoot ) {
			wordRoot = document;
		}

		let isPlaying = false;
		let isMiniVisible = false;
		let animationFrame = null;
		let miniDismissed = false;
		let highlightingEnabled = enableHighlighting;

		const loadingEl = player.querySelector( '[data-state="loading"]' );
		const placeholderEl = player.querySelector( '[data-state="no-audio"]' );
		const controlsEl = player.querySelector( '[data-state="ready"]' );
		const playBtn = player.querySelector( '[data-action="play-pause"]' );
		const progressInput = player.querySelector( '[data-action="seek"]' );
		const volumeInput = player.querySelector( '[data-action="volume"]' );
		const muteBtn = player.querySelector( '[data-action="mute"]' );
		const speedBtn = player.querySelector( '[data-action="speed"]' );
		const speedMenu = player.querySelector( '.vocasync-player__speed-menu' );
		const highlightBtn = player.querySelector( '[data-action="toggle-highlighting"]' );
		const currentTimeEl = player.querySelector( '[data-time="current"]' );
		const durationTimeEl = player.querySelector( '[data-time="duration"]' );

		let words = [];
		let spanTimings = [];
		let activeEl = null;
		let trailStart = 0;
		let trailEnd = 0;
		let lastScrolledPara = null;

		function updatePlayButton( playing ) {
			[ player, miniPlayer ].forEach( function ( scope ) {
				if ( ! scope ) {
					return;
				}
				const btn = scope.querySelector( '[data-action="play-pause"]' );
				if ( ! btn ) {
					return;
				}
				const play = btn.querySelector( '.vocasync-icon--play' );
				const pause = btn.querySelector( '.vocasync-icon--pause' );
				if ( play && pause ) {
					play.classList.toggle( 'vocasync-hidden', playing );
					pause.classList.toggle( 'vocasync-hidden', ! playing );
				}
				btn.setAttribute( 'aria-label', playing ? 'Pause' : 'Play' );
			} );
		}

		function updateVolumeIcon( muted ) {
			if ( ! muteBtn ) {
				return;
			}
			const high = muteBtn.querySelector( '.vocasync-icon--volume-high' );
			const mute = muteBtn.querySelector( '.vocasync-icon--volume-mute' );
			if ( high && mute ) {
				high.classList.toggle( 'vocasync-hidden', muted );
				mute.classList.toggle( 'vocasync-hidden', ! muted );
			}
		}

		function updateHighlightButton() {
			if ( ! highlightBtn ) {
				return;
			}
			const on = highlightBtn.querySelector( '.vocasync-icon--highlight-on' );
			const off = highlightBtn.querySelector( '.vocasync-icon--highlight-off' );
			if ( on && off ) {
				on.classList.toggle( 'vocasync-hidden', ! highlightingEnabled );
				off.classList.toggle( 'vocasync-hidden', highlightingEnabled );
			}
			highlightBtn.setAttribute( 'data-highlighting', highlightingEnabled ? 'on' : 'off' );
			highlightBtn.setAttribute( 'aria-label', highlightingEnabled ? 'Disable word highlighting' : 'Enable word highlighting' );
		}

		function initWordHighlighting() {
			if ( ! words.length || ! HL.computeSpanTimings ) {
				return false;
			}
			const spans = Array.prototype.slice.call( wordRoot.querySelectorAll( '.vocasync-word[data-i]' ) );
			if ( ! spans.length ) {
				return false;
			}
			const items = spans.map( function ( el ) {
				return {
					dataI: parseInt( el.dataset.i || '', 10 ),
					dataN: parseInt( el.dataset.n || '', 10 ),
					ref: el,
				};
			} );
			spanTimings = HL.computeSpanTimings( items, words );
			return spanTimings.length > 0;
		}

		function idxForTime( t ) {
			return HL.idxForTime( spanTimings, t );
		}

		function setActive( el ) {
			if ( activeEl === el ) {
				return;
			}
			if ( activeEl ) {
				activeEl.classList.remove( 'is-active' );
			}
			if ( el ) {
				el.classList.add( 'is-active' );
			}
			activeEl = el;
		}

		function clearHighlights() {
			if ( activeEl ) {
				activeEl.classList.remove( 'is-active' );
				activeEl = null;
			}
			for ( let i = trailStart; i < trailEnd; i++ ) {
				if ( spanTimings[ i ] ) {
					spanTimings[ i ].ref.classList.remove( 'is-trail' );
				}
			}
			trailStart = 0;
			trailEnd = 0;
		}

		function updateHighlighting() {
			if ( ! spanTimings.length ) {
				return;
			}
			const t = Number( audio.currentTime || 0 );
			if ( ! isFinite( t ) ) {
				return;
			}
			const lo = idxForTime( t + EPS );
			const entry = spanTimings[ lo ];
			const el = entry && t >= entry.start - EPS && t < entry.end + EPS ? entry.ref : null;

			if ( highlightingEnabled ) {
				setActive( el );
			}

			if ( highlightingEnabled && el ) {
				const para = el.closest( PARAGRAPH_SELECTOR );
				if ( para && para !== lastScrolledPara ) {
					const reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
					para.scrollIntoView( { behavior: reduced ? 'auto' : 'smooth', block: 'center' } );
					lastScrolledPara = para;
				}
			}

			if ( highlightingEnabled ) {
				const newEnd = lo;
				const newStart = Math.max( 0, lo - trailLength );
				for ( let i = trailStart; i < trailEnd; i++ ) {
					if ( ( i < newStart || i >= newEnd ) && spanTimings[ i ] ) {
						spanTimings[ i ].ref.classList.remove( 'is-trail' );
					}
				}
				for ( let j = newStart; j < newEnd; j++ ) {
					if ( ( j < trailStart || j >= trailEnd ) && spanTimings[ j ] ) {
						spanTimings[ j ].ref.classList.add( 'is-trail' );
					}
				}
				trailStart = newStart;
				trailEnd = newEnd;
			}
		}

		function highlightTick() {
			if ( ! highlightingEnabled ) {
				return;
			}
			updateHighlighting();
			if ( ! audio.paused && ! audio.ended ) {
				animationFrame = requestAnimationFrame( highlightTick );
			}
		}

		function startHighlightLoop() {
			cancelAnimationFrame( animationFrame );
			animationFrame = requestAnimationFrame( highlightTick );
		}

		function stopHighlightLoop() {
			cancelAnimationFrame( animationFrame );
			animationFrame = null;
		}

		function toggleHighlighting() {
			highlightingEnabled = ! highlightingEnabled;
			updateHighlightButton();
			if ( ! highlightingEnabled ) {
				clearHighlights();
			} else {
				updateHighlighting();
				// Toggling off stops the rAF loop; restart it so highlighting
				// resumes following the audio when re-enabled mid-playback.
				if ( ! audio.paused && ! audio.ended ) {
					startHighlightLoop();
				}
			}
		}

		function setupMiniPlayer() {
			if ( ! miniPlayer || ! enableMini || ! ( 'IntersectionObserver' in window ) ) {
				return;
			}
			const observer = new IntersectionObserver(
				function ( entries ) {
					const entry = entries[ 0 ];
					const shouldShow = ! entry.isIntersecting && isPlaying && ! miniDismissed;
					if ( shouldShow && ! isMiniVisible ) {
						miniPlayer.hidden = false;
						isMiniVisible = true;
					} else if ( ! shouldShow && isMiniVisible ) {
						miniPlayer.hidden = true;
						isMiniVisible = false;
					}
				},
				{ threshold: 0 }
			);
			observer.observe( player );

			const miniPlayBtn = miniPlayer.querySelector( '[data-action="play-pause"]' );
			const miniCloseBtn = miniPlayer.querySelector( '[data-action="close-mini"]' );
			if ( miniPlayBtn ) {
				miniPlayBtn.addEventListener( 'click', togglePlay );
			}
			if ( miniCloseBtn ) {
				miniCloseBtn.addEventListener( 'click', function () {
					miniDismissed = true;
					miniPlayer.hidden = true;
					isMiniVisible = false;
				} );
			}
		}

		function updateMiniProgress() {
			if ( ! miniPlayer ) {
				return;
			}
			const bar = miniPlayer.querySelector( '[data-progress]' );
			const timeEl = miniPlayer.querySelector( '[data-time="current"]' );
			if ( bar ) {
				bar.style.width = ( ( audio.currentTime / audio.duration ) * 100 || 0 ) + '%';
			}
			if ( timeEl ) {
				timeEl.textContent = formatTime( audio.currentTime );
			}
		}

		function setupWordClickHandlers() {
			if ( ! enableClickToSeek ) {
				return;
			}
			const scopeEl = wordRoot === document ? document.body : wordRoot;
			if ( scopeEl && scopeEl.classList ) {
				scopeEl.classList.add( 'vocasync-click-seek' );
			}
			( wordRoot === document ? document : wordRoot ).addEventListener( 'click', function ( e ) {
				const target = e.target;
				const span = target && target.closest ? target.closest( '.vocasync-word[data-i]' ) : null;
				if ( ! span ) {
					return;
				}
				const view = target.ownerDocument.defaultView;
				const sel = view && view.getSelection ? view.getSelection() : null;
				if ( sel && sel.toString().length > 0 ) {
					return;
				}
				const dataI = parseInt( span.dataset.i || '', 10 );
				if ( ! isFinite( dataI ) ) {
					return;
				}
				const t = words[ dataI ];
				if ( ! t || typeof t.start !== 'number' ) {
					return;
				}
				audio.currentTime = t.start;
				if ( audio.paused ) {
					audio.play();
				}
			} );
		}

		function showNoAudio() {
			if ( loadingEl ) {
				loadingEl.hidden = true;
			}
			if ( placeholderEl ) {
				placeholderEl.hidden = false;
			}
			if ( controlsEl ) {
				controlsEl.hidden = true;
			}
		}

		function showReady() {
			if ( loadingEl ) {
				loadingEl.hidden = true;
			}
			if ( placeholderEl ) {
				placeholderEl.hidden = true;
			}
			if ( controlsEl ) {
				controlsEl.hidden = false;
			}
		}

		function togglePlay() {
			if ( audio.paused ) {
				audio.play();
			} else {
				audio.pause();
			}
		}

		function init() {
			const entry = readData( player );
			if ( ! entry || ! entry.audioUrl ) {
				showNoAudio();
				return;
			}

			let audioSrc = entry.audioUrl;
			if ( entry.publishableKey ) {
				try {
					const url = new URL( entry.audioUrl );
					url.searchParams.set( 'pk', entry.publishableKey );
					audioSrc = url.toString();
				} catch ( e ) {}
			}
			audio.src = audioSrc;

			if ( enableHighlighting ) {
				words = Array.isArray( entry.words ) ? entry.words : [];
				const initHl = function () {
					initWordHighlighting();
					setupWordClickHandlers();
				};
				if ( document.readyState === 'loading' ) {
					document.addEventListener( 'DOMContentLoaded', initHl );
				} else {
					initHl();
				}
			}

			showReady();
			setupMiniPlayer();
			updateHighlightButton();
		}

		if ( playBtn ) {
			playBtn.addEventListener( 'click', togglePlay );
		}
		if ( highlightBtn ) {
			highlightBtn.addEventListener( 'click', toggleHighlighting );
		}

		audio.addEventListener( 'play', function () {
			isPlaying = true;
			updatePlayButton( true );
			startHighlightLoop();
			miniDismissed = false;
		} );
		audio.addEventListener( 'pause', function () {
			isPlaying = false;
			updatePlayButton( false );
			stopHighlightLoop();
		} );
		audio.addEventListener( 'ended', function () {
			isPlaying = false;
			updatePlayButton( false );
			stopHighlightLoop();
		} );
		audio.addEventListener( 'seeking', function () {
			if ( highlightingEnabled ) {
				updateHighlighting();
			}
		} );
		audio.addEventListener( 'seeked', function () {
			if ( highlightingEnabled ) {
				updateHighlighting();
			}
			if ( ! audio.paused && ! audio.ended ) {
				startHighlightLoop();
			}
		} );
		audio.addEventListener( 'loadedmetadata', function () {
			if ( durationTimeEl ) {
				durationTimeEl.textContent = formatTime( audio.duration );
			}
			if ( progressInput ) {
				progressInput.max = audio.duration;
			}
		} );
		audio.addEventListener( 'timeupdate', function () {
			if ( currentTimeEl ) {
				currentTimeEl.textContent = formatTime( audio.currentTime );
			}
			if ( progressInput ) {
				progressInput.value = audio.currentTime;
			}
			updateMiniProgress();
		} );
		if ( progressInput ) {
			progressInput.addEventListener( 'input', function () {
				audio.currentTime = parseFloat( progressInput.value );
			} );
		}
		if ( volumeInput ) {
			volumeInput.addEventListener( 'input', function () {
				audio.volume = parseFloat( volumeInput.value ) / 100;
				updateVolumeIcon( audio.volume === 0 );
			} );
		}
		if ( muteBtn ) {
			muteBtn.addEventListener( 'click', function () {
				audio.muted = ! audio.muted;
				updateVolumeIcon( audio.muted );
			} );
		}
		if ( speedBtn && speedMenu ) {
			speedBtn.addEventListener( 'click', function () {
				speedMenu.hidden = ! speedMenu.hidden;
			} );
			speedMenu.addEventListener( 'click', function ( e ) {
				const btn = e.target.closest( '[data-speed]' );
				if ( ! btn ) {
					return;
				}
				const speed = parseFloat( btn.dataset.speed );
				audio.playbackRate = speed;
				speedMenu.querySelectorAll( '[data-speed]' ).forEach( function ( b ) {
					b.removeAttribute( 'data-active' );
				} );
				btn.setAttribute( 'data-active', '' );
				const label = player.querySelector( '[data-speed-label]' );
				if ( label ) {
					label.textContent = speed + 'x';
				}
				speedMenu.hidden = true;
			} );
			document.addEventListener( 'click', function ( e ) {
				if ( ! speedBtn.contains( e.target ) && ! speedMenu.contains( e.target ) ) {
					speedMenu.hidden = true;
				}
			} );
		}

		player.addEventListener( 'keydown', function ( e ) {
			if ( e.target.tagName === 'INPUT' ) {
				return;
			}
			switch ( e.code ) {
				case 'Space':
					e.preventDefault();
					togglePlay();
					break;
				case 'ArrowLeft':
					e.preventDefault();
					audio.currentTime = Math.max( 0, audio.currentTime - 5 );
					break;
				case 'ArrowRight':
					e.preventDefault();
					audio.currentTime = Math.min( audio.duration || 0, audio.currentTime + 5 );
					break;
				case 'KeyM':
					e.preventDefault();
					audio.muted = ! audio.muted;
					updateVolumeIcon( audio.muted );
					break;
				case 'KeyH':
					e.preventDefault();
					toggleHighlighting();
					break;
			}
		} );

		init();
	}

	function boot() {
		const players = document.querySelectorAll( '.vocasync-player' );
		Array.prototype.forEach.call( players, initPlayer );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
