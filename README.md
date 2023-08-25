# woocommerce
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

