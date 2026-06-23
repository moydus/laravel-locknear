<?php

namespace App\Listeners;

use App\Events\LeadCompleted;
use App\Services\DispatchService;

class SendPostJobSurvey
{
    public function handle(LeadCompleted $event): void
    {
        app(DispatchService::class)->sendPostJobSurvey($event->lead, $event->company);
    }
}
