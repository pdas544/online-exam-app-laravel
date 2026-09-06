<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddExamQuestionRequest;
use App\Http\Requests\BulkAddQuestionsRequest;
use App\Http\Requests\ReorderQuestionsRequest;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateQuestionPointsRequest;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExamController extends Controller
{
    /**
     * Constructor - Apply middleware
     */
    public function __construct()
    {
        //
    }

    /**
     * Display a listing of exams.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Exam::class);

        $user = Auth::user();
        $query = Exam::with(['subject', 'teacher']);

        // Filter by subject
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Teachers see only their exams, admins see all
        if (!$user->isAdmin()) {
            $query->where('teacher_id', $user->id);
        }

        // Search by title
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Search by academic year or semester
        if ($request->filled('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }
        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        // Get count of questions for each exam
        $exams = $query->withCount('questions')->latest()->paginate(10);

        // Get all subjects for filter dropdown
        $subjects = Subject::orderBy('name')->get();

        return view('exams.index', compact('exams', 'subjects'));
    }

    /**
     * Show form for creating a new exam.
     */
    public function create()
    {
        $this->authorize('create', Exam::class);

        $subjects = Subject::orderBy('name')->get();
        return view('exams.create', compact('subjects'));
    }

    /**
     * Store a newly created exam.
     */
    public function store(StoreExamRequest $request)
    {
        $this->authorize('create', Exam::class);

        $validated = $request->validated();

        // Convert checkbox values (absent when unchecked)
        $validated['shuffle_questions'] = $request->has('shuffle_questions');
        $validated['shuffle_options'] = $request->has('shuffle_options');

        // Handle file upload
        if ($request->hasFile('instructions_file')) {
            $file = $request->file('instructions_file');
            $path = $file->store('exam-instructions', 'public');
            $validated['instructions_file'] = $path;
        }

        $exam = Exam::create(array_merge($validated, [
            'teacher_id' => Auth::id(),
            'total_marks' => 0,
        ]));

        return redirect()->route('exams.show', $exam)
            ->with('success', 'Exam created successfully. Now add questions to it.');
    }

    /**
     * Display the specified exam.
     */
    public function show(Exam $exam)
    {
        $this->authorize('view', $exam);

        $exam->load(['subject', 'teacher', 'questions' => function ($query) {
            $query->orderBy('exam_questions.order_index');
        }]);

        // Group questions by type for statistics
        $questionsByType = $exam->questions->groupBy('question_type');

        $stats = [
            'total_questions' => $exam->questions->count(),
            'total_marks' => $exam->total_marks,
            'mcq_single_count' => isset($questionsByType['mcq_single']) ? $questionsByType['mcq_single']->count() : 0,
            'mcq_multiple_count' => isset($questionsByType['mcq_multiple']) ? $questionsByType['mcq_multiple']->count() : 0,
            'true_false_count' => isset($questionsByType['true_false']) ? $questionsByType['true_false']->count() : 0,
            'fill_blank_count' => isset($questionsByType['fill_blank']) ? $questionsByType['fill_blank']->count() : 0,
        ];

        return view('exams.show', compact('exam', 'stats'));
    }

    /**
     * Show form for editing exam.
     */
    public function edit(Exam $exam)
    {
        $this->authorize('update', $exam);

        $subjects = Subject::orderBy('name')->get();
        return view('exams.edit', compact('exam', 'subjects'));
    }

    /**
     * Update the specified exam.
     */
    public function update(StoreExamRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        $validated = $request->validated();

        // Convert checkbox values (absent when unchecked)
        $validated['shuffle_questions'] = $request->has('shuffle_questions');
        $validated['shuffle_options'] = $request->has('shuffle_options');

        // Handle file upload
        if ($request->hasFile('instructions_file')) {
            // Delete old file if exists
            if ($exam->instructions_file && Storage::disk('public')->exists($exam->instructions_file)) {
                Storage::disk('public')->delete($exam->instructions_file);
            }

            $file = $request->file('instructions_file');
            $path = $file->store('exam-instructions', 'public');
            $validated['instructions_file'] = $path;
        }

        $exam->update($validated);

        return redirect()->route('exams.show', $exam)
            ->with('success', 'Exam updated successfully.');
    }

    /**
     * Remove the specified exam.
     */
    public function destroy(Exam $exam)
    {
        $this->authorize('delete', $exam);

        // Check if any student has taken this exam
        if ($exam->sessions()->whereIn('status', ['completed', 'in_progress'])->count() > 0) {
            return redirect()->route('exams.index')
                ->with('error', 'Cannot delete exam that has been started by students.');
        }

        // Detach all questions first
        $exam->questions()->detach();

        // Delete the exam
        $exam->delete();

        return redirect()->route('exams.index')
            ->with('success', 'Exam deleted successfully.');
    }

    /**
     * Show form to manage questions for an exam.
     */
    public function manageQuestions(Exam $exam)
    {
        $this->authorize('update', $exam);

        $exam->load(['questions' => function ($query) {
            $query->orderBy('exam_questions.order_index');
        }]);

        // Get available questions from same subject, not already in exam
        $availableQuestions = Question::where('subject_id', $exam->subject_id)
            ->whereNotIn('id', $exam->questions->pluck('id'))
            ->orderBy('question_type')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Get all questions for quick add (with pagination)
        $allQuestions = Question::where('subject_id', $exam->subject_id)
            ->orderBy('question_type')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('exams.questions', compact('exam', 'availableQuestions', 'allQuestions'));
    }

    /**
     * Add a question to the exam.
     */
    public function addQuestion(AddExamQuestionRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        // Check if question already exists in exam
        if ($exam->questions()->where('question_id', $request->question_id)->exists()) {
            return back()->with('error', 'Question already exists in this exam.');
        }

        // Question must belong to the exam's subject
        $question = Question::findOrFail($request->question_id);
        if ($question->subject_id !== $exam->subject_id) {
            return back()->with('error', 'Question does not belong to this exam\'s subject.');
        }

        // Get the next order index
        $nextOrder = $exam->questions()->max('order_index') ?? 0;
        $nextOrder++;

        DB::transaction(function () use ($exam, $request, $nextOrder) {
            $exam->questions()->attach($request->question_id, [
                'order_index' => $nextOrder,
                'points_override' => $request->points_override,
            ]);

            // Update total marks
            $exam->updateTotalMarks();
        });

        return back()->with('success', 'Question added to exam successfully.');
    }

    /**
     * Remove a question from the exam.
     */
    public function removeQuestion(Exam $exam, Question $question)
    {
        $this->authorize('update', $exam);

        if (! $exam->questions()->where('question_id', $question->id)->exists()) {
            abort(404);
        }

        DB::transaction(function () use ($exam, $question) {
            $exam->questions()->detach($question->id);

            // Reorder remaining questions
            $remainingQuestions = $exam->questions()->orderBy('order_index')->get();
            foreach ($remainingQuestions as $index => $q) {
                $exam->questions()->updateExistingPivot($q->id, ['order_index' => $index + 1]);
            }

            // Update total marks
            $exam->updateTotalMarks();
        });

        return back()->with('success', 'Question removed from exam successfully.');
    }

    /**
     * Update question order in exam (AJAX).
     */
    public function reorderQuestions(ReorderQuestionsRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        // Reorder set must match the exam's attached question set exactly
        $requestedIds = collect($request->questions)->pluck('id')->sort()->values()->toArray();
        $attachedIds = $exam->questions()->pluck('question_id')->sort()->values()->toArray();

        if ($requestedIds !== $attachedIds) {
            return response()->json(['error' => 'Question set does not match this exam.'], 422);
        }

        DB::transaction(function () use ($exam, $request) {
            foreach ($request->questions as $item) {
                $exam->questions()->updateExistingPivot($item['id'], ['order_index' => $item['order']]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Questions reordered successfully.']);
    }

    /**
     * Update points for a question in exam (AJAX).
     */
    public function updatePoints(UpdateQuestionPointsRequest $request, Exam $exam, Question $question)
    {
        $this->authorize('update', $exam);

        if (! $exam->questions()->where('question_id', $question->id)->exists()) {
            abort(404);
        }

        DB::transaction(function () use ($exam, $question, $request) {
            $exam->questions()->updateExistingPivot($question->id, [
                'points_override' => $request->points,
            ]);

            $exam->updateTotalMarks();
        });

        return response()->json([
            'success' => true,
            'total_marks' => $exam->total_marks,
            'message' => 'Points updated successfully.'
        ]);
    }

    /**
     * Bulk add questions to exam.
     */
    public function bulkAddQuestions(BulkAddQuestionsRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        $currentQuestionIds = $exam->questions->pluck('id')->toArray();
        $newQuestionIds = array_diff($request->question_ids, $currentQuestionIds);

        if (empty($newQuestionIds)) {
            return back()->with('info', 'All selected questions are already in the exam.');
        }

        // Every question must belong to the exam's subject
        $mismatched = Question::whereIn('id', $newQuestionIds)
            ->where('subject_id', '!=', $exam->subject_id)
            ->count();

        if ($mismatched > 0) {
            return back()->with('error', 'Some questions do not belong to this exam\'s subject.');
        }

        $nextOrder = $exam->questions()->max('order_index') ?? 0;

        DB::transaction(function () use ($exam, $newQuestionIds, &$nextOrder) {
            foreach ($newQuestionIds as $questionId) {
                $nextOrder++;
                $exam->questions()->attach($questionId, [
                    'order_index' => $nextOrder,
                ]);
            }

            $exam->updateTotalMarks();
        });

        return back()->with('success', count($newQuestionIds) . ' questions added to exam successfully.');
    }

}
