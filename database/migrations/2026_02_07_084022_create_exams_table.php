<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->year('academic_year');
            $table->enum('semester',['1','2','3','4','5','6','7','8'])->default('1');
            $table->integer('time_limit'); // in minutes
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_to')->nullable();
            $table->integer('total_marks')->default(0);
            $table->integer('passing_marks')->default(0);
            $table->integer('max_attempts')->default(1);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_year','semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
