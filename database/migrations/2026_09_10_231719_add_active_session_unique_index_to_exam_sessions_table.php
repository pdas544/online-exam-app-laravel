<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One active session per (exam, student). pgsql and sqlite (>= 3.8) both
     * support partial indexes, so the same statement runs in prod and in the
     * sqlite test suite. Other drivers skip it and rely on the app-level
     * start lock in ExamSessionService.
     */
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS exam_sessions_active_unique ON exam_sessions (exam_id, student_id) WHERE status IN ('scheduled', 'in_progress', 'paused')");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS exam_sessions_active_unique');
    }
};
