<?php

namespace App\Http\Controllers;

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
        $user = Auth::user();
        $query = Subject::query()->with('creator');

        if(!$user->isAdmin()){
            $query->forTeacher($user->id);
        }

        //search by subject name
        if($request->filled('search')){
            $search = $request->input('search');
            $query->where('name','ilike',"%{$search}%");
        }

        $subjects = $query->latest()->paginate(10)->withQueryString();

        return view('subjects.index', compact('subjects'));
    }

    public function create()
    {
        return view('subjects.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

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
        // Check if user has permission to view this subject
        if (!Auth::user()->isAdmin() && $subject->created_by != Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $subject->load(['creator', 'questions', 'exams']);
        return view('subjects.show', compact('subject'));
//        return view('subjects.index', compact('subject'));
    }

    public function edit(Subject $subject)
    {
        if (!Auth::user()->isAdmin() && $subject->created_by != Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('subjects.edit', compact('subject'));
    }

    public function update(Request $request, Subject $subject)
    {
        if (!Auth::user()->isAdmin() && $subject->created_by != Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $subject->update($validated);

        return redirect()->route('subjects.index')
            ->with('success', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject)
    {
        if (!Auth::user()->isAdmin() && $subject->created_by != Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }
}
