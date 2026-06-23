<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class CloudflareTransport extends AbstractTransport
{
    public function __construct(
        private readonly ?string $accountId,
        private readonly ?string $apiToken,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (!$this->accountId || !$this->apiToken) {
            throw new TransportException('Cloudflare Email Sending is not configured.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $payload = $this->buildPayload($email);

        $response = Http::withToken($this->apiToken)
            ->acceptJson()
            ->timeout(15)
            ->post(
                "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/email/sending/send",
                $payload,
            );

        if (!$response->successful()) {
            $error = $response->json('errors.0.message') ?? $response->body();
            throw new TransportException("Cloudflare email HTTP {$response->status()}: {$error}");
        }

        if (!$response->json('success')) {
            $error = $response->json('errors.0.message') ?? 'Unknown Cloudflare email error';
            throw new TransportException("Cloudflare email rejected: {$error}");
        }
    }

  /** @return array<string, mixed> */
    private function buildPayload(Email $email): array
    {
        $from = $email->getFrom();
        if ($from === []) {
            throw new TransportException('Cloudflare email requires a from address.');
        }

        $payload = [
            'to' => $this->formatRecipients($email->getTo()),
            'from' => $this->formatAddress($from[0]),
            'subject' => $email->getSubject() ?? '',
        ];

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        if (is_string($html) && $html !== '') {
            $payload['html'] = $html;
        }

        if (is_string($text) && $text !== '') {
            $payload['text'] = $text;
        } elseif (!empty($payload['html'])) {
            $payload['text'] = trim(preg_replace('/\s+/', ' ', strip_tags($payload['html']))) ?: ' ';
        }

        if ($email->getCc()) {
            $payload['cc'] = $this->formatRecipients($email->getCc());
        }

        if ($email->getBcc()) {
            $payload['bcc'] = $this->formatRecipients($email->getBcc());
        }

        $replyTo = $email->getReplyTo();
        if ($replyTo !== []) {
            $payload['reply_to'] = $this->formatAddress($replyTo[0]);
        }

        return $payload;
    }

    /** @param Address[] $addresses */
    private function formatRecipients(array $addresses): string|array
    {
        $emails = array_map(fn (Address $address) => $address->getAddress(), $addresses);

        return count($emails) === 1 ? $emails[0] : $emails;
    }

    private function formatAddress(Address $address): string|array
    {
        if ($address->getName()) {
            return [
                'address' => $address->getAddress(),
                'name' => $address->getName(),
            ];
        }

        return $address->getAddress();
    }

    public function __toString(): string
    {
        return 'cloudflare';
    }
}
