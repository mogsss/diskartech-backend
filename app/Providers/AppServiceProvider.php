<?php

namespace App\Providers;

use App\Support\PlainTextInput;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $retryResponse = static function (Request $request, array $headers) {
            $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));

            return response()->json([
                'status' => 'error',
                'code' => 'login_rate_limited',
                'message' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
                'retry_after' => $seconds,
            ], 429, $headers)->header('Cache-Control', 'no-store, private');
        };

        RateLimiter::for('mobile-login', static function (Request $request) use ($retryResponse) {
            $ip = (string) $request->ip();
            $email = $request->input('email');
            $email = is_string($email) ? mb_strtolower(trim($email)) : '';

            // Hash identifiers so cache keys never contain raw email addresses.
            // The IP limit also covers requests that keep changing email addresses.
            return [
                Limit::perMinute(30)->by('ip:'.hash('sha256', $ip))->response($retryResponse),
                Limit::perMinute(5)->by('email-ip:'.hash('sha256', $email.'|'.$ip))->response($retryResponse),
            ];
        });

        RateLimiter::for('mobile-google-login', static fn (Request $request) => Limit::perMinute(10)->by('ip:'.hash('sha256', (string) $request->ip()))->response($retryResponse)
        );

        $adminRetryResponse = static function (Request $request, array $headers) use ($retryResponse) {
            if ($request->expectsJson()) {
                return $retryResponse($request, $headers);
            }

            $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));

            return response()->view('admin.auth.login', [
                'loginThrottleMessage' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
                'loginEmail' => is_string($request->input('email')) ? $request->input('email') : '',
            ], 429, $headers)->header('Cache-Control', 'no-store, private');
        };

        // Shared by both admin login aliases; separate from mobile login counters.
        RateLimiter::for('admin-login', static function (Request $request) use ($adminRetryResponse) {
            $ip = (string) $request->ip();
            $email = $request->input('email');
            $email = is_string($email) ? mb_strtolower(PlainTextInput::normalize($email)) : '';

            return [
                Limit::perMinute(30)->by('ip:'.hash('sha256', $ip))->response($adminRetryResponse),
                Limit::perMinute(5)->by('email-ip:'.hash('sha256', $email.'|'.$ip))->response($adminRetryResponse),
            ];
        });

        // One allowance across avatar/document routes, devices and tokens for this account.
        RateLimiter::for('profile-uploads', static fn (Request $request) => Limit::perMinute(5)->by('user:'.$request->user()->getAuthIdentifier())
            ->response(static function (Request $request, array $headers) {
                $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));

                return response()->json([
                    'status' => 'error',
                    'code' => 'upload_rate_limited',
                    'message' => "Too many uploads. Please try again in {$seconds} seconds.",
                    'retry_after' => $seconds,
                ], 429, $headers)->header('Cache-Control', 'no-store, private');
            })
        );

        if (config('app.env') === 'production' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            URL::forceScheme('https');
        }
    }
}
