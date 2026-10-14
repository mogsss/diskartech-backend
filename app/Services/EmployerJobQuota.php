<?php

namespace App\Services;

use App\Models\Employer;
use App\Models\Jobs;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployerJobQuota
{
    public const LIMITS = ['basic' => 3, 'premium' => null];
    private const CLOSED = ['closed', 'inactive', 'archived', '0', 'false'];

    public function plan(?Employer $profile): string
    {
        $expires = $profile?->subscription_expires_at;
        return $profile && (bool) $profile->isSubscribed
            && (!$expires || CarbonImmutable::parse($expires, 'UTC')->isFuture()) ? 'premium' : 'basic';
    }

    public static function consumesSlot(?string $status): bool
    {
        return !in_array(strtolower(trim($status ?? '')), self::CLOSED, true);
    }

    public function usage(User $user, ?Employer $profile = null): ?array
    {
        if ($user->role !== 'employer') return null;
        $profile ??= Employer::where('user_id', $user->id)->first();
        $plan = $this->plan($profile);
        $limit = self::LIMITS[$plan];
        // Hidden/open and legacy blank statuses reserve a slot, matching the jobs screen.
        $count = Jobs::where('user_id', $user->id)
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN (?, ?, ?, ?, ?)", self::CLOSED)->count();
        return ['plan' => $plan, 'active_job_limit' => $limit, 'active_job_count' => $count,
            'remaining_job_slots' => $limit === null ? null : max(0, $limit - $count), 'can_post_job' => $limit === null || $count < $limit, 'limits' => self::LIMITS];
    }

    private function assertAvailable(User $user, ?Employer $profile): void
    {
        $quota = $this->usage($user, $profile);
        if (!$quota || $quota['can_post_job']) return;
        $message = 'Your '.ucfirst($quota['plan']).' plan allows up to '.$quota['active_job_limit'].' active job posts. Close an existing post before creating or reopening another.';
        if ($quota['plan'] === 'basic') $message .= ' Upgrade to Premium for unlimited active job posts.';
        throw ValidationException::withMessages(['active_job_limit' => $message]);
    }

    private function withOwnerLock(User $user, callable $operation)
    {
        return DB::transaction(function () use ($user, $operation) {
            $owner = User::whereKey($user->id)->lockForUpdate()->first() ?? $user;
            $profile = $owner->role === 'employer' ? Employer::where('user_id', $owner->id)->lockForUpdate()->first() : null;
            return $operation($owner, $profile);
        });
    }

    public function create(User $user, array $attributes): Jobs
    {
        return $this->withOwnerLock($user, function ($owner, $profile) use ($attributes) {
            $this->assertAvailable($owner, $profile);
            return Jobs::create($attributes);
        });
    }

    public function changeStatus(Jobs $job, string $status): Jobs
    {
        $owner = $job->user;
        $change = function (?User $user, ?Employer $profile) use ($job, $status) {
            $locked = Jobs::whereKey($job->id)->where('user_id', $job->user_id)->lockForUpdate()->firstOrFail();
            if ($user && self::consumesSlot($status) && !self::consumesSlot($locked->status)) $this->assertAvailable($user, $profile);
            $locked->status = $status;
            $locked->save();
            return $locked;
        };
        return $owner ? $this->withOwnerLock($owner, $change) : DB::transaction(fn () => $change(null, null));
    }
}
