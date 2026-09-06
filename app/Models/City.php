<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An upazila/thana inside a district.
 */
class City extends Model
{
    protected $fillable = ['district_id', 'slug', 'name', 'bn_name'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
