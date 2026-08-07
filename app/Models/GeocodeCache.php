<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $address_hash
 * @property string $raw_address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $accuracy_type
 * @property array<string, mixed>|null $appends
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['address_hash', 'raw_address', 'latitude', 'longitude', 'accuracy_type', 'appends'])]
class GeocodeCache extends Model
{
    protected $table = 'geocode_cache';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'appends' => 'array',
        ];
    }
}
