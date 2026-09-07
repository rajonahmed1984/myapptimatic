<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Bangladesh district offered when ordering a building.
 */
class District extends Model
{
    protected $fillable = ['slug', 'name', 'bn_name', 'division'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }
}
