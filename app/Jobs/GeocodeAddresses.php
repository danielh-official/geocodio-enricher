<?php

namespace App\Jobs;

use App\Models\GeocodeCache;
use Geocodio\Geocodio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeocodeAddresses implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $userId  Owner of the cache partition these results belong to.
     * @param  list<string>  $addresses  Raw addresses known to be absent from that partition.
     */
    public function __construct(public int $userId, public array $addresses) {}

    public function handle(Geocodio $geocodio): void
    {
        // ponytail: the endpoint accepts 10,000 per request; chunking at 1,000
        // means a failure retries a smaller unit and progress is visible in the
        // cache table while the job runs.
        foreach (array_chunk($this->addresses, 1000) as $chunk) {
            $response = retry(3, fn (): array => $geocodio->geocode($chunk, ['cd', 'census']), 1000);

            $now = now();
            $rows = [];

            foreach ($response['results'] ?? [] as $index => $result) {
                $raw = $result['query'] ?? $chunk[$index];
                $match = $result['response']['results'][0] ?? null;

                // A miss is cached too, with null coordinates, so the same bad
                // address is never billed twice.
                $rows[] = [
                    'user_id' => $this->userId,
                    'address_hash' => addressHash($raw),
                    'raw_address' => $raw,
                    'latitude' => $match['location']['lat'] ?? null,
                    'longitude' => $match['location']['lng'] ?? null,
                    'accuracy_type' => $match['accuracy_type'] ?? null,
                    'appends' => isset($match['fields']) ? json_encode($match['fields']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            GeocodeCache::upsert($rows, ['user_id', 'address_hash'], [
                'raw_address', 'latitude', 'longitude', 'accuracy_type', 'appends', 'updated_at',
            ]);
        }
    }
}
