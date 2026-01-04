<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'timesheet_id',
        'project_id',
        'work_date',
        'hours',
        'task_note',
        'status',
    ];

    protected $casts = [
        'work_date' => 'date',
    ];

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
