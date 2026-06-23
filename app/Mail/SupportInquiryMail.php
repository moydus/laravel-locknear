<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: string, phone?: string|null, topic: string, message: string}  $inquiry
     */
    public function __construct(public array $inquiry) {}

    public function envelope(): Envelope
    {
        $topic = str($this->inquiry['topic'])->replace('-', ' ')->title();

        return new Envelope(
            subject: "LockNear support: {$topic}",
            replyTo: [$this->inquiry['email']],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support-inquiry');
    }
}
