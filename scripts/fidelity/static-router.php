<?php

$be6a1ReadinessNonce = getenv('BE6A1_READINESS_NONCE');
if (is_string($be6a1ReadinessNonce) && $be6a1ReadinessNonce !== '') {
    header('X-BE6A1-Readiness: '.$be6a1ReadinessNonce);
}

$documentRoot = realpath(__DIR__.'/../../public/website');
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

$pageAliases = [
    '/' => '/index.html',
    '/about' => '/index.html',
    '/collections' => '/html/page_2.html',
    '/shop' => '/html/page_3.html',
    '/pre-order' => '/html/page_4.html',
    '/collections/limited-edition' => '/html/page_5.html',
    '/gift-cards' => '/html/page_6.html',
    '/login' => '/html/page_7.html',
    '/wishlist' => '/html/page_8.html',
    '/product/taylor-oxford-shirt' => '/html/page_9.html',
    '/product/mercerized-cotton-polo' => '/html/page_10.html',
    '/product/dar-es-salaam-linen-suit' => '/html/page_11.html',
    '/product/slim-tapered-chinos' => '/html/page_12.html',
    '/product/executive-overcoat' => '/html/page_13.html',
];

$staticNormalizationRoutes = [
    '/collections/limited-edition',
    '/product/taylor-oxford-shirt',
    '/product/mercerized-cotton-polo',
    '/product/dar-es-salaam-linen-suit',
    '/product/slim-tapered-chinos',
    '/product/executive-overcoat',
];

if (isset($pageAliases[$requestPath])) {
    $document = file_get_contents($documentRoot.$pageAliases[$requestPath]);

    if (in_array($requestPath, $staticNormalizationRoutes, true)) {
        $document = str_replace('<head>', '<head><base href="/">', $document);
        header('X-BE6A1-Static-Normalization: base-href-root-v1');
    }

    echo $document;

    return true;
}

$requestedFile = realpath($documentRoot.'/'.ltrim($requestPath, '/'));

if (
    $requestedFile !== false
    && str_starts_with($requestedFile, $documentRoot.DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    header('Cache-Control: public, max-age=3600, immutable');

    return false;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Not Found';

return true;
