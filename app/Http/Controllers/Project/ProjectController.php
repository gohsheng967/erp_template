<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Client;
use App\Models\User;
use App\Models\Department;
use App\Models\FileCategory;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    /**
     * PROJECT LISTING
     */
    public function index(Request $request)
    {
        $query = Project::query()
            ->with(['client:id,name', 'manager:id,name']);

        // SEARCH
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search);
            });
        }

        // STATUS FILTER
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // CLIENT FILTER
        if ($request->filled('client')) {
            $query->where('client_id', $request->client);
        }

        // DATE RANGE FILTER
        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('start_date', '<=', $request->date_to);
        }

        $projects = $query
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only('search', 'status', 'client', 'date_from', 'date_to'),
            'clients' => Client::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * SHOW CREATE FORM
     */
    public function create()
    {
        return Inertia::render('Projects/Create', [
            'clients'     => Client::select('id','name')->orderBy('name')->get(),
            'departments' => Department::select('id','name')->orderBy('name')->get(),
            'managers'    => User::select('id','name')->orderBy('name')->get(),
        ]);
    }

    /**
     * STORE NEW PROJECT
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'nullable|string|max:50',
            'client_id'     => 'nullable|integer|exists:clients,id',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'budget'        => 'nullable|numeric|min:0',
            'department_id' => 'nullable|integer|exists:departments,id',
            'manager_id'    => 'nullable|integer|exists:users,id',
            'description'   => 'nullable|string',
        ]);

        // Auto generate code if empty
        if (!$validated['code']) {
            $validated['code'] = 'PRJ-' . str_pad(Project::max('id') + 1, 3, '0', STR_PAD_LEFT);
        }

        Project::create($validated);

        return redirect()->route('projects.index')
            ->with('success', 'Project created successfully.');
    }

    /**
     * SHOW PROJECT DETAIL (To be used later for tabs)
     */
    public function show(Project $project)
    {
        $project->load(['client', 'manager']);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'documents' => $project->documents()->with('user', 'category')->get(),
            'categories' => FileCategory::orderBy('name')->get(),
        ]);
    }

    /**
     * SHOW EDIT FORM
     */
    public function edit(Project $project)
    {
        return Inertia::render('Projects/Edit', [
            'project'     => $project,
            'clients'     => Client::select('id','name')->orderBy('name')->get(),
            'departments' => Department::select('id','name')->orderBy('name')->get(),
            'managers'    => User::select('id','name')->orderBy('name')->get(),
        ]);
    }

    /**
     * UPDATE PROJECT
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'nullable|string|max:50',
            'client_id'     => 'nullable|integer|exists:clients,id',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'budget'        => 'nullable|numeric|min:0',
            'department_id' => 'nullable|integer|exists:departments,id',
            'manager_id'    => 'nullable|integer|exists:users,id',
            'description'   => 'nullable|string',
        ]);

        $project->update($validated);

        return redirect()->route('projects.index')
            ->with('success', 'Project updated successfully.');
    }

    /**
     * DELETE PROJECT
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted.');
    }
}
