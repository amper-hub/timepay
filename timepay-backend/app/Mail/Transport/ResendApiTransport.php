<?php

namespace App\\Mail\\Transport;

use Illuminate\\Support\\Facades\\Http;
use Symfony\\Component\\Mailer\\Exception\\TransportException;
use Symfony\\Component\\Mailer\\SentMessage;
use Symfony\\Component\\Mailer\\Transport\\AbstractTransport;
use Symfony\\Component\\Mime\\Address;
use Symfony\\Component\\Mime\\Email;
use Throwable;

class ResendApiTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $sentMessage): void
    {
        if ($this->apiKey === '') {
            throw new TransportException('The RESEND_API_KEY environment variable is not configured.');
        }

        $email = $sentMessage->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('Resend can only send standard email messages.');
        }

        $from = $email->getFrom()[0] ?? null;

        if (! $from instanceof Address) {
            throw new TransportException('The email message does not have a valid From address.');
        }

        $payload = [
            'from' => $from->toString(),
            'to' => $this->formatAddresses($email->getTo()),
            'subject' => $email->getSubject() ?? '',
        ];

        if ($email->getCc() !== []) {
            $payload['cc'] = $this->formatAddresses($email->getCc());
        }

        if ($email->getBcc() !== []) {
            $payload['bcc'] = $this->formatAddresses($email->getBcc());
        }

        if ($email->getReplyTo() !== []) {
            $payload['reply_to'] = $this->formatAddresses($email->getReplyTo());
        }

        if (($html = $email->getHtmlBody()) !== null) {
            $payload['html'] = $html;
        }

        if (($text = $email->getTextBody()) !== null) {
            $payload['text'] = $text;
        }

        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                'filename' => $attachment->getFilename() ?: 'attachment',
                'content' => base64_encode($attachment->bodyToString()),
            ];
        }

        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout(20)
                ->post('https://api.resend.com/emails', $payload);

            if (! $response->successful()) {
                $detail = $response->json('message') ?: $response->json('name') ?: 'No additional details provided.';
                throw new TransportException(
                    'Resend API returned HTTP '.$response->status().': '.$detail
                );
            }
        } catch (TransportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TransportException(
                'Resend email delivery failed: '.$exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * @param array<int, Address> $addresses
     * @return array<int, string>
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(
            static fn (Address $address): string => $address->toString(),
            $addresses
        );
    }

    public function __toString(): string
    {
        return 'resend-api';
    }
}
