<?php

namespace App\Services;

use App\Events\NewDispatchRequest;
use App\Mail\CustomerLeadMail;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadToken;
use App\Support\LockNearUrls;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

class DispatchService
{
    private ?Client $twilio = null;
    private string $from;

    public function __construct()
    {
        $this->from = config('services.twilio.from');
    }

    public function dispatch(Lead $lead, int $maxRecipients = 5): int
    {
        $companies = $this->findNearbyCompanies($lead);

        if ($companies->isEmpty()) {
            return app(GhostOutreachService::class)->inviteForLead(
                $lead,
                (int) config('locknear.outreach.ghost_dispatch_limit', 20),
            );
        }

        $sent = 0;
        $acceptMinutes = config('locknear.dispatch.accept_token_minutes', 30);

        foreach ($companies->take($maxRecipients) as $company) {
            if (!$company->phone) continue;

            $acceptToken = LeadToken::generate($lead->id, $company->id, 'accept', $acceptMinutes);
            $rejectToken = LeadToken::generate($lead->id, $company->id, 'reject', $acceptMinutes);

            $service = str($lead->service_type)->replace('-', ' ')->title();
            $location = $lead->city ? "{$lead->city}, {$lead->state}" : "ZIP {$lead->zip}";
            $acceptUrl = LockNearUrls::dispatchAccept($acceptToken->token);
            $rejectUrl = LockNearUrls::dispatchReject($rejectToken->token);
            $providerLeadUrl = LockNearUrls::providerLead($lead->id);

            if ($company->is_claimed) {
                $body = "🔑 NEW JOB — LockNear\n"
                    . "Service: {$service}\n"
                    . "Location: {$location}\n\n"
                    . "✅ ACCEPT: {$acceptUrl}\n"
                    . "📱 Open in app: {$providerLeadUrl}\n"
                    . "❌ PASS: {$rejectUrl}\n\n"
                    . "First to accept wins. Expires in 30 min.";
            } else {
                $claimToken = $company->ensureClaimToken();
                $body = "🔑 LockNear: Someone near you needs a {$service} in {$location}.\n\n"
                    . "Claim your FREE locksmith profile to accept this job:\n"
                    . LockNearUrls::providerApp() . "/claim/{$claimToken}\n\n"
                    . "Takes 2 minutes. No credit card needed.";
            }

            broadcast(new NewDispatchRequest(
                $lead,
                $company,
                $acceptToken->token,
                $rejectToken->token,
                $acceptToken->expires_at,
            ));

            if ($this->sendSms($company->phone, $body)) {
                $sent++;
            }
        }

        if ($sent === 0) {
            return app(GhostOutreachService::class)->inviteForLead(
                $lead,
                (int) config('locknear.outreach.ghost_dispatch_limit', 20),
            );
        }

        return $sent;
    }

    public function sendCustomerTrackLink(Lead $lead): void
    {
        if (!$lead->customer_token) {
            return;
        }

        $trackUrl = LockNearUrls::customerTrack($lead);
        $service = str($lead->service_type)->replace('-', ' ')->title();
        $location = $lead->city ? "{$lead->city}, {$lead->state}" : "ZIP {$lead->zip}";

        $smsBody = "🔑 LockNear: Your {$service} request in {$location} is live.\n\n"
            . "📍 Track status anytime:\n{$trackUrl}\n\n"
            . "We're matching a verified locksmith now.\n— LockNear";

        $mail = new CustomerLeadMail(
            subjectLine: "Your {$service} request is live — LockNear",
            headline: 'Your locksmith request is live',
            body: "Your {$service} request in {$location} is active. We're matching a verified locksmith now.\n\nTrack status anytime from the link below.",
            actionUrl: $trackUrl,
            actionLabel: 'Track your request',
        );

        $this->notifyCustomer($lead, $smsBody, $mail);
    }

    public function sendCustomerConfirmation(Lead $lead, Company $company): void
    {
        if (!$lead->customer_token) {
            return;
        }

        $trackUrl = LockNearUrls::customerTrack($lead);

        $smsBody = "✅ Found! {$company->name} is heading your way.\n\n"
            . "📍 Track your locksmith:\n{$trackUrl}\n\n"
            . "— LockNear";

        $mail = new CustomerLeadMail(
            subjectLine: "{$company->name} is heading your way — LockNear",
            headline: 'A locksmith is on the way',
            body: "{$company->name} accepted your request and is heading to you now.\n\nOpen the track page to see live status and ETA.",
            actionUrl: $trackUrl,
            actionLabel: 'Track your locksmith',
        );

        $this->notifyCustomer($lead, $smsBody, $mail);
    }

    public function sendPostJobSurvey(Lead $lead, Company $company): void
    {
        $reviewToken = LeadToken::generate($lead->id, $company->id, 'review', 2880);
        $reviewUrl = LockNearUrls::api() . '/api/dispatch/review/' . $reviewToken->token;

        $smsBody = "How was {$company->name}? ⭐\nRate your experience (10 sec):\n{$reviewUrl}\n— LockNear";

        $mail = new CustomerLeadMail(
            subjectLine: "How was your experience with {$company->name}?",
            headline: 'Rate your locksmith',
            body: "Your job with {$company->name} is complete. Please take 10 seconds to rate your experience — it helps other customers find trusted locksmiths.",
            actionUrl: $reviewUrl,
            actionLabel: 'Leave a review',
        );

        $this->notifyCustomer($lead, $smsBody, $mail);
    }

    private function notifyCustomer(Lead $lead, string $smsBody, CustomerLeadMail $mail): void
    {
        $smsSent = false;

        if ($this->isValidCustomerPhone($lead->phone)) {
            $smsSent = $this->sendSms($lead->phone, $smsBody);
        }

        if (!$smsSent && $this->isValidCustomerEmail($lead->email)) {
            $this->sendEmail($lead->email, $mail);
        }
    }

    private function findNearbyCompanies(Lead $lead)
    {
        $awayCutoff = now()->subMinutes(config('locknear.presence.away_minutes', 2));

        $query = Company::where('is_active', true)
            ->where('is_online', true)
            ->where('last_seen_at', '>=', $awayCutoff)
            ->whereHas('services', fn ($q) =>
                $q->where('service_type', $lead->service_type)->where('is_active', true)
            )
            ->where(function ($q) use ($lead) {
                $q->whereJsonContains('service_areas', $lead->zip)
                    ->orWhere('zip', $lead->zip);
            });

        if (config('locknear.dispatch.require_subscription', false)) {
            $query->whereHas('subscription', fn ($q) =>
                $q->whereIn('status', ['active', 'trialing'])
            );
        }

        // When the lead has coordinates, rank closer companies higher.
        // ZIP match remains the primary filter; distance is a tiebreaker.
        if ($lead->latitude && $lead->longitude) {
            $lat = (float) $lead->latitude;
            $lng = (float) $lead->longitude;
            $query->selectRaw(
                'companies.*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance_km',
                [$lat, $lng, $lat],
            )->orderByDesc('is_claimed')
             ->orderByRaw('distance_km IS NULL')
             ->orderBy('distance_km')
             ->orderByDesc('rating');
        } else {
            $query->orderByDesc('is_claimed')->orderByDesc('rating');
        }

        return $query->limit(10)->get();
    }

    private function sendSms(string $phone, string $body): bool
    {
        if (!config('services.twilio.sid') || !config('services.twilio.token')) {
            return false;
        }

        try {
            $this->twilio()->messages->create(
                $this->formatPhone($phone),
                ['from' => $this->from, 'body' => $body]
            );

            return true;
        } catch (\Exception $e) {
            Log::warning('Twilio SMS failed: ' . $e->getMessage());

            return false;
        }
    }

    private function sendEmail(string $email, CustomerLeadMail $mail): bool
    {
        try {
            Mail::to($email)->send($mail);

            return true;
        } catch (\Exception $e) {
            Log::warning("Customer email failed for {$email}: " . $e->getMessage());

            return false;
        }
    }

    private function twilio(): Client
    {
        if ($this->twilio === null) {
            $this->twilio = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );
        }

        return $this->twilio;
    }

    private function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) === 10 ? "+1{$digits}" : "+{$digits}";
    }

    private function isValidCustomerPhone(?string $phone): bool
    {
        if (!$phone || strtolower($phone) === 'pending') {
            return false;
        }

        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) >= 10;
    }

    private function isValidCustomerEmail(?string $email): bool
    {
        if (!$email) {
            return false;
        }

        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
