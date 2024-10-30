=== Ecster Pay for WooCommerce (deprecated) ===
Contributors: krokedil, niklashogefjord, slobodanmanic
Tags: ecommerce, e-commerce, woocommerce, ecster
Requires at least: 4.5
Tested up to: 5.2.2
WC requires at least: 3.0.0
WC tested up to: 3.7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: trunk

Ecster Pay for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via Ecster.


== DESCRIPTION ==
===Time to switch to Ecster Pay 2.0===
Ecster have launched a new and updated checkout - Ecster Pay 2.0. To be able to use the new platform you need to do two things:
* Get in touch with Ecster so they can help you activate Ecster Pay 2.0.
* Deactivate and remove this plugin and install the new plugin for Ecster Pay 2.0.

===Read more at Krokedil===
More information about the transition process and how to download and install the new plugin can be found at [krokedil.se/ecster](https://krokedil.se/ecster).

== INSTALLATION	 ==
1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your Ecster Pay settings.
6. Read more about the configuration process in the [plugin documentation](http://docs.krokedil.com/se/documentation/ecster-pay-woocommerce/).

== CHANGELOG ==

= 2020.03.30    - version 1.8.7 =
* Tweak         - Plugin name change to Ecster Pay for WooCommerce (deprecated).
* Tweak         - Added admin notice and settings page info about Ecster Pay 2.0.

= 2019.09.25    - version 1.8.6 =
* Enhancement   - Added support for updating WC order with shipping fee on OSN callback if needed.
* Enhancement   - Added support for updating WC order with invoice fee on OSN callback if needed.
* Enhancement   - Added logging for when WC order is updated on OSN callback.
* Enhancement   - Added logging for mismatch between WC order and Ecster order on OSN callback.

= 2019.08.26    - version 1.8.5 =
* Enhancement   - Add logging to customer authenticated ajax error.
* Tweak         - Added more accurate translation for "Ecster payment failed" string.
* Fix           - Fixed rounding issue on shipping unit amount.
* Fix           - Fixed typo on order note for when no Ecster order status detected.

= 2019.06.10    - version 1.8.4 =
* Enhancement   - Update missing customer information on callbacks. Prevents issues with Swish orders not being properly completed.

= 2019.04.26    - version 1.8.3 =
* Fix           - Fixed a compatibility error with older WooCommerce versions.

= 2019.04.26    - version 1.8.2 =
* Fix           - Fixed an error where double orders are created with WooCommerce 3.6.x due to changes in cart hash calculations.

= 2019.04.12    - version 1.8.1 =
* Tweak         - Prevent existing, not logged in customers to finalize purchases if guest checkout is not enabled.

= 2019.02.06    - version 1.8.0 =
* Feature       - Added support for order management in Ecsters system directly from WooCommerce.
* Feature       - Payment method selected by customer is now added to order notes sent to customer.
* Tweak         - Added filter wc_ecster_payment_method_title to allow overriding of payment method title.
* Tweak         - Renamed "Ecster invoice fee" to "Invoice fee" displayed in order lines.
* Fix           - Improved error handling in create & update ajax requests.

= 2018.03.20    - version 1.7.2 =
* Fix           - Improved Ecster purchase status check in thank you page to avoid changing WC order status to processing for unpaid orders.

= 2018.03.05 	- version 1.7.1 =
* Fix           - WC 3.3 js issue fix when switching from Ecster to other payment method.
* Fix           - Use $product->get_name() instead of sending all item variations to Ecster (WC 3.0+). Avoids issues with 3rd party plugins.
* Fix           - Flatsome CSS compatibility fix.

= 2018.02.12 	- version 1.7.0 =
* Tweak			- Check status via OSN for all orders 2 min after callback (scheduled via WP cron).
* Tweak			- Logging improvements.
* Tweak			- Confirmed support for WC 3.3.

= 2017.12.05 	- version 1.6.1 =
* Fix			- Instantiate order correct in ecster_thankyou() function. Caused orders to not be set to processing.

= 2017.12.04 	- version 1.6.0 =
* Feature       - Add order submission failure handling. Finalize order in WooCommerce even if checkout form submission wasn't successful.
* Tweak         - Improved logging.
* Fix           - Avoid trigger payment_complete() if customer reloads thank you page.

= 2017.11.13 	- version 1.5.1 =
* Fix			- Don't revert order status in callbacks from Ecster if order already has been set to Processing or Completed.
* Fix			- Control number of decimals in shipping sent to Ecster to avoid the checout not loading.
* Tweak			- Improved logging.

= 2017.08.25    - version 1.5.0 =
* Feature       - Added support for English language in checkout (the checkout language displayed is based on the current website language).

= 2017.06.30    - version 1.4.5 =
* Fix           - Rolled back the feature added in 1.4.4, order completed on fullyDelivered from Ecster.
* Fix           - Reimplemented the fix from version 1.4.3.

= 2017.06.15    - version 1.4.4 =
* Tweak         - Added order completed on fullyDelivered response from Ecster.

= 2017.06.13    - version 1.4.3 =
* Fix           - Moved where wc_get_order was in process_payment.

= 2017.06.10    - version 1.4.2 =
* Fix           - Added support for WC 3.0.
* Fix           - Fixed so multiple orders would not be created with invoice.
* Fix           - Fixed so the order cant be completed in checkout before the WC order number is updated in Ecsters system.
* Fix			- Remove duplicate line items in local order on checkout reload.

= 2017.05.14	- version 1.4.1 =
* Misc			- WC 3.0 compatible.
* Tweak			- Improved order status handling in OSN (server-to-server callback) from Ecster.
* Tweak			- Set order status to On hold in WooCommerce if Ecster report status as awaitingContract.
* Tweak			- Set Ecster as payment method in WooCommerce directly when local order is created.
* Tweak			- Moved payment_complete() to thank you page instead of in process_payment() function. WC 3.0 compat problem.
* Tweak			- Add order note about selected Ecster payment method to ecster_thankyou() instead of in OSN (to avoid adding it multiple times).
* Tweak     	- Removes 'required' from checkout fields if checking out with Ecster.

= 2017.02.13  	- version 1.4 =
* Feature		- Added support for displaying custom checkout fields when Ecster is the selected payment method. 
* Tweak 		- Introduced filters wc_ecster_move_checkout_fields & wc_ecster_move_checkout_fields_origin.
* Tweak			- Use firstName & lastName sent from Ecster (instead of name) to populate customer name in WC. 
* Fix			- OSN callback improvements.

= 2017.01.03  	- version 1.3.3 =
* Fix			- Better handling of different name formats returned in OSN callback from Ecster. Ecster return names in different formats depending on how the customer authenticates.
* Fix			- Fixes so addresses & internal/external reference is updated/stored correctly in OSN callback.

= 2017.01.02 	- version 1.3.2 =
* Update		- Remove changing of WC order status to 'Failed' on onPaymentDenied() & onPaymentFailure() events from Ecster. These events does not mean that the entire order has failed, just that one payment method has been denied in the Ecster iframe.
* Update		- Added setting for overriding 'Select another payment method' button text.
* Update		- Translation updates.
* Update		- Added link to documentation in settings page.

= 2016.12.20    - version 1.3.1 =
* Fix           - Fixes checkout issue when store is only selling to one country.

= 2016.12.19  	- version 1.3 =
* Update        - Improved handling of Ecster API response.
* Fix           - Stores billing and shipping address into order in OSN listener callback.
* Fix           - Sends correct SKU to Ecster if product is variation.
* Fix           - Changes how coupons are sent to Ecster (to each order line, instead of as separate order line).

= 2016.12.17  	- version 1.2.1 =
* Fix       	- Fixed Internet Explorer bug where redirect to order confirmation page (on onPaymentSuccess) didn’t work.
* Update		- Added logging in OSN listener (server to server callback from Ecster to WooCommerce).

= 2016.12.13    - version 1.2 =
* Fix           - Fixes error with WooCommerce order not being created in some cases
* Update        - Adds compatibility with Sequential Order Numbers and Sequential Order Numbers Pro.
* Update        - Sends WooCommerce order number to Ecster as externalReference.
* Update        - Adds product variations to Ecster.

= 2016.11.17  	- version 1.1.1 =
* Fix           - Fixes the issue with some Ecster invoice fee not being added to some orders.

= 2016.10.20  	- version 1.1 =
* Fix       	- Changes how invoice fee is added to WooCommerce order.
* Fix       	- Improves phone number handling when "Ange uppgifter själv" is used.

= 2016.09.25	- version 1.0 =
* Initial release.