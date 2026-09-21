<?php

use App\Models\Project;
use App\Services\CommissionService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Projects cancelled or deleted before commission followed the project
     * still show their reps' earnings. Reverse the unpaid ones once; from now
     * on the Project model does it as the status changes.
     */
    public function up(): void
    {
        $service = app(CommissionService::class);

        Project::withTrashed()
            ->where(function ($query) {
                $query->where('status', 'cancel')->orWhereNotNull('deleted_at');
            })
            ->chunkById(100, function ($projects) use ($service) {
                foreach ($projects as $project) {
                    $service->reverseProjectEarnings(
                        $project,
                        $project->trashed() ? 'project_deleted' : 'project_cancelled'
                    );
                }
            });
    }

    public function down(): void
    {
        // Earnings reversed here carry their reason in metadata and come back
        // if the project is reopened or restored.
    }
};
