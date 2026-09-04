<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A MyBuilding building ordered by a customer, and the state of handing it
 * over to their MyBuilding installation.
 */
class MyBuildingProvision extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROVISIONED = 'provisioned';
    public const STATUS_FAILED = 'failed';

    protected $table = 'mybuilding_provisions';

    protected $fillable = [
        'license_id',
        'customer_id',
        'order_id',
        'building_name',
        'building_address',
        'total_floors',
        'flats_per_floor',
        'floor_plan',
        'contracted_flats',
        'install_url',
        'district_id',
        'city_id',
        'area_id',
        'owner_name',
        'owner_email',
        'owner_phone',
        'status',
        'attempts',
        'last_error',
        'provisioned_at',
        'remote_building_id',
        'remote_client_account_id',
        'registration_code',
    ];

    protected $casts = [
        'floor_plan' => 'array',
        'total_floors' => 'integer',
        'flats_per_floor' => 'integer',
        'contracted_flats' => 'integer',
        'attempts' => 'integer',
        'provisioned_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isProvisioned(): bool
    {
        return $this->status === self::STATUS_PROVISIONED;
    }

    /**
     * Flats implied by the order: the floor plan when given, otherwise a
     * uniform floors x flats_per_floor.
     */
    public function calculatedFlats(): int
    {
        if (is_array($this->floor_plan) && $this->floor_plan !== []) {
            return (int) array_sum($this->floor_plan);
        }

        return (int) $this->total_floors * (int) $this->flats_per_floor;
    }
}
