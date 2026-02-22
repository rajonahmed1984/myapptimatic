<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileReferenceReconciliation extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'column_name',
        'metadata_key',
        'original_path',
        'path_hash',
        'status',
        'action',
        'context',
        'reconciled_at',
    ];

    protected $casts = [
        'context' => 'array',
        'reconciled_at' => 'datetime',
    ];
}
