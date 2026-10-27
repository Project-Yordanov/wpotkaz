/**
 * OtkazPro – "Prepare refund": opens the WooCommerce refund form and fills in the
 * quantities of the withdrawal statement. The amount is calculated by WooCommerce;
 * the shop reviews it and confirms the refund itself.
 */
( function ( $ ) {
	'use strict';

	function prefill( $button ) {
		var $box = $( '#woocommerce-order-items' );
		var $open = $box.find( 'button.refund-items' ).first();
		if ( ! $open.length ) {
			return false;
		}
		$open.trigger( 'click' );

		$.each( $button.data( 'items' ) || {}, function ( itemId, qty ) {
			var $input = $box.find( 'tr.item[data-order_item_id="' + parseInt( itemId, 10 ) + '"] input.refund_order_item_qty' );
			if ( $input.length ) {
				var max = parseInt( $input.attr( 'max' ), 10 );
				$input.val( max ? Math.min( qty, max ) : qty ).trigger( 'change' );
			}
		} );

		var reason = $button.data( 'reason' );
		if ( reason && ! $( '#refund_reason' ).val() ) {
			$( '#refund_reason' ).val( reason );
		}
		$( 'html, body' ).animate( { scrollTop: $box.offset().top - 40 }, 200 );
		return true;
	}

	$( document ).on( 'click', '.otkazpro-prepare-refund', function ( event ) {
		if ( prefill( $( this ) ) ) {
			event.preventDefault();
		}
	} );

	// Opened from the withdrawal register: ?otkazpro_refund=<statement ID>.
	$( function () {
		var match = /[?&]otkazpro_refund=(\d+)/.exec( window.location.search );
		if ( match ) {
			var $button = $( '.otkazpro-prepare-refund[data-otkazpro-refund="' + match[1] + '"]' ).first();
			if ( $button.length ) {
				prefill( $button );
			}
		}
	} );
} )( jQuery );
