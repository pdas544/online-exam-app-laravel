<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');

            // Student's answer
            $table->json('answer')->nullable(); // Stores the actual answer
            $table->boolean('is_answered')->default(false);
            $table->boolean('is_marked_for_review')->default(false);

            // Auto-grading results
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_earned', 5, 2)->default(0);
            $table->decimal('max_points', 5, 2)->default(0);

            // Timing
            $table->integer('time_spent')->default(0); // seconds spent on this question
            $table->timestamp('answered_at')->nullable();

            $table->timestamps();

            // Ensure one answer per question per session
            $table->unique(['exam_session_id', 'question_id'], 'unique_student_answer');

            // Indexes
            $table->index(['exam_session_id', 'is_answered']);
            $table->index('is_correct');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_answers');
    }
};
