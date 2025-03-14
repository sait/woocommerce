jQuery(document).ready(function($) {
	$('#sucursal-select').on('change', function() {
			var sucursal_id = $(this).val();
			
			if (sucursal_id) {
					$.ajax({
							url: mi_plugin_ajax.ajax_url,
							type: 'POST',
							data: {
									action: 'guardar_sucursal',
									sucursal_id: sucursal_id,
									nonce: mi_plugin_ajax.nonce
							},
							success: function(response) {
									if (response === 'success') {
											alert('Sucursal guardada correctamente.');
									} else {
											alert('Error al guardar la sucursal.');
									}
							}
					});
			}
	});
});