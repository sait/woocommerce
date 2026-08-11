<?php
/** @var array<int,array<string,mixed>> $warehouses Existencias devueltas por SAIT. */
/** @var array<int,string> $allowed_warehouses Claves de almacenes configurados. */
?>
<h3>Existencias por sucursal</h3>
<style>
	.tabla-almacenes {
		width: auto;
		border-collapse: collapse;
		margin-top: 10px;
	}
	.tabla-almacenes th,
	.tabla-almacenes td {
		border: 1px solid #ccc;
		padding: 4px 8px;
		text-align: left;
	}
	.tabla-almacenes th {
		background-color: #f0f0f0;
	}
</style>
<table class="tabla-almacenes">
	<tr><th>Sucursal</th><th>Existencia</th></tr>
	<?php foreach ($warehouses as $warehouse) : ?>
		<?php if (in_array($warehouse['numalm'], $allowed_warehouses)) : ?>
			<?php
			$stock = isset($warehouse['existencia']) && is_numeric($warehouse['existencia'])
				? max(0.0, (float) $warehouse['existencia'])
				: 0.0;
			?>
			<tr>
				<td><?php echo esc_html(isset($warehouse['nomalm']) ? trim($warehouse['nomalm']) : ''); ?></td>
				<td><?php echo esc_html(round($stock, 2)); ?></td>
			</tr>
		<?php endif; ?>
	<?php endforeach; ?>
</table>
