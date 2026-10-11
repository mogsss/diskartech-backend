<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeVerificationDocument;
use App\Models\Employer;
use App\Models\Household;
use App\Models\Student;
use Illuminate\Console\Command;

class RetryPendingDocumentAnalysis extends Command
{
    protected $signature = 'verification:retry-analysis
        {--dispatch : Queue the pending document checks; otherwise preview only}
        {--user-id= : Limit checks to one user ID}';

    protected $description = 'Preview or queue verification documents that have no completed AI result';

    public function handle(): int
    {
        $userId = $this->option('user-id');
        if ($userId !== null && ! preg_match('/^[1-9][0-9]*$/D', (string) $userId)) {
            $this->error('Use a positive numeric user ID.');
            return self::FAILURE;
        }
        $dispatch = (bool) $this->option('dispatch');
        if ($dispatch && ! config('services.gemini.key')) {
            $this->error('Configure GEMINI_KEY before dispatching document checks.');
            return self::FAILURE;
        }
        if ($dispatch && in_array(config('queue.default'), ['sync', 'null'], true)) {
            $this->error('Use an asynchronous queue connection, such as database, before dispatching checks.');
            return self::FAILURE;
        }

        $fields = [
            Student::class => [
                ['school_id', 'school_id_ai_is_valid', 'school_id'],
                ['coe', 'coe_ai_is_valid', 'coe'],
            ],
            Employer::class => [
                ['valid_id_path', 'ai_is_valid', 'valid_id'],
                ['employer_certificate_path', 'cert_ai_is_valid', 'certificate'],
            ],
            Household::class => [['valid_id_path', 'ai_is_valid', 'valid_id']],
        ];
        $pending = 0;
        foreach ($fields as $model => $documents) {
            $query = $model::query()->when($userId !== null, fn ($query) => $query->where('user_id', $userId));
            foreach ($query->lazyById(100) as $profile) {
                foreach ($documents as [$field, $validField, $type]) {
                    $path = $profile->getRawOriginal($field);
                    if (! is_string($path) || trim($path) === '' || $profile->getRawOriginal($validField) !== null) continue;
                    $pending++;
                    $this->line(class_basename($model).' #'.$profile->getKey().' '.$type.': pending.');
                    if ($dispatch) {
                        AnalyzeVerificationDocument::dispatch($profile, $path, null, $type)->afterCommit();
                    }
                }
            }
        }
        $this->info("Pending documents: {$pending}.");
        $this->line($dispatch
            ? 'Submitted pending checks to the queue; existing active checks are not duplicated. Completed results and verification status are unchanged.'
            : 'Preview only. Run again with --dispatch to queue these checks.');
        return self::SUCCESS;
    }
}
