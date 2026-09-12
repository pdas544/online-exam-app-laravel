<?php

namespace App\Data;

/**
 * Validated exam payload for create/update paths.
 * Carries everything Exam::create()/update() needs except the upload itself.
 */
readonly class ExamData
{
    public function __construct(
        public int $subject_id,
        public string $title,
        public ?string $description = null,
        public ?string $instructions = null,
        public ?int $academic_year = null,
        public ?int $semester = null,
        public string $status = 'draft',
        public int $time_limit = 60,
        public int $passing_marks = 40,
        public int $max_attempts = 1,
        public ?string $available_from = null,
        public ?string $available_to = null,
        public bool $shuffle_questions = false,
        public bool $shuffle_options = false,
        public int $teacher_id = 0,
    ) {}

    /**
     * Build from a validated StoreExamRequest payload.
     * Checkbox booleans use has() semantics (absent when unchecked).
     */
    public static function fromValidated(array $validated, bool $shuffleQuestions, bool $shuffleOptions, int $teacherId): self
    {
        return new self(
            subject_id: (int) $validated['subject_id'],
            title: $validated['title'],
            description: $validated['description'] ?? null,
            instructions: $validated['instructions'] ?? null,
            academic_year: isset($validated['academic_year']) ? (int) $validated['academic_year'] : null,
            semester: isset($validated['semester']) ? (int) $validated['semester'] : null,
            status: $validated['status'] ?? 'draft',
            time_limit: (int) ($validated['time_limit'] ?? 60),
            passing_marks: (int) ($validated['passing_marks'] ?? 40),
            max_attempts: (int) ($validated['max_attempts'] ?? 1),
            available_from: $validated['available_from'] ?? null,
            available_to: $validated['available_to'] ?? null,
            shuffle_questions: $shuffleQuestions,
            shuffle_options: $shuffleOptions,
            teacher_id: $teacherId,
        );
    }

    public function toAttributes(): array
    {
        return [
            'subject_id' => $this->subject_id,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'academic_year' => $this->academic_year,
            'semester' => $this->semester,
            'status' => $this->status,
            'time_limit' => $this->time_limit,
            'passing_marks' => $this->passing_marks,
            'max_attempts' => $this->max_attempts,
            'available_from' => $this->available_from,
            'available_to' => $this->available_to,
            'shuffle_questions' => $this->shuffle_questions,
            'shuffle_options' => $this->shuffle_options,
            'teacher_id' => $this->teacher_id,
        ];
    }
}
