<?php

namespace App\Services;

use App\Models\Channel;
use Throwable;

class MediaserverMetricsAggregator
{
    public function __construct(private readonly MediaserverApiService $api)
    {
    }

    public function available(): bool
    {
        return $this->api->isReachable();
    }

    public function forAdmin(?string $selectedStream): array
    {
        $streams = $this->api->getStreams();
        $channels = $this->channelsByStreamName();
        $rules = $this->rules();
        $globalByStream = $this->globalClientsByStream();

        $normalized = [];
        foreach ($streams as $stream) {
            $normalized[] = $this->normalize($stream, $channels, $rules);
        }

        $selected = null;
        if ($selectedStream !== null && $selectedStream !== '') {
            foreach ($normalized as $item) {
                if ($item['stream_name'] === $selectedStream) {
                    $selected = $this->withClients($item, $globalByStream);
                    break;
                }
            }
        }

        return [
            'streams' => $normalized,
            'rules' => $rules,
            'selected' => $selected,
        ];
    }

    public function forClient(array $channelIds): array
    {
        $channels = Channel::with('virtualScreen')
            ->whereIn('id', $channelIds)
            ->get();

        $streamNames = [];
        foreach ($channels as $channel) {
            $streamNames[$this->streamNameForChannel($channel)] = true;
        }

        $streams = $this->api->getStreams();
        $channelsByStream = $this->channelsByStreamName();
        $rules = $this->rules();
        $globalByStream = $this->globalClientsByStream();

        $normalized = [];
        foreach ($streams as $stream) {
            $name = (string) ($stream['name'] ?? '');
            if ($name === '' || ! isset($streamNames[$name])) {
                continue;
            }
            $normalized[] = $this->withClients($this->normalize($stream, $channelsByStream, $rules), $globalByStream);
        }

        return [
            'streams' => $normalized,
            'rules' => $rules,
        ];
    }

    private function channelsByStreamName(): \Illuminate\Support\Collection
    {
        $channels = Channel::with('virtualScreen')->get();

        $map = collect();
        foreach ($channels as $channel) {
            $map->put($this->streamNameForChannel($channel), $channel);
        }

        return $map;
    }

    private function streamNameForChannel(Channel $channel): string
    {
        $hls = (string) ($channel->public_hls_url ?? '');
        if ($hls !== '') {
            $path = (string) parse_url($hls, PHP_URL_PATH);
            $name = basename($path, '.m3u8');
            if ($name !== '' && $name !== $path) {
                return $name;
            }
        }

        $output = (string) ($channel->virtualScreen?->output_url ?? '');
        if ($output !== '' && preg_match('~^.*/live/([^/?#]+)~', $output, $m)) {
            return $m[1];
        }

        return (string) $channel->slug;
    }

    private function rules(): array
    {
        return [
            'blacklist' => $this->api->getBlacklist(),
            'geoblock' => $this->api->getGeoblock(),
            'client_rules' => $this->api->getClientRules(),
        ];
    }

    private function normalize(array $stream, \Illuminate\Support\Collection $channels, array $rules): array
    {
        $name = (string) ($stream['name'] ?? '');

        return [
            'stream_name' => $name,
            'channel' => $channels->get($name),
            'active' => (bool) ($stream['active'] ?? false),
            'health_state' => (string) ($stream['health_state'] ?? 'unknown'),
            'clients' => (int) ($stream['clients'] ?? 0),
            'kbps_recv' => (int) ($stream['kbps_recv'] ?? 0),
            'kbps_send' => (int) ($stream['kbps_send'] ?? 0),
            'video' => $stream['video'] ?? null,
            'audio' => $stream['audio'] ?? null,
            'srt_port' => $stream['srt_port'] ?? null,
            'uptime_ms' => (int) ($stream['uptime_ms'] ?? 0),
            'rules' => $this->rulesForStream($name, $rules),
            'viewers' => null,
        ];
    }

    private function rulesForStream(string $name, array $rules): array
    {
        $clientRules = array_values(array_filter($rules['client_rules'], function ($rule) use ($name) {
            $filter = $rule['stream_filter'] ?? [];
            return empty($filter) || in_array($name, $filter, true);
        }));

        return [
            'blocked' => in_array($name, $rules['blacklist'], true),
            'geoblock' => $rules['geoblock'][$name] ?? null,
            'client_rules' => $clientRules,
        ];
    }

    private function globalClientsByStream(): array
    {
        try {
            $clients = $this->api->getClients();
        } catch (Throwable) {
            return [];
        }

        $byStream = [];
        foreach ($clients as $client) {
            $stream = (string) ($client['stream'] ?? '');
            if ($stream === '') {
                continue;
            }
            $byStream[$stream][] = $client;
        }

        return $byStream;
    }

    private function withClients(array $item, array $globalByStream = []): array
    {
        if ($item['stream_name'] === '') {
            return $item;
        }

        try {
            $clients = $this->api->getStreamClients($item['stream_name']);
        } catch (Throwable) {
            $clients = [];
        }

        $viewers = array_values(array_filter($clients, fn ($c) => empty($c['publish'])));

        $ips = [];
        $agents = [];
        foreach ($viewers as $client) {
            $ip = (string) ($client['ip'] ?? '');
            if ($ip !== '') {
                $ips[$ip] = true;
            }
            $type = (string) ($client['type'] ?? 'desconocido');
            $agents[$type] = ($agents[$type] ?? 0) + 1;
        }
        arsort($agents);

        $global = $globalByStream[$item['stream_name']] ?? [];
        $globalByIp = [];
        foreach ($global as $client) {
            $ip = (string) ($client['ip'] ?? '');
            if ($ip === '') {
                continue;
            }
            $globalByIp[$ip] = $client;
        }

        $list = [];
        foreach ($viewers as $client) {
            $ip = (string) ($client['ip'] ?? '');
            $globalClient = $globalByIp[$ip] ?? null;
            $list[] = [
                'ip' => $ip,
                'country' => $globalClient['country'] ?? null,
                'user_agent' => $globalClient['user_agent'] ?? null,
                'type' => (string) ($client['type'] ?? 'desconocido'),
                'alive_seconds' => (float) ($client['alive'] ?? $client['alive_seconds'] ?? 0),
                'kbps_send_30s' => (int) ($globalClient['kbps_send_30s'] ?? $client['kbps']['send_30s'] ?? 0),
                'kbps_recv_30s' => (int) ($globalClient['kbps_recv_30s'] ?? $client['kbps']['recv_30s'] ?? 0),
            ];
        }

        usort($list, fn ($a, $b) => $b['alive_seconds'] <=> $a['alive_seconds']);

        $item['viewers'] = [
            'total' => count($viewers),
            'unique_ips' => count($ips),
            'agents' => $agents,
            'list' => $list,
        ];

        return $item;
    }
}
