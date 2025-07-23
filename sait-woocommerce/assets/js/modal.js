jQuery(function($) {
	// Función para mostrar el modal centrado
	function showModal() {
			console.log('Mostrando modal...');
			$('#sucursal-modal')
					.css({
							'display': 'flex',
							'opacity': 0
					})
					.animate({
							'opacity': 1
					}, 300, function() {
							$(this).addClass('active');
					});
	}

	// Función para ocultar el modal
	function hideModal() {
			console.log('Ocultando modal...');
			$('#sucursal-modal')
					.animate({
							'opacity': 0
					}, 250, function() {
							$(this)
									.removeClass('active')
									.css('display', 'none');
					});
	}

	// Evento para abrir modal
	$(document).on('click', '#sucursal-button', function(e) {
			e.preventDefault();
			showModal();
	});

	// Evento para cerrar modal
	$(document).on('click', '#cerrar-modal', function(e) {
			e.preventDefault();
			hideModal();
	});

	// Cerrar al hacer clic fuera del contenido
	$(document).on('click', '#sucursal-modal', function(e) {
			if ($(e.target).is('#sucursal-modal')) {
					hideModal();
			}
	});

	// Prevenir que el clic en el contenido cierre el modal
	$(document).on('click', '.modal-content', function(e) {
			e.stopPropagation();
	});

	// Selección de sucursal 
	$(document).on('click', '.sucursal-opcion', function() {
			var sucursalId = $(this).data('id');
			var sucursalNombre = $(this).find('strong').text();
			
			console.log('Sucursal seleccionada:', sucursalNombre);
			// Enviar la solicitud AJAX para guardar la sucursal seleccionada
			$.ajax({
					url: sait_woocommerce_ajax.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: {
							action: 'guardar_sucursal',
							sucursal_id: sucursalId,
							nonce: sait_woocommerce_ajax.nonce
					},
					beforeSend: function() {
							$('.sucursal-opcion').css('opacity', '0.6');
					},
					success: function(response) {
							if (response.success) {
									// Actualizar el botón del menú con el nombre de la sucursal
									$('#sucursal-button').html('<i class="fas fa-map-marker-alt"></i> ' + sucursalNombre);
									hideModal();
							}
					},
					error: function(xhr, status, error) {
							console.error('Error:', error);
							$('.sucursal-opcion').css('opacity', '1');
					},
					complete: function() {
							$('.sucursal-opcion').css('opacity', '1');
					}
			});
	});

	// Verificar que los elementos existan
	if ($('#sucursal-modal').length && $('#sucursal-button').length) {
			console.log('Elementos del modal cargados correctamente');
	} else {
			console.warn('Algunos elementos del modal no se encontraron en el DOM');
	}
});