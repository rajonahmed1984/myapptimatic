<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use App\Support\SystemLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateCustomers extends Command
{
    protected $signature = 'customers:remove-duplicates {--dry-run : Report changes without updating records}';

    protected $description = 'Remove duplicate customers that share an email and have no invoices, subscriptions, projects or orders.';

    public function handle(): int
    {
        $groups = Customer::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Customer $customer) => strtolower(trim((string) $customer->email)))
            ->filter(fn ($customers) => $customers->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate customer emails found.');
            return Command::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $removed = 0;
        $skipped = 0;

        foreach ($groups as $email => $customers) {
            // Keep the oldest customer with billing/project history, otherwise the oldest one.
            $keeper = $customers->first(fn (Customer $customer) => $this->hasHistory($customer))
                ?? $customers->first();

            $this->line(sprintf('%s: keeping customer %d (%s)', $email, $keeper->id, $keeper->name));

            foreach ($customers as $duplicate) {
                if ($duplicate->is($keeper)) {
                    continue;
                }

                if ($this->hasHistory($duplicate)) {
                    $skipped++;
                    $this->warn(sprintf(
                        '  Skipped customer %d (%s): has invoices, subscriptions, projects or orders. Merge manually.',
                        $duplicate->id,
                        $duplicate->name
                    ));
                    continue;
                }

                $removed++;
                $this->info(sprintf(
                    '  %s customer %d (%s)',
                    $dryRun ? 'Would remove' : 'Removed',
                    $duplicate->id,
                    $duplicate->name
                ));

                if (! $dryRun) {
                    $this->removeDuplicate($duplicate, $keeper);
                }
            }
        }

        $this->newLine();
        $this->info("Summary: removed={$removed}, skipped={$skipped}");

        if ($dryRun) {
            $this->comment('Dry run mode: no database changes were made.');
        }

        return Command::SUCCESS;
    }

    private function hasHistory(Customer $customer): bool
    {
        return $customer->invoices()->exists()
            || $customer->subscriptions()->exists()
            || $customer->projects()->exists()
            || $customer->orders()->exists();
    }

    private function removeDuplicate(Customer $duplicate, Customer $keeper): void
    {
        DB::transaction(function () use ($duplicate, $keeper) {
            $users = User::where('customer_id', $duplicate->id)->orderBy('id')->get();

            foreach ($users as $user) {
                // Keep the duplicate's client login (e.g. created by Google sign-in) when the
                // kept customer has none, so the person can still sign in.
                $keeperHasLogin = $keeper->users()->where('role', Role::CLIENT)->exists();

                if ($user->role === Role::CLIENT && ! $keeperHasLogin) {
                    $user->forceFill(['customer_id' => $keeper->id])->save();
                    $this->line(sprintf('    Moved login user %d (%s) to customer %d', $user->id, $user->email, $keeper->id));
                    continue;
                }

                $user->delete();
                $this->line(sprintf('    Deleted login user %d (%s)', $user->id, $user->email));
            }

            SystemLogger::write('activity', 'Duplicate customer removed.', [
                'customer_id' => $duplicate->id,
                'kept_customer_id' => $keeper->id,
                'name' => $duplicate->name,
                'email' => $duplicate->email,
            ]);

            $duplicate->delete();
        });
    }
}
