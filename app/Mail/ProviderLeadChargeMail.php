<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderLeadChargeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public Lead $lead,
        public float $amount,
        public ?string $chargeId,
    ) {}

    public function envelope(): Envelope
    {
        $service = str($this->lead->service_type)->replace('-', ' ')->title();

        return new Envelope(
            subject: "Lead accepted — \${$this->amount} charged ({$service})",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.provider-lead-charge');
    }
}
