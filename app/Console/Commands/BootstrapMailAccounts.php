<?php

namespace App\Console\Commands;

use App\Services\Mail\MailBootstrapService;
use Illuminate\Console\Command;

class BootstrapMailAccounts extends Command
{
    protected $signature = 'mail:bootstrap {--dry-run : Preview changes without writing records}';

    protected $description = 'Bootstrap Apptimatic Email mailboxes and mailbox assignments from config/apptimatic_email.php.';

    public function handle(MailBootstrapService $service): int
    {
        $mailboxes = config('apptimatic_email.bootstrap.mailboxes', []);
        $assignments = config('apptimatic_email.bootstrap.assignments', []);

        if (! is_array($mailboxes) || ! is_array($assignments)) {
            $this->error('Invalid apptimatic_email.bootstrap config. Expected arrays.');

            return self::FAILURE;
        }

        if (count($mailboxes) === 0 && count($assignments) === 0) {
            $this->warn('No mail bootstrap definitions found in config/apptimatic_email.php.');
            $this->line('Add bootstrap.mailboxes and bootstrap.assignments, then run this command again.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $service->bootstrap($mailboxes, $assignments, $dryRun);

        $this->newLine();
        $this->info($dryRun ? 'Mail bootstrap dry-run summary:' : 'Mail bootstrap summary:');
        $this->line('Mailboxes created: ' . $result['mailboxes_created']);
        $this->line('Mailboxes updated: ' . $result['mailboxes_updated']);
        $this->line('Assignments created: ' . $result['assignments_created']);
        $this->line('Assignments updated: ' . $result['assignments_updated']);

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
