<?php

function normalizeMediaPath(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^(?:\./|\../)+#', '', $path);

    return ltrim((string)$path, '/');
}

function buildMediaUrl(?string $path, string $relativePrefix = ''): string
{
    $normalized = normalizeMediaPath($path);
    if ($normalized === '') {
        return '';
    }

    if (preg_match('#^(?:https?:)?//#i', $normalized) || str_starts_with($normalized, 'data:')) {
        return $normalized;
    }

    $relativePrefix = trim($relativePrefix);
    if ($relativePrefix === '') {
        return $normalized;
    }

    return rtrim(str_replace('\\', '/', $relativePrefix), '/') . '/' . ltrim($normalized, '/');
}
