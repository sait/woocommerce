<?php if ($is_product_page) : ?>
	<span class="precio-promocion-principal" style="font-size:28px;color:#cc0000;font-weight:bold;">
		<?php echo wp_kses_post(wc_price($promotional_price)); ?>
	</span><br>
	<span style="opacity:0.9; font-size:15px;">
		Antes: <del style="color:#3c3636;"><?php echo wp_kses_post(wc_price($regular_price)); ?></del>
	</span><br>
	<span style="background:#cc0000;color:white;padding:3px 8px;border-radius:6px;font-size:13px;">
		-<?php echo esc_html($discount); ?>% OFF
	</span>
<?php else : ?>
	<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
		<span style="font-size:22px;color:#cc0000;font-weight:bold;"><?php echo wp_kses_post(wc_price($promotional_price)); ?></span>
		<span style="background:#cc0000;color:white;padding:2px 6px;font-size:11px;border-radius:4px;font-weight:bold;">
			-<?php echo esc_html($discount); ?>%
		</span>
	</div>
	<small style="opacity:0.9;font-size:13px;">
		Antes: <del style="color:#3c3636;"><?php echo wp_kses_post(wc_price($regular_price)); ?></del>
	</small>
<?php endif; ?>
