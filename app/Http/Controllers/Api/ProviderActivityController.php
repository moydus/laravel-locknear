<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DomainEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => []]);
        }

        $limit = min(max((int) $request->integer('limit', 50), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $events = DomainEvent::query()
            ->where(function ($query) use ($company) {
                $query->where('company_id', $company->id)
                    ->orWhere(function ($nested) use ($company) {
                        $nested->where('aggregate_type', $company::class)
                            ->where('aggregate_id', $company->id);
                    });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('event_name', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%")
                        ->orWhere('aggregate_type', 'like', "%{$search}%");
                });
            })
            ->latest('occurred_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (DomainEvent $event) => [
                'id' => $event->id,
                'event' => $event->event_name ?: $event->event_type,
                'scope' => $this->scopeFor($event),
                'status' => $event->processing_status ?: 'recorded',
                'occurred_at' => optional($event->occurred_at ?? $event->created_at)->toIso8601String(),
                'payload' => $event->payload,
            ]);

        return response()->json(['data' => $events]);
    }

    private function scopeFor(DomainEvent $event): string
    {
        $name = strtolower($event->event_name ?: $event->event_type);

        return match (true) {
            str_contains($name, 'payment'), str_contains($name, 'stripe'), str_contains($name, 'capture') => 'Payment',
            str_contains($name, 'dispatch'), str_contains($name, 'offer'), str_contains($name, 'accepted') => 'Dispatch',
            str_contains($name, 'booking'), str_contains($name, 'lead'), str_contains($name, 'job') => 'Job',
            str_contains($name, 'review') => 'Review',
            str_contains($name, 'claim'), str_contains($name, 'provider'), str_contains($name, 'company') => 'Provider',
            default => 'System',
        };
    }
}
