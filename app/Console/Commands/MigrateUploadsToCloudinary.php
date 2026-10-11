<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeVerificationDocument;
use App\Models\Employer;
use App\Models\Household;
use App\Models\Student;
use App\Services\UploadStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

class MigrateUploadsToCloudinary extends Command
{
    protected $signature = 'uploads:migrate-cloudinary {--write : Upload available local files and save their new references}';

    protected $description = 'Preview or migrate existing local profile uploads to Cloudinary, preserving the original files';

    public function handle(UploadStorage $storage): int
    {
        $write = (bool) $this->option('write');
        if ($write && config('uploads.driver') !== 'cloudinary') {
            $this->error('Set UPLOAD_STORAGE=cloudinary before using --write.');
            return self::FAILURE;
        }
        $fields = [
            Student::class => ['avatar' => null, 'student_resume' => null, 'school_id' => 'school_id', 'coe' => 'coe'],
            Employer::class => ['avatar' => null, 'valid_id_path' => 'valid_id', 'employer_certificate_path' => 'certificate'],
            Household::class => ['avatar' => null, 'valid_id_path' => 'valid_id'],
        ];
        $available = $migrated = $missing = $failed = 0;
        foreach ($fields as $model => $columns) {
            foreach ($model::query()->lazyById(100) as $profile) {
                foreach ($columns as $field => $analysisType) {
                    $path = $profile->getRawOriginal($field);
                    if (! is_string($path) || $path === '' || preg_match('~^https?://~i', $path) || $storage->isDocument($path)) continue;
                    $label = class_basename($model).' #'.$profile->id.' '.$field;
                    try {
                        $storage->assertSafePath($path);
                        if (! Storage::disk('public')->exists($path)) {
                            $missing++;
                            $this->warn($label.': original file missing; reference retained.');
                            continue;
                        }
                        $available++;
                        if (! $write) {
                            $this->line($label.': available to migrate.');
                            continue;
                        }
                        $file = new File(Storage::disk('public')->path($path));
                        $reference = $storage->store($file, dirname($path) === '.' ? 'legacy' : dirname($path), in_array($field, ['avatar', 'profile_picture'], true));
                        // Do not overwrite an upload made while the migration was running.
                        $updated = $model::query()->whereKey($profile->id)->where($field, $path)->update([$field => $reference]);
                        if (! $updated) {
                            $this->warn($label.': changed during migration; current reference retained.');
                            continue;
                        }
                        $migrated++;
                        $validField = match ($analysisType) {
                            'school_id' => 'school_id_ai_is_valid', 'coe' => 'coe_ai_is_valid',
                            'certificate' => 'cert_ai_is_valid', default => 'ai_is_valid',
                        };
                        if ($analysisType !== null && $profile->getRawOriginal($validField) === null) {
                            AnalyzeVerificationDocument::dispatch($profile->fresh(), $reference, $file->getMimeType(), $analysisType)->afterCommit();
                        }
                        $this->info($label.': migrated.');
                    } catch (\Throwable $exception) {
                        $failed++;
                        $this->error($label.': migration failed; original file retained. Check storage configuration and file size.');
                    }
                }
            }
        }
        $this->info("Available: {$available}; migrated: {$migrated}; missing: {$missing}; failed: {$failed}.");
        if (! $write) $this->line('Preview only. Run again with --write to upload and save references.');
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
