<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Leads', Lead::count())
                ->description('All-time leads submitted')
                ->color('success'),
            Stat::make('Active Companies', Company::where('is_active', true)->where('is_verified', true)->count())
                ->description('Verified & active')
                ->color('success'),
            Stat::make('Active Subscriptions', Subscription::where('status', 'active')->count())
                ->description('Paying customers')
                ->color('warning'),
            Stat::make('Pending Verifications', Company::where('is_verified', false)->count())
                ->description('Awaiting review')
                ->color('danger'),
        ];
    }
}
