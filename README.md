# SAIT WooCommerce

Plugin de WordPress para integrar WooCommerce con SAIT/SAITNube.

El plugin sincroniza informacion de SAIT hacia WooCommerce mediante webhooks y envia ordenes de WooCommerce hacia SAIT como pedidos o cotizaciones.

## Funciones Principales

- Crear, actualizar, restaurar o desactivar productos WooCommerce desde eventos SAIT.
- Actualizar precios, existencias, categorias y clientes.
- Actualizar precios de articulos en dolares cuando cambia el tipo de cambio.
- Mostrar existencias por almacen base o por multiples almacenes configurados.
- Aplicar precios promocionales en catalogo/carrito cuando la opcion esta activa.
- Validar monto minimo de carrito cuando la opcion esta activa.
- Enviar ordenes WooCommerce a SAITNube como pedidos o cotizaciones.
- Reenviar manualmente pedidos cuando SAITNube/API no estuvo disponible.

## Requisitos

- WordPress con WooCommerce activo.
- Acceso a SAITNube/API.
- Un webhook configurado en SAITNube para enviar eventos hacia WooCommerce.

## Instalacion

1. Comprimir el directorio del plugin en un archivo `.zip`.
2. Instalarlo en WordPress desde `Plugins -> Agregar nuevo -> Subir plugin`.
3. Activar el plugin.
4. Configurarlo en `Ajustes -> Configuracion SAIT`.

Al activarse, el plugin crea la tabla `{prefix}sait_claves`, usada para relacionar claves SAIT con IDs de WordPress/WooCommerce.

## Configuracion Basica

Las opciones se guardan en WordPress dentro de `opciones_sait`.

| Opcion | Uso |
| --- | --- |
| `SAITNube_URL` | URL base de SAITNube/API. |
| `SAITNube_APIKey` | Valor enviado en el header `X-sait-api-key` al llamar a SAITNube. |
| `SAITNube_AccessToken` | Valor esperado en el header entrante `x-AccessToken` para webhooks SAIT. |
| `SAITNube_TipoDoc` | `P` envia pedidos; cualquier otro valor envia cotizaciones. |
| `SAITNube_NumAlm` | Almacen base para existencias y documentos enviados a SAIT. |
| `SAITNube_PrecioLista` | Lista de precio SAIT usada para actualizar precios WooCommerce. |
| `SAITNube_TipoCambio` | Tipo de cambio guardado por el evento `ACTTC`. |

Opciones adicionales:

| Opcion | Uso |
| --- | --- |
| `SAITNube_Sucursal_enabled` | Activa el modal/selector de sucursal. |
| `SAITNube_ExistAlm_enabled` | Activa existencias por multiples almacenes. |
| `SAITNube_ExistAlm` | Lista de almacenes separados por coma para mostrar/sumar existencias. |
| `SAITNube_OcultarSinPrecio_enabled` | Oculta productos con precio `0` en catalogo. |
| `SAITNube_Promo_enabled` | Activa recalculo de precios promocionales en carrito. |
| `SAITNube_PromoGlobal_enabled` | Muestra precio promocional en catalogo/producto. |
| `SAITNube_MinimoCarrito_Enabled` | Activa validacion de monto minimo de carrito. |
| `SAITNube_MinimoCarrito` | Monto minimo requerido para checkout. |
| `SAITNube_PedidoObs_enabled` | Envia observaciones de la orden a SAIT. |
| `SAITNube_PedidoDirenvio_enabled` | Envia direccion de envio a SAIT. |
| `SAITNube_FuncionPersonalizadaPedido_enabled` | Ejecuta personalizacion del documento antes de enviarlo a SAIT. |

## Endpoints REST

Las rutas se registran bajo `/wp-json/saitplugin/v1`.

| Metodo | Ruta | Uso |
| --- | --- | --- |
| `GET` | `/hello` | Prueba simple del plugin. |
| `POST` | `/saitevents` | Recibe eventos XML enviados por SAITNube. |
| `POST` | `/reenviar-pedido-sait/{idpedido}` | Reenvia manualmente una orden WooCommerce a SAIT. |
| `GET` | `/testpedido/{idpedido}` | Alias historico de compatibilidad para reenvio manual. |

`/saitevents` valida el header `x-AccessToken` contra la opcion `SAITNube_AccessToken`.

La ruta de reenvio manual esta disponible sin token por compatibilidad operativa. Su uso recomendado es cuando SAITNube/API no estuvo disponible y se necesita reenviar una orden especifica.

## SAIT -> WooCommerce

SAITNube debe enviar eventos al endpoint:

```http
POST https://mitienda.com/wp-json/saitplugin/v1/saitevents
```

Headers requeridos:

```http
x-AccessToken: valor-configurado-en-sait
Content-Type: application/xml
```

Eventos soportados por el procesador:

| Evento | Accion en WooCommerce |
| --- | --- |
| `MODART` | Crea, actualiza, restaura o manda a papelera productos. |
| `ACTEXIST` | Actualiza existencia por articulo/almacen. |
| `ACTEXISGBL` | Actualiza existencia global cuando aplica. |
| `ACTPRECIO` | Actualiza precio regular del producto. |
| `ACTTC` | Actualiza tipo de cambio y recalcula productos en dolares. |
| `MODFAMILIA` | Crea o actualiza categorias desde familias. |
| `MODDEPTO` | Crea o actualiza categorias desde departamentos. |
| `MODLINEA` | Crea o actualiza categorias desde lineas. |
| `MODCATEGO` | Crea o actualiza categorias desde categorias SAIT. |
| `MODCLI` | Crea, liga o actualiza clientes WooCommerce. |

Ejemplo de evento:

```xml
<event version="2" dev="LINUX#Admin" usr="SAIT" time="20230823162613" loc="1" ref="1603" type="MODFAMILIA" src="sait">
  <action cmd="write" tbl="familias">
    <dbf fld="NUMFAM" val="1603"/>
    <keys numfam="1603"/>
    <flds nomfam="1603-POSTE METALICO" margen="0.00"/>
  </action>
</event>
```

### Respuestas HTTP En Webhooks

Algunos casos operativos responden HTTP `200` aunque el mensaje parezca un error, por ejemplo `ART NO EXISTE`, `STOCK ERR ACTEXIST` o `IGNORADO (ppubv*)`.

Esto significa que el webhook fue recibido y evaluado, pero el evento no era aplicable al estado actual de WooCommerce. El mensaje queda como diagnostico operativo y el `200` evita que SAIT trate el evento como una entrega fallida.

Errores de autenticacion, formato o fallas reales de WordPress si usan codigos HTTP de error, por ejemplo:

- Token invalido: `401`.
- XML invalido: `500`.
- Error al crear terminos/categorias: `500`.

## WooCommerce -> SAIT

El plugin escucha hooks de WooCommerce:

- `woocommerce_payment_complete`: envia con `formapago = "1"`.
- `woocommerce_thankyou`: envia con `formapago = "2"`.

Segun `SAITNube_TipoDoc`, genera:

- `P`: pedido hacia `/api/v3/pedidos`.
- Otro valor: cotizacion hacia `/api/v3/cotizaciones`.

Los envios automaticos se disparan sin esperar respuesta de SAITNube. Para evitar duplicados entre hooks, la orden se marca con metadata de idempotencia antes de enviar.

SAITNube responde `201` cuando recibe correctamente pedidos o cotizaciones.

## Reenvio Manual De Pedidos

Cuando SAITNube/API no estuvo disponible, se puede reenviar una orden con:

```http
POST https://mitienda.com/wp-json/saitplugin/v1/reenviar-pedido-sait/1234
```

Donde `1234` es el ID de la orden WooCommerce.

El endpoint manual espera respuesta de SAITNube y guarda metadata del ultimo intento en la orden:

- `_sait_ultimo_envio_estado`: `enviado`, `error` o `reintento_requerido`.
- `_sait_ultimo_status_code`: status HTTP recibido.
- `_sait_ultimo_envio_at`
- `_sait_ultimo_envio_formapago`
- `_sait_ultimo_envio_tipodoc`
- `_sait_ultimo_envio_modo`
- `_sait_ultimo_error`, cuando el resultado no fue exitoso.

Si SAIT responde `400` porque el pedido ya existe, el plugin lo registra como error operativo del ultimo intento.

La ruta legacy sigue disponible:

```http
GET https://mitienda.com/wp-json/saitplugin/v1/testpedido/1234
```

## Pruebas De Sintaxis

El repositorio incluye un entorno minimo de Docker para lint PHP:

```bash
docker compose -f tests/docker-compose.yml run --rm php sh tests/php-lint.sh
```

## Documentacion Interna

Para mantenimiento y revision tecnica:

- `docs/PROJECT_OVERVIEW.md`: resumen del proyecto.
- `docs/FLOWS.md`: flujos principales y convenciones operativas.
- `docs/CONFIGURATION.md`: opciones del plugin.
- `docs/CODE_MAP.md`: mapa de archivos y responsabilidades.
- `docs/REVIEW_NOTES.md`: checklist de revision y pendientes.
