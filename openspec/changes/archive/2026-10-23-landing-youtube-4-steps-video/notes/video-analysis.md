# Análisis del video fuente (referencia de producción)

Archivo de origen: `simplescreenrecorder-2026-10-22_17.01.06.mp4` (grabado con Simple Screen Recorder).

## Metadata

| Campo | Valor |
|---|---|
| Contenedor | mp4 (isom/iso2avc1/mp41), `Lavf60.16.100` |
| Video | H.264 High, 1920×1080, 30 fps, yuv420p, bt709, **595 kb/s** |
| Audio | Vorbis 48 kHz estéreo, 105 kb/s — `mean_volume −45.5 dB`, `max_volume −20.4 dB` (ruido de sistema; **no hay narración utilizable**) |
| Duración | 346,1 s (5:46) |
| Tamaño | 30.669.301 B (30,7 MB) — overall 708 kb/s |

## Mapa del contenido (fotogramas verificados)

```
0:00 ─ 0:12   Panel Restream (cliente): selector de canal "Prueba Stream",
              badge "Slots (Prueba Stream): 1/1", bloque "Cuentas conectadas"
              → "Youtube [Conectar]" | "Facebook [Conectar]"
0:12 ─ 0:30   Click en "Conectar" → OAuth Google: "Selecciona una cuenta"
              → "Cloudstream Restream quiere acceder a tu cuenta de Google"
              → vuelve al panel: "Youtube · Efrain Suarez (Jhon Suarez) [Desconectar]"
0:30 ─ 0:45   Panel admin + consola de logs del daemon (ruido para el público)
0:45 ─ 1:00   "+ Nuevo destino" → modal "Destino de Restream": Plataforma=YouTube,
              Nombre, toggle "Usar mi cuenta de … · Conectada",
              "Activar al guardar (consume un slot)"
1:00 ─ 2:45   Pestaña "Programación" → calendario → modal "Nueva ventana de emisión":
              Inicio/Fin, presets +30m…+24h, Duración, Zona horaria (America/Bogota),
              Título de la emisión, Privacidad, Conservar la grabación, Miniatura, Notas.
              Incluye la validación "Fin debe ser después del inicio" y correcciones.
2:45 ─ 3:15   Listado de destinos con estado y columna Programación
3:15 ─ 5:00   YouTube: canal/broadcast en vivo, YouTube Studio, chat en vivo
5:00 ─ 5:46   Panel admin con la consola de logs (broadcast creado → arranque → health)
              y el reproductor de YouTube con el programa y el chat en vivo
```

## Geometría del recorte

- Cromo del navegador (pestañas + barra de direcciones): ~105 px verticales.
- Opción A (16:9, recorta el menú lateral): `crop=1734:975:93:105` → 1280×720.
- Opción B (2:1, conserva el menú lateral de la app): `crop=1920:960:0:120` → 1280×640. **Es la elegida**: el menú lateral muestra que el visitante está dentro del módulo *Restream*.

## Medición de peso (recorte de prueba, CRF 26, sin audio)

| Tramo | Duración | Tamaño |
|---|---|---|
| 0:05–0:29 (conectar cuenta) | 24 s | 298 KB |
| 0:41–0:59 (crear destino) | 18 s | 266 KB |
| 1:03–2:00 (programar ventana) | 57 s | 507 KB |
| 3:15–3:41 (emisión en YouTube) | 26 s | 218 KB |
| 5:20–5:46 (chat en vivo) | 26 s | 2.062 KB |
| **Total** | **2:37** | **3,3 MB** |

Corte original: 30,7 MB → **×9 más liviano** después de recortar y recodificar.

## Inventario de datos que impiden publicar el original

- ✉️ correo personal `jhonyesg@gmail.com` (selección de cuenta de Google, ~0:15–0:25)
- 👤 nombre de la persona y de su canal ("Efrain Suarez (Jhon Suarez)", "El Canal Sin Filtros", "Prueba Stream") — visibles en el encabezado, en el modal de destino y en YouTube
- 🌐 pestañas del navegador ajenas al producto (Platzi, Hostinginger, AnimaFLV, "Contrator", Gmail, Tcloud)
- 📺 contenido de terceros: un noticiero real ("DANIELA PASÓ 36 HORAS ATRAPADA") con su chat en vivo

Por eso el bloque del plan exige un *gate* de sanitización con checklist y firma antes de publicar (ver `specs/public-video-walkthrough/spec.md`).
