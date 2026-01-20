<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function recurringExpenses(): HasMany
    {
        return $this->hasMany(RecurringExpense::class, 'category_id');
    }
}
