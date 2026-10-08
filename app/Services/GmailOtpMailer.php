<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class GmailOtpMailer
{
    public function sendVerificationCode(string $recipient, string $otp): void
    {
        $settings = config('services.gmail');
        foreach (['client_id', 'client_secret', 'refresh_token', 'from_address'] as $field) {
            if (empty($settings[$field])) {
                throw new GmailDeliveryException('Gmail OTP credentials are not configured.');
            }
        }

        // Changing credentials also invalidates the cached access token.
        $cacheKey = 'gmail:access-token:'.hash('sha256', implode('|', [
            $settings['client_id'], $settings['client_secret'], $settings['refresh_token'],
        ]));
        $accessToken = Cache::get($cacheKey);
        $timeout = max(1, (int) $settings['timeout']);

        if (! $accessToken) {
            $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout($timeout)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $settings['client_id'],
                    'client_secret' => $settings['client_secret'],
                    'refresh_token' => $settings['refresh_token'],
                    'grant_type' => 'refresh_token',
                ]);

            $accessToken = $response->json('access_token');
            $expiresIn = (int) $response->json('expires_in', 0);
            if (! $response->successful() || ! is_string($accessToken) || $accessToken === '' || $expiresIn <= 0) {
                throw new GmailDeliveryException('Gmail authorization failed (HTTP '.$response->status().').');
            }

            Cache::put($cacheKey, $accessToken, max(1, $expiresIn - 60));
        }

        $expiryMinutes = max(1, (int) config('services.otp.expiry_minutes', 5));

        $message = (new Email)
            ->from(new Address($settings['from_address'], 'DiskarTech'))
            ->to($recipient)
            ->subject('Verify Your Email Address | DiskarTech')
            ->text("Hello,\n\nYour DiskarTech verification code is: {$otp}\n\n"
                ."This code expires in {$expiryMinutes} minutes. Do not share it with anyone.\n\n"
                ."If you did not request this code, please ignore this email.\n\nThe DiskarTech Team")
            ->html('<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:24px">'
                .'<h1 style="color:#4f46e5">DiskarTech</h1><p>Hello,</p>'
                .'<p>Enter this verification code to verify your email address:</p>'
                .'<p style="font-size:36px;font-weight:bold;letter-spacing:8px;color:#4f46e5">'.$otp.'</p>'
                .'<p>This code expires in {$expiryMinutes} minutes. Do not share it with anyone.</p>'
                .'<p>If you did not request this code, please ignore this email.</p></div>');

        $response = Http::withToken($accessToken)->acceptJson()->connectTimeout(5)->timeout($timeout)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '='),
            ]);

        if ($response->status() === 401) {
            Cache::forget($cacheKey);
        }

        if (! $response->successful() || ! $response->json('id')) {
            // Do not log provider bodies: they can contain tokens or message data.
            throw new GmailDeliveryException('Gmail did not accept the OTP email (HTTP '.$response->status().').');
        }
    }
}
