// modal.js
jQuery(document).ready(function($) {
	// Manejar el clic en el botón de sucursal
	$('#sucursal-button').on('click', function(e) {
			e.preventDefault();
			$('#sucursal-modal')
					.css('display', 'flex') // Cambiar a flex para centrar
					.hide() // Ocultar primero para evitar parpadeo
					.fadeIn(); // Mostrar con animación
	});

	// Manejar el clic en el botón de cerrar modal
	$('#cerrar-modal').on('click', function(e) {
			e.preventDefault();
			$('#sucursal-modal').fadeOut();
	});
	// Ocultar el modal al hacer clic fuera del contenido
	$(document).on('click', function(e) {
			if ($(e.target).is('#sucursal-modal')) {
					$('#sucursal-modal').fadeOut(); // Ocultar el modal
			}
	});
	// Manejar el clic en una opción de sucursal
	$('.sucursal-opcion').on('click', function(e) {
			e.preventDefault();
			var sucursalId = $(this).data('id');
			var sucursalNombre = $(this).find('strong').text(); // Obtener el nombre de la sucursal

			// Enviar la solicitud AJAX para guardar la sucursal seleccionada
			$.ajax({
					url: sait_woocommerce_ajax.ajax_url,
					type: 'POST',
					data: {
							action: 'guardar_sucursal',
							sucursal_id: sucursalId,
							nonce: sait_woocommerce_ajax.nonce
					},
					success: function(response) {
							if (response.success) {
									// Actualizar el botón del menú con el nombre de la sucursal
									$('#sucursal-button').html('<i class="fas fa-map-pin"></i> ' + sucursalNombre);
									$('#sucursal-modal').fadeOut(); // Ocultar el modal
									window.location.reload();
							} else {
									alert('Error al guardar la sucursal.');
							}
					},
					error: function() {
							alert('Error en la solicitud AJAX.');
					}
			});
	});
});

