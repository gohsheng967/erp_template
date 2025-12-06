<?php

namespace App\Http\Controllers;

use App\Models\FileCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FileCategoryController extends Controller
{
    /**
     * Display all categories.
     */
    public function index()
    {
        return Inertia::render('FileCategories/Index', [
            'categories' => FileCategory::with('children')
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(),

            // Optional: for dropdown selections
            'allCategories' => FileCategory::select('id', 'name', 'parent_id')->get(),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'parent_id'           => ['nullable', 'exists:file_categories,id'],
            'allowed_extensions'  => ['nullable', 'array'],
            'max_size'            => ['required', 'integer', 'min:100'], // KB
            'visibility'          => ['required', 'in:public,department,role'],
            'allowed_departments' => ['nullable', 'array'],
            'allowed_roles'       => ['nullable', 'array'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        FileCategory::create($validated);

        return back()->with('success', 'File category created successfully.');
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, FileCategory $fileCategory)
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'parent_id'           => ['nullable', 'exists:file_categories,id'],
            'allowed_extensions'  => ['nullable', 'array'],
            'max_size'            => ['required', 'integer', 'min:100'],
            'visibility'          => ['required', 'in:public,department,role'],
            'allowed_departments' => ['nullable', 'array'],
            'allowed_roles'       => ['nullable', 'array'],
        ]);

        // Avoid setting parent_id to itself
        if ($request->parent_id == $fileCategory->id) {
            return back()->withErrors(['parent_id' => 'A category cannot be its own parent.']);
        }

        // Reslug if name changed
        if ($validated['name'] !== $fileCategory->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $fileCategory->update($validated);

        return back()->with('success', 'File category updated successfully.');
    }

    /**
     * Remove a category.
     */
    public function destroy(FileCategory $fileCategory)
    {
        // Optionally prevent deletion if category has children
        if ($fileCategory->children()->count() > 0) {
            return back()->withErrors(['delete' => 'Cannot delete category with sub-categories.']);
        }

        $fileCategory->delete();

        return back()->with('success', 'File category deleted successfully.');
    }
}
