<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusAuditLog extends Model
{
    protected $table = 'status_audit_logs';

    protected $fillable = [
        'model_type',
        'model_id',
        'old_status',
        'new_status',
        'reason',
        'triggered_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Log a status change
     */
    public static function logChange(
        string $modelType,
        int $modelId,
        ?string $oldStatus,
        string $newStatus,
        ?string $reason = null,
        ?int $triggeredBy = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'triggered_by' => $triggeredBy,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get status history for a model
     */
    public static function getHistory(string $modelType, int $modelId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->orderByDesc('created_at')
            ->get();
    }
}
