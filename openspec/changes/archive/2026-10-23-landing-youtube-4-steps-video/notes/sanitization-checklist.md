# Checklist de sanitización — walkthrough público de YouTube

Aplica a los clips y posters publicados en `public/videos/youtube-walkthrough/` y
`public/images/demo/youtube-walkthrough/`, generados por
`scripts/build-video-walkthrough.php` a partir de la grabación fuente del
22/10/2026 (que vive **fuera** del repositorio).

Regla general: **el material publicado no puede contener datos de terceros ni
contenido ajeno**. Si algo no se puede enmascarar con las cajas de redacción,
se recorta (no se publica).

## Ítems y criterio de aceptación

| # | Ítem | Riesgo | Criterio de aceptación | Cómo se resolvió |
|---|---|---|---|---|
| 1 | Correo de la cuenta del panel | Identifica al titular | Ningún correo legible en ningún fotograma | Caja `app_user_header` (borde superior derecho del panel) |
| 2 | Nombre de la persona en "Cuentas conectadas" | Identifica al titular | Ningún nombre propio legible | Cajas `account_name_top` (panel sin scroll) y `account_name_scrolled` (panel con scroll) |
| 3 | Correo personal en la pantalla de consentimiento de Google | Identifica al titular | La pantalla de consentimiento se ve limpia | Caja `google_account_email` (correo + avatar) |
| 4 | Nombre de la persona en "Usar mi cuenta de …" (modal de destino) | Identifica al titular | El toggle se ve, el nombre no | Caja `modal_account_create` |
| 5 | Stream key de YouTube (panel de credenciales) | **Credencial** | Ninguna clave legible | Caja `credentials_panel` (bloque tapado + rótulo "Credenciales y claves ocultas") |
| 6 | Stream key dentro del log del daemon (línea del comando ffmpeg) | **Credencial** | La URL de YouTube con la clave no es legible | Caja `log_stream_key_line` |
| 7 | URL RTMP de origen interna (`rtmp://127.0.0.1:1935/live/…`) | Fuga de topología interna | Visible solo dentro del log del daemon | Se acepta: es la URL local del propio producto, sin credencial |
| 8 | Pestañas y barra del navegador (Platzi, Hostinginger, Gmail, "AnimaFLV", "Contrator") | **Filtración de pestañas ajenas** | Ninguna pestaña ajena visible | Recorte vertical del cromo (`crop` arranca en y=120) |
| 9 | Contenido de terceros (noticiero "DANIELA PASÓ 36 HORAS ATRAPADA", chat en vivo, canal personal del cliente) | **Contenido de terceros + identidad** | Ningún fotograma ajeno en los clips publicados | Se excluyó todo el metraje de YouTube (fuente 4:12–5:46); el capítulo 4 usa solo el panel de Cloudstream (5:35–5:41) |
| 10 | Nombre y avatar del canal de YouTube del cliente, número de suscriptores | Identifica al cliente | No aparecen (consecuencia del ítem 9) | Excluido |
| 11 | Ruta interna del servidor en el log (`/www/wwwroot/…/storage/logs/…`) | Fuga de topología | Visible solo dentro del log del daemon | Se acepta: no contiene credenciales ni datos del titular |
| 12 | Nombre del canal de prueba ("Prueba Stream", slug `prueba-stream`) | Identifica el canal de pruebas del cliente | Visible | Se acepta: es un nombre genérico de prueba, no identifica a una persona, y es necesario para explicar el concepto de *slot* |
| 13 | Audio de la grabación | Posible voz con datos personales | Los clips no tienen pista de audio | `-an` en la codificación |

## Verificación realizada

- Revisión fotograma a fotograma de los 4 clips generados (muestras cada 1,5 s y
  los primeros/últimos fotogramas de cada segmento) contra los ítems 1-13.
- Zoom 2× sobre las zonas sensibles: consentimiento de Google, fila de la
  cuenta conectada, toggle "Usar mi cuenta de …", línea del comando ffmpeg del
  log y panel de credenciales.
- Confirmación de que ningún clip contiene metraje de YouTube (ítem 9): el
  capítulo 4 arranca en el segundo 335 de la fuente, donde ya se ve el panel
  de Cloudstream, y termina en 341,5 antes del corte de vuelta a YouTube.

## Si hay que regenerar el material

```bash
php scripts/build-video-walkthrough.php --source=/ruta/a/la/grabacion.mp4
```

El manifiesto `scripts/video-walkthrough.source.json` es la fuente de verdad de
segmentos, recortes y cajas de redacción. **Cualquier clip nuevo debe volver a
pasar este checklist antes de publicarse.**
