<?php
declare(strict_types=1);

function public_asset_url_if_exists(?string $path): ?string
{
    $path = trim((string) $path);

    if ($path === '' || str_contains($path, "\0")) {
        return null;
    }

    $path = str_replace('\\', '/', $path);

    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path) === 1) {
        return null;
    }

    $path = ltrim($path, '/');
    $segments = explode('/', $path);

    if (in_array('..', $segments, true) || in_array('', $segments, true)) {
        return null;
    }

    $publicRoot = realpath(__DIR__ . '/../../public');
    $assetPath = realpath(__DIR__ . '/../../public/' . $path);

    if ($publicRoot === false || $assetPath === false) {
        return null;
    }

    $publicRoot = rtrim(str_replace('\\', '/', $publicRoot), '/') . '/';
    $assetPath = str_replace('\\', '/', $assetPath);

    if (!str_starts_with($assetPath, $publicRoot) || !is_file($assetPath)) {
        return null;
    }

    return $path;
}
