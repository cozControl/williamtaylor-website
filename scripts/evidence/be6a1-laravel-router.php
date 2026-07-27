<?php

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

require $public.'/index.php';

return true;
