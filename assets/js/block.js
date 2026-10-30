/* EntryStage — "EntryStage list" block: settings in the sidebar, preview drawn by the server. No build step. */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var c = wp.components;
	var be = wp.blockEditor;
	var ServerSideRender = wp.serverSideRender;
	var data = window.estgBlock || { forms: [], i18n: {} };
	var t = function ( key ) {
		return data.i18n[ key ] || key;
	};

	function formOptions() {
		var options = [ { value: '0', label: t( 'choose' ) } ];
		data.forms.forEach( function ( f ) {
			options.push( { value: String( f.id ), label: f.title + ' (#' + f.id + ')' + ( f.enabled ? '' : ' ' + t( 'off' ) ) } );
		} );
		return options;
	}

	function findForm( id ) {
		for ( var i = 0; i < data.forms.length; i++ ) {
			if ( data.forms[ i ].id === id ) {
				return data.forms[ i ];
			}
		}
		return null;
	}

	function userMode( user ) {
		if ( user === 'current' ) {
			return 'current';
		}
		return user ? 'one' : '';
	}

	function formPicker( props ) {
		return el( c.SelectControl, {
			label: t( 'form' ),
			value: String( props.attributes.form || 0 ),
			options: formOptions(),
			onChange: function ( v ) {
				props.setAttributes( { form: parseInt( v, 10 ) || 0 } );
			},
		} );
	}

	function isMulti( a ) {
		return !! ( a.forms && a.forms.length ) || !! a.multi;
	}

	function formChecks( props ) {
		var a = props.attributes;
		var chosen = a.forms || [];
		return el(
			'fieldset',
			{ className: 'estg-block-forms', style: { margin: '0 0 16px', padding: 0, border: 0 } },
			el( 'legend', { className: 'components-base-control__label' }, t( 'tabs' ) ),
			data.forms.filter( function ( f ) {
				return f.enabled;
			} ).map( function ( f ) {
				return el( c.CheckboxControl, {
					key: f.id,
					label: f.title + ' (#' + f.id + ')',
					checked: chosen.indexOf( f.id ) !== -1,
					onChange: function ( on ) {
						var next = chosen.filter( function ( id ) {
							return id !== f.id;
						} );
						if ( on ) {
							next.push( f.id );
						}
						props.setAttributes( { forms: next } );
					},
				} );
			} )
		);
	}

	function sidebar( props ) {
		var a = props.attributes;
		var mode = userMode( a.user );
		var multi = false;
		multi = isMulti( a );
		return el(
			be.InspectorControls,
			null,
			el(
				c.PanelBody,
				{ title: t( 'settings' ), initialOpen: true },
				el( c.ToggleControl, {
					label: t( 'multi' ),
					help: t( 'multiHelp' ),
					checked: multi,
					onChange: function ( on ) {
						props.setAttributes( on ? { multi: true, forms: a.form ? [ a.form ] : [] } : { multi: false, forms: [] } );
					},
				} ),
				multi ? formChecks( props ) : null,
				multi ? null : formPicker( props ),
				el( c.SelectControl, {
					label: t( 'entries' ),
					value: mode,
					options: [
						{ value: '', label: t( 'asSet' ) },
						{ value: 'current', label: t( 'current' ) },
						{ value: 'one', label: t( 'oneUser' ) },
					],
					onChange: function ( v ) {
						props.setAttributes( { user: v === 'current' ? 'current' : ( v === 'one' ? '1' : '' ) } );
					},
				} ),
				mode === 'one'
					? el( c.TextControl, {
							label: t( 'userId' ),
							type: 'number',
							min: 1,
							value: a.user,
							onChange: function ( v ) {
								var n = parseInt( v, 10 );
								props.setAttributes( { user: n > 0 ? String( n ) : '1' } );
							},
					  } )
					: null,
				el( c.TextControl, {
					label: t( 'perPage' ),
					help: t( 'perPageHelp' ),
					type: 'number',
					min: 1,
					max: 100,
					value: a.perPage ? String( a.perPage ) : '',
					onChange: function ( v ) {
						var n = parseInt( v, 10 );
						props.setAttributes( { perPage: n > 0 ? Math.min( n, 100 ) : 0 } );
					},
				} ),
				multi ? null : el( c.TextControl, {
					label: t( 'page' ),
					help: t( 'pageHelp' ),
					value: a.page,
					onChange: function ( v ) {
						props.setAttributes( { page: v } );
					},
				} ),
				el( 'p', { className: 'components-base-control__help' }, t( 'more' ) )
			)
		);
	}

	wp.blocks.registerBlockType( 'entrystage/list', {
		apiVersion: 2,
		title: t( 'title' ),
		edit: function ( props ) {
			var blockProps = be.useBlockProps ? be.useBlockProps() : {};
			var form = findForm( props.attributes.form );
			var body;
			if ( isMulti( props.attributes ) ) {
				body = props.attributes.forms && props.attributes.forms.length
					? el( c.Disabled, null, el( ServerSideRender, { block: 'entrystage/list', attributes: props.attributes } ) )
					: el( c.Placeholder, { icon: 'list-view', label: t( 'title' ) }, el( 'p', null, t( 'chooseMany' ) ) );
				return el( Fragment, null, sidebar( props ), el( 'div', blockProps, body ) );
			}
			if ( ! data.forms.length ) {
				body = el( c.Placeholder, { icon: 'list-view', label: t( 'title' ) }, t( 'none' ) );
			} else if ( ! form ) {
				body = el( c.Placeholder, { icon: 'list-view', label: t( 'title' ) }, formPicker( props ) );
			} else if ( ! form.enabled ) {
				body = el( c.Placeholder, { icon: 'list-view', label: t( 'title' ) }, el( 'p', null, t( 'offHelp' ) ) );
			} else {
				// The preview is not clickable: buttons and links work on the site.
				body = el( c.Disabled, null, el( ServerSideRender, { block: 'entrystage/list', attributes: props.attributes } ) );
			}
			return el( Fragment, null, sidebar( props ), el( 'div', blockProps, body ) );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
