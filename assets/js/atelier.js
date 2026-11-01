/* EntryStage — settings workshop of a form: steps, column builder, shortcode composer; in EntryStage Pro also details, statuses and button icons (media library). */
( function () {
	'use strict';

	var T = window.estg_atelier_strings || {};
	var root, data, fields = {}, state = { cols: [], dets: [], statuses: [], entry: [] };

	function t( key, value ) {
		var text = T[ key ] || key;
		return value === undefined ? text : text.replace( '%s', value ).replace( '%d', value );
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( name ) {
			var v = attrs[ name ];
			if ( v === null || v === undefined || v === false ) {
				return;
			}
			if ( name === 'text' ) {
				node.textContent = v;
			} else if ( name.indexOf( 'on' ) === 0 ) {
				node.addEventListener( name.slice( 2 ), v );
			} else {
				node.setAttribute( name, v === true ? '' : v );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
			}
		} );
		return node;
	}

	function q( sel ) {
		return root.querySelector( sel );
	}

	function input( name ) {
		var list = root.querySelectorAll( '[data-estga-input="' + name + '"]' );
		if ( ! list.length ) {
			return null;
		}
		if ( list[ 0 ].type === 'radio' ) {
			for ( var i = 0; i < list.length; i++ ) {
				if ( list[ i ].checked ) {
					return list[ i ].value;
				}
			}
			return '';
		}
		return list[ 0 ].type === 'checkbox' ? list[ 0 ].checked : list[ 0 ].value;
	}

	function say( text ) {
		var live = q( '[data-estga-live]' );
		if ( live ) {
			live.textContent = '';
			window.setTimeout( function () {
				live.textContent = text;
			}, 30 );
		}
	}

	// ---------------------------------------------------------------- Steps.

	function go( step ) {
		root.querySelectorAll( '[data-estga-panel]' ).forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-estga-panel' ) !== step;
		} );
		root.querySelectorAll( '[data-estga-go]' ).forEach( function ( button ) {
			var on = button.getAttribute( 'data-estga-go' ) === step;
			button.classList.toggle( 'is-current', on );
			if ( on ) {
				button.setAttribute( 'aria-current', 'step' );
			} else {
				button.removeAttribute( 'aria-current' );
			}
		} );
		var keep = q( '[data-estga-step-input]' );
		if ( keep ) {
			keep.value = step;
		}
	}

	function fieldCount() {
		return state.cols.filter( function ( c ) {
			return c.key.charAt( 0 ) !== '_';
		} ).length;
	}

	function hasStatus() {
		return false;
	}

	function states() {
		var page = q( '#estga-page' );
		var noPage = !! q( '[data-estga-nopage]' ) && page && page.value === '';
		var roles = root.querySelectorAll( 'input[name="estg[access_roles][]"]:checked' ).length;
		var list = {
			access: input( 'access' ) === 'roles' && ! roles ? 'check' : 'ready',
			columns: fieldCount() ? 'ready' : 'check',
			actions: ( input( 'edit' ) || input( 'duplicate' ) ) && noPage ? 'check' : 'ready',
			display: 'ready'
		};
		Object.keys( list ).forEach( function ( step ) {
			var badge = q( '[data-estga-state="' + step + '"]' );
			if ( badge ) {
				badge.textContent = t( list[ step ] );
				badge.className = 'estga-state is-' + list[ step ];
			}
		} );
	}

	function dependents() {
		root.querySelectorAll( '[data-estga-show]' ).forEach( function ( node ) {
			var rule = node.getAttribute( 'data-estga-show' ).split( '=' );
			var value = input( rule[ 0 ] );
			node.hidden = rule.length > 1 ? value !== rule[ 1 ] : ! value;
		} );
	}

	// ---------------------------------------------------------------- Columns builder.

	function used( key ) {
		return state.cols.some( function ( c ) {
			return c.key === key;
		} ) || state.dets.some( function ( d ) {
			return d.key === key;
		} );
	}

	function labelOf( item ) {
		if ( item.key === '_status' || item.key === '_date' ) {
			return item.label || data.fixed[ item.key ];
		}
		return item.label || ( fields[ item.key ] ? fields[ item.key ].label : item.key );
	}

	function modeInfo( key, mode ) {
		var f = fields[ key ];
		var found = null;
		( f ? f.modes : [] ).forEach( function ( m ) {
			if ( m.value === mode ) {
				found = m;
			}
		} );
		return found || ( f && f.modes.length ? f.modes[ 0 ] : { value: 'default', label: '', sortable: false } );
	}

	function newColumn( key ) {
		var f = fields[ key ];
		var mode = f.modes.length ? f.modes[ 0 ].value : 'default';
		return { key: key, label: '', mode: mode, width: '', sort: modeInfo( key, mode ).sortable, filter: false };
	}

	function swap( list, i, d ) {
		var j = i + d;
		if ( j < 0 || j >= list.length ) {
			return false;
		}
		var tmp = list[ i ];
		list[ i ] = list[ j ];
		list[ j ] = tmp;
		return true;
	}

	function refocus( key, act ) {
		var node = root.querySelector( '[data-key="' + key + '"] [data-act="' + act + '"]' ) || root.querySelector( '[data-key="' + key + '"] button' );
		if ( node && ! node.disabled ) {
			node.focus();
		}
	}

	function change( key, act, message ) {
		render();
		if ( key ) {
			refocus( key, act );
		}
		if ( message ) {
			say( message );
		}
	}

	function iconButton( text, label, act, onclick, extra ) {
		return el( 'button', { type: 'button', class: 'estga-ibtn' + ( extra ? ' ' + extra : '' ), 'aria-label': label, title: label, 'data-act': act, onclick: onclick, text: text, disabled: extra === 'is-off' } );
	}

	function toggle( text, on, disabled, onchange, note ) {
		var box = el( 'input', { type: 'checkbox', disabled: disabled, onchange: onchange } );
		box.checked = !! on && ! disabled;
		return el( 'label', { class: 'estga-switch estga-switch-small' + ( disabled ? ' is-disabled' : '' ), title: disabled ? note : null }, [
			box,
			el( 'span', { class: 'estga-track', 'aria-hidden': 'true' } ),
			el( 'span', { text: text } ),
			disabled && note ? el( 'span', { class: 'screen-reader-text', text: ' (' + note + ')' } ) : null
		] );
	}

	// example under the "Display" choice (label of the first choice / its stored value).
	function exampleOf( f, mode ) {
		if ( ! f || ! f.example || ! f.example[ mode ] ) {
			return null;
		}
		return el( 'span', { class: 'estga-example', text: t( 'example', f.example[ mode ] ) } );
	}

	function modeSelect( item, f, name, onchange ) {
		var select = el( 'select', { class: 'estga-input', 'data-act': 'mode', 'aria-label': t( 'display' ) + ' : ' + name, onchange: onchange } );
		f.modes.forEach( function ( m ) {
			var option = el( 'option', { value: m.value, text: m.label } );
			option.selected = m.value === item.mode;
			select.appendChild( option );
		} );
		return el( 'label', { class: 'estga-label estga-cell-mode' }, [ t( 'display' ), select, exampleOf( f, item.mode ) ] );
	}

	// one card per column, laid out on two lines: name and tools, then the settings side by side.
	function colCard( item, i ) {
		var fixed = item.key.charAt( 0 ) === '_';
		var f = fields[ item.key ];
		var name = labelOf( item );
		var last = state.cols.length - 1;
		var tools = el( 'span', { class: 'estga-tools' }, [
			iconButton( '↑', t( 'moveUp', name ), 'up', function () {
				if ( swap( state.cols, i, -1 ) ) {
					change( item.key, 'up', t( 'moved', name ) );
				}
			}, i === 0 ? 'is-off' : '' ),
			iconButton( '↓', t( 'moveDown', name ), 'down', function () {
				if ( swap( state.cols, i, 1 ) ) {
					change( item.key, 'down', t( 'moved', name ) );
				}
			}, i === last ? 'is-off' : '' ),
			item.key !== '_status' ? iconButton( '✕', t( 'remove', name ), 'remove', function () {
				state.cols.splice( i, 1 );
				change( null, null, t( 'removed', name ) );
				var search = q( '[data-estga-search]' );
				if ( search ) {
					search.focus();
				}
			}, 'is-danger' ) : null
		] );
		var head = el( 'div', { class: 'estga-item-head' }, [
			el( 'span', { class: 'estga-order', 'aria-hidden': 'true', text: String( i + 1 ) } ),
			el( 'span', { class: 'estga-item-title' }, [
				el( 'span', { class: 'estga-item-name', text: fixed ? data.fixed[ item.key ] : ( f ? f.label : item.key ) } ),
				el( 'span', { class: 'estga-sub-type', text: fixed ? ( item.key === '_status' ? t( 'fixedStatus' ) : t( 'fixedDate' ) ) : ( f ? f.type : '' ) } )
			] ),
			tools
		] );
		var labelBox = el( 'input', { type: 'text', class: 'estga-input', value: item.label, placeholder: fixed ? data.fixed[ item.key ] : ( f ? f.label : '' ), oninput: function ( e ) {
			item.label = e.target.value;
			sync();
		} } );
		var widthBox = el( 'input', { type: 'text', class: 'estga-input', value: item.width, placeholder: t( 'auto' ), oninput: function ( e ) {
			item.width = e.target.value.trim();
			sync();
		} } );
		var cells = [ el( 'label', { class: 'estga-label estga-cell-label' }, [ t( 'colLabel' ), labelBox ] ) ];
		if ( ! fixed && f && f.modes.length > 1 ) {
			cells.push( modeSelect( item, f, name, function ( e ) {
				item.mode = e.target.value;
				if ( ! modeInfo( item.key, item.mode ).sortable ) {
					item.sort = false;
				}
				change( item.key, 'mode' );
			} ) );
		}
		cells.push( el( 'label', { class: 'estga-label estga-cell-width' }, [ t( 'width' ), widthBox ] ) );
		var canSort = fixed ? true : modeInfo( item.key, item.mode ).sortable;
		var toggles = [ toggle( t( 'sortable' ), item.sort, ! canSort, function ( e ) {
			item.sort = e.target.checked;
			sync();
		}, f ? f.sortNote : '' ) ];
		cells.push( el( 'div', { class: 'estga-cell-toggles' }, toggles ) );
		var notes = [];
		if ( ! fixed && f && ! canSort && f.sortNote ) {
			notes.push( el( 'p', { class: 'estga-note', text: f.sortNote } ) );
		}
		if ( ! fixed && f && f.warning ) {
			notes.push( el( 'p', { class: 'estga-note is-warn', text: f.warning } ) );
		}
		return el( 'li', { class: 'estga-item estga-row-item' + ( fixed ? ' is-fixed' : '' ), 'data-key': item.key }, [
			head,
			el( 'div', { class: 'estga-cells' }, cells )
		].concat( notes ) );
	}


	function availCard( f ) {
		return el( 'li', { class: 'estga-item is-avail', 'data-key': f.key }, [
			el( 'span', { class: 'estga-item-name' }, [ f.label, el( 'span', { class: 'estga-sub-type', text: f.type + ' · #' + f.key } ) ] ),
			el( 'span', { class: 'estga-tools' }, [
				el( 'button', { type: 'button', class: 'estga-ibtn estga-ibtn-text is-primary', 'data-act': 'tocol', 'aria-label': t( 'addColumn', f.label ), text: t( 'toColumns' ), onclick: function () {
					state.cols.push( newColumn( f.key ) );
					change( f.key, 'up', t( 'added', f.label ) );
				} } )
			] )
		] );
	}

	function renderBuilder() {
		var cols = q( '[data-estga-zone="cols"]' );
		var avail = q( '[data-estga-zone="avail"]' );
		if ( ! cols ) {
			return;
		}
		if ( hasStatus() && ! state.cols.some( function ( c ) {
			return c.key === '_status';
		} ) ) {
			state.cols.push( { key: '_status', label: '', mode: 'default', width: '', sort: true, filter: false } );
		}
		cols.textContent = '';
		state.cols.forEach( function ( item, i ) {
			// no Status card when the status is shown above the actions.
			if ( item.key === '_status' && ( ! hasStatus() || input( 'status_place' ) !== 'column' ) ) {
				return;
			}
			if ( item.key.charAt( 0 ) === '_' || fields[ item.key ] ) {
				cols.appendChild( colCard( item, i ) );
			}
		} );
		if ( ! fieldCount() ) {
			cols.appendChild( el( 'li', { class: 'estga-empty', text: t( 'noColumns' ) } ) );
		}
		avail.textContent = '';
		var search = ( q( '[data-estga-search]' ) || {} ).value || '';
		search = search.trim().toLowerCase();
		// forms with hundreds of fields: the search also matches the field number (#12 or 12),
		// and at most 40 fields are drawn; the others are reached by searching.
		var taken = {};
		state.cols.concat( state.dets ).forEach( function ( c ) {
			taken[ c.key ] = true;
		} );
		var free = data.fields.filter( function ( f ) {
			return ! taken[ f.key ];
		} );
		// "#12": field 12 only; otherwise the text is looked for in the label, the type and the number.
		var exact = /^#\d+(\.\d+)?$/.test( search ) ? search.slice( 1 ) : '';
		var shown = free.filter( function ( f ) {
			if ( exact ) {
				return f.key === exact;
			}
			return ! search || f.key === search || ( f.label + ' ' + f.type + ' #' + f.key ).toLowerCase().indexOf( search ) !== -1;
		} );
		var limit = 40;
		var frag = document.createDocumentFragment();
		shown.slice( 0, limit ).forEach( function ( f ) {
			frag.appendChild( availCard( f ) );
		} );
		avail.appendChild( frag );
		if ( shown.length > limit ) {
			avail.appendChild( el( 'li', { class: 'estga-empty estga-more-fields', text: t( 'moreFields', String( shown.length - limit ) ) } ) );
		}
		if ( ! state.cols.some( function ( c ) {
			return c.key === '_date';
		} ) ) {
			avail.appendChild( el( 'li', { class: 'estga-item is-avail is-fixed', 'data-key': '_date' }, [
				el( 'span', { class: 'estga-item-name', text: data.fixed._date } ),
				el( 'button', { type: 'button', class: 'estga-ibtn estga-ibtn-text is-primary', 'data-act': 'tocol', text: t( 'addDate' ), onclick: function () {
					state.cols.push( { key: '_date', label: '', mode: 'default', width: '', sort: true, filter: false } );
					change( '_date', 'up', t( 'added', data.fixed._date ) );
				} } )
			] ) );
		}
		if ( ! free.length ) {
			avail.appendChild( el( 'li', { class: 'estga-empty', text: t( 'allUsed' ) } ) );
		} else if ( ! shown.length ) {
			avail.appendChild( el( 'li', { class: 'estga-empty', text: t( 'noField' ) } ) );
		}
		var count = q( '[data-estga-count]' );
		if ( count ) {
			count.textContent = t( 'count', fieldCount() );
		}
	}



	// ---------------------------------------------------------------- Shortcode composer.

	function composeShortcode() {
		var box = q( '[data-estga-sc]' );
		if ( ! box ) {
			return;
		}
		var val = function ( name ) {
			var node = box.querySelector( '[data-sc="' + name + '"]' );
			return node ? String( node.value || '' ).trim().replace( /["\[\]]/g, '' ) : '';
		};
		var parts = [ 'form="' + box.getAttribute( 'data-form' ) + '"' ];
		var user = val( 'user' );
		box.querySelectorAll( '[data-sc-show]' ).forEach( function ( node ) {
			node.hidden = node.getAttribute( 'data-sc-show' ) !== 'user=' + user;
		} );
		if ( user === 'current' ) {
			parts.push( 'user="current"' );
		} else if ( user === 'id' && /^\d+$/.test( val( 'user_id' ) ) ) {
			parts.push( 'user="' + val( 'user_id' ) + '"' );
		}
		[ 'per_page', 'page' ].forEach( function ( name ) {
			if ( val( name ) ) {
				parts.push( name + '="' + val( name ) + '"' );
			}
		} );
		var code = '[entrystage ' + parts.join( ' ' ) + ']';
		var out = box.querySelector( '[data-sc-out]' );
		var copy = box.querySelector( '[data-sc-copy]' );
		if ( out ) {
			out.textContent = code;
		}
		if ( copy ) {
			copy.setAttribute( 'data-estga-copy', code );
		}
	}


	// ---------------------------------------------------------------- Sync.

	function sync() {
		var cols = q( '[data-estga-columns]' );
		if ( cols ) {
			cols.value = JSON.stringify( state.cols.filter( function ( c ) {
				return c.key !== '_status' || hasStatus();
			} ) );
		}
		states();
	}

	function render() {
		renderBuilder();
		sync();
	}

	function init() {
		root = document.querySelector( '.estga' );
		var source = document.getElementById( 'estga-data' );
		if ( ! root || ! source ) {
			return;
		}
		try {
			data = JSON.parse( source.textContent );
		} catch ( e ) {
			return;
		}
		data.fields.forEach( function ( f ) {
			fields[ f.key ] = f;
		} );
		state.cols = ( data.columns || [] ).map( function ( c ) {
			return { key: String( c.key ), label: c.label || '', mode: c.mode || 'default', width: c.width || '', sort: !! c.sort, filter: !! c.filter, board: c.board !== false };
		} );

		root.addEventListener( 'click', function ( event ) {
			var target = event.target.closest ? event.target.closest( 'button' ) : null;
			if ( ! target || ! root.contains( target ) ) {
				return;
			}
			if ( target.hasAttribute( 'data-estga-go' ) ) {
				go( target.getAttribute( 'data-estga-go' ) );
			} else if ( target.hasAttribute( 'data-estga-copy' ) ) {
				var code = target.getAttribute( 'data-estga-copy' );
				var done = function () {
					target.textContent = t( 'copied' ) + ' ✓';
					window.setTimeout( function () {
						target.textContent = t( 'copy' );
					}, 2000 );
				};
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( code ).then( done, function () {} );
				}
			}
		} );
		root.addEventListener( 'change', function ( event ) {
			if ( event.target.hasAttribute( 'data-sc' ) ) {
				composeShortcode();
				return;
			}
			if ( event.target.hasAttribute( 'data-estga-input' ) || event.target.name === 'estg[access_roles][]' || event.target.id === 'estga-page' || event.target.id === 'estga-pdf' ) {
				dependents();
				sync();
			}
		} );
		root.addEventListener( 'input', function ( event ) {
			if ( event.target.hasAttribute( 'data-sc' ) ) {
				composeShortcode();
				return;
			}
			if ( event.target.hasAttribute( 'data-estga-search' ) ) {
				renderBuilder();
			} else if ( event.target.hasAttribute( 'data-estga-input' ) ) {
				sync();
			}
		} );

		// Banner about EntryStage Pro: shown unless hidden earlier in this browser.
		var proBanner = q( '[data-estga-pro]' );
		if ( proBanner ) {
			var proHidden = false;
			try {
				proHidden = window.localStorage.getItem( 'estga-pro-hidden' ) === '1';
			} catch ( e ) {}
			proBanner.hidden = proHidden;
			proBanner.addEventListener( 'click', function ( event ) {
				var button = event.target.closest ? event.target.closest( 'button' ) : null;
				if ( ! button ) {
					return;
				}
				if ( button.hasAttribute( 'data-estga-pro-hide' ) ) {
					proBanner.hidden = true;
					try {
						window.localStorage.setItem( 'estga-pro-hidden', '1' );
					} catch ( e ) {}
				} else if ( button.hasAttribute( 'data-estga-pro-toggle' ) ) {
					var open = button.getAttribute( 'aria-expanded' ) !== 'true';
					button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
					proBanner.classList.toggle( 'is-open', open );
					q( '#estga-pro-more' ).hidden = ! open;
				}
			} );
		}
		go( root.getAttribute( 'data-step' ) || 'access' );
		composeShortcode();
		dependents();
		render();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
