<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();

            // Session Status
            $table->enum('status', [
                'scheduled',    // Exam is scheduled but not started
                'in_progress',  // Student is actively taking exam
                'paused',       // Student paused (if allowed)
                'completed',    // Student submitted exam
                'terminated',   // Forcefully ended due to violation
                'expired'       // Time limit exceeded
            ])->default('scheduled');

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->integer('time_spent')->default(0); // in seconds
            $table->integer('remaining_time')->nullable(); // in seconds

            // Progress Tracking
            $table->integer('current_question_index')->default(0);
            $table->integer('total_questions')->default(0);
            $table->json('answered_questions')->nullable(); // Track answered question IDs

            // Integrity
            $table->integer('violation_count')->default(0);
            $table->boolean('fullscreen_enabled')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Result (populated after grading)
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('passed')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['exam_id', 'student_id', 'status']);
            $table->index('status');
            $table->index('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
