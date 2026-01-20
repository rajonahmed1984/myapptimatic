<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'source_type',
        'source_id',
        'expense_type',
        'invoice_no',
        'status',
        'invoice_date',
        'due_date',
        'amount',
        'currency',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount' => 'float',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
