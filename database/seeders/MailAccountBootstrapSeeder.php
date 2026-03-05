<?php

namespace Database\Seeders;

use App\Services\Mail\MailBootstrapService;
use Illuminate\Database\Seeder;

class MailAccountBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $mailboxes = config('apptimatic_email.bootstrap.mailboxes', []);
        $assignments = config('apptimatic_email.bootstrap.assignments', []);

        $result = app(MailBootstrapService::class)->bootstrap(
            is_array($mailboxes) ? $mailboxes : [],
            is_array($assignments) ? $assignments : [],
            false
        );

        $this->command?->info('Mail bootstrap complete.');
        $this->command?->line('Mailboxes created: ' . $result['mailboxes_created']);
        $this->command?->line('Mailboxes updated: ' . $result['mailboxes_updated']);
        $this->command?->line('Assignments created: ' . $result['assignments_created']);
        $this->command?->line('Assignments updated: ' . $result['assignments_updated']);

        foreach ($result['warnings'] as $warning) {
            $this->command?->warn($warning);
        }
    }
}
