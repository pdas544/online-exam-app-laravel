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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->text('question_text');
            $table->enum('question_type', ['mcq_single', 'mcq_multiple','true_false','fill_blank']);
            $table->json('options')->nullable();
            $table->json('correct_answers');
            $table->integer('points')->default(1);
            $table->text('explanation')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
