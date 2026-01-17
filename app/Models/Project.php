<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Employee;
use App\Models\SalesRepresentative;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'order_id',
        'subscription_id',
        'advance_invoice_id',
        'final_invoice_id',
        'name',
        'type',
        'status',
        'start_date',
        'expected_end_date',
        'due_date',
        'description',
        'notes',
        'total_budget',
        'initial_payment_amount',
        'currency',
        'sales_rep_ids',
        'budget_amount',
        'planned_hours',
        'hourly_cost',
        'actual_hours',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_end_date' => 'date',
        'due_date' => 'date',
        'total_budget' => 'decimal:2',
        'initial_payment_amount' => 'decimal:2',
        'budget_amount' => 'decimal:2',
        'planned_hours' => 'decimal:2',
        'hourly_cost' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'sales_rep_ids' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            if ($project->isForceDeleting()) {
                $project->maintenances()->forceDelete();
            } else {
                $project->maintenances()->delete();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function advanceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'advance_invoice_id');
    }

    public function finalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'final_invoice_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(ProjectMaintenance::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Models\Invoice::class);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_project')->withTimestamps();
    }

    public function salesRepresentatives()
    {
        return $this->belongsToMany(SalesRepresentative::class, 'project_sales_representative')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function getSalesRepTotalAttribute(): float
    {
        return (float) $this->salesRepresentatives
            ->sum(fn ($rep) => (float) ($rep->pivot?->amount ?? 0));
    }

    public function getRemainingBudgetAttribute(): ?float
    {
        if ($this->total_budget === null) {
            return null;
        }

        return (float) $this->total_budget - $this->sales_rep_total;
    }
}
