<?php

/**
 * Genera los clips y posters del walkthrough público de YouTube.
 *
 * Uso:
 *   php scripts/build-video-walkthrough.php [--source=/ruta/al.mp4] [--out=public/videos/youtube-walkthrough]
 *
 * Lee `scripts/video-walkthrough.source.json` (segmentos, recortes, cajas de
 * redacción y parámetros de codificación) y produce, por cada capítulo:
 *   - public/videos/<slug>/<id>.mp4        clip H.264 sin audio, con faststart
 *   - public/images/demo/<slug>/<id>.jpg   poster del capítulo
 *
 * El archivo fuente NUNCA se copia ni se publica: solo se lee. Es material
 * de cliente, por eso vive fuera del repositorio.
 */

$root = dirname(__DIR__);

$options = getopt('', ['source::', 'out::', 'config::', 'poster-dir::']);
$configPath = $options['config'] ?? $root . '/scripts/video-walkthrough.source.json';

if (! is_file($configPath)) {
    fwrite(STDERR, "No se encontró la configuración: {$configPath}\n");
    exit(1);
}

$config = json_decode((string) file_get_contents($configPath), true);
if (! is_array($config)) {
    fwrite(STDERR, "Configuración inválida (JSON): {$configPath}\n");
    exit(1);
}

$source = $options['source'] ?? ($config['source_default'] ?? null);
if (! $source || ! is_file($source)) {
    fwrite(STDERR, "No se encontró la grabación fuente: " . ($source ?: '(sin definir)') . "\n");
    fwrite(STDERR, "Pásala con --source=/ruta/al/archivo.mp4\n");
    exit(1);
}

$slug = $config['slug'] ?? 'walkthrough';
$outDir = $options['out'] ?? $root . '/public/videos/' . $slug;
$posterDir = $options['poster-dir'] ?? $root . '/public/images/demo/' . $slug;
$tmpDir = sys_get_temp_dir() . '/walkthrough-build-' . getmypid();

@mkdir($outDir, 0755, true);
@mkdir($posterDir, 0755, true);
@mkdir($tmpDir, 0755, true);

$crop = $config['crop'] ?? ['w' => 1920, 'h' => 960, 'x' => 0, 'y' => 120];
$scale = $config['scale'] ?? ['w' => 1280, 'h' => 640];
$enc = $config['encode'] ?? ['crf' => 26, 'preset' => 'medium', 'pix_fmt' => 'yuv420p', 'gop' => 60, 'audio' => false];
$redactions = $config['redactions'] ?? [];

$font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

function run(array $cmd, string $label): void
{
    $cmd = array_map('strval', $cmd);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (! is_resource($proc)) {
        fwrite(STDERR, "[{$label}] no se pudo ejecutar el comando\n");
        exit(1);
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) {
        fwrite(STDERR, "[{$label}] ffmpeg terminó con código {$code}\n");
        fwrite(STDERR, $err . "\n");
        exit(1);
    }
}

/**
 * Construye la cadena de filtros de un segmento: redacción + recorte + escala.
 */
function filtersFor(array $segment, array $redactions, array $crop, array $scale, string $font): string
{
    $parts = [];

    // El recorte puede sobrescribirse por segmento (zoom a una zona concreta).
    $crop = $segment['crop'] ?? $crop;
    $scale = $segment['scale'] ?? $scale;

    foreach (($segment['redactions'] ?? []) as $key) {
        $box = $redactions[$key] ?? null;
        if (! $box) {
            continue;
        }

        if (($box['kind'] ?? 'blur') === 'cover') {
            $parts[] = sprintf(
                'drawbox=x=%d:y=%d:w=%d:h=%d:color=0x0d1428@0.98:t=fill',
                $box['x'], $box['y'], $box['w'], $box['h']
            );
            $parts[] = sprintf(
                'drawbox=x=%d:y=%d:w=%d:h=%d:color=0xa855f7@0.85:t=3',
                $box['x'], $box['y'], $box['w'], $box['h']
            );
            if (! empty($box['label']) && is_file($font)) {
                $parts[] = sprintf(
                    "drawtext=fontfile=%s:text='%s':fontcolor=0xc4b5fd:fontsize=22:x=%d:y=%d",
                    $font,
                    str_replace("'", '', (string) $box['label']),
                    $box['x'] + 30,
                    $box['y'] + 90
                );
            }
            continue;
        }

        $parts[] = sprintf(
            'delogo=x=%d:y=%d:w=%d:h=%d',
            $box['x'], $box['y'], $box['w'], $box['h']
        );
    }

    $parts[] = sprintf('crop=%d:%d:%d:%d', $crop['w'], $crop['h'], $crop['x'], $crop['y']);
    $parts[] = sprintf('scale=%d:%d', $scale['w'], $scale['h']);

    return implode(',', $parts);
}

echo "=== Walkthrough público: " . ($config['title'] ?? $slug) . " ===\n";
echo "Fuente : {$source}\n";
echo "Salida : {$outDir}\n\n";

$manifest = [];

foreach (($config['chapters'] ?? []) as $chapter) {
    $id = $chapter['id'];
    $clipPath = $outDir . '/' . $id . '.mp4';
    $posterPath = $posterDir . '/' . $id . '.jpg';
    $segments = $chapter['segments'] ?? [];

    if (! $segments) {
        fwrite(STDERR, "Capítulo {$id} sin segmentos, se omite\n");
        continue;
    }

    echo "— Capítulo {$id} (" . count($segments) . " segmento(s))\n";

    $parts = [];
    $index = 0;

    foreach ($segments as $segment) {
        $filter = filtersFor($segment, $redactions, $crop, $scale, $font);
        $part = sprintf('%s/part-%02d-%s.mp4', $tmpDir, $index, $id);

        $cmd = [
            'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y',
            '-ss', (string) $segment['start'],
            '-t', (string) $segment['duration'],
            '-i', $source,
            '-vf', $filter,
            '-c:v', 'libx264',
            '-preset', (string) ($enc['preset'] ?? 'medium'),
            '-crf', (string) ($enc['crf'] ?? 26),
            '-g', (string) ($enc['gop'] ?? 60),
            '-pix_fmt', (string) ($enc['pix_fmt'] ?? 'yuv420p'),
        ];

        if (empty($enc['audio'])) {
            $cmd[] = '-an';
        }

        $cmd = array_merge($cmd, [$part]);
        run($cmd, "segmento {$index} de {$id}");

        $parts[] = $part;
        $index++;
    }

    if (count($parts) === 1) {
        $part = $parts[0];
        run([
            'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $part,
            '-c', 'copy',
            '-movflags', '+faststart',
            $clipPath,
        ], "clip {$id}");
    } else {
        $listFile = $tmpDir . '/concat-' . $id . '.txt';
        file_put_contents($listFile, implode("\n", array_map(
            fn ($p) => "file '" . str_replace("'", "'\\''", $p) . "'",
            $parts
        )) . "\n");

        run([
            'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'concat', '-safe', '0', '-i', $listFile,
            '-c', 'copy',
            '-movflags', '+faststart',
            $clipPath,
        ], "clip {$id}");
    }

    // Poster: primer fotograma del capítulo, con las mismas redacciones.
    $posterSegment = $segments[0];
    $posterFilter = filtersFor($posterSegment, $redactions, $crop, $scale, $font);
    $posterAt = $chapter['poster_at'] ?? ($posterSegment['start'] + 1.5);

    run([
        'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y',
        '-ss', (string) $posterAt,
        '-i', $source,
        '-frames:v', '1',
        '-vf', $posterFilter,
        '-q:v', '3',
        $posterPath,
    ], "poster {$id}");

    $duration = 0;
    foreach ($segments as $segment) {
        $duration += (float) $segment['duration'];
    }

    $manifest[] = [
        'id' => $id,
        'order' => $chapter['order'] ?? 0,
        'duration_sec' => (int) round($duration),
        'media' => '/videos/' . $slug . '/' . $id . '.mp4',
        'poster' => '/images/demo/' . $slug . '/' . $id . '.jpg',
    ];

    printf(
        "   clip %s (%d KB) · poster %s (%d KB)\n",
        basename($clipPath),
        (int) (filesize($clipPath) / 1024),
        basename($posterPath),
        (int) (filesize($posterPath) / 1024)
    );
}

// Vídeo continuo: los capítulos concatenados en orden, para que el visitante
// pueda ver el flujo completo de un tirón y saltar a cualquier paso.
$fullPath = $outDir . '/walkthrough-completo.mp4';
$listFile = $tmpDir . '/concat-completo.txt';
file_put_contents($listFile, implode("\n", array_map(
    fn ($m) => "file '" . $outDir . '/' . basename($m['media']) . "'",
    $manifest
)) . "\n");

run([
    'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y',
    '-f', 'concat', '-safe', '0', '-i', $listFile,
    '-c', 'copy',
    '-movflags', '+faststart',
    $fullPath,
], 'video completo');

// El inicio de cada capítulo dentro del video continuo.
$offset = 0;
foreach ($manifest as &$chapter) {
    $chapter['start'] = $offset;
    $offset += (int) $chapter['duration_sec'];
}
unset($chapter);

foreach (glob($tmpDir . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($tmpDir);

$total = 0;
foreach (glob($outDir . '/*.mp4') ?: [] as $file) {
    $total += filesize($file);
}

file_put_contents(
    dirname($configPath) . '/video-walkthrough.manifest.json',
    json_encode([
        'slug' => $slug,
        'video' => '/videos/' . $slug . '/walkthrough-completo.mp4',
        'duration_sec' => $offset,
        'chapters' => $manifest,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

printf("\nVideo continuo: %s (%d KB, %d s)\n", basename($fullPath), (int) (filesize($fullPath) / 1024), $offset);
printf("Total publicado: %d KB en %s\n", (int) ($total / 1024), $outDir);
echo 'Manifiesto: ' . dirname($configPath) . "/video-walkthrough.manifest.json\n";
