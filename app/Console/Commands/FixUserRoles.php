<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Console\Command;

class FixUserRoles extends Command
{
    protected $signature = 'users:fix-roles {--dry-run : Report changes without updating records}';

    protected $description = 'Fix null or invalid user roles when a confident role can be inferred.';

    public function handle(): int
    {
        $users = User::query()
            ->whereNull('role')
            ->orWhereNotIn('role', Role::allowed())
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users with null or invalid roles found.');
            return Command::SUCCESS;
        }

        $fixed = 0;
        $skipped = 0;
        $uncertain = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($users as $user) {
            $matches = [];

            if (Employee::query()->where('user_id', $user->id)->exists()) {
                $matches[] = Role::EMPLOYEE;
            }

            if (SalesRepresentative::query()->where('user_id', $user->id)->exists()) {
                $matches[] = Role::SALES;
            }

            if ($user->customer_id) {
                $matches[] = Role::CLIENT;
            }

            $matches = array_values(array_unique($matches));

            if (count($matches) !== 1) {
                $uncertain++;
                $this->warn(sprintf(
                    'Skipped user %d (%s): unable to infer role confidently.',
                    $user->id,
                    $user->email ?? 'no-email'
                ));
                continue;
            }

            $role = $matches[0];
            if (! $dryRun) {
                $user->update(['role' => $role]);
            }

            $fixed++;
            $this->info(sprintf(
                '%s user %d (%s) -> role=%s',
                $dryRun ? 'Would update' : 'Updated',
                $user->id,
                $user->email ?? 'no-email',
                $role
            ));
        }

        $skipped = $users->count() - $fixed;

        $this->newLine();
        $this->info("Summary: fixed={$fixed}, skipped={$skipped}, uncertain={$uncertain}");

        if ($dryRun) {
            $this->comment('Dry run mode: no database changes were made.');
        }

        return Command::SUCCESS;
    }
}
