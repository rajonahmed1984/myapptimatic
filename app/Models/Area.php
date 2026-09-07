<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An area/neighbourhood inside a city (e.g. West Agargaon under Dhaka city).
 */
class Area extends Model
{
    protected $fillable = [
        'district_id',
        'city_id',
        'slug',
        'name',
        'bn_name',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
