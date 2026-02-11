<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\SalesRepresentative;

class ProjectMaintenance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'customer_id',
        'title',
        'amount',
        'currency',
        'billing_cycle',
        'start_date',
        'next_billing_date',
        'last_billed_at',
        'status',
        'auto_invoice',
        'sales_rep_visible',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'next_billing_date' => 'date',
        'last_billed_at' => 'datetime',
        'auto_invoice' => 'boolean',
        'sales_rep_visible' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'maintenance_id');
    }

    public function salesRepresentatives(): BelongsToMany
    {
        return $this->belongsToMany(
            SalesRepresentative::class,
            'project_maintenance_sales_representative',
            'project_maintenance_id',
            'sales_representative_id'
        )->withPivot('amount')
            ->withTimestamps();
    }
}
