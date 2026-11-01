/* EntryStage — confirmations, column widths and cards; in EntryStage Pro also select all, selection counter and "More details". No dependency. */
( function () {
	'use strict';


	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		// rows per page reload the list at once.
		if ( target.hasAttribute && target.hasAttribute( 'data-estg-autosubmit' ) && target.form ) {
			// requestSubmit() goes through the submit event (list updated in place when it is on).
			if ( target.form.requestSubmit ) {
				target.form.requestSubmit();
			} else {
				target.form.submit();
			}
			return;
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( 'button[name="estg_do"]' ) : null;
		if ( ! button ) {
			return;
		}
		var question = button.getAttribute( 'data-estg-confirm' );
		if ( question && ! window.confirm( question ) ) {
			event.preventDefault();
		}
	} );

	// No horizontal scroll: when a table is wider than its container, show its rows as cards.
	function fit() {
		document.querySelectorAll( '.estg:not(.estg-print)' ).forEach( function ( list ) {
			var table = list.querySelector( '.estg-table' );
			if ( ! table ) {
				return;
			}
			list.classList.remove( 'estg-cards' );
			// Rows per page: the list reloads on change, the "OK" button is only for browsers without JavaScript.
			list.querySelectorAll( '.estg-nojs' ).forEach( function ( b ) {
				b.hidden = true;
			} );
			if ( window.innerWidth <= 700 ) {
				return;
			}
			// widths in % = share of the space left by the columns with a fixed width (actions, chevron, boxes).
			// the actions column never takes more than 25 % of the table; its buttons wrap instead.
			var fontPx = parseFloat( window.getComputedStyle( table ).fontSize ) || 16;
			var actTh = table.querySelector( 'th.estg-actions-col' );
			var actCol = table.querySelector( 'colgroup col[data-estg-actions]' );
			if ( actTh ) {
				var cap = Math.floor( table.clientWidth * 0.25 );
				var wanted = parseFloat( actTh.getAttribute( 'data-estg-em' ) || '0' ) * fontPx;
				var w = Math.min( wanted || cap, cap );
				actTh.style.minWidth = '';
				actTh.style.width = w + 'px';
				if ( actCol ) {
					actCol.style.width = w + 'px';
				}
			}
			var cols = table.querySelectorAll( 'colgroup col' );
			if ( cols.length ) {
				var libre = table.clientWidth, parts = [];
				cols.forEach( function ( col ) {
					if ( col.hasAttribute( 'data-estg-share' ) ) {
						parts.push( col );
					} else if ( /em$/.test( col.style.width ) ) {
						libre -= parseFloat( col.style.width ) * fontPx;
					} else if ( /px$/.test( col.style.width ) ) {
						libre -= parseFloat( col.style.width );
					}
				} );
				// when every free column has a share (no column without width) and the shares add up to
				// less than 100 %, they are scaled up so that the spare room is not given to the actions column.
				var total = 0, auto = false;
				cols.forEach( function ( col ) {
					if ( col.hasAttribute( 'data-estg-share' ) ) {
						total += parseFloat( col.getAttribute( 'data-estg-share' ) ) || 0;
					} else if ( ! col.style.width ) {
						auto = true;
					}
				} );
				var scale = ! auto && total > 0 && total < 1 ? 1 / total : 1;
				parts.forEach( function ( col ) {
					col.style.width = Math.max( 0, Math.floor( libre * scale * parseFloat( col.getAttribute( 'data-estg-share' ) ) ) ) + 'px';
				} );
			}
			// cards also when a column is squeezed below 3em (widths in % on a narrow page),
			// or when a value cannot fit its cell (status label and its button, thumbnails).
			var em = parseFloat( window.getComputedStyle( list ).fontSize ) || 16;
			var serre = false;
			table.querySelectorAll( 'thead th' ).forEach( function ( th ) {
				if ( ! th.classList.contains( 'estg-check' ) && ! th.classList.contains( 'estg-more-col' ) && th.clientWidth < 3 * em ) {
					serre = true;
				}
			} );
			if ( ! serre ) {
				table.querySelectorAll( 'tbody tr:not([hidden]) > td' ).forEach( function ( td ) {
					if ( ! serre && td.scrollWidth > td.clientWidth + 2 ) {
						serre = true;
					}
				} );
			}
			if ( serre || table.scrollWidth > list.clientWidth + 1 ) {
				list.classList.add( 'estg-cards' );
			}
		} );
	}
	var timer = null;
	window.addEventListener( 'resize', function () {
		window.clearTimeout( timer );
		timer = window.setTimeout( fit, 150 );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', fit );
	} else {
		fit();
	}
	window.addEventListener( 'load', fit );
	// web fonts of the theme can arrive after the page: text gets wider, so lay out again.
	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( fit );
	}
	// Block editor: the preview of the block is drawn later by the server; lay it out when it arrives.
	// the editor canvas is a frame whose classes are set later, or the admin page itself in older editors.
	var inEditor = window.self !== window.top || /\bblock-editor/.test( document.documentElement.className + ' ' + ( document.body ? document.body.className : '' ) );
	if ( inEditor && window.MutationObserver ) {
		new window.MutationObserver( function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( fit, 150 );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
	// Search, sorting, pages, rows per page and tabs without reloading the page: the same page is fetched
	// and the list (or the personal space holding it) is replaced; the address follows (Back button, links).
	// Any problem: the browser simply goes to the address, as without JavaScript.
	var live = null;
	function announce( text ) {
		if ( ! live ) {
			live = document.createElement( 'div' );
			live.className = 'screen-reader-text estg-live';
			live.setAttribute( 'aria-live', 'polite' );
			live.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;';
			document.body.appendChild( live );
		}
		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = text;
		}, 50 );
	}

	// the same element in another document: same id (or the personal space), same position on the page.
	function twin( doc, el ) {
		var sel = el.classList.contains( 'estg-space' ) ? '.estg-space' : '#' + el.id;
		var i = Array.prototype.indexOf.call( document.querySelectorAll( sel ), el );
		return i < 0 ? null : doc.querySelectorAll( sel )[ i ] || null;
	}

	// the part to replace for a link or a form: the personal space for its tabs, otherwise the list.
	function host( el ) {
		var space = el.closest( '.estg-space' );
		if ( space && el.closest( '.estg-space-tabs' ) ) {
			return space.querySelector( '.estg[data-estg-ajax]' ) ? space : null;
		}
		var list = el.closest( '.estg' );
		return list && list.hasAttribute( 'data-estg-ajax' ) ? list : null;
	}

	function load( url, target, push, keep ) {
		if ( ! window.fetch || ! window.DOMParser || ! window.history || ! window.history.pushState ) {
			window.location.href = url;
			return;
		}
		target.setAttribute( 'aria-busy', 'true' );
		target.classList.add( 'is-loading' );
		window.fetch( url, { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.ok ? response.text() : Promise.reject( response.status );
			} )
			.then( function ( html ) {
				var doc = new window.DOMParser().parseFromString( html, 'text/html' );
				var fresh = twin( doc, target );
				if ( ! fresh ) {
					window.location.href = url;
					return;
				}
				fresh = document.importNode( fresh, true );
				target.parentNode.replaceChild( fresh, target );
				if ( push ) {
					// The page router of WordPress (Interactivity API, used by some themes) reloads the page when
					// Back reaches an address it did not make: then the address is only replaced (it can still be
					// shared or reloaded; Back goes to the previous page).
					var current = window.history.state;
					if ( current && current.wpInteractivityId ) {
						window.history.replaceState( current, '', url );
					} else {
						window.history.pushState( { estg: 1 }, '', url );
					}
				}
				fit();
				// focus: back in the field that was used (search), otherwise on the list itself.
				var field = keep ? fresh.querySelector( '[name="' + keep + '"]' ) : null;
				if ( field ) {
					field.focus();
				} else {
					fresh.setAttribute( 'tabindex', '-1' );
					fresh.focus( { preventScroll: true } );
				}
				if ( fresh.getBoundingClientRect().top < 0 ) {
					fresh.scrollIntoView( { block: 'start' } );
				}
				var total = fresh.querySelector( '.estg-total, .estg-empty' );
				if ( total ) {
					announce( total.textContent );
				}
			} )
			.catch( function () {
				window.location.href = url;
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		if ( event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || ! event.target.closest ) {
			return;
		}
		var link = event.target.closest( '.estg-pages a, .estg-table thead a, .estg-tabs a, .estg-toolbar a.estg-link, .estg-view-switch a' );
		var target = link ? host( link ) : null;
		if ( ! target || link.target === '_blank' ) {
			return;
		}
		event.preventDefault();
		load( link.href, target, true );
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( event.defaultPrevented || ! form.matches || ! form.matches( 'form.estg-toolbar, form.estg-perpage' ) ) {
			return;
		}
		var target = host( form );
		if ( ! target || ! window.URL || ! window.FormData ) {
			return;
		}
		event.preventDefault();
		var url = new window.URL( form.action, window.location.href );
		url.search = '';
		new window.FormData( form ).forEach( function ( value, name ) {
			if ( '' !== value ) {
				url.searchParams.append( name, value );
			}
		} );
		var active = document.activeElement;
		load( url.toString(), target, true, active && active.name && form.contains( active ) && active.type !== 'submit' ? active.name : 'estg_s' );
	} );

	// Back and Forward: the lists of the page are fetched again for that address.
	window.addEventListener( 'popstate', function () {
		var targets = [];
		document.querySelectorAll( '.estg-space, .estg[data-estg-ajax]' ).forEach( function ( el ) {
			if ( ! el.parentNode.closest( '.estg-space' ) || el.classList.contains( 'estg-space' ) ) {
				targets.push( el );
			}
		} );
		targets.forEach( function ( el ) {
			load( window.location.href, el, false );
		} );
	} );
	// Printable list: the Print button, and the print dialog opened once, when the page has loaded (pictures included).
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-estg-print]' ) : null;
		if ( button ) {
			window.print();
		}
	} );
	window.addEventListener( 'load', function () {
		if ( document.querySelector( '.estg-print[data-estg-autoprint]' ) ) {
			window.setTimeout( function () {
				window.print();
			}, 300 );
		}
	} );
} )();
