<?php

namespace App\Http\Controllers;

use App\Jobs\GeocodeAddresses;
use App\Models\GeocodeCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnricherController extends Controller
{
    /**
     * Price per Geocodio lookup, used only to render the savings counter.
     */
    private const float PRICE_PER_LOOKUP = 0.001;

    public function index(Request $request): Response
    {
        /** @var list<string> $hashes */
        $hashes = $request->session()->get('csv.hashes', []);

        return Inertia::render('enricher', [
            'filename' => $request->session()->get('csv.name'),
            'headers' => $request->session()->get('csv.headers'),
            'column' => $request->session()->get('csv.column'),
            'stats' => $request->session()->get('csv.stats'),
            'resolved' => $hashes === [] ? 0 : GeocodeCache::whereIn('address_hash', $hashes)->count(),
            'demo' => ! hasGeocodioApiKey(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        /**
         * @var UploadedFile
         */
        $file = $validated['file'];

        $path = $file->store('uploads');

        if ($path === false) {
            throw new RuntimeException('Unable to store the uploaded CSV.');
        }

        $handle = $this->openFile(Storage::path($path), 'r');
        $headers = fgetcsv($handle) ?: [];
        fclose($handle);

        $request->session()->forget(['csv.column', 'csv.hashes', 'csv.stats']);
        $request->session()->put([
            'csv.path' => $path,
            'csv.name' => $file->getClientOriginalName(),
            'csv.headers' => $headers,
        ]);

        return back();
    }

    public function enrich(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'column' => ['required', 'string', Rule::in($request->session()->get('csv.headers', []))],
        ]);

        /**
         * @var string
         */
        $column = $validated['column'];

        $addresses = $this->readColumn($request->session()->get('csv.path'), $column);

        // Unique by hash: duplicate rows in one file must not be billed twice.
        $unique = [];
        foreach ($addresses as $raw) {
            $unique[addressHash($raw)] = $raw;
        }

        $cached = GeocodeCache::whereIn('address_hash', array_keys($unique))
            ->pluck('address_hash')
            ->all();

        $misses = array_values(array_diff_key($unique, array_flip($cached)));

        if ($misses !== []) {
            GeocodeAddresses::dispatch($misses);
        }

        $request->session()->put([
            'csv.column' => $column,
            // ponytail: hash list lives in the session so the page and the
            // download need no second table. Ceiling is session size; move to a
            // `jobs`-style row if files get past ~50k addresses.
            'csv.hashes' => array_keys($unique),
            'csv.stats' => [
                'processed' => count($unique),
                'cached' => count($cached),
                'api_calls' => count($misses),
                'spent' => round(count($misses) * self::PRICE_PER_LOOKUP, 2),
                'avoided' => round(count($cached) * self::PRICE_PER_LOOKUP, 2),
            ],
        ]);

        return back();
    }

    public function download(Request $request): StreamedResponse
    {
        $path = $request->session()->get('csv.path');
        $column = $request->session()->get('csv.column');

        abort_if($path === null || $column === null, 404);

        $cache = GeocodeCache::whereIn('address_hash', $request->session()->get('csv.hashes', []))
            ->get()
            ->keyBy('address_hash')
            ->all();

        $filename = 'enriched-'.$request->session()->get('csv.name', 'addresses.csv');

        return response()->streamDownload(function () use ($path, $column, $cache) {
            $in = $this->openFile(Storage::path($path), 'r');
            $out = $this->openFile('php://output', 'w');

            $headers = fgetcsv($in) ?: [];
            $index = array_search($column, $headers, true);

            fputcsv($out, [...$headers, 'latitude', 'longitude', 'accuracy_type', 'congressional_district', 'census_tract', 'census_fips']);

            while (($row = fgetcsv($in)) !== false) {
                $hit = $cache[addressHash($row[$index] ?? '')] ?? null;
                $appends = $hit->appends ?? [];
                $census = is_array($appends['census'] ?? null) ? reset($appends['census']) : [];

                fputcsv($out, [
                    ...$row,
                    $hit?->latitude,
                    $hit?->longitude,
                    $hit?->accuracy_type,
                    $appends['congressional_districts'][0]['district_number'] ?? null,
                    $census['tract_code'] ?? null,
                    $census['full_fips'] ?? null,
                ]);
            }

            fclose($in);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return list<string>
     */
    private function readColumn(string $path, string $column): array
    {
        $handle = $this->openFile(Storage::path($path), 'r');
        $headers = fgetcsv($handle) ?: [];
        $index = array_search($column, $headers, true);

        $addresses = [];

        while (($row = fgetcsv($handle)) !== false) {
            $address = trim($row[$index] ?? '');

            if ($address !== '') {
                $addresses[] = $address;
            }
        }

        fclose($handle);

        return $addresses;
    }

    /**
     * @return resource
     */
    private function openFile(string $file, string $mode)
    {
        $handle = fopen($file, $mode);

        if ($handle === false) {
            throw new RuntimeException("Unable to open {$file}.");
        }

        return $handle;
    }
}
