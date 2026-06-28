<?php

namespace App\Services;

use App\Enums\CompanyLifecycleStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\OutreachCampaign;
use App\Models\OutreachMessage;
use App\Models\ProviderInvitation;
use App\Support\LockNearUrls;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Twilio\Rest\Client;

class GhostOutreachService
{
    private ?Client $twilio = null;

    public function __construct(private DomainEventRecorder $events) {}

    public function inviteForLead(Lead $lead, int $limit = 20): int
    {
        if (!config('locknear.outreach.ghost_dispatch_enabled', true)) {
            return 0;
        }

        $companies = $this->findUnclaimedCompanies($lead, $limit);
        if ($companies->isEmpty()) {
            $this->events->record('GhostOutreachSkipped', $lead, [
                'lead_id' => $lead->id,
                'reason' => 'no_unclaimed_companies',
                'zip' => $lead->zip,
                'city' => $lead->city,
                'state' => $lead->state,
            ], ['aggregate_type' => Lead::class, 'aggregate_id' => $lead->id]);

            return 0;
        }

        $campaign = $this->campaignFor($lead, $companies->count());
        $sent = 0;

        foreach ($companies as $company) {
            $invitation = ProviderInvitation::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'outreach_campaign_id' => $campaign->id,
                ],
                [
                    'phone' => $company->phone,
                    'email' => $company->email,
                    'token' => $this->invitationToken($company),
                    'status' => 'pending',
                    'expires_at' => now()->addDays(7),
                    'metadata' => [
                        'lead_id' => $lead->id,
                        'service_type' => $lead->service_type,
                        'zip' => $lead->zip,
                        'city' => $lead->city,
                        'state' => $lead->state,
                        'source' => 'ghost_dispatch',
                    ],
                ],
            );

            $claimUrl = LockNearUrls::providerApp() . '/claim/' . $company->ensureClaimToken()
                . '?invitation=' . urlencode($invitation->token)
                . '&lead=' . $lead->id;

            $message = $this->messageBody($lead, $company, $claimUrl);
            $status = $this->sendInvitation($company, $message, $claimUrl) ? 'sent' : 'queued';

            OutreachMessage::create([
                'outreach_campaign_id' => $campaign->id,
                'provider_invitation_id' => $invitation->id,
                'company_id' => $company->id,
                'channel' => $company->phone ? 'sms' : 'email',
                'recipient' => $company->phone ?: $company->email,
                'status' => $status,
                'queued_at' => now(),
                'sent_at' => $status === 'sent' ? now() : null,
                'payload' => [
                    'body' => $message,
                    'claim_url' => $claimUrl,
                ],
                'metadata' => [
                    'lead_id' => $lead->id,
                    'service_type' => $lead->service_type,
                ],
            ]);

            $company->update([
                'lifecycle_status' => CompanyLifecycleStatus::Invited,
            ]);

            if ($status === 'sent') {
                $sent++;
            }
        }

        $campaign->update([
            'status' => 'active',
            'target_count' => $companies->count(),
            'sent_count' => $campaign->messages()->where('status', 'sent')->count(),
            'started_at' => $campaign->started_at ?? now(),
        ]);

        $this->events->record('GhostOutreachStarted', $lead, [
            'lead_id' => $lead->id,
            'campaign_id' => $campaign->id,
            'target_count' => $companies->count(),
            'sent_count' => $sent,
            'zip' => $lead->zip,
            'city' => $lead->city,
            'state' => $lead->state,
        ], ['aggregate_type' => Lead::class, 'aggregate_id' => $lead->id]);

        return $sent;
    }

    private function findUnclaimedCompanies(Lead $lead, int $limit): Collection
    {
        $query = Company::query()
            ->where('is_claimed', false)
            ->where(function ($query) {
                $query->whereNotNull('phone')->orWhereNotNull('email');
            })
            ->where(function ($query) use ($lead) {
                $query->where('zip', $lead->zip);

                if ($lead->city && $lead->state) {
                    $query->orWhere(function ($cityQuery) use ($lead) {
                        $cityQuery->where('city', $lead->city)
                            ->where('state', strtoupper((string) $lead->state));
                    });
                }
            });

        if ($lead->latitude && $lead->longitude) {
            $lat = (float) $lead->latitude;
            $lng = (float) $lead->longitude;

            $query->selectRaw(
                'companies.*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance_km',
                [$lat, $lng, $lat],
            )
                ->orderByRaw('distance_km IS NULL')
                ->orderBy('distance_km');
        }

        return $query
            ->orderByDesc('rating')
            ->orderByDesc('review_count')
            ->limit($limit)
            ->get();
    }

    private function campaignFor(Lead $lead, int $targetCount): OutreachCampaign
    {
        $name = implode(' ', array_filter([
            'Ghost Dispatch',
            $lead->city ?: null,
            $lead->state ?: null,
            '#' . $lead->id,
        ]));

        return OutreachCampaign::create([
            'name' => $name,
            'market' => 'locksmith',
            'city' => $lead->city,
            'state' => $lead->state,
            'zip' => $lead->zip,
            'status' => 'draft',
            'channel_mix' => 'sms,email',
            'target_count' => $targetCount,
            'metadata' => [
                'lead_id' => $lead->id,
                'service_type' => $lead->service_type,
                'source' => 'ghost_dispatch',
            ],
        ]);
    }

    private function invitationToken(Company $company): string
    {
        return 'pi_' . $company->id . '_' . Str::random(32);
    }

    private function messageBody(Lead $lead, Company $company, string $claimUrl): string
    {
        $service = str($lead->service_type)->replace('-', ' ')->title();
        $location = $lead->city ? "{$lead->city}, {$lead->state}" : "ZIP {$lead->zip}";

        return "LockNear: A customer near {$location} needs {$service} now.\n\n"
            . "Claim {$company->name} to accept this job and get future dispatches:\n"
            . "{$claimUrl}\n\n"
            . "Free to claim. First available provider wins.";
    }

    private function sendInvitation(Company $company, string $body, string $claimUrl): bool
    {
        if ($company->phone && $this->sendSms($company->phone, $body)) {
            return true;
        }

        if ($company->email) {
            return $this->sendEmail($company->email, $company->name, $body, $claimUrl);
        }

        return false;
    }

    private function sendSms(string $phone, string $body): bool
    {
        if (!config('services.twilio.sid') || !config('services.twilio.token') || !config('services.twilio.from')) {
            return false;
        }

        try {
            $this->twilio()->messages->create(
                $this->formatPhone($phone),
                ['from' => config('services.twilio.from'), 'body' => $body],
            );

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Ghost outreach SMS failed: ' . $exception->getMessage());

            return false;
        }
    }

    private function sendEmail(string $email, string $companyName, string $body, string $claimUrl): bool
    {
        try {
            Mail::raw($body, function ($message) use ($email, $companyName) {
                $message->to($email)
                    ->subject("Customer waiting near you — claim {$companyName} on LockNear");
            });

            return true;
        } catch (\Throwable $exception) {
            Log::warning("Ghost outreach email failed for {$email}: " . $exception->getMessage());

            return false;
        }
    }

    private function twilio(): Client
    {
        if ($this->twilio === null) {
            $this->twilio = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token'),
            );
        }

        return $this->twilio;
    }

    private function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) === 10 ? "+1{$digits}" : "+{$digits}";
    }
}
