## Context

El panel admin de Cloudstream permite configurar la salida virtual de un canal
(`VirtualScreen`: protocolo rtmp/srt/hls, output_url, codecs, bitrate, logo,
fallback) y arrancar/detener la emisión (`EmissionState.status`). Pero para
"ver" lo que está saliendo solo hay dos caminos: pegar el `.m3u8` en VLC, o
confiar en un preview externo. Desde el panel no se puede:

- Confirmar visualmente que "Iniciar transmisión" realmente publicó stream.
- Diagnosticar si un canal configurado como HLS está sirviendo bien.
- Hacer QA rápido del logo/fallback/resolución sin abrir otra herramienta.

Adicionalmente, las acciones de la tabla `admin/channels` son texto con color
(Editar / Pantalla / Archivar) que rompe el lenguaje visual de iconos
circulares ya usado en el calendario del scheduler
(`resources/views/admin/scheduler/index.blade.php:258-290`). Y en el bloque
de control de emisión del scheduler no hay ningún atajo a "ver".

La salida HLS es servida por nginx-rtmp fuera de Laravel
(`https://canal.mediaserver.com.co/live/{slug}.m3u8`), por lo que la app
solo necesita conocer la URL pública y opcionalmente consultar
`VirtualScreen.output_url` (que ya se rellena al guardar la pantalla virtual).

No hay `package.json` ni build pipeline (Laravel clásico con Tailwind por CDN
y Alpine por CDN), por lo que cualquier librería JS se carga on-demand desde
CDN dentro del componente que la necesita.

## Goals / Non-Goals

**Goals:**
- Dar a los admins un preview de la emisión HLS de un canal sin salir del panel.
- Reusar `VirtualScreen.output_url` como fuente de verdad de la URL pública;
  si no hay HLS configurado, mostrar un CTA claro para configurarlo.
- Reemplazar los 3 botones de acción de `admin/channels` por iconos circulares
  consistentes con el calendario del scheduler.
- Añadir un botón "Ver en vivo" en el bloque de control de emisión del
  scheduler, con estado visual según `EmissionState.status`.
- Reproducir HLS en cualquier navegador moderno: Chrome/Firefox/Edge vía
  HLS.js desde CDN, Safari/iOS vía `<video>` nativo (HLS ya soportado).
- Cero migraciones, cero nuevas rutas Laravel, cero nuevos endpoints.

**Non-Goals:**
- Sustituir al preview interno de nginx-rtmp o a un player externo: este
  modal es un atajo, no la fuente de verdad del viewer público.
- Grabar / descargar el stream.
- Player con chat, DVR, multi-calidad, PiP, o AirPlay. Si se necesitan más
  adelante, se hace como enhancement.
- Añadir autenticación al `.m3u8`; ese control sigue siendo de nginx-rtmp.
- Internacionalizar el modal (se queda en español como el resto del admin).

## Decisions

### 1. Cargar HLS.js desde CDN, on-demand

- **Decisión**: Cuando el modal se abre por primera vez, se inyecta
  dinámicamente `<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13">`
  y se cachea en `window.Hls` para aperturas siguientes.
- **Por qué**: No hay `package.json` ni build pipeline (Laravel clásico).
  Cargar de CDN es la convención del proyecto para librerías que no trae
  Tailwind/Alpine (que ya vienen por CDN en `admin-layout.blade.php`).
- **Alternativas**:
  - Hospedar `hls.min.js` en `public/js/` y referenciarlo desde el modal.
    Más "self-hosted", pero añade un asset binario al repo y obliga a
    actualizar manualmente con cada release upstream.
  - Usar `video.js` o `shaka-player`. Más pesados, overkill para un preview.
- **Fallback**: Si la inyección del script falla (sin red, CSP, etc.),
  se renderiza `<video src={hls_url} controls>` y se delega en la
  reproducción nativa (Safari/iOS funciona; en Chrome/Firefox el navegador
  descargará el `.m3u8` en vez de reproducirlo, y el modal mostrará
  un mensaje "Tu navegador no soporta HLS embebido; ábrelo en pestaña nueva").

### 2. Resolución de la URL HLS

- **Decisión**: API call `GET /api/virtual-screens/{channelId}` (ya existe,
  usada por el editor). Si `output_protocol === 'hls'` y `output_url`
  presente y no vacío, se usa esa URL. Si no, se muestra el estado
  "Sin salida HLS configurada" con un botón "Configurar pantalla virtual"
  que abre `$store.modals.open('virtual-screen-editor', { channel_id })`.
- **Por qué**: La `VirtualScreen` ya es la fuente de verdad del "qué
  protocolo y qué URL pública" del canal; no queremos otra config paralela.
- **Alternativas**:
  - Hardcodear `https://canal.mediaserver.com.co/live/{slug}.m3u8` con
    una env var. Funciona pero acopla la app al dominio público y pierde
    la capacidad de apuntar a otro CDN/dominio por canal.
  - Construir desde `request()->getSchemeAndHttpHost()`. Asume mismo host,
    pero el admin suele vivir en un dominio distinto al de la CDN.

### 3. Estilo de los botones de Canales

- **Decisión**: Iconos circulares `w-9 h-9` con `bg-gradient-to-br` y `hover:scale-110`,
  idénticos a los del calendario del scheduler (líneas 258-290). Color por
  intención:
  - ✏ Editar: teal-400 → teal-600
  - 🖥 Pantalla: teal-400 → teal-600 (igual; ya existía)
  - 👁 Ver vivo: sky-400 → sky-600
  - 🗑 Archivar: red-400 → red-600 (solo si `status !== 'archived'`)
- **Por qué**: Es el lenguaje visual que ya pasó QA visual del calendario;
  aplicarlo a Canales unifica la marca sin reinventar tokens.
- **Alternativa descartada**: "pills" con icono + texto. Probada en otras
  vistas, ocupa mucho ancho en tablas con muchas columnas (Resolución +
  Asignados + Acciones ya consumen la fila). En el calendario del
  scheduler funciona porque son celdas compactas; en una tabla densa no.

### 4. Botón "Ver vivo" en scheduler — estado visual

- **Decisión**: Siempre visible (descubrimiento). Habilitado si
  `emission_state.status === 'live'` o `'starting'`; deshabilitado con
  `opacity-50 cursor-not-allowed` si `'offline'` o `'error'`.
- **Por qué**: Que aparezca deshabilitado indica al usuario "esta función
  existe pero ahora no aplica", mejor que aparecer/desaparecer.
- **Texto contextual**: cuando está habilitado y en aire, el tooltip dice
  "Abrir el reproductor en vivo". Cuando está deshabilitado: "No hay
  emisión activa".

### 5. Sin nueva ruta Laravel, sin nuevo endpoint

- **Decisión**: El modal usa las APIs ya existentes
  (`/api/virtual-screens/{id}` y `/api/channels/{id}/emission/status`).
- **Por qué**: Mantiene la superficie de ataque y el contrato externos sin
  cambios. El modal es 100% cliente.

## Risks / Trade-offs

- [HLS.js desde CDN añade dependencia externa] → Usar versión pineada
  (`@1.5.13`, no `@latest`) para evitar breaking changes surprises. El
  proyecto ya depende de CDNs (`tailwind.js`, `alpine.min.js` locales
  servidos desde `public/js`); no es un salto de fe nuevo.
- [Safari/iOS reproduce HLS nativamente sin HLS.js] → Si Hls.js se carga,
  priorizamos Hls.js porque permite buffering/levels/estadísticas; el
  fallback a nativo se activa solo si la inyección falla.
- [El modal hace 2 fetch en paralelo (VirtualScreen + status) al abrir] →
  Si la red está lenta, el usuario ve "Cargando…" <1s. Aceptable; no
  pre-cacheamos para no acoplar el ciclo de vida del modal al store global.
- [Botón "Ver vivo" en channels/index siempre visible, incluso para canales
  archivados] → Decisión: mostrarlo deshabilitado en `archived` también
  (consistente con "offline" en el scheduler). Mantiene simetría.
- [El modal reproduce audio del stream] → El `<video>` arranca `muted` por
  defecto (política de autoplay de Chrome); hay botón explícito de unmute.
  Esto evita sorpresas auditivas en un panel admin.
- [No testeamos automated del reproductor HLS] → El smoke test es manual:
  abrir el modal, ver que el video carga, ver que el botón "abrir en
  nueva pestaña" funciona. No hay infra de browser-testing en el repo.

## Migration Plan

No hay migración de datos ni cambios destructivos. Despliegue = merge +
deploy normal.

Rollback: revertir el merge.

## Open Questions

- (ninguna — todas las decisiones se resolvieron en la fase de exploración
  con el usuario).