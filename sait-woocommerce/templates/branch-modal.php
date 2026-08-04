<div id="sucursal-modal">
	<div class="modal-content">
		<h2 style="margin-top: 0; color: #2c3e50; text-align: center;">Selecciona tu Sucursal</h2>
		<ul class="lista-sucursales">
			<?php foreach ($branches as $branch) : ?>
				<?php
				$street = isset($branch['calle']) ? $branch['calle'] : '';
				$exterior = isset($branch['numext']) ? $branch['numext'] : '';
				$neighborhood = isset($branch['colonia']) ? $branch['colonia'] : '';
				$city = isset($branch['ciudad']) ? $branch['ciudad'] : '';
				$state = isset($branch['estado']) ? $branch['estado'] : '';
				$postcode = isset($branch['cp']) ? $branch['cp'] : '';
				?>
				<li>
					<div class="sucursal-opcion" data-id="<?php echo esc_attr(trim($branch['numalm'])); ?>">
						<strong><?php echo esc_html(isset($branch['nomalm']) ? $branch['nomalm'] : ''); ?></strong>
						<small>
							<?php echo esc_html(trim($street . ' ' . $exterior)); ?><br>
							<?php echo esc_html(trim($neighborhood . ', ' . $city, ', ')); ?><br>
							<?php echo esc_html(trim($state . ', ' . $postcode, ', ')); ?><br>
						</small>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<button id="cerrar-modal" class="button">Cerrar</button>
	</div>
</div>
