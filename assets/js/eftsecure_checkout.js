jQuery( function( $ ) {
	'use strict';

	var wc_eftsecure_form = {

		init: function( form ) {
			eftSec.checkout.settings.serviceUrl = "{protocol}://eftsecure.callpay.com/eft";
			this.form          = form;
			this.eftsecure_submit = false;

			$( this.form )
				.on( 'click', '#place_order', this.onSubmit )
				.on( 'submit checkout_place_order_eftsecure' );

			$( document.body ).on( 'checkout_error', this.resetModal );
		},

        validates: function() {

            var $required_inputs;

            if ( $( '#createaccount' ).is( ':checked' ) && $( '#account_password' ).length && $( '#account_password' ).val() === '' ) {
                return false;
            }

            // check to see if we need to validate shipping address
            if ( $( '#ship-to-different-address-checkbox' ).is( ':checked' ) ) {
                $required_inputs = $( '.woocommerce-billing-fields .validate-required, .woocommerce-shipping-fields .validate-required' );
            } else {
                $required_inputs = $( '.woocommerce-billing-fields .validate-required' );
            }

            if ( $required_inputs.length ) {
                var required_error = false;

                $required_inputs.each( function() {
                    if ( $( this ).find( 'input.input-text, select' ).not( $( '#account_password, #account_username' ) ).val() === '' ) {
                        required_error = true;
                    }
                });

                if ( required_error ) {
                    return false;
                }
            }

            return true;
        },

		isEftsecureChosen: function() {
			return $( '#payment_method_eftsecure' ).is( ':checked' );
		},

		isEftsecureModalNeeded: function( e ) {
			// Don't affect submit if modal is not needed.
			if (!wc_eftsecure_form.isEftsecureChosen() || !wc_eftsecure_form.validates()) {
				return false;
			}
            // Don't affect submit if payment already complete.
			if (wc_eftsecure_form.eftsecure_submit) {
				return false;
			}
			return true;
		},

		block: function() {
			wc_eftsecure_form.form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			wc_eftsecure_form.form.unblock();
		},

		onClose: function() {
			wc_eftsecure_form.unblock();
		},

		onSubmit: function( e ) {

			if ( wc_eftsecure_form.isEftsecureModalNeeded()) {
				var $data = jQuery('#eftsecure-payment-data');
				e.preventDefault();

				wc_eftsecure_form.block();
				eftSec.checkout.init({
					organisation_id: wc_eftsecure_params.organisation_id,
					token: wc_eftsecure_params.token,
					reference: wc_eftsecure_params.reference,
					primaryColor: wc_eftsecure_params.pcolor,
					secondaryColor: wc_eftsecure_params.scolor,
					amount: ($data.data('amount')).toFixed( 2 ) ,
                    onLoad: function() {
						wc_eftsecure_form.unblock();
					},
                    onComplete: function(data) {
                        eftSec.checkout.hideFrame();
                        console.log('Transaction Completed');
                        wc_eftsecure_form.eftsecure_submit = true;
                        var $form = wc_eftsecure_form.form;
                        if ($form.find( 'input.eftsecure_transaction_id' ).length > 0) {
                            $form.find('input.eftsecure_transaction_id').remove();
                        }
                        $form.append( '<input type="hidden" class="eftsecure_transaction_id" name="eftsecure_transaction_id" value="' + data.transaction_id + '"/>' );
                        $form.submit();
                    }
				});

				return false;
			}

			return true;
		},

		resetModal: function() {
			if (wc_eftsecure_form.form.find( 'input.eftsecure_transaction_id' ).length > 0) {
                wc_eftsecure_form.form.find('input.eftsecure_transaction_id').remove();
            }
			wc_eftsecure_form.eftsecure_submit = false;
		}
	};

	wc_eftsecure_form.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
} );
