<?php

namespace App\Events;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public Company $company,
    ) {}
}
