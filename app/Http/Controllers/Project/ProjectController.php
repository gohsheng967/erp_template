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
use App\Models\ArInvoice;
use App\Models\PettyCashTopup;
use App\Models\SubCon;
use App\Models\SubConTask;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use DB;

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
            'end_date'      => 'nullable|date|after_or_equal:start_date|required_with:extension_date',
            'extension_date' => 'nullable|date|after_or_equal:end_date',
            'budget'        => 'nullable|numeric|min:0',
            'project_value' => 'nullable|numeric|min:0',
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
        $boundSubCons = $project->subCons()
            ->with('bankAccounts')
            ->orderBy('name')
            ->get(['sub_cons.id', 'sub_cons.uuid', 'sub_cons.name', 'sub_cons.company_name', 'sub_cons.phone', 'sub_cons.email']);

        $subConTasks = SubConTask::with([
            'subCon:id,uuid,name,company_name',
            'parent:id,uuid,title',
            'children:id,parent_id',
            'updates',
            'paymentSlip.companyBankAccount',
            'paymentSlip.attachments',
            'paymentSlip.source',
            'paymentSlip.source.subCon',
            'paymentSlip.source.project',
        ])
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get();

        $projectSubConSummaries = $this->buildProjectSubConSummaries($boundSubCons, $subConTasks);
        $boundSubConIds = $boundSubCons->pluck('id');

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

            'subCons' => $boundSubCons->map(fn ($subCon) => [
                'id' => $subCon->id,
                'uuid' => $subCon->uuid,
                'name' => $subCon->name,
                'company_name' => $subCon->company_name,
            ])->values(),

            'subConOptions' => SubCon::orderBy('name')
                ->whereNotIn('id', $boundSubConIds)
                ->get(['id', 'uuid', 'name', 'company_name']),

            'projectSubCons' => $projectSubConSummaries,

            'bankOptions' => config('banks.malaysia', []),

            'subConTasks' => $subConTasks,
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
            'end_date'      => 'nullable|date|after_or_equal:start_date|required_with:extension_date',
            'extension_date' => 'nullable|date|after_or_equal:end_date',
            'budget'        => 'nullable|numeric|min:0',
            'project_value' => 'nullable|numeric|min:0',
            'department_id' => 'nullable|integer|exists:departments,id',
            'manager_id'    => 'nullable|integer|exists:users,id',
            'description'   => 'nullable|string',
        ]);

        $project->update($validated);

        return redirect()->route('projects.index')
            ->with('success', 'Project updated successfully.');
    }

    public function toggleFinished(Request $request, Project $project)
    {
        $validated = $request->validate([
            'is_finished' => ['required', 'boolean'],
        ]);

        $isFinished = (bool) $validated['is_finished'];

        $project->update([
            'is_finished' => $isFinished,
            'finished_at' => $isFinished ? now() : null,
        ]);

        return back()->with('success', $isFinished
            ? 'Project flagged as finished.'
            : 'Project marked as ongoing.');
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

    public function bindSubCon(Request $request, string $projectUuid)
    {
        $project = Project::where('uuid', $projectUuid)->firstOrFail();

        $validated = $request->validate([
            'sub_con_id' => ['required', 'integer', Rule::exists('sub_cons', 'id')],
        ]);

        $alreadyBound = $project->subCons()
            ->where('sub_cons.id', $validated['sub_con_id'])
            ->exists();

        if ($alreadyBound) {
            return back()->withErrors([
                'sub_con_id' => 'This Sub Con is already bound to the project.',
            ]);
        }

        $project->subCons()->attach($validated['sub_con_id']);

        return back()->with('success', 'Sub Con bound to project.');
    }

    public function createAndBindSubCon(Request $request, string $projectUuid)
    {
        $project = Project::where('uuid', $projectUuid)->firstOrFail();
        $banks = config('banks.malaysia', []);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'bank_accounts' => 'nullable|array|max:10',
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255', Rule::in($banks)],
            'bank_accounts.*.account_name' => 'nullable|string|max:255',
            'bank_accounts.*.account_no' => 'nullable|string|max:100',
        ]);

        $bankAccounts = $this->normalizeSubConBankAccounts($validated['bank_accounts'] ?? []);
        $legacyBank = $bankAccounts[0]['bank_name'] ?? null;

        DB::transaction(function () use ($project, $validated, $bankAccounts, $legacyBank) {
            $subCon = SubCon::create([
                'name' => $validated['name'],
                'company_name' => $validated['company_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'bank' => $legacyBank,
            ]);

            if (!empty($bankAccounts)) {
                $subCon->bankAccounts()->createMany($bankAccounts);
            }

            $project->subCons()->attach($subCon->id);
        });

        return back()->with('success', 'Sub Con created and bound to project.');
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

    private function buildProjectSubConSummaries($boundSubCons, $subConTasks)
    {
        $tasksBySubCon = $subConTasks->groupBy('sub_con_id');

        return $boundSubCons->map(function ($subCon) use ($tasksBySubCon) {
            $tasks = $tasksBySubCon->get($subCon->id, collect());
            $statusCounts = $tasks->countBy('status');

            $totalTasks = $tasks->count();
            $submitted = (int) ($statusCounts['submitted'] ?? 0);
            $verified = (int) ($statusCounts['verified'] ?? 0);
            $justified = (int) ($statusCounts['justified'] ?? 0);
            $certified = (int) ($statusCounts['certified'] ?? 0);
            $paid = (int) ($statusCounts['paid'] ?? 0);
            $draft = (int) ($statusCounts['draft'] ?? 0);

            $avgProgress = $totalTasks > 0
                ? round((float) $tasks->avg('progress_percent'), 1)
                : 0.0;

            $invoicedAmount = (float) $tasks
                ->whereIn('status', ['certified', 'paid'])
                ->sum(fn ($task) => (float) $task->amount);

            $paidAmount = (float) $tasks
                ->where('status', 'paid')
                ->sum(fn ($task) => (float) $task->amount);

            $paymentStatus = 'No Task';
            if ($totalTasks > 0 && $paid === $totalTasks) {
                $paymentStatus = 'Paid';
            } elseif ($paid > 0) {
                $paymentStatus = 'Partially Paid';
            } elseif ($certified > 0) {
                $paymentStatus = 'Pending Payment';
            } elseif ($submitted > 0 || $verified > 0 || $justified > 0) {
                $paymentStatus = 'In Progress';
            } elseif ($draft > 0) {
                $paymentStatus = 'Draft';
            }

            $latestTaskUpdate = $tasks->max('updated_at');

            return [
                'id' => $subCon->id,
                'uuid' => $subCon->uuid,
                'name' => $subCon->name,
                'company_name' => $subCon->company_name,
                'phone' => $subCon->phone,
                'email' => $subCon->email,
                'bank_accounts' => $subCon->bankAccounts->map(fn ($account) => [
                    'id' => $account->id,
                    'bank_name' => $account->bank_name,
                    'account_name' => $account->account_name,
                    'account_no' => $account->account_no,
                ])->values(),
                'stats' => [
                    'total_tasks' => $totalTasks,
                    'draft_tasks' => $draft,
                    'submitted_tasks' => $submitted,
                    'verified_tasks' => $verified,
                    'justified_tasks' => $justified,
                    'certified_tasks' => $certified,
                    'paid_tasks' => $paid,
                    'avg_progress_percent' => $avgProgress,
                    'invoiced_amount' => $invoicedAmount,
                    'paid_amount' => $paidAmount,
                    'outstanding_amount' => max($invoicedAmount - $paidAmount, 0),
                    'payment_status' => $paymentStatus,
                    'latest_task_update_at' => $latestTaskUpdate,
                ],
            ];
        })->values();
    }

    private function normalizeSubConBankAccounts(array $accounts): array
    {
        return collect($accounts)
            ->map(function ($account) {
                return [
                    'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                    'account_name' => trim((string) ($account['account_name'] ?? '')),
                    'account_no' => trim((string) ($account['account_no'] ?? '')),
                ];
            })
            ->filter(function ($account) {
                return $account['bank_name'] !== ''
                    || $account['account_name'] !== ''
                    || $account['account_no'] !== '';
            })
            ->values()
            ->all();
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

    public function aRSummary(Project $project)
    {
        // Base invoices (exclude cancelled)
        $invoices = $project->arInvoices()
            ->whereNotIn('status', ['cancelled']);

        /* =========================
        TOTAL INVOICED
        ========================== */
        $totalAmount = (clone $invoices)->sum('total_amount');

        /* =========================
        RECEIVED (FROM RECEIPTS)
        ========================== */
        $receivedAmount = DB::table('ar_invoice_receipts')
            ->join('ar_invoices', 'ar_invoices.id', '=', 'ar_invoice_receipts.ar_invoice_id')
            ->where('ar_invoices.project_id', $project->id)
            ->sum('ar_invoice_receipts.amount');

        /* =========================
        OUTSTANDING
        ========================== */
        $outstanding = max($totalAmount - $receivedAmount, 0);

        /* =========================
        STATUS COUNTS
        ========================== */
        $counts = [
            'draft'     => (clone $invoices)->where('status', 'draft')->count(),
            'issued'    => (clone $invoices)->where('status', 'issued')->count(),
            'approved'  => (clone $invoices)->where('status', 'approved')->count(),
            'received'  => (clone $invoices)->where('status', 'received')->count(),
            'cancelled' => $project->arInvoices()->where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'summary' => [
                ...$counts,
                'total_amount'        => (float) $totalAmount,
                'received_amount'     => (float) $receivedAmount,
                'outstanding_amount'  => (float) $outstanding,
            ],
        ]);
    }

    public function topupRequests($project, Request $request)
    {
        $project = Project::query()
            ->where('id', $project)
            ->orWhere('uuid', $project)
            ->firstOrFail();

        $status = $request->get('status', 'requested');
        $allowedStatuses = ['requested', 'approved', 'rejected', 'paid'];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'requested';
        }

        $baseQuery = PettyCashTopup::query()
            ->whereHas('wallet', function ($query) use ($project) {
                $query->where('context_type', 'project')
                    ->where('context_id', $project->id);
            })
            ->with([
                'wallet.project',
                'bankAccount',
                'companyBankAccount',
                'paymentSlip',
                'requester:id,name',
                'approver:id,name',
                'rejector:id,name',
                'payer:id,name',
                'attachment',
            ])
            ->orderByDesc('created_at');

        $counts = [
            'requested' => (clone $baseQuery)->where('status', 'requested')->count(),
            'approved'  => (clone $baseQuery)->where('status', 'approved')->count(),
            'rejected'  => (clone $baseQuery)->where('status', 'rejected')->count(),
            'paid'      => (clone $baseQuery)->where('status', 'paid')->count(),
        ];

        $topups = (clone $baseQuery)
            ->where('status', $status)
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'topups' => $topups,
            'counts' => $counts,
        ]);
    }

}
