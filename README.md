# SAIT WooCommerce Pluging
Plugin de comunicación SAIT con WooCommerce

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

Tablas
---
- wp_sait_claves

wp_sait_claves
---

| campo |     tipo    |                                           contenido                                            |
|-------|-------------|------------------------------------------------------------------------------------------------|
| id    | int         | incremental key                                                                                |
| tabla | varchar(20) | tabla en sait por ejemplo: arts, clientes                                                      |
| clave | varchar(20) | clave de la entidad en la tabla de SAIT, por ejemplo el valor de arts.NUMART o clientes.NUMCLI |
| wcid  | int         | id de la entidad, en los post de WordPress                                                     |



Procesar_Evento_ModArt
---
```
Revisar si articulo NO aparece en tienda

Buscar evento.fieles.numart en sait_claves

Si evento.fields.statusweb==0
	Mandar Producto Papelera de WooPress
	Se podria poner en Borrador de WooCommerce
	Regresar

Existe en sait_claves
	Articulo ya existe en WooCommerce
	Actualizar Informacion
	Regresar

Buscar en WooComm el Producto con SKU = evento.fields.numart
Si lo encuentro
	Crear registro en sait_claves

Buscar en WooComm el Producto con SKU = evento.fields.codigo
Si lo encuentro
	Crear registro en sait_claves

Articulo No existe en WooCommerce
Crear Producto en WooCommerce 
	Status: Borrador
	Categoria: arts.Familia
Crear registro en sait_claves
Regresar
```

