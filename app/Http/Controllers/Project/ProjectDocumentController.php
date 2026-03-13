<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\FileCategory;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;

class ProjectDocumentController extends Controller
{
    /**
     * List documents + summary (API for DocumentTab)
     */
    public function index(Request $request, string $projectUuid)
    {
        $project = $this->resolveProject($request, $projectUuid);

        $documents = ProjectDocument::with(['category', 'user'])
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->latest()
            ->get()
            ->map(fn ($doc) => [
                'id' => $doc->uuid,
                'filename' => $doc->filename,
                'filepath' => $doc->filepath,
                'filesize' => $doc->filesize,
                'category_id' => $doc->category_id,
                'category_name' => $doc->category?->name ?? 'Others',
                'user_name' => $doc->user?->name ?? 'System',
                'type' => $doc->type,
                'version' => $doc->version,
                'created_at' => $doc->created_at->format('Y-m-d H:i'),
            ]);

        $byCategory = ProjectDocument::query()
            ->select('category_id', DB::raw('COUNT(*) as count'))
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->groupBy('category_id')
            ->with('category:id,name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->category_id,
                'name' => $row->category?->name ?? 'Others',
                'count' => (int) $row->count,
            ])
            ->values();

        return response()->json([
            'summary' => [
                'total' => $documents->count(),
                'by_category' => $byCategory,
            ],
            'documents' => $documents,
        ]);
    }

    /**
     * Upload FILE document
     */
    public function upload(Request $request, string $projectUuid)
    {
        $project = $this->resolveProject($request, $projectUuid);

        $request->validate([
            'file' => 'required|file',
            'category_id' => ['required', 'string'],
        ]);

        $isOthers = (string) $request->category_id === 'others';
        if (!$isOthers) {
            $request->validate([
                'category_id' => ['required', Rule::exists('file_categories', 'id')],
            ]);
        }

        $category = $isOthers
            ? null
            : FileCategory::findOrFail((int) $request->category_id);

        $file = $request->file('file');

        if ($category) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (!empty($category->allowed_extensions)
                && !in_array($ext, $category->allowed_extensions)) {
                return back()->withErrors([
                    'file' => "File type .$ext not allowed for category '{$category->name}'."
                ]);
            }

            $maxBytes = ($category->max_size ?? 2048) * 1024;
            if ($file->getSize() > $maxBytes) {
                return back()->withErrors([
                    'file' => "File exceeds maximum size of {$category->max_size} KB."
                ]);
            }
        }

        $path = $file->store("project_documents/{$project->uuid}");

        ProjectDocument::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'category_id' => $category?->id,
            'filename' => $file->getClientOriginalName(),
            'filepath' => $path,
            'filesize' => $file->getSize(),
            'type' => 'file',
            'version' => 1,
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    /**
     * Attach URL as document
     */
    public function uploadUrl(Request $request, string $projectUuid)
    {
        $project = $this->resolveProject($request, $projectUuid);

        $request->validate([
            'url' => 'required|url|max:2048',
            'category_id' => ['required', 'string'],
        ]);

        $isOthers = (string) $request->category_id === 'others';
        if (!$isOthers) {
            $request->validate([
                'category_id' => ['required', Rule::exists('file_categories', 'id')],
            ]);
        }

        $category = $isOthers
            ? null
            : FileCategory::findOrFail((int) $request->category_id);

        ProjectDocument::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'category_id' => $category?->id,
            'filename' => parse_url($request->url, PHP_URL_HOST) ?? 'External Link',
            'filepath' => $request->url,
            'filesize' => null,
            'type' => 'url',
            'version' => 1,
        ]);

        return back()->with('success', 'URL attached successfully.');
    }

    /**
     * Download internal file OR redirect to external URL
     */
    public function download(Request $request, string $documentUuid)
    {
        $document = $this->resolveDocument($request, $documentUuid);

        if (is_string($document->filepath)
            && str_starts_with($document->filepath, 'http')) {
            return redirect()->away($document->filepath);
        }

        if (!Storage::exists($document->filepath)) {
            abort(404, 'File not found.');
        }

        return Storage::download(
            $document->filepath,
            $document->filename
        );
    }

    /**
     * Soft delete document
     */
    public function destroy(Request $request, string $documentUuid)
    {
        $document = $this->resolveDocument($request, $documentUuid);

        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    private function resolveProject(Request $request, string $uuid): Project
    {
        $query = Project::where('uuid', $uuid);
        $this->scopeToActiveBranch($request, $query, 'branch_id');

        return $query->firstOrFail();
    }

    private function resolveDocument(Request $request, string $uuid): ProjectDocument
    {
        $query = ProjectDocument::where('uuid', $uuid)
            ->whereHas('project', function (Builder $projectQuery) use ($request) {
                $this->scopeToActiveBranch($request, $projectQuery, 'branch_id');
            });

        return $query->firstOrFail();
    }

    private function scopeToActiveBranch(Request $request, Builder $query, string $column = 'branch_id'): void
    {
        $user = $request->user();
        if (!$user || !$this->shouldScopeToActiveBranch($request)) {
            return;
        }

        $branchId = (int) ($user->active_branch_id ?? 0);
        if ($branchId <= 0) {
            abort(403, 'Active branch is required.');
        }

        $query->where($column, $branchId);
    }

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
    }
}
