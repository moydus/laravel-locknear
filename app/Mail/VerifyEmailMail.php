<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verifyUrl;

    public string $logoUrl;

    public string $marketingUrl;

    public function __construct(public User $user)
    {
        $appUrl = rtrim((string) config('locknear.app_url', 'http://localhost:3000'), '/');
        $this->logoUrl = $appUrl . '/locknear.svg';
        $this->marketingUrl = rtrim(
            (string) config('locknear.marketing_url', config('services.frontend_url', 'https://locknear.com')),
            '/',
        );

        $this->verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your LockNear provider account',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.verify-email');
    }
}
