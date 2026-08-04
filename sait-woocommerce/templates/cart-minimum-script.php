<script type="text/javascript">
jQuery(function($) {
	function bloquearSiNoCumple() {
		var totalText = $("tr.cart-subtotal td .woocommerce-Price-amount bdi, td[data-title='Subtotal'] bdi").last().text();
		if (!totalText) {
			return;
		}

		var total = parseFloat(totalText.replace(/[^0-9.,]/g, '').replace(',', ''));
		var minimo = <?php echo wp_json_encode($minimum); ?>;
		var buttons = $('.checkout-button, #place_order, .wc-proceed-to-checkout a, .paypal-button, .wc-stripe-checkout-button');

		if (total < minimo) {
			buttons.prop('disabled', true).css({'opacity': '0.5', 'pointer-events': 'none'});
		} else {
			buttons.prop('disabled', false).css({'opacity': '1', 'pointer-events': 'auto'});
		}
	}

	bloquearSiNoCumple();
	$(document.body).on('updated_cart_totals updated_checkout', bloquearSiNoCumple);
});
</script>
