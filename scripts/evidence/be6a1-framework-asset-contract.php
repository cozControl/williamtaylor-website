<?php

declare(strict_types=1);

use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identify only framework asset shapes that are worth bootstrapping Laravel for.
 *
 * This is deliberately only a pre-filter. Livewire candidates are approved again
 * after Laravel has loaded the installation-specific endpoint prefix.
 */
function be6a1FrameworkAssetCandidateKind(string $path): ?string
{
    $fluxAssets = [
        '/flux/flux.js' => 'javascript',
        '/flux/flux.min.js' => 'javascript',
        '/flux/editor.js' => 'javascript',
        '/flux/editor.min.js' => 'javascript',
        '/flux/editor.css' => 'css',
    ];

    if (isset($fluxAssets[$path])) {
        return $fluxAssets[$path];
    }

    if (preg_match(
        '#^/livewire-[a-f0-9]{8}/(?:livewire(?:\.min)?\.js|js/[^/]+\.js)$#D',
        $path,
    ) === 1) {
        return 'javascript';
    }

    if (preg_match(
        '#^/livewire-[a-f0-9]{8}/css/[^/]+(?:\.global)?\.css$#D',
        $path,
    ) === 1) {
        return 'css';
    }

    return null;
}

/**
 * Approve the candidate against Laravel's effective key/debug configuration.
 */
function be6a1ApprovedFrameworkAssetKind(string $path): ?string
{
    $candidateKind = be6a1FrameworkAssetCandidateKind($path);

    if ($candidateKind === null || str_starts_with($path, '/flux/')) {
        return $candidateKind;
    }

    $prefix = EndpointResolver::prefix();
    $mainScript = EndpointResolver::scriptPath(minified: ! config('app.debug'));

    if ($path === $mainScript) {
        return 'javascript';
    }

    $quotedPrefix = preg_quote($prefix, '#');

    if (preg_match('#^'.$quotedPrefix.'/js/[^/]+\.js$#D', $path) === 1) {
        return 'javascript';
    }

    if (preg_match('#^'.$quotedPrefix.'/css/[^/]+(?:\.global)?\.css$#D', $path) === 1) {
        return 'css';
    }

    return null;
}

/**
 * Fail closed when a framework route produces a non-asset response.
 */
function be6a1FrameworkAssetResponseIsValid(Response $response, string $kind): bool
{
    if ($response->getStatusCode() === Response::HTTP_NOT_MODIFIED) {
        return true;
    }

    if (! $response->isSuccessful()) {
        return false;
    }

    $contentType = (string) $response->headers->get('Content-Type', '');
    $mimeIsValid = match ($kind) {
        'javascript' => preg_match(
            '#^(?:application|text)/(?:javascript|ecmascript)(?:;|$)#i',
            $contentType,
        ) === 1,
        'css' => preg_match('#^text/css(?:;|$)#i', $contentType) === 1,
        default => false,
    };

    if (! $mimeIsValid) {
        return false;
    }

    $body = $response instanceof BinaryFileResponse
        ? @file_get_contents($response->getFile()->getPathname())
        : $response->getContent();

    if (! is_string($body) || trim($body) === '') {
        return false;
    }

    $prefix = ltrim(substr($body, 0, 512));

    return preg_match(
        '/^(?:<!doctype\s+html\b|<html\b|<head\b|<body\b|not\s+found\b|internal\s+server\s+error\b|whoops\b)/i',
        $prefix,
    ) !== 1;
}
