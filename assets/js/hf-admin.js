;( function( $ ) {

	'use strict';

	var HFEAdmin = {

		/**
		 * Start the engine.
		 *
		 * @since 1.3.9
		 */
		_init: function() {

			var ehf_hide_shortcode_field = function() {
				var selected = $('#ehf_template_type').val() || 'none';
				$( '.hfe-options-table' ).removeClass().addClass( 'hfe-options-table widefat hfe-selected-template-type-' + selected );
			}

			var $document = $( document );
		
			$document.on( 'change', '#ehf_template_type', function( e ) {
				ehf_hide_shortcode_field();
			});
		
			ehf_hide_shortcode_field();
			
		},
	};

	$( document ).ready( function( e ) {
		HFEAdmin._init();
	});

	window.HFEAdmin = HFEAdmin;

} )( jQuery );

