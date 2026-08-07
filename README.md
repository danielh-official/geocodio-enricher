# Geocodio enricher

Upload a CSV, pick the column holding the address, get the file back with
latitude, longitude, accuracy type, congressional district and census tract
appended. API calls go through the official
[geocodio-library-php](https://github.com/Geocodio/geocodio-library-php).

`/` is a public landing page; `/enricher` requires a verified account.

## Why the cache exists

Geocodio's terms permit storing geocoding results indefinitely; Google's
require deleting them within 30 days. That licensing difference is the whole
reason this app has a `geocode_cache` table: every address it has ever resolved
stays resolved, so the second and every later time an address appears — in
another file, another month, another user's upload — it costs nothing. The
counters on the results page measure exactly that. "$1.20 avoided" is the dollar
value of a permission Google does not grant.

```
1,847 addresses processed
1,203 served from cache
  644 API calls made
   $0.64 spent, $1.20 avoided
```

## Cache key

Addresses are keyed by `sha256(normalizeAddress($raw))`. Normalization
(`app/helpers.php`) uppercases, strips `.`/`,`/`#`, and collapses whitespace —
and stops there. Standardizing `ST` → `STREET` is address parsing, which is the
product Geocodio sells; reimplementing it here would be a worse version of
their core competency. The cost of stopping early is that `123 Main St` and
`123 Main Street` occupy two cache entries. That is cheaper than being subtly
wrong about addresses.

## Setup

```bash
composer setup          # install, .env, key, migrate, npm install, build
```

Add your key to `.env` — the library's own config (`config/geocodio.php` in the
package) reads it, so nothing else needs wiring:

```
GEOCODIO_API_KEY=your-key
QUEUE_CONNECTION=database
```

Then run the app and a worker:

```bash
composer run dev        # or Herd: http://geocodio-enricher.test
php artisan queue:work
```

Enrichment runs on the queue. The page does not poll — refresh it, and the
download button appears once every address in the file is in the cache.

## Tests

```bash
php artisan test --compact tests/Unit/NormalizeAddressTest.php
```

The normalizer is the only unit with real edge cases, so it is the only thing
tested.

## Deliberately not built

No auth, no user accounts, no Horizon, no map rendering, no progress polling.
Each would triple the surface area and demonstrate nothing the cache does not
already demonstrate.
