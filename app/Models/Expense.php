<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'recurring_expense_id',
        'title',
        'amount',
        'expense_date',
        'notes',
        'attachment_path',
        'type',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'expense_date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class, 'recurring_expense_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(ExpenseInvoice::class);
    }
}
