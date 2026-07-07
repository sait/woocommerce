# Contexto Para Agentes

Este repositorio contiene un plugin de WordPress/WooCommerce llamado `SAIT WooCommerce`.

El plugin conecta SAIT/SAITNube con WooCommerce en dos direcciones:

- SAIT -> WooCommerce: recibe eventos XML por REST y crea/actualiza productos, precios, existencias, categorias y clientes.
- WooCommerce -> SAIT: envia pedidos o cotizaciones a la API de SAITNube cuando se generan ordenes.

Archivos clave:

- `sait-woocommerce/SAIT_WOOCOMMERCE.php`: archivo principal del plugin, registra endpoints REST, hooks de WooCommerce y assets del modal de sucursal.
- `sait-woocommerce/includes/SAIT_WOOCOMMERCE-process-events.php`: procesa eventos XML de SAIT.
- `sait-woocommerce/includes/SAIT_WOOCOMMERCE-orders.php`: arma pedidos/cotizaciones y los manda a SAITNube.
- `sait-woocommerce/includes/SAIT_UTILS.php`: llamadas HTTP, tabla de mapeos `sait_claves`, modal/sucursales, existencias y precio promocional.
- `sait-woocommerce/includes/SAIT_WOOCOMMERCE-cart.php`: recalculo de precios en carrito y monto minimo.
- `sait-woocommerce/includes/SAIT_WOOCOMMERCE-options.php`: pagina de configuracion en admin.

Documentacion local:

- `docs/PROJECT_OVERVIEW.md`: resumen funcional y arquitectura.
- `docs/FLOWS.md`: flujos principales y eventos.
- `docs/CONFIGURATION.md`: opciones de configuracion.
- `docs/CODE_MAP.md`: mapa de archivos y funciones.
- `docs/REVIEW_NOTES.md`: hallazgos tecnicos y riesgos.

Notas de trabajo:

- No hay `composer.json`, `package.json` ni suite de tests detectada.
- El directorio `.vscode/` aparece como no rastreado y no debe tocarse salvo instruccion explicita.
- Algunas banderas de configuracion tienen diferencias de mayusculas/minusculas entre registro y uso; revisar antes de cambiar comportamiento.
- Varias consultas SQL se construyen por concatenacion. Si se edita esa zona, preferir `$wpdb->prepare`.
