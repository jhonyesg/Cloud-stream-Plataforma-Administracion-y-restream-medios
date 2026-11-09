# Tasks

## 1. Material

- [x] 1.1 Actualizar `scripts/video-walkthrough.source.json` con seis capítulos (`el-panel`, `conectar-cuenta`, `crear-destino`, `programar-ventana`, `varias-ventanas`, `encender-compartir`) y verificar que el manifiesto declara seis capítulos con segmentos dentro de la duración de la grabación
- [x] 1.2 Regenerar clips, capturas y video continuo con `php scripts/build-video-walkthrough.php`, y verificar que se producen 6 clips + 6 capturas + el video continuo con los offsets de cada capítulo en el manifiesto
- [x] 1.3 Revisar fotograma a fotograma las seis capturas publicadas contra `notes/sanitization-checklist.md` del change anterior, y verificar que ninguna muestra el nombre de la persona, su correo ni el stream key

## 2. Home

- [x] 2.1 Reescribir la sección como seis pasos (panel → conectar → destino → programar → varias ventanas → encender) con su captura, su texto y su offset de capítulo, y verificar que el HTML renderizado expone seis `data-image` y seis `data-start` en orden ascendente
- [x] 2.2 Actualizar el encabezado de la sección a "Emite a YouTube en 6 pasos" y el `VideoObject` (nombre, descripción y duración 73 s) y verificar que el JSON-LD sigue siendo válido y apunta al video continuo
- [x] 2.3 Quitar de la home las dos capturas estáticas antiguas y borrarlas de `public/images/demo/`, y verificar que ninguna vista sigue referenciándolas y que ya no están en disco
- [x] 2.4 Regenerar el bundle de Tailwind (`npm run build:css`) y limpiar vistas (`php artisan view:clear`), y verificar que cada clase nueva del bloque aparece en `public/css/tailwind.min.css`

## 3. Verificación

- [x] 3.1 Actualizar `tests/Feature/LandingYouTubeWalkthroughTest.php` para los seis pasos y añadir el escenario de ausencia de datos personales, y verificar que `php artisan test --filter=LandingYouTubeWalkthroughTest` pasa
- [x] 3.2 Ejecutar la suite de SEO pública (`php artisan test --filter=PublicSeo`) y verificar que sigue pasando sin regresiones
- [x] 3.3 Comprobar en producción que la home responde 200, que las seis capturas y el video continuo responden 200 y que el peso de medios de la sección no supera 6 MB
- [x] 3.4 Verificar que el HTML publicado no contiene nombre de persona, correo de cuenta ni stream key (`grep` sobre la respuesta de la home)
