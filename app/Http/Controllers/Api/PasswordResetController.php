<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GmailOtpMailer;
use App\Services\GmailDeliveryException;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private function email(Request $request): string
    {
        // Normalize identifiers only. Passwords must remain exactly as entered.
        if (is_string($request->input('email'))) $request->merge(['email' => trim($request->input('email'))]);
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:254']]);
        return strtolower($data['email']);
    }

    private function eligible(?User $user): bool
    {
        return $user && in_array($user->role, ['student', 'employer', 'household'], true) && !$user->registered_with_google;
    }

    private function user(string $email): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
    }

    public function sendCode(Request $request, GmailOtpMailer $mailer)
    {
        $email = $this->email($request);
        $cooldown = max(1, (int) config('services.otp.password_reset_resend_cooldown', 90));
        // A separate key replaces the old hourly counter without clearing other
        // accounts' cache entries. Every resend gets the same short cooldown.
        $key = 'password-reset:'.hash('sha256', $email).':cooldown';
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $wait = max(1, RateLimiter::availableIn($key));
            return response()->json(['message' => 'Please wait before requesting another code.', 'retry_after' => $wait], 429)->header('Retry-After', $wait);
        }
        RateLimiter::hit($key, $cooldown);
        $stage = 'load_account';
        try {
            DB::transaction(function () use ($email, $mailer, $cooldown, &$stage) {
                $user = $this->user($email);
                if (!$this->eligible($user)) return;
                $stage = 'load_challenge';
                $previous = DB::table('mobile_password_resets')->where('email', $email)->first();
                if ($previous && Carbon::parse($previous->sent_at)->addSeconds($cooldown)->isFuture()) return;
                $code = (string) random_int(100000, 999999);
                $stage = 'send_email';
                $mailer->sendPasswordResetCode($user->email, $code);
                $stage = 'save_challenge';
                DB::table('mobile_password_resets')->updateOrInsert(['email' => $email], [
                    'user_id' => $user->id, 'code_hash' => hash_hmac('sha256', $code, config('app.key')),
                    'expires_at' => now()->addMinutes(10), 'attempts' => 0,
                    'reset_token_hash' => null, 'reset_expires_at' => null, 'sent_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            // Never log the code, password, email body, or provider exception payload.
            $context = ['stage' => $stage, 'exception_type' => get_class($exception)];
            if ($exception instanceof GmailDeliveryException) $context['reason'] = $exception->getMessage();
            if ($exception instanceof QueryException) $context['sqlstate'] = (string) $exception->getCode();
            Log::channel('stderr')->warning('Password reset email delivery could not complete.', $context);
            return response()->json(['message' => 'Unable to send the reset email right now. Please try again later.'], 503);
        }
        // Same response for missing accounts and Google registration; no account lookup disclosure.
        return response()->json(['status' => 'success', 'message' => 'If this email belongs to an eligible account, a reset code will be sent. For Google registration, use Continue with Google.', 'retry_after' => $cooldown])->header('Cache-Control', 'no-store');
    }

    public function verifyCode(Request $request)
    {
        $email = $this->email($request);
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        $token = DB::transaction(function () use ($email, $data) {
            $user = $this->user($email);
            if (!$this->eligible($user)) return null;
            $reset = DB::table('mobile_password_resets')->where('email', $email)->lockForUpdate()->first();
            if (!$reset || $reset->user_id != $user->id || !$reset->code_hash || $reset->attempts >= 5 || Carbon::parse($reset->expires_at)->lessThanOrEqualTo(now())) return null;
            if (!hash_equals($reset->code_hash, hash_hmac('sha256', $data['code'], config('app.key')))) {
                DB::table('mobile_password_resets')->where('email', $email)->increment('attempts');
                return null;
            }
            $token = bin2hex(random_bytes(32));
            DB::table('mobile_password_resets')->where('email', $email)->update([
                'code_hash' => null, 'reset_token_hash' => hash('sha256', $token), 'reset_expires_at' => now()->addMinutes(10),
            ]);
            return $token;
        });
        if (!$token) throw ValidationException::withMessages(['code' => 'The code is incorrect, expired, or has reached its attempt limit. Request a new code.']);
        return response()->json(['status' => 'success', 'reset_token' => $token, 'expires_in' => 600])->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request)
    {
        $email = $this->email($request);
        $data = $request->validate([
            'reset_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'password' => ['bail', 'required', 'string', 'min:8', 'max:72', 'confirmed',
                'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*(),.?":{}|<>]/', 'not_regex:/[\x00-\x1F\x7F]/',
                function ($attribute, $value, $fail) { if (strlen($value) > 72) $fail('Please choose a shorter password.'); },
            ],
            'password_confirmation' => ['required', 'string', 'max:72'],
        ]);
        DB::transaction(function () use ($email, $data) {
            $user = $this->user($email);
            $reset = DB::table('mobile_password_resets')->where('email', $email)->lockForUpdate()->first();
            if (!$this->eligible($user) || !$reset || $reset->user_id != $user->id || !$reset->reset_token_hash || !$reset->reset_expires_at
                || Carbon::parse($reset->reset_expires_at)->lessThanOrEqualTo(now()) || !hash_equals($reset->reset_token_hash, hash('sha256', $data['reset_token']))) {
                throw ValidationException::withMessages(['reset_token' => 'Your reset session is invalid or expired. Request a new code.']);
            }
            if (Hash::check($data['password'], $user->password)) throw ValidationException::withMessages(['password' => 'Choose a different password from your previous password.']);
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
            $user->tokens()->delete();
            DB::table('mobile_password_resets')->where('email', $email)->delete();
        });
        return response()->json(['status' => 'success', 'message' => 'Password reset. Log in with your new password.'])->header('Cache-Control', 'no-store');
    }
}
