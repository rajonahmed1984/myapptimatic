<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpenseAdvance extends Model
{
    use HasFactory;

    protected $fillable = [
        'recurring_expense_id',
        'payment_method',
        'amount',
        'paid_at',
        'payment_reference',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'date',
    ];

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class, 'recurring_expense_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
