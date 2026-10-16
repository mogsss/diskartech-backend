<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletion;
use App\Services\GmailOtpMailer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AccountDeletionController extends Controller
{
    private function account(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['student', 'employer', 'household'], true)
            && $user->currentAccessToken() instanceof PersonalAccessToken, 403);
        return $user;
    }

    public function sendCode(Request $request, GmailOtpMailer $mailer)
    {
        $account = $this->account($request);
        $key = 'account-deletion:'.$account->id;
        if (RateLimiter::tooManyAttempts($key.':minute', 1) || RateLimiter::tooManyAttempts($key.':hour', 3)) {
            $wait = max(RateLimiter::availableIn($key.':minute'), RateLimiter::tooManyAttempts($key.':hour', 3) ? RateLimiter::availableIn($key.':hour') : 0, 1);
            return response()->json(['message' => 'Please wait before requesting another deletion code.', 'retry_after' => $wait], 429)
                ->header('Retry-After', $wait)->header('Cache-Control', 'no-store');
        }
        RateLimiter::hit($key.':minute', 60);
        RateLimiter::hit($key.':hour', 3600);
        try {
            $sent = DB::transaction(function () use ($account, $request, $mailer) {
                $user = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
                $previous = DB::table('account_deletion_codes')->where('user_id', $user->id)->first();
                if ($previous && Carbon::parse($previous->sent_at)->addSeconds(60)->isFuture()) return false;
                $code = (string) random_int(100000, 999999);
                $mailer->sendAccountDeletionCode($user->email, $code);
                DB::table('account_deletion_codes')->updateOrInsert(['user_id' => $user->id], [
                    'session_hash' => hash('sha256', $request->bearerToken()),
                    'code_hash' => hash_hmac('sha256', $code, config('app.key')),
                    'expires_at' => now()->addMinutes(10), 'attempts' => 0, 'sent_at' => now(),
                ]);
                return true;
            });
        } catch (\Throwable) {
            Log::warning('Account deletion email delivery could not complete.');
            return response()->json(['message' => 'Unable to send the deletion code right now. Please try again later.'], 503)->header('Cache-Control', 'no-store');
        }
        if (!$sent) return response()->json(['message' => 'Please wait before requesting another deletion code.', 'retry_after' => 60], 429)->header('Retry-After', 60)->header('Cache-Control', 'no-store');
        return response()->json(['message' => 'A deletion code was sent to your registered email. It expires in 10 minutes.', 'expires_in' => 600, 'retry_after' => 60])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, AccountDeletion $deletion)
    {
        $account = $this->account($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'confirmation' => ['required', 'in:DELETE'],
            'acknowledged' => ['required', 'accepted'],
        ]);
        $deleted = DB::transaction(function () use ($request, $account, $data, $deletion) {
            $user = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $challenge = DB::table('account_deletion_codes')->where('user_id', $user->id)->lockForUpdate()->first();
            if (!$challenge || $challenge->attempts >= 5 || Carbon::parse($challenge->expires_at)->lessThanOrEqualTo(now())
                || !hash_equals($challenge->session_hash, hash('sha256', $request->bearerToken()))) return false;
            if (!hash_equals($challenge->code_hash, hash_hmac('sha256', $data['code'], config('app.key')))) {
                // Return rather than throw inside the transaction so failed attempts persist.
                DB::table('account_deletion_codes')->where('user_id', $user->id)->increment('attempts');
                return false;
            }
            $deletion->delete($user);
            DB::table('account_deletion_codes')->where('user_id', $user->id)->delete();
            return true;
        });
        if (!$deleted) throw ValidationException::withMessages(['code' => 'The code is incorrect, expired, or unavailable for this session. Request a new code.']);
        return response()->json(['status' => 'success', 'message' => 'Your DiskarTech account has been permanently deleted.'])->header('Cache-Control', 'no-store');
    }
}
