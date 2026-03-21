<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // exam_sessions: missing targeted composite indexes
        // Note: (exam_id, student_id, status) already exists; add the two-column variants
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->index(['exam_id', 'status']);          // monitor() + getSessions(): WHERE exam_id=X AND status IN (...)
            $table->index(['student_id', 'status']);       // student dashboard: resume/in-progress queries
            $table->index(['teacher_id', 'status']);       // teacher dashboard active-exam queries
            $table->index(['status', 'created_at']);       // time-based admin/reporting queries
        });

        // student_answers: add composite for grading lookups
        Schema::table('student_answers', function (Blueprint $table) {
            $table->index(['question_id', 'is_correct']); // grading/reporting queries
        });

        // violation_logs: cross-entity lookup indexes
        Schema::table('violation_logs', function (Blueprint $table) {
            $table->index(['student_id', 'exam_id']);      // per-student per-exam violation lookup
            $table->index(['violation_type', 'created_at']); // trend/audit queries
        });

        // questions: filter by subject+type and creator+type
        Schema::table('questions', function (Blueprint $table) {
            $table->index(['subject_id', 'question_type']); // question bank filtering
            $table->index(['created_by', 'question_type']); // teacher's questions by type
        });

        // exams: frequently filtered combinations
        Schema::table('exams', function (Blueprint $table) {
            $table->index(['teacher_id', 'status']);           // teacher's published/archived exams
            $table->index(['subject_id', 'status']);           // exams by subject and status
            $table->index(['available_from', 'available_to']); // availability window lookup
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropIndex(['exam_id', 'status']);
            $table->dropIndex(['student_id', 'status']);
            $table->dropIndex(['teacher_id', 'status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropIndex(['question_id', 'is_correct']);
        });

        Schema::table('violation_logs', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'exam_id']);
            $table->dropIndex(['violation_type', 'created_at']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['subject_id', 'question_type']);
            $table->dropIndex(['created_by', 'question_type']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['teacher_id', 'status']);
            $table->dropIndex(['subject_id', 'status']);
            $table->dropIndex(['available_from', 'available_to']);
        });
    }
};
