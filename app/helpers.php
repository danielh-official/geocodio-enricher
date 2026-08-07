<?php

/**
 * Normalize a raw address into a stable cache key.
 *
 * ponytail: casing, punctuation and whitespace only. Standardizing street
 * suffixes (ST -> STREET, AVE -> AVENUE) is the problem Geocodio exists to
 * solve, and doing it here means reimplementing their product badly. The
 * accepted cost is that "123 Main St" and "123 Main Street" occupy two cache
 * entries. Upgrade path: none needed unless duplicate-entry rate is measured
 * and found expensive.
 */
function normalizeAddress(string $raw): string
{
    $s = strtoupper(trim($raw));
    $s = preg_replace('/[.,#]/', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);

    return $s;
}

function addressHash(string $raw): string
{
    return hash('sha256', normalizeAddress($raw));
}

/**
 * Whether a real Geocodio key is configured. 'Geocodio' is the package config
 * default, which is what you get when GEOCODIO_API_KEY is unset.
 *
 * Single source of truth for live-vs-fake: AppServiceProvider binds the client
 * on it, the enricher page shows its demo banner on it.
 */
function hasGeocodioApiKey(): bool
{
    $key = config('geocodio.api_key');

    return filled($key) && $key !== 'Geocodio';
}
