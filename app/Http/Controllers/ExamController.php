<?php

namespace App\Http\Controllers;

use App\Data\ExamData;
use App\Http\Requests\AddExamQuestionRequest;
use App\Http\Requests\BulkAddQuestionsRequest;
use App\Http\Requests\ReorderQuestionsRequest;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateQuestionPointsRequest;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Services\ExamManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    public function __construct(private ExamManagementService $exams) {}

    /**
     * Display a listing of exams.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Exam::class);

        $user = Auth::user();
        $exams = $this->exams->paginateFor($user, $request->only([
            'subject_id', 'status', 'search', 'academic_year', 'semester',
        ]) + ['per_page' => 10]);

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

        $data = ExamData::fromValidated(
            $request->validated(),
            $request->has('shuffle_questions'),
            $request->has('shuffle_options'),
            Auth::id()
        );

        $exam = $this->exams->createExam($data, $request->file('instructions_file'));

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

        $stats = $this->exams->examStats($exam);

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

        $data = ExamData::fromValidated(
            $request->validated(),
            $request->has('shuffle_questions'),
            $request->has('shuffle_options'),
            $exam->teacher_id,
        );

        $this->exams->updateExam($exam, $data, $request->file('instructions_file'));

        return redirect()->route('exams.show', $exam)
            ->with('success', 'Exam updated successfully.');
    }

    /**
     * Remove the specified exam.
     */
    public function destroy(Exam $exam)
    {
        $this->authorize('delete', $exam);

        try {
            $this->exams->deleteExam($exam->fresh());
        } catch (\DomainException $e) {
            return redirect()->route('exams.index')
                ->with('error', 'Cannot delete exam that has been started by students.');
        }

        return redirect()->route('exams.index')
            ->with('success', 'Exam deleted successfully.');
    }

    /**
     * Show form to manage questions for an exam.
     */
    public function manageQuestions(Exam $exam)
    {
        $this->authorize('update', $exam);

        $lists = $this->exams->manageLists($exam);
        $exam->setRelation('questions', $lists['attached']);

        // Available (same subject, not attached) + full bank for quick add
        $availableQuestions = $lists['available'];
        $allQuestions = $lists['all'];

        return view('exams.questions', compact('exam', 'availableQuestions', 'allQuestions'));
    }

    /**
     * Add a question to the exam.
     */
    public function addQuestion(AddExamQuestionRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        try {
            $this->exams->attachQuestion($exam, (int) $request->question_id, $request->points_override);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Question added to exam successfully.');
    }

    /**
     * Remove a question from the exam.
     */
    public function removeQuestion(Exam $exam, Question $question)
    {
        $this->authorize('update', $exam);

        try {
            $this->exams->detachQuestion($exam, $question->id);
        } catch (\DomainException $e) {
            abort(404);
        }

        return back()->with('success', 'Question removed from exam successfully.');
    }

    /**
     * Update question order in exam (AJAX).
     */
    public function reorderQuestions(ReorderQuestionsRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        try {
            $this->exams->reorderQuestions($exam, $request->questions);
        } catch (\DomainException $e) {
            return response()->json(['error' => 'Question set does not match this exam.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Questions reordered successfully.']);
    }

    /**
     * Update points for a question in exam (AJAX).
     */
    public function updatePoints(UpdateQuestionPointsRequest $request, Exam $exam, Question $question)
    {
        $this->authorize('update', $exam);

        try {
            $this->exams->updateQuestionPoints($exam, $question->id, $request->points);
        } catch (\DomainException $e) {
            abort(404);
        }

        return response()->json([
            'success' => true,
            'total_marks' => $exam->fresh()->total_marks,
            'message' => 'Points updated successfully.',
        ]);
    }

    /**
     * Bulk add questions to exam.
     */
    public function bulkAddQuestions(BulkAddQuestionsRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        try {
            $attached = $this->exams->bulkAddQuestions($exam, $request->question_ids);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($attached === 0) {
            return back()->with('info', 'All selected questions are already in the exam.');
        }

        return back()->with('success', $attached.' questions added to exam successfully.');
    }

}
