<?php

namespace App\Domain\Payments\Snippe;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class SnippeClient
{
    /** @param array<string, mixed> $payload */
    public function create(array $payload, string $key): SessionEvidence
    {
        if (strlen($key) > 30 || $key === '') {
            throw new SnippeException('invalid_attempt_key', false);
        }

        return SessionEvidence::fromArray($this->request('POST', '/api/v1/sessions', $payload, $key));
    }

    public function get(string $reference): SessionEvidence
    {
        return SessionEvidence::fromArray($this->request('GET', '/api/v1/sessions/'.rawurlencode($reference)));
    }

    public function cancel(string $reference, string $key): void
    {
        if ($key === '' || strlen($key) > 30) {
            throw new SnippeException('invalid_attempt_key', false);
        }
        $this->request('POST', '/api/v1/sessions/'.rawurlencode($reference).'/cancel', [], $key, false);
    }

    public function findAttempt(string $key): ?SessionEvidence
    {
        $found = null;
        // Bounded discovery only; absence is never proof that creation failed.
        for ($page = 0; $page < 5; $page++) {
            $rows = $this->request('GET', '/api/v1/sessions?limit=100&offset='.($page * 100));
            foreach ($rows as $row) {
                if (is_array($row) && ($row['metadata']['payment_attempt'] ?? null) === $key) {
                    if ($found !== null) {
                        throw new SnippeException('multiple_remote_sessions');
                    }
                    $found = SessionEvidence::fromArray($row);
                }
            }
            if (count($rows) < 100) {
                break;
            }
        }

        return $found;
    }

    /** @param array<string, mixed> $payload
     * @return array<string|int, mixed>
     */
    public function request(string $method, string $path, array $payload = [], ?string $key = null, bool $dataRequired = true, bool $retryGet = true): array
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Provider calls must be outside database transactions.');
        }
        $base = rtrim((string) config('snippe.base_url'), '/');
        if (! config('snippe.enabled') || blank(config('snippe.api_key')) || parse_url($base, PHP_URL_SCHEME) !== 'https' || parse_url($base, PHP_URL_USER) !== null) {
            throw new SnippeException('configuration', false);
        }
        // Session-specific create idempotency is not documented. Never auto-retry POST.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $request = Http::acceptJson()->asJson()->withToken(config('snippe.api_key'))->connectTimeout(5)->timeout(15)->withoutRedirecting();
                if ($key !== null) {
                    $request = $request->withHeaders(['Idempotency-Key' => $key]);
                }
                $response = $request->send($method, $base.$path, $method === 'GET' ? [] : ['json' => $payload]);
            } catch (ConnectionException) {
                if ($method === 'GET' && $retryGet && $attempt < 2) {
                    usleep(100000 * (2 ** $attempt));

                    continue;
                }
                throw new SnippeException('network_unknown');
            }
            if ($response->status() === 429) {
                $delay = max(60, min(3600, (int) $response->header('Retry-After')));
                $reset = $response->header('X-Ratelimit-Reset');
                if (ctype_digit($reset)) {
                    $delay = max($delay, min(3600, (int) $reset));
                }
                throw new SnippeException('rate_limited', true, $delay);
            }
            if ($response->serverError() && $method === 'GET' && $retryGet && $attempt < 2) {
                usleep(100000 * (2 ** $attempt));

                continue;
            }
            if (! $response->successful()) {
                throw new SnippeException('http_'.$response->status(), ! in_array($response->status(), [400, 401, 403, 404], true));
            }
            if (! $dataRequired) {
                return [];
            }
            $data = $response->json('data');
            if (! is_array($data)) {
                throw new SnippeException('malformed_response');
            }

            return $data;
        }
        throw new SnippeException('network_unknown');
    }
}
