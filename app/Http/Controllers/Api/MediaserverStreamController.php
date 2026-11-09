<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\MediaserverApiException;
use App\Services\MediaserverApiService;
use App\Services\MediaserverStreamResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * URLs de publicación y reproducción de un canal, tomadas de MediaServer
 * Platform (fuente de verdad) y normalizadas en filas para el modal de
 * Programación.
 *
 * Alcance: admin ve cualquier canal; client solo los de su
 * effectiveChannelIds() (patrón authorizeAccess de VirtualScreenController).
 */
class MediaserverStreamController extends Controller
{
    /**
     * Etiquetas de cada URL conocida del panel. Un protocolo nuevo que
     * agregue la plataforma aparece con una etiqueta genérica, sin tocar el
     * frontend.
     */
    private const LABELS = [
        'rtmp' => 'RTMP (OBS, vMix, Wirecast)',
        'hls' => 'HLS',
        'flv' => 'HTTP-FLV',
        'webrtc_play' => 'WebRTC',
        'mpegts' => 'MPEG-TS',
    ];

    public function __construct(
        private readonly MediaserverApiService $api,
        private readonly MediaserverStreamResolver $resolver,
    ) {
    }

    public function show(Request $request, string $channelId): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $this->authorizeAccess($request, $channel);

        $streamName = $this->resolver->streamNameForChannel($channel);

        $payload = [
            'channel' => [
                'id' => $channel->id,
                'name' => $channel->display_name,
            ],
            'stream' => $streamName,
            'available' => false,
            'live' => false,
            'clients' => 0,
            'rows' => [],
            'urls' => [],
            'message' => null,
        ];

        try {
            $stream = $this->api->getStream($streamName);
        } catch (MediaserverApiException $e) {
            return response()->json($this->degraded($payload, $channel, $e));
        }

        $urls = is_array($stream['urls'] ?? null) ? $stream['urls'] : [];

        $payload['available'] = true;
        $payload['live'] = (bool) ($stream['publish']['active'] ?? false);
        $payload['clients'] = (int) ($stream['clients'] ?? 0);
        $payload['urls'] = $urls;
        $payload['rows'] = $this->rows($urls);
        $payload['message'] = $payload['rows'] === []
            ? 'MediaServer Platform no devolvió URLs para este stream.'
            : null;

        return response()->json($payload);
    }

    /**
     * Normaliza el bloque `urls` del panel en filas planas.
     *
     * Superficie de cliente: se publica **solo la URL RTMP** y la
     * reproducción **solo por dominio** (HTTPS/HTTP/SRT). Las URLs directas
     * al servidor por IP+puerto quedan fuera del modal: exponen la IP
     * pública del servidor y no son las que consume el cliente final.
     *
     * @return array<int, array{group: string, label: string, url: string, open: bool}>
     */
    private function rows(array $urls): array
    {
        $publish = is_array($urls['publish'] ?? null) ? $urls['publish'] : [];
        $play = is_array($urls['play'] ?? null) ? $urls['play'] : [];
        $proxy = is_array($play['via_proxy'] ?? null) ? $play['via_proxy'] : [];

        $rows = [];

        // PUBLICAR — solo RTMP: es lo que pega el operador en OBS, vMix o
        // Wirecast. El SRT de publicación no se ofrece por el modal.
        $rtmp = (string) ($publish['rtmp'] ?? '');
        if ($rtmp !== '') {
            $rows[] = [
                'group' => 'publish',
                'label' => self::LABELS['rtmp'],
                'url' => $rtmp,
                'open' => false,
            ];
        }

        // REPRODUCIR vía dominio — la única vía de consumo que ve el cliente.
        foreach (['https', 'http'] as $scheme) {
            $bucket = is_array($proxy[$scheme] ?? null) ? $proxy[$scheme] : [];
            foreach (['hls', 'flv', 'webrtc_play', 'mpegts'] as $key) {
                $url = (string) ($bucket[$key] ?? '');
                if ($url === '') {
                    continue;
                }
                $rows[] = [
                    'group' => 'proxy',
                    'label' => self::LABELS[$key] . ' (' . strtoupper($scheme) . ')',
                    'url' => $url,
                    'open' => true,
                ];
            }
        }

        $proxySrt = (string) ($proxy['srt'] ?? '');
        if ($proxySrt !== '') {
            $rows[] = [
                'group' => 'proxy',
                'label' => 'SRT (lectura, dominio)',
                'url' => $proxySrt,
                'open' => false,
            ];
        }

        return $rows;
    }

    /**
     * Estado degradado: la plataforma no conoce el stream (el canal no está
     * emitiendo) o la API no responde. Se responde 200 con el motivo y, si
     * existe, la URL pública que Cloudstream ya tiene configurada.
     */
    private function degraded(array $payload, Channel $channel, MediaserverApiException $e): array
    {
        if ($e->statusCode === 404) {
            $payload['message'] = 'Este canal todavía no tiene stream en MediaServer Platform. '
                . 'Las URLs de la plataforma aparecen cuando el canal publica.';
        } elseif ($e->detail === 'unreachable') {
            $payload['message'] = 'MediaServer Platform no está disponible en este momento.';
        } else {
            $payload['message'] = $e->getMessage();
        }

        $publicHls = (string) ($channel->public_hls_url ?? '');
        if ($publicHls !== '') {
            $payload['rows'][] = [
                'group' => 'channel',
                'label' => 'URL pública configurada en Cloudstream',
                'url' => $publicHls,
                'open' => true,
            ];
        }

        return $payload;
    }

    private function authorizeAccess(Request $request, Channel $channel): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if ($user->isAdmin()) {
            return;
        }
        if (! $user->canAccessChannel($channel)) {
            abort(403, 'No tiene acceso a este canal.');
        }
    }
}
