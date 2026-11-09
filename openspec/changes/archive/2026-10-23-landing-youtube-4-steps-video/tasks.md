# Tasks

## 1. Producción y sanitización del material

- [x] 1.1 Sacar la grabación fuente del árbol versionado: mover `simplescreenrecorder-2026-10-22_17.01.06.mp4` a una ruta fuera del repositorio y verificar con `git status --porcelain` que ningún `.mp4` aparece en la raíz del proyecto
- [x] 1.2 Escribir el checklist de sanitización en `openspec/changes/landing-youtube-4-steps-video/notes/sanitization-checklist.md` (datos personales del titular, pestañas/dominios ajenos, contenido de terceros, credenciales o claves visibles, audio) y verificar que cada ítem tiene un criterio de aceptación concreto
- [x] 1.3 Producir el master sanitizado: preferido, regrabar el flujo con una cuenta Google demo de Cloudstream, un canal demo y un perfil de navegador limpio; alternativa, recortar/mascarar la grabación existente. Verificar revisando fotograma a fotograma los cuatro tramos publicados: no aparece el correo, ni el nombre personal, ni pestañas ajenas, ni el noticiero de terceros
- [x] 1.4 Definir el manifiesto de capítulos (JSON) con los cuatro capítulos del diseño (conectar cuenta, crear destino/slot, programar ventana, encender y compartir) con `start`, `duration`, `title` y `text`, y verificar que el manifiesto contiene exactamente cuatro entradas con tiempos dentro de la duración del master
- [x] 1.5 Implementar `scripts/build-video-walkthrough.php` (recorte `crop=1920:960:0:120`, escala 1280×640, libx264 CRF 26, `-movflags +faststart`, `-an`, y un poster por capítulo) y verificar ejecutándolo que regenera 4 clips + 4 posters y que el peso total de `public/videos/` no supera 6 MB (`du -ch`)
- [x] 1.6 Registrar la revisión de sanitización firmada (fecha, revisor, checklist completo) en `notes/` y verificar que el registro existe antes de que los archivos se copien a `public/`
- [x] 1.7 Generar además el video continuo (`walkthrough-completo.mp4`, concatenación por *stream copy* de los capítulos) con los offsets de inicio de cada capítulo en el manifiesto, y verificar que el video continuo contiene los cuatro capítulos en orden y pesa 1,3 MB

## 2. Bloque de walkthrough reutilizable

- [x] 2.1 Crear `resources/views/components/public-video-walkthrough.blade.php` con props `src`, `poster`, `chapters` (array de `{ id, start, title, text }`) y `label`, sin markup específico de página, y verificar que el componente se renderiza aislado con `php artisan tinker` (`view('components.public-video-walkthrough', [...])->render()`) sin errores
- [x] 2.2 Implementar la navegación por capítulos con Alpine (click en un paso → `video.currentTime = start` y `video.play()`), sin autoplay, y verificar en el navegador (Playwright) que al pulsar el paso 3 el `currentTime` del video es el `start` del capítulo 3
- [x] 2.3 Cumplir accesibilidad: `aria-label` en el `<video>`, texto de los cuatro pasos presente en el DOM, controles nativos visibles, `muted` + `playsinline` + `preload="none"`, y verificar que requesting la home sin los archivos de video devuelve igualmente el encabezado y los cuatro textos de paso
- [x] 2.4 Declarar en el componente los `data-start` de cada capítulo para que el seek sea testeable sin depender del JS inline, y verificar con un grep del HTML renderizado que los cuatro `data-start` están presentes
- [x] 2.5 Mostrar en cada paso su propia captura (print) de la interfaz con la instrucción al lado, y verificar que los cuatro `data-image` apuntan a imágenes distintas que existen en `public/images/demo/youtube-walkthrough/`

## 3. Home: sección, copy y SEO

- [x] 3.1 Sustituir el bloque "Emite a YouTube en 4 pasos" de `resources/views/landing.blade.php` por `<x-public-video-walkthrough>` con los cuatro capítulos, y verificar con `curl -s http://localhost/` que la home devuelve 200, contiene un `<video>` y exactamente cuatro elementos de paso
- [x] 3.2 Reescribir los textos de los pasos con el contenido real (OAuth de Google, "usar mi cuenta", el slot que consume el destino según el plan, inicio/fin/zona horaria/título/privacidad/miniatura de la ventana, arranque y stop automáticos, publicación en el canal de YouTube con chat y link público) y verificar que el HTML contiene las menciones de "slot", "privacidad" y "automática"
- [x] 3.3 Reubicar las dos capturas existentes (`public/images/demo/restream-panel.jpg`, `restream-schedule.jpg`) como ilustración estática de respaldo bajo el bloque, y verificar que ambas siguen referenciadas en la home y que ninguna otra vista las usa
- [x] 3.4 Añadir el nodo `VideoObject` (name, description, thumbnailUrl, contentUrl, uploadDate, duration) al grafo JSON-LD de la home en `resources/views/partials/public/schema.blade.php`, y verificar que el `contentUrl` y el `thumbnailUrl` son rutas first-party que coinciden con las referenciadas por el bloque
- [x] 3.5 Añadir `og:video`, `og:video:type`, `og:video:poster` y `og:video:url` en `resources/views/components/public-layout.blade.php` alimentados por las mismas rutas del bloque, y verificar con `curl -s` que las cuatro etiquetas aparecen con valores no vacíos
- [x] 3.6 Actualizar el `lastmod` de la home en `config/seo.php` a la fecha de publicación del bloque, y verificar que el sitemap generado incluye la nueva fecha
- [x] 3.7 Apuntar el reproductor y el `VideoObject` al video continuo y validar el salto a capítulo con la lógica Alpine extraída del HTML (node): el paso 3 deja el `currentTime` en 37 s

## 4. Build, pruebas y verificación

- [x] 4.1 Regenerar el bundle de Tailwind (`npm run build:css`) y limpiar las vistas (`php artisan view:clear`), y verificar que cada clase nueva usada en el bloque aparece en `public/css/tailwind.min.css` (grep con conteo ≥ 1 por clase)
- [x] 4.2 Añadir `tests/Feature/LandingYouTubeWalkthroughTest.php` cubriendo: la home expone un `<video>` de origen propio con poster, los cuatro pasos y sus `data-start`, el `VideoObject` en el JSON-LD con rutas first-party, la presencia de `og:video*` y el peso total de los medios referenciados ≤ 6 MB, y verificar que `php artisan test --filter=LandingYouTubeWalkthroughTest` pasa
- [x] 4.3 Ejecutar la suite de SEO pública (`php artisan test --filter=PublicSeo`) y verificar que sigue pasando sin regresiones (H1 único, meta tags, JSON-LD, sitemap)
- [x] 4.4 Comprobar en el navegador que la sección se ve correcta en móvil y escritorio (el bloque no desborda, el poster es visible antes de cargar el video, los pasos son legibles) y verificar con `curl -s -o /dev/null -w '%{http_code}'` que los cuatro MP4 y los cuatro posters responden 200 desde `public/`
- [x] 4.5 Verificar que el archivo fuente no quedó en el repositorio ni en `public/` (`find . -name 'simplescreenrecorder*' -not -path './node_modules/*'` no devuelve resultados) y que el `.gitignore` cubre `public/videos/` sólo si se decide no versionar los medios

## 5. Housekeeping de OpenSpec (previo al archivado)

- [x] 5.1 Normalizar `openspec/specs/landing/spec.md` cambiando el encabezado `## ADDED Requirements` residual por el encabezado canónico `## Requirements`, y verificar con `openspec validate landing-youtube-4-steps-video --strict` que desaparece el aviso de "Archive would refuse this delta"
- [x] 5.2 Verificar que la normalización no altera los requisitos existentes del spec `landing` (mismos encabezados, mismos escenarios) y que `openspec list --specs` sigue mostrando la capacidad `landing` con sus siete requisitos

## Workflow follow-up

- Archivar el cambio (`/opsx:archive-change`) una vez satisfied los requisitos de revisión del proyecto.
- Verificar el resultado archivado: el spec `landing` incluye los requisitos del walkthrough y `public-video-walkthrough` queda creado con su `## Purpose`.
- Decidir en un cambio posterior si el mismo bloque se conecta a `/restream-facebook-youtube-tiktok` y a las páginas verticales, y si se produce una versión narrada con subtítulos.
