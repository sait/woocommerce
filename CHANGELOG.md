# Changelog


## [1.1.6] - 05/AGO/2025 
## add
- Se agregaron opcion al menu
-  Activar/Desactivar promociones en carrito
### fix
- correcciones a MODART



## [1.1.5] - 22/JUL/2025 
### fix
- fix evento moddepto
- fix buscar por codigo

## [1.1.4] - 05/JUL/2025 
### add
- Se agregaron opciones al menu
-  Activar/Desactivar seleccion de sucursal
-  Activar/Desactivar promociones en carrito
-  Activar/Desactivar tabla de existencia en sucursal
-  Sucursales a mostrar en existencias

## [1.1.4] - 05/JUL/2025 
### add
- Se agregaron opciones al menu
-  Activar/Desactivar seleccion de sucursal
-  Activar/Desactivar tabla de existencia en sucursal
-  Sucursales a mostrar en existencias


## [1.1.3] - 02/JUL/2025 
### add
- Se agrego modal para seleccionar sucursal en menu principal
- Al seleccionar sucursal se usa para revisar promociones
- Se agrega tabla para mostrar existencias por sucursal en descripcion de articulo
### upd
- al registrar articulo se busca si ya esta registrado por codigo de barras en la tienda


## [1.1.2] - 12/MAR/2025 
### add
- seleccion de sucursal en menu de la tienda
- Aplicar promociones del sistema SAIT en carrito, se hace una consulta a SAITNUBE.
### fix
- no procesar eventos modart vacios
- arreglar encode de direccion de correo para consulta de cliente por email.
- correccion si la respuesta de saitnube es Null
### upd
- se reorganizo y comento el archivo principal
- se agrego procesamiento de evento MODCATEGO


## [1.1.1] - 22/NOV/2024 
### fix
- Se corrige precio en dolar para precio pub

## [1.1.0] - 09/OCT/2024 
### add
- al hacer completar pago enviar pedido a SAIT, previamente solo era en pedidos sin pago.
- se integra clase SAIT utils con funciones generales
- se agregan funciones GET Y POST para hacer peticiones a SAITnube.
- se consulta si el cliente existe en SAITNUBE
### upd
- usar las funciones de request de sait utils, para hacer consultas a saitnube
- se integran las funciones de registro de categorias en una sola.


## [1.0.30] - 09/OCT/2024 
### upd
- actualizar correo electronico del cliente si viene distinto en el evento


## [1.0.29] - 03/OCT/2024 
### fix
- cliente ya registrado desde SAIT

## [1.0.28] - 14/SEP/2024 
### fix
- precios en dolar


## [1.0.27] - 14/SEP/2024 
### add
- manejo de precios en dolar


## [1.0.26] - 12/SEP/2024 
### add
- proteger peticiones a plugin con token de acceso

## [1.0.25] - 27/AGO/2024 
### add
- dar de alta clientes desde SAIT
- asignar cliente a pedido
- usar lista de precios

## [1.0.24] - 04/JUN/2024 
### add
- add: al subir nuevo articulo se guarda como borrador


## [1.0.23] - 28/FEB/2024 
### add
- add: se cambia a nube v3
- fix: trim en eventos

## [1.0.22] - 01/FEB/2024 
### Fix
- add: Se puede seleccionar existencia de X almacen

## [1.0.21] - 19/ENE/2024 
### Fix
- fix: lineas


## [1.0.20] - 18/ENE/2024 
### Fix
- categorias: se usaran las lineas de SAIT

## [1.0.19] - 16/ENE/2024 
### Fix
- Precios en dolar: Se muestra tc en config, input deshabilitado
- Eliminadas lineas duplicadas

## [1.0.18] - 16/ENE/2024 
### Added
- Precios en dolar: Se hace la conversion del tipo de cambio para mostrar precios en pesos.