<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Subject;
use App\Models\Question;
use App\Services\ExamService;
use App\Services\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    /**
     * Constructor - Apply middleware
     */
    public function __construct()
    {

        $user = Auth::user();
        if (!$user->isTeacher() && !$user->isAdmin()) {
            abort(403, 'Unauthorized access. Teacher or Admin privileges required.');
        }


    }

    /**
     * Display a listing of exams.
     */
    public function index(Request $request)
    {
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
        $subjects = app(SubjectService::class)->getAllSubjects();
        return view('exams.create', compact('subjects'));
    }

    /**
     * Store a newly created exam.
     */
    public function store(Request $request)
    {
        $validated = $this->validateExam($request);

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
        $this->authorizeExam($exam);

        $exam = app(ExamService::class)->getExamWithQuestions($exam);

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
        $this->authorizeExam($exam);

        $subjects = app(SubjectService::class)->getAllSubjects();
        return view('exams.edit', compact('exam', 'subjects'));
    }

    /**
     * Update the specified exam.
     */
    public function update(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);

        $validated = $this->validateExam($request, $exam);

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
        app(ExamService::class)->invalidateExamCache($exam);

        return redirect()->route('exams.show', $exam)
            ->with('success', 'Exam updated successfully.');
    }

    /**
     * Remove the specified exam.
     */
    public function destroy(Exam $exam)
    {
        $this->authorizeExam($exam);

        // Check if any student has taken this exam
        if ($exam->sessions()->whereIn('status', ['completed', 'in_progress'])->count() > 0) {
            return redirect()->route('exams.index')
                ->with('error', 'Cannot delete exam that has been started by students.');
        }

        // Detach all questions first
        $exam->questions()->detach();
        app(ExamService::class)->invalidateExamCache($exam);

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
        if(!$this->authorizeExam($exam)){
            abort(403, 'Unauthorized access to this exam.');
        }

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
    public function addQuestion(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'points_override' => 'nullable|integer|min:1|max:10',
        ]);

        // Check if question already exists in exam
        if ($exam->questions()->where('question_id', $request->question_id)->exists()) {
            return back()->with('error', 'Question already exists in this exam.');
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

        app(ExamService::class)->invalidateExamCache($exam);

        return back()->with('success', 'Question added to exam successfully.');
    }

    /**
     * Remove a question from the exam.
     */
    public function removeQuestion(Exam $exam, Question $question)
    {
        $this->authorizeExam($exam);

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

        app(ExamService::class)->invalidateExamCache($exam);

        return back()->with('success', 'Question removed from exam successfully.');
    }

    /**
     * Update question order in exam (AJAX).
     */
    public function reorderQuestions(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);

        $request->validate([
            'questions' => 'required|array',
            'questions.*.id' => 'required|exists:questions,id',
            'questions.*.order' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($exam, $request) {
            foreach ($request->questions as $item) {
                $exam->questions()->updateExistingPivot($item['id'], ['order_index' => $item['order']]);
            }
        });

        app(ExamService::class)->invalidateExamCache($exam);

        return response()->json(['success' => true, 'message' => 'Questions reordered successfully.']);
    }

    /**
     * Update points for a question in exam (AJAX).
     */
    public function updatePoints(Request $request, Exam $exam, Question $question)
    {
        $this->authorizeExam($exam);

        $request->validate([
            'points' => 'required|integer|min:1|max:10',
        ]);

        DB::transaction(function () use ($exam, $question, $request) {
            $exam->questions()->updateExistingPivot($question->id, [
                'points_override' => $request->points,
            ]);

            $exam->updateTotalMarks();
        });

        app(ExamService::class)->invalidateExamCache($exam);

        return response()->json([
            'success' => true,
            'total_marks' => $exam->total_marks,
            'message' => 'Points updated successfully.'
        ]);
    }

    /**
     * Bulk add questions to exam.
     */
    public function bulkAddQuestions(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);

        $request->validate([
            'question_ids' => 'required|array',
            'question_ids.*' => 'exists:questions,id',
        ]);

        $currentQuestionIds = $exam->questions->pluck('id')->toArray();
        $newQuestionIds = array_diff($request->question_ids, $currentQuestionIds);

        if (empty($newQuestionIds)) {
            return back()->with('info', 'All selected questions are already in the exam.');
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

        app(ExamService::class)->invalidateExamCache($exam);

        return back()->with('success', count($newQuestionIds) . ' questions added to exam successfully.');
    }

    /**
     * Validate exam data.
     */
    private function validateExam(Request $request, Exam $exam = null)
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'instructions_file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:5120',
            'subject_id' => 'required|exists:subjects,id',
            'academic_year' => 'required|integer|min:2000|max:' . date('Y'),
            'semester' => 'required|in:1,2,3,4,5,6,7,8',
            'time_limit' => 'required|integer|min:5|max:480',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options' => 'nullable|boolean',
            'available_from' => 'nullable|date',
            'available_to' => 'nullable|date|after:available_from',
            'passing_marks' => 'required|integer|min:0',
            'max_attempts' => 'required|integer|min:1|max:10',
            'status' => 'required|in:draft,published,archived',
        ];

        $validated = $request->validate($rules);

        // Convert checkbox values
        $validated['shuffle_questions'] = $request->has('shuffle_questions');
        $validated['shuffle_options'] = $request->has('shuffle_options');

        return $validated;
    }

    /**
     * Authorize that user can access/modify this exam.
     */
    private function authorizeExam(Exam $exam): bool
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($exam->teacher_id !== $user->id) {
            abort(403, 'Unauthorized access to this exam.');
        }

        return true;
    }
}
