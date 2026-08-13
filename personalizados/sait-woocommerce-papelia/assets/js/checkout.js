(function($) {
	'use strict';

	var validating = false;

	function selectedShippingMethod() {
		return $('input[name^="shipping_method"]:checked').val() || '';
	}

	function togglePickupFields() {
		var pickup = selectedShippingMethod() === saitPapeliaCheckout.shippingMethod;
		var $phone = $('#billing_phone');
		var $addressFields = $('#billing_address_1_field, #billing_address_2_field, #billing_postcode_field, #billing_city_field, #billing_state_field, #billing_country_field');

		$('.sait-papelia-branch-wrapper').toggle(pickup);
		$('#shipping_address, .woocommerce-shipping-fields').toggle(!pickup);
		$addressFields.toggle(!pickup);
		$phone.prop('required', pickup);
	}

	function addMissingStockFlag() {
		var $form = $('form.checkout');
		$form.find('input[name="sait_papelia_missing_stock"]').remove();
		$('<input>', {
			type: 'hidden',
			name: 'sait_papelia_missing_stock',
			value: '1'
		}).appendTo($form);
	}

	function submitWithoutRevalidation() {
		$('form.checkout').off('checkout_place_order.saitPapelia').trigger('submit');
	}

	$(document.body)
		.on('change', 'input[name^="shipping_method"]', function() {
			togglePickupFields();
			$(document.body).trigger('update_checkout');
		})
		.on('updated_checkout', togglePickupFields);

	$('form.checkout').on('checkout_place_order.saitPapelia', function() {
		var branchId = $('#sait_papelia_branch').val();
		if (validating || selectedShippingMethod() !== saitPapeliaCheckout.shippingMethod || !branchId) {
			return true;
		}

		validating = true;
		$.ajax({
			url: saitPapeliaCheckout.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'sait_papelia_validate_stock',
				nonce: saitPapeliaCheckout.nonce,
				branchId: branchId
			}
		}).done(function(response) {
			var missing = response.success && response.data ? response.data.missingStock || [] : [];
			if (missing.length === 0) {
				submitWithoutRevalidation();
				return;
			}

			var list = missing.map(function(name) { return '• ' + name; }).join('\n');
			var message = saitPapeliaCheckout.warningTitle + '\n\n' + list + '\n\n' + saitPapeliaCheckout.warningBody;
			if (window.confirm(message)) {
				addMissingStockFlag();
				submitWithoutRevalidation();
			} else {
				validating = false;
				window.location.href = saitPapeliaCheckout.cartUrl;
			}
		}).fail(function() {
			validating = false;
			window.alert(saitPapeliaCheckout.errorMessage);
		});

		return false;
	});

	togglePickupFields();
})(jQuery);
