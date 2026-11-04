<?php

namespace Tests\Unit;

use App\Mail\Transport\ResendApiTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ResendApiTransportTest extends TestCase
{
    public function test_it_sends_email_through_the_resend_https_api(): void
    {
        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'email_test_123'], 200),
        ]);

        $email = (new Email())
            ->from('TimePay <onboarding@resend.dev>')
            ->to('amperaxell@gmail.com')
            ->subject('Verify your TimePay account')
            ->html('<p>Verify your account.</p>');

        (new ResendApiTransport('re_test_key'))->send($email);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.resend.com/emails'
                && $request->hasHeader('Authorization', 'Bearer re_test_key')
                && $request['from'] === 'TimePay <onboarding@resend.dev>'
                && $request['to'] === ['amperaxell@gmail.com']
                && $request['subject'] === 'Verify your TimePay account';
        });
    }
}
