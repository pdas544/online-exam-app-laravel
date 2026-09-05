<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('answers:normalize-storage {--dry-run : Show changes without writing} {--regrade : Recalculate grading for completed sessions}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $regrade = (bool) $this->option('regrade');

    $normalize = static function (mixed $value, ?string $questionType = null): mixed {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;

                if (is_string($value)) {
                    $secondDecode = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $secondDecode;
                    }
                }
            }
        }

        if (is_array($value)) {
            $filtered = array_values(array_filter($value, static function ($item) {
                if ($item === null) {
                    return false;
                }

                return trim((string) $item) !== '';
            }));

            return array_map(static function ($item) use ($questionType) {
                $token = strtolower(trim((string) $item));
                if ($questionType === 'true_false') {
                    if (in_array($token, ['1', 'true', 'yes'], true)) {
                        return 'true';
                    }

                    if (in_array($token, ['0', 'false', 'no'], true)) {
                        return 'false';
                    }
                }

                return trim((string) $item);
            }, $filtered);
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        if ($questionType === 'true_false') {
            $normalized = strtolower($trimmed);
            if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                return ['true'];
            }
            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return ['false'];
            }
        }

        return [$trimmed];
    };

    $stats = [
        'questions_updated' => 0,
        'answers_updated' => 0,
        'answers_regraded' => 0,
    ];

    DB::table('questions')->orderBy('id')->chunkById(100, function ($rows) use (&$stats, $normalize, $dryRun) {
        foreach ($rows as $row) {
            $newCorrect = $normalize($row->correct_answers, $row->question_type ?? null);

            $newOptions = $row->options;
            if (is_string($newOptions)) {
                $decodedOptions = json_decode($newOptions, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $newOptions = $decodedOptions;

                    if (is_string($newOptions)) {
                        $secondDecode = json_decode($newOptions, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $newOptions = $secondDecode;
                        }
                    }
                }
            }

            $newOptions = is_array($newOptions) ? $newOptions : null;

            $currentCorrectJson = is_string($row->correct_answers)
                ? $row->correct_answers
                : json_encode($row->correct_answers);
            $newCorrectJson = json_encode($newCorrect);
            $currentOptionsJson = is_string($row->options)
                ? $row->options
                : json_encode($row->options);
            $newOptionsJson = json_encode($newOptions);

            if ($currentCorrectJson === $newCorrectJson && $currentOptionsJson === $newOptionsJson) {
                continue;
            }

            $stats['questions_updated']++;

            if (!$dryRun) {
                DB::table('questions')
                    ->where('id', $row->id)
                    ->update([
                        'correct_answers' => $newCorrectJson,
                        'options' => $newOptionsJson,
                        'updated_at' => now(),
                    ]);
            }
        }
    });

    DB::table('student_answers')->orderBy('id')->chunkById(200, function ($rows) use (&$stats, $normalize, $dryRun) {
        $questionIds = collect($rows)->pluck('question_id')->filter()->unique()->values();
        $questionTypes = DB::table('questions')
            ->whereIn('id', $questionIds)
            ->pluck('question_type', 'id');

        foreach ($rows as $row) {
            $questionType = $questionTypes[(int) $row->question_id] ?? null;
            $newAnswer = $normalize($row->answer, $questionType);

            $currentJson = is_string($row->answer)
                ? $row->answer
                : json_encode($row->answer);
            $newJson = json_encode($newAnswer);
            if ($currentJson === $newJson) {
                continue;
            }

            $stats['answers_updated']++;

            if (!$dryRun) {
                DB::table('student_answers')
                    ->where('id', $row->id)
                    ->update([
                        'answer' => $newJson,
                        'is_answered' => is_array($newAnswer) && count($newAnswer) > 0,
                        'updated_at' => now(),
                    ]);
            }
        }
    });

    if ($regrade) {
        StudentAnswer::query()
            ->with(['question', 'session'])
            ->whereHas('session', function ($query) {
                $query->where('status', 'completed');
            })
            ->chunkById(200, function ($answers) use (&$stats, $dryRun) {
                foreach ($answers as $answer) {
                    if (!$answer->question) {
                        continue;
                    }

                    if (!$answer->is_answered) {
                        $stats['answers_regraded']++;
                        if (!$dryRun) {
                            $answer->update([
                                'is_correct' => false,
                                'points_earned' => 0,
                            ]);
                        }
                        continue;
                    }

                    $stats['answers_regraded']++;
                    if (!$dryRun) {
                        $answer->autoGrade();
                    }
                }
            });
    }

    $mode = $dryRun ? 'DRY RUN' : 'APPLIED';
    $this->info("[{$mode}] questions updated: {$stats['questions_updated']}");
    $this->info("[{$mode}] student_answers updated: {$stats['answers_updated']}");
    if ($regrade) {
        $this->info("[{$mode}] student_answers regraded: {$stats['answers_regraded']}");
    }
})->purpose('Normalize answer/correct-answer JSON storage to canonical arrays');
