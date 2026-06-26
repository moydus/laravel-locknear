<?php

namespace App\Services;

use App\Mail\CustomerLeadMail;
use App\Models\Lead;
use Illuminate\Support\Facades\Mail;

class NotificationEngine
{
    public function sms(string $phone, string $body): bool
    {
        return false;
    }

    public function email(string $email, CustomerLeadMail $mail): bool
    {
        Mail::to($email)->send($mail);

        return true;
    }

    public function push(int|string $recipientId, array $payload): bool
    {
        return false;
    }

    public function customer(Lead $lead, string $smsBody, CustomerLeadMail $mail): void
    {
        if ($lead->email) {
            $this->email($lead->email, $mail);
        }
    }
}
