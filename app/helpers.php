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
