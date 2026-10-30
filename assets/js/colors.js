/* EntryStage — colour pickers of the plugin settings (WordPress colour picker). */
( function ( $ ) {
	'use strict';
	$( function () {
		$( 'input.estg-color-field' ).each( function () {
			var input = $( this );
			input.wpColorPicker( { defaultColor: input.attr( 'placeholder' ) || false } );
		} );
	} );
}( window.jQuery ) );
