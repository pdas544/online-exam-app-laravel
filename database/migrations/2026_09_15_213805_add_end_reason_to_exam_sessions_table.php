<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            // Nullable reason; known values: force_ended, auto_terminated, expired.
            // Completed/normal submissions leave it null; pre-migration rows stay
            // null unless matched by the backfill below (flagged as approximate
            // in the reports UI).
            $table->string('end_reason', 32)->nullable()->after('status');
            $table->foreignId('ended_by')->nullable()->after('end_reason')->constrained('users')->nullOnDelete();
        });

        // SQLite rebuilds the table when adding columns via Schema::table, which
        // drops raw partial indexes. Re-create the active-session unique index
        // with its WHERE predicate so tests (sqlite) and prod (pgsql) stay in
        // sync. Without this, the index comes back as a plain UNIQUE on
        // (exam_id, student_id) and blocks multiple completed attempts.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS exam_sessions_active_unique');
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS exam_sessions_active_unique ON exam_sessions (exam_id, student_id) WHERE status IN ('scheduled', 'in_progress', 'paused')");
        }

        // Best-effort backfill for pre-migration terminated rows.
        // terminated + auto_terminated violation → auto_terminated
        // other terminated → force_ended (approximate — no audit row existed)
        // expired status → expired
        try {
            DB::table('exam_sessions')
                ->where('status', 'expired')
                ->whereNull('end_reason')
                ->update(['end_reason' => 'expired']);

            $terminatedIds = DB::table('exam_sessions')
                ->where('status', 'terminated')
                ->whereNull('end_reason')
                ->pluck('id');

            if ($terminatedIds->isNotEmpty()) {
                $autoIds = DB::table('violation_logs')
                    ->whereIn('exam_session_id', $terminatedIds)
                    ->where('auto_terminated', true)
                    ->pluck('exam_session_id')
                    ->unique()
                    ->all();

                if (! empty($autoIds)) {
                    DB::table('exam_sessions')->whereIn('id', $autoIds)->update(['end_reason' => 'auto_terminated']);
                }

                $forceIds = array_diff($terminatedIds->all(), $autoIds ?? []);
                if (! empty($forceIds)) {
                    DB::table('exam_sessions')->whereIn('id', $forceIds)->update(['end_reason' => 'force_ended']);
                }
            }
        } catch (\Throwable $e) {
            // Backfill is best-effort; don't fail the migration if the
            // violation_logs table shape differs (e.g. in stripped test envs).
            \Illuminate\Support\Facades\Log::warning('end_reason backfill skipped: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ended_by');
            $table->dropColumn('end_reason');
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS exam_sessions_active_unique');
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS exam_sessions_active_unique ON exam_sessions (exam_id, student_id) WHERE status IN ('scheduled', 'in_progress', 'paused')");
        }
    }
};
