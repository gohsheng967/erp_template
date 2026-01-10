<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Client;
use App\Models\User;
use App\Models\Department;
use App\Models\FileCategory;
use App\Models\Claim;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDocument;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;

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
    public function show($uuid)
    {
        $project = Project::where('uuid', $uuid)->firstOrFail();

        $project->load(['client', 'manager']);

        return Inertia::render('Projects/Show', [
            'project' => $project,

            // keep existing props
            'documents' => $project->documents()
                ->with('user', 'category')
                ->get(),

            'categories' => FileCategory::orderBy('name')->get(),

            'milestones' => fn () => $project->milestones()
                ->with([
                    'actionTasks.assignee',
                    'phases.tasks',
                ])
                ->orderByDesc('id')
                ->get(),

            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * SHOW EDIT FORM
     */
    public function edit($uuid)
    {
        $project = Project::where('uuid', $uuid)->firstOrFail();

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

    public function summary(Project $project)
    {
        $baseQuery = Claim::where('project_id', $project->id);

        $summary = [
            'pending_approval' => (clone $baseQuery)
                ->where('status', 'submitted')
                ->count(),

            'pending_payment' => (clone $baseQuery)
                ->where('status', 'approved')
                ->count(),

            'total_approved_amount' => (clone $baseQuery)
                ->where('status', 'approved')
                ->sum('total_amount'),

            'total_paid_amount' => (clone $baseQuery)
                ->where('status', 'paid')
                ->sum('total_amount'),
        ];

        $pendingClaims = (clone $baseQuery)
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->latest()
            ->get([
                'id',
                'uuid',
                'title',
                'total_amount',
                'status',
                'created_at',
            ]);

        return response()->json([
            'summary' => $summary,
            'pending_claims' => $pendingClaims,
        ]);
    }

    public function updateBudget(Request $request, Project $project)
    {
        $validated = $request->validate([
            'budget' => ['required', 'numeric', 'min:0'],
        ]);

        $project->update([
            'budget' => $validated['budget'],
        ]);

        return back()->with('success', 'Budget updated successfully');
    }

    public function kpi(Project $project)
    {
        $purchaseOrderUsed = (float) PurchaseOrder::whereHas(
            'purchaseRequest',
            fn ($q) => $q->where('project_id', $project->id)
        )
        ->whereNotNull('confirmed_at') 
        ->sum('total_amount');

        $claimUsed = (float) $project->claims()
            ->whereIn('status', ['approved', 'paid'])
            ->sum('total_amount');

        $expenseUsed = 0;

        $used = $purchaseOrderUsed + $claimUsed + $expenseUsed;
        $total = (float) ($project->budget ?? 0);
        $remaining = max($total - $used, 0);

        $poCosts = PurchaseOrder::whereHas(
            'purchaseRequest',
            fn ($q) => $q->where('project_id', $project->id)
        )
        ->whereNotNull('confirmed_at')
        ->orderByDesc('total_amount')
        ->limit(5)
        ->get()
        ->map(fn ($po) => [
            'id'     => $po->id,
            'label'  => $po->code ?? 'PO #' . $po->id,
            'amount' => (float) $po->total_amount,
            'type'   => 'purchase_order',
        ]);

        $claimCosts  = $project->claims()
            ->whereIn('status', ['approved', 'paid'])
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(fn ($claim) => [
                'id' => $claim->id,
                'label' => $claim->title
                    ?? $claim->description
                    ?? 'Claim #' . $claim->id,
                'amount' => (float) $claim->total_amount,
                'type' => 'claim',
            ]);

        $topCosts = $poCosts
            ->merge($claimCosts)
            ->sortByDesc('amount')
            ->take(10)
            ->values();

        // 🔥 Recent activities (latest 5)
        $recentActivities = ProjectActivityLog::with('user')
            ->where('project_id', $project->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'message' => $this->formatActivityMessage($log),
                    'user_name' => $log->user?->name ?? 'System',
                    'time_ago' => $log->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'budget' => [
                'total' => $total,
                'used' => $used,
                'remaining' => $remaining,
                'percentage' => $total > 0
                    ? round(($used / $total) * 100, 1)
                    : 0,
            ],
            'breakdown' => [
                'purchase_orders' => $purchaseOrderUsed,
                'claims' => $claimUsed,
                'expenses' => $expenseUsed,
            ],
            'top_costs' => $topCosts,
            'recent_activities' => $recentActivities,
        ]);
    }

    private function formatActivityMessage(ProjectActivityLog $log): string
    {
        $data = is_array($log->data)
            ? $log->data
            : json_decode($log->data, true);

        return match ($log->action) {
            'milestone_created' =>
                'Milestone "' . ($data['message'] ?? '') . '" created',

            'document_uploaded' =>
                'Uploaded document "' . ($data['file'] ?? '') . '"',

            'claim_submitted' =>
                'Claim expense RM ' . number_format($data['amount'] ?? 0, 2) . ' submitted',

            'claim_approved' =>
                'Claim approved (RM ' . number_format($data['amount'] ?? 0, 2) . ')',

            default =>
                ucfirst(str_replace('_', ' ', $log->action)),
        };
    }
    
    public function purchaseRequestSummary(Project $project)
    {
        // =========================
        // PURCHASE REQUEST SUMMARY
        // =========================

        $pendingApproval = $project->purchaseRequests()
            ->whereIn('status', ['draft', 'submitted'])
            ->count();

        $confirmedPOAmount = (float) PurchaseOrder::whereHas(
            'purchaseRequest',
            fn ($q) => $q->where('project_id', $project->id)
        )
        ->whereNotNull('confirmed_at')
        ->sum('total_amount');

        // =========================
        // PENDING PR LIST
        // =========================
        $pendingPRs = $project->purchaseRequests()
            ->whereIn('status', ['draft', 'submitted'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($pr) => [
                'id'         => $pr->id,
                'uuid'       => $pr->uuid,
                'title'      => $pr->title,
                'status'     => $pr->status,
                'created_at' => $pr->created_at->toDateString(),
            ]);

        return response()->json([
            'summary' => [
                'pending_approval' => $pendingApproval,
                'total_po_amount'  => $confirmedPOAmount,
            ],
            'pending_requests' => $pendingPRs,
        ]);
    }
}
