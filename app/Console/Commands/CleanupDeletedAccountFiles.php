<?php

namespace App\Console\Commands;

use App\Services\UploadStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CleanupDeletedAccountFiles extends Command
{
    protected $signature = 'accounts:cleanup-files {--limit=25}';
    protected $description = 'Remove queued profile files for permanently deleted accounts; retain failed items for retry';

    public function handle(UploadStorage $storage): int
    {
        $files = DB::table('account_deletion_files')->orderBy('updated_at')->orderBy('id')->limit(max(1, min(500, (int) $this->option('limit'))))->get();
        foreach ($files as $file) {
            if (!DB::table('users')->where('id', $file->user_id)->where('role', 'deleted')->exists()) continue;
            try {
                // A shared reference must remain usable by its other owner.
                $shared = false;
                foreach (['students', 'employers', 'households'] as $table) {
                    foreach (['avatar', 'profile_picture', 'student_resume', 'school_id', 'coe', 'valid_id_path', 'employer_certificate_path'] as $field) {
                        if (Schema::hasColumn($table, $field) && DB::table($table)->where($field, $file->path)->exists()) $shared = true;
                    }
                }
                if (!$shared) $storage->deleteProfileFile($file->path);
                DB::table('account_deletion_files')->where('id', $file->id)->delete();
            } catch (\Throwable) {
                DB::table('account_deletion_files')->where('id', $file->id)->update(['updated_at' => now()]);
                Log::warning('Deleted account file cleanup will be retried.', ['cleanup_id' => $file->id]);
            }
        }
        return self::SUCCESS;
    }
}
