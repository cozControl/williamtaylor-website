<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$be6a1ReadinessNonce = getenv('BE6A1_READINESS_NONCE');
if (is_string($be6a1ReadinessNonce) && $be6a1ReadinessNonce !== '') {
    header('X-BE6A1-Readiness: '.$be6a1ReadinessNonce);
}

$public = realpath(__DIR__.'/../../public');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$candidate = realpath($public.'/'.ltrim($path, '/'));

if (
    $path !== '/'
    && $candidate !== false
    && str_starts_with($candidate, $public.DIRECTORY_SEPARATOR)
    && is_file($candidate)
) {
    $contentTypes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));

    header('Cache-Control: public, max-age=3600, immutable');
    header('Content-Type: '.($contentTypes[$extension] ?? mime_content_type($candidate) ?: 'application/octet-stream'));
    readfile($candidate);

    return true;
}

$requestedExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

if (in_array($requestedExtension, ['js', 'mjs', 'css'], true)) {
    require_once __DIR__.'/be6a1-framework-asset-contract.php';

    $candidateKind = be6a1FrameworkAssetCandidateKind($path);

    if ($candidateKind !== null) {
        if (! defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        require_once $public.'/../vendor/autoload.php';

        /** @var Application $app */
        $app = require $public.'/../bootstrap/app.php';
        $request = Request::capture();
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();
        $approvedKind = be6a1ApprovedFrameworkAssetKind($path);

        if ($approvedKind === $candidateKind) {
            $response = $kernel->handle($request);
            $valid = be6a1FrameworkAssetResponseIsValid($response, $approvedKind);

            if ($valid) {
                $response->send();
                $kernel->terminate($request, $response);

                return true;
            }

            $kernel->terminate($request, $response);
        }
    }

    http_response_code(404);
    header('Cache-Control: no-store');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not Found';

    return true;
}

require $public.'/index.php';

return true;
