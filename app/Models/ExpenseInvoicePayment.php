<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseInvoicePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_invoice_id',
        'payment_method',
        'payment_type',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ExpenseInvoice::class, 'expense_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
