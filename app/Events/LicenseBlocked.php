<?php

namespace App\Events;

use App\Models\License;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LicenseBlocked
{
    use Dispatchable, SerializesModels;

    public function __construct(public License $license, public string $reason, public array $context = [])
    {
    }
}
