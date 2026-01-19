<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Models\ProjectOverhead;

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
        'software_overhead',
        'website_overhead',
        'contract_file_path',
        'contract_original_name',
        'proposal_file_path',
        'proposal_original_name',
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
        'software_overhead' => 'decimal:2',
        'website_overhead' => 'decimal:2',
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

    public function projectClients(): HasMany
    {
        return $this->hasMany(User::class)
            ->where('role', Role::CLIENT_PROJECT)
            ->where('project_id', $this->id);
    }

    public function overheads(): HasMany
    {
        return $this->hasMany(ProjectOverhead::class);
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

    public function getOverheadTotalAttribute(): float
    {
        $relationTotal = (float) $this->overheads
            ->sum(fn ($overhead) => (float) ($overhead->amount ?? 0));

        return $relationTotal + $this->getColumnOverheadTotal();
    }

    private function getColumnOverheadTotal(): float
    {
        return (float) ($this->software_overhead ?? 0) + (float) ($this->website_overhead ?? 0);
    }

    public function getRemainingBudgetAttribute(): ?float
    {
        if ($this->total_budget === null) {
            return null;
        }

        $budgetWithOverhead = (float) $this->total_budget + $this->overhead_total;
        $initialPayment = (float) ($this->initial_payment_amount ?? 0);
        
        // Sum all remaining budget invoices
        $remainingBudgetInvoiced = (float) $this->invoices()
            ->where('type', 'project_remaining_budget')
            ->sum('total');
        
        return $budgetWithOverhead - $this->sales_rep_total - $initialPayment - $remainingBudgetInvoiced;
    }
}
