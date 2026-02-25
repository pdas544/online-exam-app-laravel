<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');

            // Violation details
            $table->enum('violation_type', [
                'tab_switch',
                'window_blur',
                'copy_attempt',
                'paste_attempt',
                'fullscreen_exit',
                'multiple_ips',
                'time_manipulation',
                'suspicious_activity'
            ]);

            $table->text('description');
            $table->json('metadata')->nullable(); // Additional data (e.g., tab title, IP)
            $table->integer('severity')->default(1); // 1-5 scale

            // Auto actions
            $table->boolean('auto_warned')->default(false);
            $table->boolean('auto_terminated')->default(false);

            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index(['exam_session_id', 'violation_type']);
            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_logs');
    }
};
