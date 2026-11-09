# Tasks

## 1. Fuente de verdad de las URLs

- [x] 1.1 Crear `config/streaming_public.php` con `display_host` (`streaming.mediaserver.com.co`), `example_slug` (`tu-canal`) y `ports` (`rtmp` 1935, `srt_publish` 10080, `srt_play` 7386), y verificar con `php artisan tinker` que `config('streaming_public.display_host')` devuelve el host de exhibición y que es distinto de `canal.mediaserver.com.co`
- [x] 1.2 Confirmar por `grep` que ninguna vista pública lee `config('mediaserver.public_host')` ni `MEDIASERVER_API_URL` (el primero contiene la IP de producción y el segundo el panel interno), y verificar que el grep no devuelve coincidencias en `resources/views/`

## 2. Componente de la sección

- [x] 2.1 Crear `resources/views/components/public-delivery-urls.blade.php` con los dos bloques (`PUBLICAR` con RTMP/SRT y `CONSUMIR` con HLS/FLV/WebRTC/MPEG-TS en HTTPS y HTTP más SRT) y la franja de flujo (tu estudio → datacenter → tu audiencia), y verificar que renderizado aislado produce los dos bloques y todas las filas de protocolo
- [x] 2.2 Implementar el botón de copiar por fila (Clipboard API con respaldo `textarea` + `execCommand`) con `aria-label` que nombre el protocolo y un toast `role="status"` que indique que hay que reemplazar `tu-canal`, y verificar que al activarlo el portapapeles recibe exactamente la URL de esa fila
- [x] 2.3 Añadir el callout de MPEG-TS (Astra, Lastra, Cesbo, vMix, operador de cable, troncal de televisión, captura satelital, sin depender de SRT ni de plugins) y la nota de que las URLs mostradas son el formato que se entrega, y verificar que ambos textos aparecen en el render
- [x] 2.4 Construir todas las URLs del componente a partir de `config/streaming_public.php` (nada de host, puerto o nombre de canal escritos a mano en el Blade), y verificar que al cambiar `display_host` y `example_slug` en la config el HTML renderizado cambia en todas las filas

## 3. Integración en el home

- [x] 3.1 Insertar `<x-public-delivery-urls />` en `resources/views/landing.blade.php` entre el bloque de planes (termina en la línea 322) y el de preguntas frecuentes (empieza en la 324), con `id="formatos"` y un `<h2>` de título, y verificar en el HTML renderizado que la posición de la sección es posterior a la de planes y anterior a la de FAQ
- [x] 3.2 Añadir el ancla `#formatos` al nav público (`resources/views/components/public-nav.blade.php`) entre "Planes" y "Contacto", y verificar que el enlace apunta al `id="formatos"` de la home
- [x] 3.3 Confirmar que la home sigue renderizando exactamente un `<h1>` y que la sección nueva no introduce un segundo, y verificarlo con una aserción sobre el HTML renderizado

## 4. Estilos y documentación

- [x] 4.1 Regenerar el bundle de Tailwind (`npm run build:css`) y limpiar vistas compiladas (`php artisan view:clear`), y verificar por `grep` que cada clase nueva de la sección aparece en `public/css/tailwind.min.css`
- [x] 4.2 Documentar en `AGENTS.md` la existencia de la sección de formatos de entrega, el host de exhibición `streaming.mediaserver.com.co` y la regla de que el host de producción (`canal.mediaserver.com.co`) y la IP `15.204.8.139` no se publican, y verificar que la documentación nombra la restricción

## 5. Pruebas y guardarraíles

- [x] 5.1 Crear `tests/Feature/LandingDeliveryUrlsTest.php` cubriendo la matriz de formatos (RTMP, SRT, HLS, FLV, WebRTC, MPEG-TS, con las variantes HTTPS, HTTP y SRT de consumo), el orden de la sección entre planes y FAQ, el `aria-label` de cada botón de copiar y la presencia del texto de MPEG-TS, y verificar que `php artisan test --filter=LandingDeliveryUrlsTest` pasa
- [x] 5.2 Añadir al test la aserción de no-fuga (el HTML publicado no contiene `15.204.8.139`, ni `canal.mediaserver.com.co`, ni nombres de stream o claves de clientes) y verificar que el test falla si se sustituye el host de exhibición por el de producción
- [x] 5.3 Ejecutar la suite de SEO pública (`php artisan test --filter=PublicSeo`) y la de la home (`--filter=Landing`), y verificar que sigue pasando sin regresiones
- [x] 5.4 Revisar el render en móvil (filas en una columna, URL truncada sin romper el layout) y en escritorio, y verificar que los botones de copiar son alcanzables con teclado y tienen foco visible

## Workflow follow-up

- Archivar el change cuando la implementación esté revisada y los tests en verde.
