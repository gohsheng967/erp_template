<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\FileCategory;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentController extends Controller
{
    /**
     * Upload a document.
     */
    public function upload(Request $request, Project $project)
    {
        $request->validate([
            'file' => 'required|file',
            'category_id' => 'required|exists:file_categories,id',
        ]);

        $category = FileCategory::findOrFail($request->category_id);

        $file = $request->file('file');

        // Validate extension
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $category->allowed_extensions ?? [])) {
            return back()->withErrors([
                'file' => "File type .$ext not allowed for category '{$category->name}'."
            ]);
        }

        // Validate max size (stored in KB)
        $maxBytes = ($category->max_size ?? 2048) * 1024;
        if ($file->getSize() > $maxBytes) {
            return back()->withErrors([
                'file' => "File exceeds maximum size of {$category->max_size} KB."
            ]);
        }

        // Store file
        $path = $file->store("project_documents/{$project->id}");

        ProjectDocument::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'category_id' => $category->id,
            'filename' => $file->getClientOriginalName(),
            'filepath' => $path,
            'filesize' => $file->getSize(),
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    /**
     * Download a document.
     */
    public function download(ProjectDocument $document)
    {
        if (!Storage::exists($document->filepath)) {
            abort(404, 'File not found.');
        }

        return Storage::download($document->filepath, $document->filename);
    }

    /**
     * Soft Delete Document.
     */
    public function destroy(ProjectDocument $document)
    {
        $document->delete(); // <-- SOFT DELETE
        return back()->with('success', 'Document deleted.');
    }
}
