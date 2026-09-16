<?php

// Read-only preflight. No payment lifecycle service, scheduler or mutation endpoint is called.
use App\Domain\Payments\Snippe\SnippeClient;
use App\Domain\Payments\Snippe\SnippeException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
require __DIR__.'/../../vendor/autoload.php';

function emitPreflight(array $result): void
{
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
}

try {
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $mode = $argv[1] ?? 'inspect';
    if ($mode === 'inspect') {
        $route = app('router')->getRoutes()->getByName('snippe.webhook');
        $origin = rtrim((string) config('app.url'), '/');
        // Refuse to display a URL containing credentials, query strings or fragments.
        $parts = parse_url($origin);
        $safeOrigin = is_array($parts) && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment']);
        $secret = config('snippe.webhook_secret');
        $forgery = app(PreventRequestForgery::class);
        emitPreflight([
            'snippe_enabled' => config('snippe.enabled') === true,
            'base_url_expected' => config('snippe.base_url') === 'https://api.snippe.sh',
            'api_key_present' => is_string(config('snippe.api_key')) && filled(config('snippe.api_key')),
            'webhook_secret_present' => is_string($secret) && filled($secret),
            'webhook_secret_verifier_shape_valid' => is_string($secret) && $secret !== '',
            'app_url' => $safeOrigin ? $origin : 'unsafe_to_display',
            'generated_webhook_url' => $safeOrigin && $route ? $origin.route('snippe.webhook', [], false) : null,
            'app_url_https' => ($parts['scheme'] ?? '') === 'https',
            'config_cached' => $app->configurationIsCached(),
            'webhook_route_exists' => $route !== null,
            'webhook_accepts_post' => $route && in_array('POST', $route->methods(), true),
            'webhook_middleware' => $route ? app('router')->gatherRouteMiddleware($route) : [],
            'webhook_csrf_excluded' => in_array('webhooks/snippe', $forgery->getExcludedPaths(), true),
            'custom_ca_bundle_exists' => is_file(storage_path('ssl/cacert.pem')),
            'php_curl_cainfo_configured' => ini_get('curl.cainfo') !== '',
            'php_openssl_cafile_configured' => ini_get('openssl.cafile') !== '',
        ]);
        exit;
    }
    if (! in_array($mode, ['tls', 'auth'], true) || config('snippe.base_url') !== 'https://api.snippe.sh') {
        emitPreflight(['check' => 'refused', 'reason' => 'mode_or_origin_not_allowlisted']);
        exit(1);
    }
    if ($mode === 'tls') {
        $customCa = realpath((string) ini_get('curl.cainfo')) === realpath(storage_path('ssl/cacert.pem'));
        try {
            // No token and no custom verify option. Uses the application's Laravel/Guzzle stack.
            $response = Http::acceptJson()->connectTimeout(5)->timeout(15)->withoutRedirecting()->get('https://api.snippe.sh');
            emitPreflight(['check' => 'tls_connectivity', 'verified' => true, 'http_status' => $response->status(), 'custom_ca_used' => $customCa]);
        } catch (ConnectionException $exception) {
            $errno = null;
            $verifyResult = null;
            $issuerMissing = false;
            $cause = $exception;
            do {
                $issuerMissing = $issuerMissing || str_contains($cause->getMessage(), 'unable to get local issuer certificate');
                if (method_exists($cause, 'getHandlerContext')) {
                    $errno = $cause->getHandlerContext()['errno'] ?? $errno;
                    $verifyResult = $cause->getHandlerContext()['ssl_verify_result'] ?? $verifyResult;
                }
            } while ($cause = $cause->getPrevious());
            emitPreflight(['check' => 'tls_connectivity', 'verified' => false, 'curl_errno' => $errno,
                'ssl_verify_result' => $verifyResult, 'unable_to_get_local_issuer_certificate' => $issuerMissing,
                'certificate_trust_failure' => $errno === 60, 'custom_ca_used' => $customCa]);
        }
        exit;
    }
    $status = null;
    $missingScope = null;
    $retryAfter = null;
    Event::listen(ResponseReceived::class, function (ResponseReceived $event) use (&$status, &$missingScope, &$retryAfter): void {
        $status = $event->response->status();
        if ($status === 403) {
            $message = $event->response->json('message');
            // Only a documented scope identifier may leave this process, never a raw message/body.
            if (is_string($message) && preg_match('/required scope:\s*(collection:(?:read|create)|disbursement:(?:read|create))\b/', $message, $match)) {
                $missingScope = $match[1];
            }
        }
        if ($status === 429) {
            $header = $event->response->header('Retry-After');
            $retryAfter = ctype_digit($header) ? (int) $header : null;
        }
    });
    try {
        // A single bounded read via the existing client. No automatic GET retries or response logging.
        app(SnippeClient::class)->request('GET', '/v1/payments?limit=1&offset=0', retryGet: false);
        emitPreflight(['check' => 'collection_read', 'http_status' => $status, 'authentication_accepted' => true,
            'collection_read' => true, 'collection_create' => 'not_determined_by_read', 'account_data_reported' => false]);
    } catch (SnippeException $exception) {
        emitPreflight(['check' => 'collection_read', 'http_status' => $status, 'authentication_accepted' => false,
            'collection_read' => false, 'collection_create' => 'unverified', 'reason' => $exception->reason,
            'missing_scope' => $missingScope, 'retry_after_seconds' => $retryAfter, 'account_data_reported' => false]);
    }
} catch (Throwable) {
    // Never emit exception text, request objects, response bodies, arguments or stack traces.
    emitPreflight(['check' => 'preflight_error', 'details_suppressed' => true]);
    exit(1);
}
