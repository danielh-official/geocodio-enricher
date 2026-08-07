<?php

namespace App\Services;

use Geocodio\Geocodio;

/**
 * Stand-in used when GEOCODIO_API_KEY is unset, so the app is demoable and
 * testable without a paid key. Every address resolves to the same fixed record.
 *
 * ponytail: no configuration, no per-address variation. If a demo ever needs
 * distinguishable pins on a map, derive the coordinates from addressHash().
 */
class FakeGeocodio extends Geocodio
{
    /**
     * @param  string|array<int|string, string>  $query
     * @param  array<int, string>  $fields
     * @param  mixed  ...$args
     * @return array{results: list<array{query: string, response: array{results: list<array<string, mixed>>}}>}
     */
    public function geocode($query, array $fields = [], ...$args): array
    {
        $addresses = is_array($query) ? array_values($query) : [$query];

        return [
            'results' => array_map(fn (string $address): array => [
                'query' => $address,
                'response' => ['results' => [$this->fixedMatch()]],
            ], $addresses),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixedMatch(): array
    {
        return [
            'formatted_address' => '1600 Pennsylvania Ave NW, Washington, DC 20500',
            'location' => ['lat' => 38.897675, 'lng' => -77.036547],
            'accuracy' => 1,
            'accuracy_type' => 'rooftop',
            'source' => 'fake',
            'fields' => [
                'congressional_districts' => [['district_number' => 0]],
                'census' => [
                    '2023' => [
                        'census_year' => 2023,
                        'state_fips' => '11',
                        'county_fips' => '11001',
                        'tract_code' => '006202',
                        'full_fips' => '11001006202',
                    ],
                ],
            ],
        ];
    }
}
