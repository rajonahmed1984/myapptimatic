<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'amount',
        'notes',
        'recurrence_type',
        'recurrence_interval',
        'start_date',
        'end_date',
        'next_run_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_date' => 'date',
        'recurrence_interval' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'recurring_expense_id');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(RecurringExpenseAdvance::class, 'recurring_expense_id');
    }
}
