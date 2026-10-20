<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccountDeletion
{
    private const PROFILE_FILES = ['avatar', 'profile_picture', 'student_resume', 'school_id', 'coe', 'valid_id_path', 'employer_certificate_path'];
    private const NAMES = ['student_name', 'student_school_name', 'employer_name', 'hirer_name', 'household_name'];

    // The caller holds the account lock and verifies the owner challenge or admin confirmation.
    public function delete(User $user, bool $byAdmin = false): void
    {
        $studentId = DB::table('students')->where('user_id', $user->id)->value('id');
        $jobIds = DB::table('available_jobs')->where('user_id', $user->id)->pluck('id');
        $applications = DB::table('job_applications')->where(function ($query) use ($studentId, $jobIds) {
            $query->whereIn('job_id', $jobIds);
            if ($studentId) $query->orWhere('student_id', $studentId);
        });
        (clone $applications)->whereIn('status', ['accepted', 'hired'])->update([
            'status' => 'terminated', 'terminated_at' => now(),
            'termination_reason' => $byAdmin
                ? 'A participant\'s DiskarTech account was permanently deleted by an administrator. Outstanding obligations must be settled directly.'
                : 'A participant permanently deleted their DiskarTech account. Outstanding obligations must be settled directly.',
            'interview_ended_at' => now(), 'updated_at' => now(),
        ]);
        (clone $applications)->whereIn('status', ['pending', 'viewed', 'interview'])->update([
            'status' => 'cancelled', 'interview_ended_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('available_jobs')->whereIn('id', $jobIds)->update(['status' => 'closed', 'updated_at' => now()]);
        if (Schema::hasTable('job_postings')) DB::table('job_postings')->where('user_id', $user->id)->update(['status' => 'closed', 'updated_at' => now()]);
        if ($studentId) DB::table('saved_jobs')->where('student_id', $studentId)->delete();

        foreach (['students', 'employers', 'households'] as $table) {
            $profiles = DB::table($table)->where('user_id', $user->id)->lockForUpdate()->get();
            if ($profiles->isEmpty()) continue;
            foreach ($profiles as $profile) {
                foreach (self::PROFILE_FILES as $field) {
                    if (!empty($profile->$field)) DB::table('account_deletion_files')->insert([
                        'user_id' => $user->id, 'path' => $profile->$field, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            // Clear all profile fields, including legacy/AI fields, respecting the
            // deployed schema's nullability. Keep only foreign keys and timestamps.
            $cleared = [];
            foreach (Schema::getColumns($table) as $column) {
                $name = $column['name'];
                if (in_array($name, ['id', 'user_id', 'created_at', 'updated_at'], true)) continue;
                $cleared[$name] = in_array($name, self::NAMES, true) ? 'Deleted account'
                    : ($column['nullable'] ? null : (preg_match('/int|bool|decimal|double|float|real|numeric/i', $column['type_name']) ? 0 : ''));
            }
            foreach (['isVerified', 'isSubscribed'] as $field) if (array_key_exists($field, $cleared)) $cleared[$field] = false;
            DB::table($table)->where('user_id', $user->id)->update([...$cleared, 'updated_at' => now()]);
        }

        if (Schema::hasTable('mobile_password_resets')) DB::table('mobile_password_resets')->where('user_id', $user->id)->delete();
        DB::table('account_deletion_codes')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        // Retain an anonymous relational placeholder rather than cascade-delete
        // the other participant's contracts, reviews, payment or safety records.
        $user->forceFill([
            'email' => 'deleted-'.Str::uuid().'@deleted.invalid',
            'password' => Hash::make(Str::random(64)), 'role' => 'deleted',
            'registered_with_google' => false, 'isEmailVerified' => false,
            'otp_code' => null, 'otp_expires_at' => null,
        ])->save();
    }
}
