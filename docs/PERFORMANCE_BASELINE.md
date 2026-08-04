# Línea Base De Rendimiento Del Frontend

## Alcance

Esta línea base local caracteriza el código previo a la extracción del
frontend en la etapa 9. No sustituye una medición de staging: usa WordPress,
WooCommerce y PHP fijados por `tests/docker-compose.yml`, junto con respuestas
JSON locales y una latencia simulada de 25 ms por llamada SAIT.

Las cuatro pantallas se solicitan como el mismo visitante invitado. El catálogo
es una categoría exclusiva con un producto fixture; el carrito se prepara una
vez antes de medir. Antes de cada muestra se eliminan únicamente los transients
con prefijo `sait_`, se vacía la caché de objetos y se reinician los contadores
del mock.

## Cómo Repetirla

```sh
sh tests/test-frontend-performance.sh
```

El comando imprime cada muestra con TTFB, tiempo HTTP total, estado, llamadas
SAIT y duración simulada por ruta. La comparación posterior debe ejecutar el
mismo comando, con el mismo volumen y cantidad de muestras.

## Resultado Inicial

Medición ejecutada el 4 de agosto de 2026 con cinco muestras frías por
pantalla. En las cinco repeticiones de cada escenario el conteo de llamadas
fue estable.

| Pantalla | Mediana TTFB | Llamadas SAIT | Duración SAIT simulada |
| --- | ---: | ---: | ---: |
| Catálogo | 0.186372 s | 3 | 75 ms |
| Producto | 0.226422 s | 4 | 100 ms |
| Carrito | 0.383862 s | 7 | 175 ms |
| Checkout | 0.350125 s | 5 | 125 ms |

Rutas observadas:

- Catálogo: artículo, cálculo de precio y almacenes, una vez cada una.
- Producto: las tres anteriores más existencias, una vez cada una.
- Carrito: dos consultas del artículo del carrito, tres cálculos de precio,
  una consulta de un producto complementario renderizado y almacenes.
- Checkout: dos consultas del artículo, dos cálculos de precio y almacenes.

Estas cifras son el punto de comparación para los siguientes commits de la
etapa. El objetivo no es sólo reducir TTFB: una mejora debe reducir o justificar
también las llamadas remotas durante el request.

## Límites De La Medición

- La duración SAIT es una latencia controlada, no una observación de API v3.
- Docker local permite comparar cambios de código, pero no representa red,
  cachés ni capacidad del servidor de producción.
- La validación definitiva en staging continúa pendiente y debe conservar URL,
  usuario, caché y datos entre el antes y el después.
