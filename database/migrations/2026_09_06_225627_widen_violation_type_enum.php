<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ALTER TYPE ... ADD VALUE cannot run inside a transaction on pgsql.
     */
    public $withinTransaction = false;

    private const TYPES = [
        'tab_switch',
        'window_blur',
        'copy_attempt',
        'paste_attempt',
        'fullscreen_exit',
        'multiple_ips',
        'time_manipulation',
        'suspicious_activity',
        'tab_key',
        'new_tab_attempt',
        'page_navigation',
        'window_resize',
        'window_minimize',
    ];

    private const ADDED = [
        'tab_key',
        'new_tab_attempt',
        'page_navigation',
        'window_resize',
        'window_minimize',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::ADDED as $value) {
                DB::statement("ALTER TYPE violation_type ADD VALUE IF NOT EXISTS '{$value}'");
            }

            return;
        }

        // sqlite/mysql: rebuild the table with the widened enum, preserving rows.
        Schema::create('violation_logs_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->enum('violation_type', self::TYPES);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->integer('severity')->default(1);
            $table->boolean('auto_warned')->default(false);
            $table->boolean('auto_terminated')->default(false);
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();
            $table->index(['exam_session_id', 'violation_type']);
            $table->index('detected_at');
        });

        DB::statement(
            'INSERT INTO violation_logs_new (id, exam_session_id, student_id, exam_id, violation_type, description, metadata, severity, auto_warned, auto_terminated, detected_at, created_at, updated_at) '.
            'SELECT id, exam_session_id, student_id, exam_id, violation_type, description, metadata, severity, auto_warned, auto_terminated, detected_at, created_at, updated_at FROM violation_logs'
        );

        Schema::drop('violation_logs');
        Schema::rename('violation_logs_new', 'violation_logs');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // pgsql cannot drop enum values in use; down-migration is a no-op.
            return;
        }

        DB::table('violation_logs')->whereIn('violation_type', self::ADDED)->delete();

        Schema::create('violation_logs_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->enum('violation_type', array_diff(self::TYPES, self::ADDED));
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->integer('severity')->default(1);
            $table->boolean('auto_warned')->default(false);
            $table->boolean('auto_terminated')->default(false);
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();
            $table->index(['exam_session_id', 'violation_type']);
            $table->index('detected_at');
        });

        DB::statement(
            'INSERT INTO violation_logs_old (id, exam_session_id, student_id, exam_id, violation_type, description, metadata, severity, auto_warned, auto_terminated, detected_at, created_at, updated_at) '.
            'SELECT id, exam_session_id, student_id, exam_id, violation_type, description, metadata, severity, auto_warned, auto_terminated, detected_at, created_at, updated_at FROM violation_logs'
        );

        Schema::drop('violation_logs');
        Schema::rename('violation_logs_old', 'violation_logs');
    }
};
