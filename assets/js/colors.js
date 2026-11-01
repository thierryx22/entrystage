/* EntryStage — colour pickers of the plugin settings (WordPress colour picker). */
( function ( $ ) {
	'use strict';
	$( function () {
		// a click on the preview of a style picks that style
		$( document ).on( 'click', '.estg-preview[data-estg-style]', function () {
			var style = $( this ).attr( 'data-estg-style' );
			$( 'input[name="_gform_setting_style"][value="' + style + '"]' ).prop( 'checked', true ).trigger( 'change' );
			$( '.estg-preview' ).removeClass( 'is-current' );
			$( this ).addClass( 'is-current' );
		} );
		// the frame follows the checked style: on a change, on load (right after a save the page is built
		// before the new choice is stored) and when the browser restores the form from its cache
		var syncPreview = function () {
			var checked = $( 'input[name="_gform_setting_style"]:checked' );
			if ( checked.length ) {
				$( '.estg-preview' ).removeClass( 'is-current' ).filter( '[data-estg-style="' + checked.val() + '"]' ).addClass( 'is-current' );
			}
		};
		$( document ).on( 'change', 'input[name="_gform_setting_style"]', syncPreview );
		syncPreview();
		$( window ).on( 'pageshow', syncPreview );
		$( 'input.estg-color-field' ).each( function () {
			var input = $( this );
			input.wpColorPicker( { defaultColor: input.attr( 'placeholder' ) || false } );
		} );
	} );
}( window.jQuery ) );
