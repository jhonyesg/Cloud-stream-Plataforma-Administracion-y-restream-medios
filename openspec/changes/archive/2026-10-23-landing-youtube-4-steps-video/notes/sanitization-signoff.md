# Registro de revisión de sanitización — walkthrough "Emite a YouTube en 4 pasos"

| Campo | Valor |
|---|---|
| Material | `public/videos/youtube-walkthrough/*.mp4` + `public/images/demo/youtube-walkthrough/*.jpg` |
| Fuente | Grabación de pantalla del 22/10/2026 (5:46) — **fuera del repositorio**, nunca publicada |
| Manifiesto de build | `scripts/video-walkthrough.source.json` |
| Script | `scripts/build-video-walkthrough.php` |
| Fecha de la revisión | 2026-10-23 |
| Revisor | Implementación asistida (agente) — revisión visual fotograma a fotograma |
| Checklist aplicado | `notes/sanitization-checklist.md` (13 ítems) |

## Resultado por capítulo

| Capítulo | Clips | Duración | Peso | Contenido | Datos personales |
|---|---|---|---|---|---|
| `conectar-cuenta` | 1 | 22 s | 358 KB | Panel Restream → consentimiento OAuth de Google → panel con la cuenta conectada | Ninguno legible (cajas 1-3) |
| `crear-destino` | 1 | 15 s | 359 KB | Modal "Destino de Restream": plataforma, nombre, "usar mi cuenta", activar | Ninguno legible (cajas 1, 2, 4) |
| `programar-ventana` | 1 | 23 s | 426 KB | Modal "Nueva ventana de emisión": inicio/fin, zona horaria, título, privacidad, grabación, miniatura, notas | Ninguno legible (cajas 1, 2) |
| `encender-compartir` | 1 | 6,5 s | 174 KB | Destino "En vivo en YouTube" + log del daemon con bitrate/fps/estado | Nombre y correo tapados; **stream key y URL de YouTube tapados** (cajas 1, 2, 5, 6) |
| **Total** | **4** | **66,5 s** | **1,3 MB** | | |

## Decisiones de alcance

- Se **excluyó** el metraje de YouTube (4:12–5:46): mostraba el noticiero de un
  tercero con su chat en vivo y el canal personal del cliente con su nombre,
  avatar y número de suscriptores. Es contenido ajeno e identidad de un cliente.
  La prueba de que la emisión llega a YouTube se comunica con el texto del paso
  4 y con la insignia "En vivo en YouTube" del propio panel de Cloudstream.
- Se **excluyó** la pantalla de selección de cuenta de Google (0:15–0:19): la
  lista de cuentas del titular. La pantalla de consentimiento (0:19–0:27) sí se
  publica, con el correo enmascarado, porque es la que explica el flujo OAuth.
- Se acepta la visibilidad de "Prueba Stream", el slug `prueba-stream` y la URL
  local `rtmp://127.0.0.1:1935/live/…` (no contienen credenciales ni identifican
  a una persona) — el nombre del canal de prueba es necesario para explicar el
  concepto de *slot*.
- Todos los clips se publican **sin audio**: la grabación original solo tenía
  ruido de sistema (media −45 dB, pico −20 dB).

## Estado

**APROBADO PARA PUBLICACIÓN.** El material está en `public/` y referenciado por
la home. Antes de publicar un material nuevo (o de regrabar), hay que repetir
esta revisión completa.
