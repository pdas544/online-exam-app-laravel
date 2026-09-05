<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionRequest;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    /**
     * Constructor - Apply middleware
     */
    public function __construct()
    {

    }

    /**
     * Display a listing of questions.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Question::class);

        $user = Auth::user();
        $query = Question::with(['subject', 'creator'])->withCount('exams');

        // Filter by subject if provided
        if ($request->has('subject_id') && $request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by question type if provided
        if ($request->has('question_type') && $request->question_type) {
            $query->where('question_type', $request->question_type);
        }

        // Filter by creator (teachers see only their questions, admins see all)
        if (!$user->isAdmin()) {
            $query->where('created_by', $user->id);
        }

        // Search by question text
        if ($request->has('search') && $request->search) {
            $query->where('question_text', 'like', '%' . $request->search . '%');
        }

        $questions = $query->latest()->paginate(15)->withQueryString();
        $subjectQuery = Subject::query();
        if(!$user->isAdmin()){
            $subjectQuery->forTeacher($user->id);
        }

        $subjects = $subjectQuery->orderBy('name')->get();

        return view('questions.index', compact('questions', 'subjects'));
    }

    /**
     * Show the form for creating a new question.
     */
    public function create()
    {
        $this->authorize('create', Question::class);

        $user = Auth::user();
        $subjectQuery = Subject::query();
        if(!$user->isAdmin()){
            $subjectQuery->forTeacher($user->id);
        }
        $subjects = $subjectQuery->orderBy('name')->get();
        return view('questions.create', compact('subjects'));
    }

    /**
     * Store a newly created question in storage.
     */
    public function store(StoreQuestionRequest $request)
    {
        $this->authorize('create', Question::class);

        $validated = $request->validated();

        // Handle options and correct answers based on question type
        $processedData = $this->processQuestionData($validated);

        $question = Question::create(array_merge($processedData, [
            'created_by' => Auth::id(),
        ]));

        return redirect()->route('questions.show', $question)
            ->with('success', 'Question created successfully.');
    }

    /**
     * Display the specified question.
     */
    public function show(Question $question)
    {
        $this->authorize('view', $question);

        $question->load(['subject', 'creator', 'exams']);
        return view('questions.show', compact('question'));
    }

    /**
     * Show the form for editing the specified question.
     */
    public function edit(Question $question)
    {
        $this->authorize('view', $question);

        $user = Auth::user();
        $subjectQuery = Subject::query();
        if(!$user->isAdmin()){
            $subjects = Subject::forTeacher($user->id);
        }

        $subjects = $subjectQuery->orderBy('name')->get();
        if (!$subjects->contains('id', $question->subject_id)) {
            $selectedSubject = Subject::query()->whereKey($question->subject_id)->first();
            if ($selectedSubject) {
                $subjects = $subjects->push($selectedSubject)->sortBy('name')->values();
            }
        }

        return view('questions.edit', compact('question', 'subjects'));
    }

    public function duplicate(Request $request, Question $question)
    {
        $newQuestion = $question->replicate();
        $newQuestion->question_text = '[COPY] ' . $question->question_text;

        if ($request->filled('subject_id')) {
            $newQuestion->subject_id = $request->subject_id;
        }

        if (!$request->has('include_explanation')) {
            $newQuestion->explanation = null;
        }

        $newQuestion->created_by = Auth::id();
        $newQuestion->save();

        return redirect()->route('questions.show', $newQuestion)
            ->with('success', 'Question duplicated successfully.');
    }

    /**
     * Update the specified question in storage.
     */
    public function update(StoreQuestionRequest $request, Question $question)
    {
        $this->authorize('update', $question);

        $validated = $request->validated();
        $processedData = $this->processQuestionData($validated);

        $question->update($processedData);

        return redirect()->route('questions.show', $question)
            ->with('success', 'Question updated successfully.');
    }

    /**
     * Remove the specified question from storage.
     */
    public function destroy(Question $question)
    {
        $this->authorize('delete', $question);

        // Check if question is used in any exams
        if ($question->exams()->count() > 0) {
            return redirect()->route('questions.index')
                ->with('error', 'Cannot delete question because it is used in one or more exams.');
        }

        $question->delete();

        return redirect()->route('questions.index')
            ->with('success', 'Question deleted successfully.');
    }

    /**
     * Process and format question data for storage
     */
    private function processQuestionData(array $validatedData): array
    {
        $processed = [
            'subject_id' => $validatedData['subject_id'],
            'question_text' => $validatedData['question_text'],
            'question_type' => $validatedData['question_type'],
            'points' => $validatedData['points'],
            'explanation' => $validatedData['explanation'] ?? null,
        ];

        switch ($validatedData['question_type']) {
            case 'mcq_single':
            case 'mcq_multiple':
                // Format options as JSON: {"A": "Option text", "B": "Option text"}
                $options = [];
                foreach ($validatedData['options'] as $index => $option) {
                    $letter = chr(65 + $index); // A, B, C, D...
                    $options[$letter] = $option;
                }
                $processed['options'] = json_encode($options);
                $processed['correct_answers'] = json_encode($validatedData['correct_answers']);
                break;

            case 'true_false':
            case 'fill_blank':
                $processed['correct_answers'] = json_encode($validatedData['correct_answers']);
                $processed['options'] = null;
                break;
        }

        return $processed;
    }

    /**
     * Bulk question import via CSV (Future enhancement)
     */
    public function import()
    {
        // To be implemented in next phase
        return view('questions.import');
    }
}
