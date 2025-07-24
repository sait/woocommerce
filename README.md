# sait-woocommerce
## Plugin para comunicar SAIT Software Administrativo con WooCommerce

Las funciones que se obtienen con este plugin son:
- Enviar articulos de SAIT a WooCommerce
- Actualizar precios en WooCommerce, cuando cambian en SAIT
- Mostrar la existencia de un almacen base
- Actualizar existencias de WooCommerce, segun cambian las existencias en el almacen base de SAIT
- Actualizar precio de productos en moneda DOLAR cuando cambie el Tipo de Cambio
- Opcional: Enviar correo de invitacion a clientes ( cuando se trata de una tienda cerrada )
- Opcional: Mostrar la existencia de otros almacenes
- Opcional: Usar otra lista de precios como base, en lugar de usar la lista 1 publico general.
- Generar un Pedido o Cotizacion para SAIT, cuando un cliente hagan una compra en WooCommerce


Comunicacion de SAIT hacia WooCommerce, se realiza mediante el uso de WebHooks. SAITNube genera una peticion POST hacia WooCommerce por cada evento que generan los usuarios de SAIT, este plugin procesa los eventos en WooCommerce segun esta tabla:

| Tipo      | Uso en SAIT                         | Funcion que realiza en WooCommerce                                                                                          |
| --------- | ----------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| ModArt    | Al crear o modificar articulos      | Crea un producto en WooCommerce y lo clasifica en alguna categoria                                                          |
| ActExt    | Actualizar Eexistencia de Articulo. | Si se trata del almacen base, actualiza el stock en WooCommerce                                                             |
| ActPrecio | Al modificar los precios.           | Actualizar precio de productos                                                                                              |
| ActTC     | Actualizar Tipo de Cambio           | Actualiza los precios de los productos en moneda DOLAR                                                                      |
| ModCli    | Crear o Modificar un cliente        | Si tiene definido cliente.emailTW envia invitacion a WooCommerce. Si ya es cliente le manda aviso de modificacion de emeail |
|           |                                     |                                                                                                                             |

Comunicacion de WooCommerce hacia SAIT, se logra mediante la llamada a la API de [SAITNube](https://github.com/sait/saitnube)
- Cuando un cliente genere una orden en WooCommerce, se genera un pedido en SAITNube


Variables de Configuracion del Plugin:
- SAITNube_URL: Punto de acceso o URL para comunicarse con SAITNube y colocar un pedido nuevo u obtener datos adicionales.
- SAITNube_APIKey: Valor que debe tener el header: ```X-sait-api-key``` para poder llamar a la API de SAITNube
- SAITNube_AccessToken: Valor que debe tener el header ```x-AccessToken``` para poder procesar eventos enviados por SAITNube
- SAITNube_TipoDoc: Tipo de documento a generar en SAIT, cuando un cliente genere una compra: Pedido o Cotizaion
- SAITNube_NumAlm: Almacen a usar como base, para mostrar existencia en WooCommerce
- SAITNube_PrecioList: Lista de Precios a usar para actualizar el precio del producto en WooCommerce
- SAITNube_TipoCambio: Funciona en combinacion con la anterior, TC a usar para actualizar precio de productos en Dolares. Se actualiza cuando se recibe un evento ActTC
- SAITNube_Sucursal_enabled: Nos indica si vamos a mostrar o no el modal de Sucursal
- SAITNube_ExistAlm_enabled: Se mostrara la existencias de todos los almacenes 
- SAITNube_ExistAlm: Se mostrara la existencia de los almacenes contenidos en esta lista


Para Usarlo:
---
1. En tu portal de saitnube.com crear un WebHook con las siguientes datos:
- Nombre de la App: WooCommerce
- URL que procesará eventos:  https:mitienda-woocommere.com/wp-json/saitplugin/v1/saitevents/
- Valor a usar en  header ```x-AccessToken``` : definir identificador unico de al menos 8 posiciones
- Eventos a Enviar: Lista de eventos de SAIT a enviar a WooCommerce, por ejemplo: ModArt,ActPrecio,ActExistencia,ActTC,ModCli

2. En WooCommerce:
- instalar este plugin
- activarlo
- configurarlo


Enviar articulos de SAIT a WooCommerce
---
Los articulos de SAIT se reciben y se crean como productos WooCommerce, usando lo siguiente:
- Solamente se procesan los articulos que se marcan con el campo: Mostrar en TiendaWeb arts.statusweb==1
- Los articulos se crean como productos con status de Borrador(draft), para que el administrador de la tienda lo publique posteriormente.
- arts.numart, se pone en product_set_sku()
- arts.desc, se pone en product_set_name()
- arts.modelo se pone en product_short_description
- arts.familia, se usa como categoria en WooCommerce


Sugerencias:
- Cuando cambien en SAIT el valor de "Mostrar en Tienda Web" hay que mover a status "draft" no borrarlo
- Evento: ACTEXISGBL de donde viene ? porque se puso ?


# Empaquetar el plugin

```
Simplemente comprimirmos el archivo php en un ZIP y este lo instalaremos como archivo en la seccion de plugins de wordpress

```

## Tienda de prueba

GET
https://tiendaprovlimpieza.saitnube.com/wp-json/saitplugin/v1/hello

Return 200
```
Hello world!
```

POST
https://tiendaprovlimpieza.saitnube.com/wp-json/saitplugin/v1/saitevents/

body
```xml
    <event version="2" dev="LINUX#Admin" usr="MSL_SAIT NACIONAL" time="20230823162613" loc="1" ref="1603" type="MODFAMILIA" src="sait">
      <action cmd="write" tbl="familias">
        <dbf fld="NUMFAM" val=" 1603"/>
        <keys numfam="1603"/>
        <flds nomfam="1603-POSTE METALICO" margen="0.00"/>
      </action>
    </event>
```

