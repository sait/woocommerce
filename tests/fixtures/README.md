# Fixtures De Integracion SAIT

Estos archivos describen entradas estables para caracterizar el plugin antes
de mover su logica. Los XML pueden enviarse como cuerpo de
`POST /wp-json/saitplugin/v1/saitevents` usando el `x-AccessToken` configurado.

Los estados como alta, actualizacion o producto preexistente dependen de la
base de datos, no de una variante del evento. Por eso un mismo fixture se usa
con distintas precondiciones.

## Escenarios De Eventos

| Escenario | Fixture | Precondicion | Resultado actual esperado |
| --- | --- | --- | --- |
| Alta de articulo | `events/modart-active.xml` | SKU y mapeo inexistentes. | `ART ADD`; producto borrador y mapeo `arts`. |
| Actualizacion | `events/modart-active.xml` | Existe el mapeo `arts`. | `ART UPD`. |
| Baja | `events/modart-disabled.xml` | Existe el mapeo `arts`. | Producto en papelera y `OK`. |
| SKU preexistente | `events/modart-active.xml` | Existe SKU, no existe mapeo. | Crea otro producto o falla por SKU duplicado; brecha conocida. |
| Precio publico | `events/actprecio.xml` | Producto local resoluble. | Precio regular `116.00`. |
| Lista configurada | `events/actprecio.xml` | Lista `1` y respuesta `articulo_pesos`. | Precio `116.00` con impuestos. |
| Articulo en dolares | `events/actprecio.xml` | TC `18.50` y respuesta `articulo_dolares`. | Precio convertido desde el XML. |
| Precio por volumen | `events/actprecio-volume-only.xml` | Producto local resoluble. | `IGNORADO (ppubv*)`; comportamiento actual. |
| Existencia unica | `events/actexist.xml` | Almacen configurado `1`. | WooCommerce guarda `7`; trunca `7.500` actualmente. |
| Existencia multiple | `events/actexist.xml` | Multi-almacen `1,2`. | Suma `9.75`, pero WooCommerce guarda `9` actualmente. |
| Existencia global | `events/actexisgbl.xml` | Sin almacen base configurado. | Guarda `13`; trunca `13.500` actualmente. |
| Tipo de cambio | `events/acttc.xml` | TC distinto de `18.50`. | Guarda TC y recalcula articulos en dolares. |
| Alta de cliente | `events/modcli.xml` | Sin usuario ni mapeo. | `Cli ADD`. |
| Actualizacion | `events/modcli.xml` | Mapeo con otro correo. | `Cliente actualizado`. |
| Correo duplicado | `events/modcli.xml` | Correo pertenece a otro usuario. | Mensaje de correo ya asignado. |
| Usuario preexistente | `events/modcli.xml` | Usuario con correo, sin mapeo. | Liga el usuario existente. |
| Categorias | `events/mod*.xml` | Termino/mapeo ausente o presente. | Alta o actualizacion del termino. |

## Documentos

`api/responses.json` contiene respuestas simuladas de SAITNube.
`expected/documents-current.json` congela payloads representativos que el
codigo actual construye. Las fechas son valores deterministas de ejemplo.

`expected/document-eventual-existing-target.json` define la regresion de
clientes eventuales: `/clientes` devuelve un `numcli` con `-`, que se envia en
`numcliev` sin volver a incluir `clievent`.
