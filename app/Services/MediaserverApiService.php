<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MediaserverApiService
{
    private const CACHE_KEY = 'mediaserver_jwt';
    private const CACHE_TTL_SECONDS = 12 * 3600;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiUser,
        private readonly string $apiPassword,
        private readonly int $httpTimeout = 5,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            apiUrl: (string) config('mediaserver.api_url', ''),
            apiUser: (string) config('mediaserver.api_user', ''),
            apiPassword: (string) config('mediaserver.api_password', ''),
            httpTimeout: (int) config('mediaserver.http_timeout', 5),
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl !== '' && $this->apiUser !== '' && $this->apiPassword !== '';
    }

    public function isReachable(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        try {
            $response = $this->http()->timeout(2)->get(rtrim($this->apiUrl, '/') . '/api/health');
            if (! $response->successful()) {
                return false;
            }
            $payload = $response->json();
            return is_array($payload) && (($payload['srs_alive'] ?? false) === true || ($payload['status'] ?? null) === 'ok');
        } catch (Throwable) {
            return false;
        }
    }

    public function getStreams(): array
    {
        $response = $this->request('GET', '/api/streams/', ['only_active' => 'false']);
        return is_array($response['streams'] ?? null) ? $response['streams'] : [];
    }

    public function getClients(): array
    {
        $response = $this->request('GET', '/api/admin/srs/clients', []);
        return is_array($response['clients'] ?? null) ? $response['clients'] : [];
    }

    public function getStreamClients(string $name): array
    {
        $response = $this->request('GET', "/api/streams/{$name}/clients", []);
        return is_array($response['clients'] ?? null) ? $response['clients'] : [];
    }

    public function getDiagnostics(): array
    {
        $response = $this->request('GET', '/api/diagnostics/streams', []);
        return is_array($response['streams'] ?? null) ? $response['streams'] : [];
    }

    public function getBlacklist(): array
    {
        $response = $this->request('GET', '/api/rules/blacklist', []);
        return is_array($response['blocked'] ?? null) ? $response['blocked'] : [];
    }

    public function getGeoblock(): array
    {
        $response = $this->request('GET', '/api/rules/geoblock', []);
        return is_array($response['rules'] ?? null) ? $response['rules'] : [];
    }

    public function getClientRules(): array
    {
        $response = $this->request('GET', '/api/rules/clients', []);
        return is_array($response['rules'] ?? null) ? $response['rules'] : [];
    }

    public function getSrtStatus(): array
    {
        return $this->request('GET', '/api/srt/status', []);
    }

    public function getHealth(): array
    {
        return $this->request('GET', '/api/health', []);
    }

    public function kickClient(int $cid): bool
    {
        $response = $this->request('POST', "/api/admin/srs/clients/{$cid}/kick", []);
        return ($response['code'] ?? null) === 0;
    }

    private function login(): string
    {
        if (! $this->isConfigured()) {
            throw MediaserverApiException::unreachable($this->apiUrl);
        }
        try {
            $response = $this->http()->post(rtrim($this->apiUrl, '/') . '/api/auth/login', [
                'username' => $this->apiUser,
                'password' => $this->apiPassword,
            ]);
        } catch (ConnectionException $e) {
            throw MediaserverApiException::unreachable($this->apiUrl, $e);
        }

        if ($response->status() === 401) {
            throw MediaserverApiException::authFailed('/api/auth/login', $response->body());
        }
        if (! $response->successful()) {
            throw MediaserverApiException::panelError('/api/auth/login', $response->status(), $response->body());
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new MediaserverApiException(
                message: 'MediaServer Platform no devolvió access_token en /api/auth/login',
                statusCode: $response->status(),
                endpoint: '/api/auth/login',
                body: $response->body(),
                detail: 'auth_response_invalid',
            );
        }
        return $token;
    }

    private function jwt(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->login();
        });
    }

    private function invalidateJwt(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        if (! $this->isConfigured()) {
            throw MediaserverApiException::unreachable($this->apiUrl . $path);
        }
        $url = rtrim($this->apiUrl, '/') . $path;
        $attempt = function () use ($method, $url, $payload): Response {
            return $this->http()->withToken($this->jwt())->send($method, $url, [
                $method === 'GET' ? 'query' : 'json' => $payload,
            ]);
        };

        try {
            $response = $attempt();
        } catch (ConnectionException $e) {
            throw MediaserverApiException::unreachable($url, $e);
        }

        if ($response->status() === 401) {
            $this->invalidateJwt();
            try {
                $response = $attempt();
            } catch (ConnectionException $e) {
                throw MediaserverApiException::unreachable($url, $e);
            }
            if ($response->status() === 401) {
                throw MediaserverApiException::authFailed($path, $response->body());
            }
        }

        if (! $response->successful()) {
            $this->logFailure($method, $path, $response);
            throw MediaserverApiException::panelError($path, $response->status(), $response->body());
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new MediaserverApiException(
                message: "MediaServer Platform devolvió respuesta no-JSON en {$path}",
                statusCode: $response->status(),
                endpoint: $path,
                body: $response->body(),
                detail: 'invalid_json',
            );
        }
        return $body;
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->httpTimeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['User-Agent' => 'Cloudstream-Emision/1.0']);
    }

    private function logFailure(string $method, string $path, Response $response): void
    {
        Log::warning('Mediaserver API non-2xx', [
            'method' => $method,
            'path' => $path,
            'status' => $response->status(),
            'body_preview' => substr($response->body(), 0, 500),
        ]);
    }
}
