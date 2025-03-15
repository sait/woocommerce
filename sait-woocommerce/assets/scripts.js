jQuery(document).ready(function($) {
	$('#sucursal-select').on('change', function() {
			var sucursal_id = $(this).val();
			
			if (sucursal_id) {
					$.ajax({
							url: sait_woocommerce_ajax.ajax_url,
							type: 'POST',
							dataType: 'json', // Importante para manejar JSON correctamente
							data: {
									action: 'guardar_sucursal',
									sucursal_id: sucursal_id,
									nonce: sait_woocommerce_ajax.nonce
							},
							success: function(response) {
									if (response.success) { // "success" es un booleano en la respuesta JSON
											//alert(response.data); // "data" contiene el mensaje
									} else {
											//alert('Error: ' + response.data);
									}
							},
							error: function() {
									//alert('Hubo un problema con la solicitud AJAX.');
							}
					});
			}
	});
});