<?php

namespace App\Console\Commands;

use App\Models\WorkOrderEvidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredWorkOrderEvidence extends Command
{
    protected $signature = 'locknear:purge-work-order-evidence';
    protected $description = 'Delete expired sensitive work-order evidence from private storage';

    public function handle(): int
    {
        $count = 0;
        WorkOrderEvidence::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($items) use (&$count) {
                foreach ($items as $evidence) {
                    Storage::disk($evidence->disk)->delete($evidence->path);
                    $evidence->update(['status' => 'deleted', 'deleted_at' => now(), 'path' => '']);
                    $count++;
                }
            });

        $this->info("Purged {$count} expired work-order evidence files.");
        return self::SUCCESS;
    }
}
