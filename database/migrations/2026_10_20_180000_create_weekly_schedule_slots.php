<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['student_availability_slots' => ['students', 'student_id'], 'job_schedule_slots' => ['available_jobs', 'job_id']] as $name => [$parent, $key]) {
            // MySQL DDL may survive an interrupted deployment. Resume safely on retry.
            if (Schema::hasTable($name)) continue;
            Schema::create($name, function (Blueprint $table) use ($parent, $key) {
                $table->id();
                $table->foreignId($key)->constrained($parent)->cascadeOnDelete();
                $table->string('day_of_week', 3);
                $table->string('start_time', 5);
                $table->string('end_time', 5);
                $table->boolean('ends_next_day')->default(false);
                $table->unique([$key, 'day_of_week', 'start_time', 'end_time', 'ends_next_day'], $key.'_weekly_unique');
            });
        }
        if (!Schema::hasColumn('job_applications', 'work_schedule_slots')) Schema::table('job_applications', function (Blueprint $table) {
            // Freeze agreed hours: later edits to the job must not rewrite a contract.
            $table->json('work_schedule_slots')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', fn (Blueprint $table) => $table->dropColumn('work_schedule_slots'));
        Schema::dropIfExists('job_schedule_slots');
        Schema::dropIfExists('student_availability_slots');
    }
};
