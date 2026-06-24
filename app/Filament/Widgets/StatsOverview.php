<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $todayLeads = Lead::whereDate('created_at', today())->count();
        $onlineNow = Company::where('is_online', true)->count();

        return [
            Stat::make('Leads Today', $todayLeads)
                ->description('Total: ' . Lead::count() . ' all-time')
                ->color('success')
                ->icon('heroicon-o-bolt'),
            Stat::make('Online Now', $onlineNow)
                ->description(Company::where('is_active', true)->where('is_verified', true)->count() . ' verified & active')
                ->color($onlineNow > 0 ? 'success' : 'gray')
                ->icon('heroicon-o-signal'),
            Stat::make('Active Subscriptions', Subscription::where('status', 'active')->count())
                ->description('Paying companies')
                ->color('warning')
                ->icon('heroicon-o-credit-card'),
            Stat::make('Pending Verification', Company::where('is_verified', false)->where('is_claimed', true)->count())
                ->description('Claimed but unverified')
                ->color('danger')
                ->icon('heroicon-o-shield-exclamation'),
            Stat::make('Total Companies', Company::count())
                ->description(Company::onlyTrashed()->count() . ' deleted')
                ->color('info')
                ->icon('heroicon-o-building-storefront'),
            Stat::make('Total Users', User::count())
                ->description(User::where('role', 'business')->count() . ' business · ' . User::where('role', 'customer')->count() . ' customers')
                ->color('info')
                ->icon('heroicon-o-users'),
        ];
    }
}
