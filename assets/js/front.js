/* EntryStage — confirmations, column widths and cards; in EntryStage Pro also select all, selection counter and "More details". No dependency. */
( function () {
	'use strict';


	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		// rows per page reload the list at once.
		if ( target.hasAttribute && target.hasAttribute( 'data-estg-autosubmit' ) && target.form ) {
			target.form.submit();
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
