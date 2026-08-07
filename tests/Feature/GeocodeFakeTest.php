<?php

use App\Models\GeocodeCache;
use App\Models\User;
use App\Services\FakeGeocodio;
use Geocodio\Geocodio;
use Illuminate\Http\UploadedFile;

test('the fake geocoder is bound when no api key is configured', function () {
    expect(app(Geocodio::class))->toBeInstanceOf(FakeGeocodio::class);
});

test('a csv can be enriched end to end without an api key', function () {
    $this->actingAs(User::factory()->create());

    $csv = "name,addr\nA,123 Main St.\nB,456 Oak Ave\nC,123 MAIN ST\n";

    $this->post('/upload', [
        'file' => UploadedFile::fake()->createWithContent('addresses.csv', $csv),
    ])->assertRedirect();

    $this->post('/enrich', ['column' => 'addr'])->assertRedirect();

    // Two unique addresses: "123 Main St." and "123 MAIN ST" share a hash.
    expect(GeocodeCache::count())->toBe(2)
        ->and(GeocodeCache::pluck('latitude')->all())->each->toEqual(38.897675);

    $csv = $this->get('/download')->streamedContent();

    expect($csv)->toContain('latitude,longitude,accuracy_type,congressional_district,census_tract,census_fips')
        ->toContain('A,"123 Main St.",38.897675,-77.036547,rooftop,0,006202,11001006202');
});

test('one account is never served another account cache', function () {
    $other = User::factory()->create();

    GeocodeCache::create([
        'user_id' => $other->id,
        'address_hash' => addressHash('456 Oak Ave'),
        'raw_address' => '456 Oak Ave',
        'latitude' => 1.5,
        'longitude' => -2.5,
        'accuracy_type' => 'rooftop',
    ]);

    $this->actingAs($mine = User::factory()->create());

    $this->post('/upload', [
        'file' => UploadedFile::fake()->createWithContent('a.csv', "addr\n456 Oak Ave\n"),
    ])->assertRedirect();

    $this->post('/enrich', ['column' => 'addr'])->assertRedirect();

    // The other account's row was not counted as a hit, and geocoding it again
    // wrote a second row under this account rather than updating theirs.
    expect(session('csv.stats'))->toMatchArray(['cached' => 0, 'api_calls' => 1])
        ->and(GeocodeCache::where('user_id', $mine->id)->count())->toBe(1)
        ->and(GeocodeCache::where('user_id', $other->id)->value('latitude'))->toEqual(1.5);

    // The download joins against this account's row, not the other one's.
    expect($this->get('/download')->streamedContent())
        ->toContain('"456 Oak Ave",38.897675,-77.036547');
});
