<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

class MarkStaleCompaniesOffline extends Command
{
    protected $signature = 'locknear:mark-stale-offline';

    protected $description = 'Set is_online=false for providers without a recent heartbeat';

    public function handle(): int
    {
        $count = Company::markStaleOffline();

        $this->info("Marked {$count} companies offline.");

        return self::SUCCESS;
    }
}
