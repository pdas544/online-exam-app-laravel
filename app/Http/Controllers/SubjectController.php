<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubjectController extends Controller
{
    public function __construct()
    {

    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Subject::class);

        $user = Auth::user();
        $query = Subject::query()->with('creator');

        if(!$user->isAdmin()){
            $query->forTeacher($user->id);
        }

        //search by subject name
        if($request->filled('search')){
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $subjects = $query->latest()->paginate(10)->withQueryString();

        return view('subjects.index', compact('subjects'));
    }

    public function create()
    {
        $this->authorize('create', Subject::class);

        return view('subjects.create');
    }

    public function store(StoreSubjectRequest $request)
    {
        $this->authorize('create', Subject::class);

        $validated = $request->validated();

        Subject::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('subjects.index')
            ->with('success', 'Subject created successfully.');
    }

    public function show(Subject $subject)
    {
        $this->authorize('view', $subject);

        $subject->load(['creator', 'questions', 'exams']);
        return view('subjects.show', compact('subject'));
//        return view('subjects.index', compact('subject'));
    }

    public function edit(Subject $subject)
    {
        $this->authorize('view', $subject);

        return view('subjects.edit', compact('subject'));
    }

    public function update(StoreSubjectRequest $request, Subject $subject)
    {
        $this->authorize('update', $subject);

        $validated = $request->validated();

        $subject->update($validated);

        return redirect()->route('subjects.index')
            ->with('success', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject)
    {
        $this->authorize('delete', $subject);

        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }
}
