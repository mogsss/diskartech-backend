<?php

namespace App\Models\Concerns;

use App\Services\UploadStorage;

trait SerializesCloudinaryDocuments
{
    protected bool $includeOwnedDocumentLinks = false;

    // Registration/login responses are built before authentication exists.
    public function withOwnedDocumentLinks(): static
    {
        $this->includeOwnedDocumentLinks = true;
        return $this;
    }

    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();
        // Store permanent references, but serialize fresh links for existing
        // clients. Raw model values remain unchanged for queued AI jobs.
        foreach (['school_id', 'coe', 'student_resume', 'valid_id_path', 'employer_certificate_path'] as $field) {
            if (is_string($attributes[$field] ?? null) && app(UploadStorage::class)->isDocument($attributes[$field])) {
                $user = auth()->user();
                $ownsProfile = $user && (string) $user->id === (string) $this->getRawOriginal('user_id');
                $canView = $this->includeOwnedDocumentLinks || $ownsProfile || auth('admin')->check();
                if (! $canView && $field === 'student_resume' && $user && in_array($user->role, ['employer', 'household'], true)) {
                    $canView = $this->applications()->whereHas('job', fn ($job) => $job->where('user_id', $user->id))->exists();
                }
                if ($canView) $attributes[$field] = app(UploadStorage::class)->url($attributes[$field]);
                else unset($attributes[$field]);
            }
        }
        return $attributes;
    }
}
