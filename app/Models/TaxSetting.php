<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxSetting extends Model
{
    protected $fillable = [
        'tax_mode_default',
        'default_tax_rate_id',
        'invoice_tax_label',
        'invoice_tax_note_template',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'tax_mode_default' => 'exclusive',
            'invoice_tax_label' => 'VAT/Tax',
            'invoice_tax_note_template' => 'Tax ({rate}%)',
            'enabled' => false,
        ]);
    }

    public function defaultRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'default_tax_rate_id');
    }

    public function renderNote(?float $ratePercent): ?string
    {
        $template = trim((string) ($this->invoice_tax_note_template ?? ''));
        if ($template === '') {
            return null;
        }

        $rate = $ratePercent !== null ? rtrim(rtrim(number_format($ratePercent, 2, '.', ''), '0'), '.') : '';

        return str_replace('{rate}', $rate, $template);
    }
}
