/* global jQuery, wc_ecster, EcsterPay, console */
(function ($) {
	'use strict';
	var wc_ecster_initialized = false;
	var wc_ecster_cart_key = null;
	var wc_ecster_on_customer_authenticated_data = false;
	var wc_ecster_on_changed_delivery_address_data = false;
	var doing_wc_ecster_on_customer_authenticated = false;
	var doing_wc_ecster_on_changed_delivery_address = false;

	var wc_ecster_body_class = function wc_ecster_body_class() {
		if ("ecster" === $("input[name='payment_method']:checked").val()) {
			$("body").addClass("ecster-selected").removeClass("ecster-deselected");
		} else {
			$("body").removeClass("ecster-selected").addClass("ecster-deselected");
		}
	};

	var wc_ecster_add_container = function wc_ecster_add_container() {
		if (!$("#ecster-pay-ctr").length) {
			// Add the element
			$('form.woocommerce-checkout').after('<div id="ecster-pay-ctr"></div>');
		}
	};

	var wc_ecster_create_cart = function wc_ecster_create_cart() {
		$.ajax(
			wc_ecster.ajaxurl,
			{
				type: "POST",
				dataType: "json",
				async: true,
				data: {
					action: "wc_ecster_create_cart",
					nonce: wc_ecster.wc_ecster_nonce
				},
				success: function (response) {
					if (response.success && response.data.wc_ecster_cart_key) {
						wc_ecster_cart_key = response.data.wc_ecster_cart_key;
						wc_ecster_add_container();

                        /*$('#billing_first_name, #billing_last_name, #billing_company, #billing_email, #billing_phone, #billing_country, #billing_address_1, #billing_address_2, #billing_postcode, #billing_city').val('');
                        $('#shipping_first_name, #shipping_last_name, #shipping_company, #shipping_country, #shipping_address_1, #shipping_address_2, #shipping_postcode, #shipping_city').val('');
*/

                        // Check if Ecster is selected, Ecster library loaded and Ecster container exists
						if ("ecster" === $("input[name='payment_method']:checked").val() && typeof window.EcsterPay === "object" && $("#ecster-pay-ctr").length) {
							if (null !== wc_ecster_cart_key) {
							    EcsterPay.start({
									cartKey: wc_ecster_cart_key, // from create cart REST call
									shopTermsUrl: wc_ecster.terms,
									showCart: false,
									showPaymentResult: false,
									onCheckoutStartInit: function () {
										$("#order_review").block({
											message: null,
											overlayCSS: {
												background: "#fff",
												opacity: 0.6
											}
										});
									},
									onCheckoutStartSuccess: function () {
										$("#order_review").unblock();
										wc_ecster_initialized = true; // Mark Ecster as initialized on success
									},
									onCheckoutStartFailure: function (failureData) {
										$("#order_review").unblock();
									},
									onCheckoutUpdateInit: function () {
										$("#order_review").block({
											message: null,
											overlayCSS: {
												background: "#fff",
												opacity: 0.6
											}
										});
									},
									onCheckoutUpdateSuccess: function () {
										$("#order_review").unblock();
                                        $('#ecster-pay-ctr').unblock();
									},
									onCheckoutUpdateFailure: function () {
										$("#order_review").unblock();
									},
									onCustomerAuthenticated: function (authenticatedData) {
										if( false === doing_wc_ecster_on_changed_delivery_address ) {
                                        	wc_ecster_on_customer_authenticated_data = authenticatedData.customer;
											wc_ecster_on_customer_authenticated(authenticatedData.customer);
										}
									},
									onChangedDeliveryMethod: function (newDeliveryMethod) {
									},
									onChangedDeliveryAddress: function (newDeliveryAddress) {
										if( false === doing_wc_ecster_on_customer_authenticated ) {
											wc_ecster_on_changed_delivery_address_data = newDeliveryAddress;
                                        	wc_ecster_on_changed_delivery_address(newDeliveryAddress);
										}
									},
									onPaymentSuccess: function (paymentData) {
                                        wc_ecster_on_payment_success(paymentData);
									},
									onPaymentFailure: function () {
										wc_ecster_fail_local_order('failed');
									},
									onPaymentDenied: function (deniedData) {
										wc_ecster_fail_local_order('denied');
									}
								});
							}
						}
					} else {
						wc_ecster_add_container();
						$('#ecster-pay-ctr').html('<div class="woocommerce-error" id="wc-ecster-api-error">' + response.data.error_message + '</div>');
					}
				}
			}
		);
	};

	var wc_ecster_update_cart = function wc_ecster_update_cart() {
		var updated_cart_callback = EcsterPay.updateCart(wc_ecster_cart_key);

		$.ajax(
			wc_ecster.ajaxurl,
			{
				type: "POST",
				dataType: "json",
				async: true,
				data: {
					action: "wc_ecster_update_cart",
					cart_key: wc_ecster_cart_key,
					nonce: wc_ecster.wc_ecster_nonce
				},
				success: function (response) {
					if (response.success && response.data.wc_ecster_cart_key) {
						updated_cart_callback(response.data.wc_ecster_cart_key);
						wc_ecster_cart_key = response.data.wc_ecster_cart_key;
					} else {
						wc_ecster_add_container();
						$("#ecster-pay-ctr").html('<div class="woocommerce-error" id="wc-ecster-api-error">' + response.data.error_message + '</div>');
					}
				}
			}
		);
	};

	var wc_ecster_fail_local_order = function wc_ecster_fail_local_order(reason) {
		$.ajax(
			wc_ecster.ajaxurl,
			{
				type: "POST",
				dataType: "json",
				async: true,
				data: {
					action: "wc_ecster_fail_local_order",
					reason: reason,
					nonce: wc_ecster.wc_ecster_nonce
				}
			}
		);
	};

	// Initializes Ecster in checkout page
	// Triggered when page is first loaded, if Ecster is selected or when payment method is
	// changed to Ecster for the first time
	var wc_ecster_init = function wc_ecster_init() {
		wc_ecster_create_cart();
	};

    // on customer authentication
    var wc_ecster_on_customer_authenticated = function wc_ecster_on_customer_authenticated(customer_data) {

		// Set a flag to let other functions know we're doing customer authenticated now.
		doing_wc_ecster_on_customer_authenticated = true;
	
        $('#ecster-pay-ctr').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
        $.ajax(
            wc_ecster.ajaxurl,
            {
                type:     "POST",
                dataType: "json",
                async:    true,
                data: {
                    action:        "wc_ecster_on_customer_authenticated",
                    customer_data: customer_data,
                    nonce:         wc_ecster.wc_ecster_nonce
                },
                success: function(response) {
                    var customerAuthCountry;

                    if (customer_data.countryCode) {
                        customerAuthCountry = customer_data.countryCode;
                    } else {
                        customerAuthCountry = 'SE';
                    }

                    // Update country and ZIP, so shipping can be calculated on update_checkout
                    $("form.checkout #ship-to-different-address-checkbox").prop("checked", false);
                    $("form.checkout #billing_country").val(customerAuthCountry);
                    $("form.checkout #billing_postcode").val(customer_data.zip);
					console.log('Customer authenticated sucess');
					console.log(response);
					
					if( 'yes' == response.data.mustLogin ) {
						// Customer might need to login.
						var $form = $( 'form.checkout' );
						$form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview"><ul class="woocommerce-error" role="alert"><li>' + response.data.mustLoginMessage + '</li></ul></div>' );
						var etop = $('form.checkout').offset().top;
						$('html, body').animate({
							scrollTop: etop
						  }, 1000);
					} else {
						$("body").trigger("update_checkout");
					}
				},
				error: function(response) {
					// Log the error.
					console.log('Customer authenticated error');
					console.log(response);

				},
				complete: function() {
					// Remove the flag.
					doing_wc_ecster_on_customer_authenticated = false;
				}
            }
        );
    };

    // on changed delivery address
    var wc_ecster_on_changed_delivery_address = function wc_ecster_on_changed_delivery_address(delivery_address) {
		
		// Set a flag to let other functions know we're doing changed delivery address now.
		doing_wc_ecster_on_changed_delivery_address = true;

		$.ajax(
            wc_ecster.ajaxurl,
            {
                type:     "POST",
                dataType: "json",
                async:    true,
                data: {
                    action:           "wc_ecster_on_changed_delivery_address",
                    delivery_address: delivery_address,
                    nonce:            wc_ecster.wc_ecster_nonce
                },
                success: function() {
                    var customerDeliveryCountry;

                    if (delivery_address.countryCode) {
                        customerDeliveryCountry = delivery_address.countryCode;
                    } else {
                        customerDeliveryCountry = 'SE';
                    }
                    
                    if (wc_ecster_on_customer_authenticated_data) { // If authentication is done
                        // Update country and ZIP, so shipping can be calculated on update_checkout
                        $("form.checkout #ship-to-different-address-checkbox").prop("checked", true);
                        $("form.checkout #shipping_country").val(customerDeliveryCountry);
                        $("form.checkout #shipping_postcode").val(delivery_address.zip);
                    } else {
                        // Update country and ZIP, so shipping can be calculated on update_checkout
                        $("form.checkout #ship-to-different-address-checkbox").prop("checked", false);
                        $("form.checkout #billing_country").val(customerDeliveryCountry);
                        $("form.checkout #billing_postcode").val(delivery_address.zip);
                    }

                    $("body").trigger("update_checkout");
				},
				complete: function() {
					// Remove the flag.
					doing_wc_ecster_on_changed_delivery_address = false;
				}
            }
        );
    };
	
    // on Ecster payment success
    var wc_ecster_on_payment_success = function wc_ecster_on_payment_success(paymentData) {
        // Block the iframe until page reloads
        $("#ecster-pay-ctr").block({
            message: null,
            overlayCSS: {
                background: "#fff",
                opacity: 0.6
            }
        });

        // Also block the order review
        $("#order_review").block({
            message: null,
            overlayCSS: {
                background: "#fff",
                opacity: 0.6
            }
        });

        // Update ongoing order cart hash
        // Add invoice fee, if needed
        $.ajax(
            wc_ecster.ajaxurl,
            {
                type: "POST",
                dataType: "json",
                async: true,
                data: {
                    action:       "wc_ecster_on_payment_success",
                    payment_data: paymentData,
                    nonce:        wc_ecster.wc_ecster_nonce
                },
                success: function() {
                    // Clear WooCommerce checkout form
                    $('#ship-to-different-address-checkbox').prop('checked', true);

                    // Populate the form and submit it.
                    var customerCountry;
                    var customerPhone;

                    if (paymentData.customer.countryCode) {
                        customerCountry = paymentData.customer.countryCode;
                    } else {
                        customerCountry = 'SE';
                    }

                    if (paymentData.customer.cellular.indexOf("*") > -1) {
                        customerPhone = '0';
                    } else {
                        customerPhone = paymentData.customer.cellular;
                    }

                    $("form.checkout #billing_first_name").val(paymentData.customer.firstName);
                    $("form.checkout #billing_last_name").val(paymentData.customer.lastName);
                    $("form.checkout #billing_email").val(paymentData.customer.email);
                    $("form.checkout #billing_country").val(customerCountry);
                    $("form.checkout #billing_address_1").val(paymentData.customer.address);
                    $("form.checkout #billing_city").val(paymentData.customer.city);
                    $("form.checkout #billing_postcode").val(paymentData.customer.zip);
                    $("form.checkout #billing_phone").val(customerPhone);

                    // Check if there's separate shipping address
                    if (paymentData.recipient) {
                        $("form.checkout #shipping_first_name").val(paymentData.recipient.firstName);
                        $("form.checkout #shipping_last_name").val(paymentData.recipient.lastName);
                        $("form.checkout #shipping_country").val(paymentData.recipient.countryCode);
                        $("form.checkout #shipping_address_1").val(paymentData.recipient.address);
                        $("form.checkout #shipping_city").val(paymentData.recipient.city);
                        $("form.checkout #shipping_postcode").val(paymentData.recipient.zip);
                    } else {
                        $("form.checkout #ship-to-different-address-checkbox").prop("checked", false);
                    }

                    // Check Terms checkbox, if it exists
                    if ($("form.checkout #terms").length > 0) {
                        $("form.checkout #terms").prop("checked", true);
                    }

                    $("#place_order").trigger("submit");
                }
            }
        );
    };

        // Set body class when DOM is ready
	$(document).ready(function () {
		wc_ecster_body_class();
	});

	// When payment method is changed:
	//
	// - Change body class (CSS uses body class to hide and show elements)
	// - If changing to Ecster trigger update_checkout
	$(document.body).on("change", "input[name='payment_method']", function (event) {
		wc_ecster_body_class();

		// If switching to Ecster, update checkout
		if ("ecster" === event.target.value) {
			$("body").trigger("update_checkout");
		}
	});

	// When checkout gets updated
	//
	// If Ecster is the selected method and Ecster has not been initialized, initialize it
	// If Ecster is the selected method and Ecster has been initialized, update cart
	$(document.body).on("updated_checkout", function () {
		if ("ecster" === $("input[name='payment_method']:checked").val()) {
			if (!wc_ecster_initialized) {
				wc_ecster_init();
			} else {
				wc_ecster_update_cart();
			}

			// Remove the "choose another payment method" link to prevent duplication
			$('form.woocommerce-checkout .ecster-pay-choose-other').remove();

			// Move order review table
			$('table.woocommerce-checkout-review-order-table').addClass('ecster-pay-cart').detach().appendTo('form.woocommerce-checkout');
            $( document.body ).trigger( 'ecster_move_order_review' );

			// Move order notes
			$('#order_comments_field').addClass('ecster-pay-order-notes').detach().appendTo('form.woocommerce-checkout');

			// Move CSS id's and/or classes defined via the filter wc_ecster_move_checkout_fields
			var extra_div_counter = 0;
			$.each(wc_ecster.move_checkout_fields, function (index, value) {
				extra_div_counter ++;
				$( value ).addClass('ecster-pay-moved-div-'+extra_div_counter).detach().appendTo('form.woocommerce-checkout');
			});

			// Add "choose another payment method" link
			if ($("input[name='payment_method']").length > 1) {
				$('<p><a href="#" class="button">' + wc_ecster.select_another_method_text + '</a></p>').appendTo('form.woocommerce-checkout').addClass('ecster-pay-choose-other');
			}
		}
	});

	$(document.body).on('click', '.ecster-pay-choose-other a', function (e) {
		e.preventDefault();

		$('.ecster-pay-cart').detach().prependTo('#order_review');
		$('.ecster-pay-order-notes').detach().appendTo('.woocommerce-shipping-fields');
		
		// Move CSS id's and/or classes (defined in wc_ecster_move_checkout_fields) back to their origial place.
		// Original place defaults to .woocommerce-shipping-fields (defined via filter wc_ecster_move_checkout_fields_origin).
		var extra_div_counter = 0;
		$.each(wc_ecster.move_checkout_fields, function (index, value) {
			extra_div_counter ++;
			$('.ecster-pay-moved-div-'+extra_div_counter).detach().appendTo(wc_ecster.move_checkout_fields_origin);
		});
		
		$('.ecster-pay-choose-other').remove();

		// Deselect Ecster and select the first available non-Ecster method
		$("input[name='payment_method']:checked").prop('checked', false);
		if ("ecster" === $("input[name='payment_method']:eq(0)").val()) {
			$("input[name='payment_method']:eq(1)").prop("checked", true);
		} else {
			$("input[name='payment_method']:eq(0)").prop("checked", true);
		}
		wc_ecster_body_class();
	});
	
	
	// When WooCommerce checkout submission fails
	$(document.body).on("checkout_error", function () {
		if ("ecster" === $("input[name='payment_method']:checked").val()) {
			
			$.ajax(
	            wc_ecster.ajaxurl,
	            {
	                type: "POST",
	                dataType: "json",
	                async: true,
	                data: {
		                cart_key: 	wc_ecster_cart_key,
	                    action:		"wc_ecster_on_checkout_error",
	                    nonce:		wc_ecster.wc_ecster_nonce
	                },
	                success: function (data) {
					},
					error: function (data) {
					},
					complete: function (data) {
						console.log('ecster checkout error');
						console.log(data.responseJSON);
						window.location.href = data.responseJSON.data.redirect;
					}
	            }
	        );
			
		}
	});
}(jQuery));
