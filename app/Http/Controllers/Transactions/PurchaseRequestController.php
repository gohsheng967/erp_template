<?php

namespace App\Http\Controllers\Transactions;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\PurchaseQuotation;
use App\Models\Project;
use App\Models\CompanyProfile;
use App\Models\Supplier;
use App\Models\SubCon;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Services\RunningNumberService;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Services\Document\PurchaseRequestToPurchaseOrderService;
use DB;

class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $allowedTabs = [
            'draft',
            'submitted',
            'verified_own_department',
            'verified_project_department',
            'verified_purchasing_department',
            'po',
            'payment',
            'rejected',
        ];
        $tab = in_array($request->get('tab'), $allowedTabs, true)
            ? $request->get('tab')
            : 'draft';

        $filters = [
            'search' => $request->get('search'),
            'from'   => $request->get('from'),
            'to'     => $request->get('to'),
            'requester_id' => $request->get('requester_id'),
            'project_id' => $request->get('project_id'),
            'department_id' => $request->get('department_id'),
            'project_linked' => $request->get('project_linked'),
            'has_quotation' => $request->get('has_quotation'),
            'amount_min' => $request->get('amount_min'),
            'amount_max' => $request->get('amount_max'),
        ];

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Base Filter Query (NO relations, NO aggregates)
        |--------------------------------------------------------------------------
        */
        $filterQuery = PurchaseRequest::query()
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('purchase_requests.branch_id', $this->activeBranchId($request))
            )
            ->when($filters['search'], function ($q) use ($filters) {
                $q->where(function ($qq) use ($filters) {
                    $qq->where('code', 'like', "%{$filters['search']}%")
                    ->orWhere('title', 'like', "%{$filters['search']}%")
                    ->orWhereHas('requester', fn ($u) =>
                            $u->where('name', 'like', "%{$filters['search']}%")
                    );
                });
            })
            ->when($filters['from'], fn ($q) =>
                $q->whereDate('created_at', '>=', $filters['from'])
            )
            ->when($filters['to'], fn ($q) =>
                $q->whereDate('created_at', '<=', $filters['to'])
            )
            ->when($filters['requester_id'], fn ($q) =>
                $q->where('requested_by', (int) $filters['requester_id'])
            )
            ->when($filters['project_id'], fn ($q) =>
                $q->where('project_id', (int) $filters['project_id'])
            )
            ->when($filters['department_id'], fn ($q) =>
                $q->where('department_id', (int) $filters['department_id'])
            )
            ->when($filters['project_linked'] === 'yes', fn ($q) =>
                $q->whereNotNull('project_id')
            )
            ->when($filters['project_linked'] === 'no', fn ($q) =>
                $q->whereNull('project_id')
            )
            ->when($filters['has_quotation'] === 'yes', fn ($q) =>
                $q->whereHas('quotations')
            )
            ->when($filters['has_quotation'] === 'no', fn ($q) =>
                $q->whereDoesntHave('quotations')
            )
            ->when($filters['amount_min'] !== null && $filters['amount_min'] !== '', function ($q) use ($filters) {
                $q->whereRaw(
                    '(select coalesce(sum(pri.total_price),0) from purchase_request_items pri where pri.purchase_request_id = purchase_requests.id) >= ?',
                    [(float) $filters['amount_min']]
                );
            })
            ->when($filters['amount_max'] !== null && $filters['amount_max'] !== '', function ($q) use ($filters) {
                $q->whereRaw(
                    '(select coalesce(sum(pri.total_price),0) from purchase_request_items pri where pri.purchase_request_id = purchase_requests.id) <= ?',
                    [(float) $filters['amount_max']]
                );
            });

        // Auto-push PR to payment stage once payable/AP invoice exists.
        $paymentSyncQuery = PurchaseRequest::query()
            ->where('status', 'po')
            ->whereHas('purchaseOrder.apInvoice', function ($q) {
                $q->whereIn('status', ['confirmed', 'partially_paid', 'paid']);
            });
        if ($this->shouldScopeToActiveBranch($request)) {
            $paymentSyncQuery->where('branch_id', $this->activeBranchId($request));
        }
        $paymentSyncQuery->update(['status' => 'payment']);

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Listing Query (relations + aggregates ONLY)
        |--------------------------------------------------------------------------
        */
        $listQuery = (clone $filterQuery)
            ->with([
                'requester',
                'approver',
                'project:id,name',
                'purchaseOrder.apInvoice',
                'approvedQuotation.attachment',
            ])
            ->withCount([
                'items',
                'quotations',
            ])
            ->withSum('items as total_amount', 'total_price');

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Tabs Data
        |--------------------------------------------------------------------------
        */
        $statuses = [
            'draft',
            'submitted',
            'verified_own_department',
            'verified_project_department',
            'verified_purchasing_department',
            'rejected',
            'po',
            'payment',
        ];

        $purchaseRequests = [];
        foreach ($statuses as $status) {
            $purchaseRequests[$status] = (clone $listQuery)
                ->where('status', $status)
                ->latest($status === 'draft' ? 'created_at' : 'updated_at')
                ->paginate(10, ['*'], "all_{$status}_page");
        }

        $myListQuery = (clone $listQuery)
            ->where('requested_by', auth()->id());

        $myPurchaseRequests = [];
        foreach ($statuses as $status) {
            $myPurchaseRequests[$status] = (clone $myListQuery)
                ->where('status', $status)
                ->latest($status === 'draft' ? 'created_at' : 'updated_at')
                ->paginate(10, ['*'], "my_{$status}_page");
        }

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Tab Counters (FAST & SAFE)
        |--------------------------------------------------------------------------
        */
        $counts = [];
        foreach ($statuses as $status) {
            $counts[$status] = (clone $filterQuery)
                ->where('status', $status)
                ->count();
        }

        $myFilterQuery = (clone $filterQuery)
            ->where('requested_by', auth()->id());

        $myCounts = [];
        foreach ($statuses as $status) {
            $myCounts[$status] = (clone $myFilterQuery)
                ->where('status', $status)
                ->count();
        }

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Render
        |--------------------------------------------------------------------------
        */
        return Inertia::render('Transactions/PurchaseRequest/Index', [
            'purchaseRequests' => $purchaseRequests,
            'myPurchaseRequests' => $myPurchaseRequests,
            'counts'           => $counts,
            'myCounts'         => $myCounts,
            'filters'          => $filters,
            'activeTab'        => $tab,
            'filterOptions'    => [
                'requesters' => User::query()
                    ->select('id', 'name')
                    ->where('status', 1)
                    ->where(function ($q) use ($request) {
                        $branchId = $this->activeBranchId($request);
                        $q->where('active_branch_id', $branchId)
                            ->orWhereHas('branches', fn ($qq) => $qq->where('branches.id', $branchId));
                    })
                    ->orderBy('name')
                    ->get(),
                'projects' => Project::query()
                    ->select('id', 'name')
                    ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                        $q->where('branch_id', $this->activeBranchId($request))
                    )
                    ->orderBy('name')
                    ->get(),
                'departments' => Department::query()
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),
            ],
        ]);
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'purpose'          => 'nullable|string',
            'department_id'    => 'nullable|exists:departments,id',
            'project_id'       => 'nullable|exists:projects,id',
            'is_subcon_purchase_request' => 'nullable|boolean',
            'required_date'    => 'required|date',
            'requester_remark' => 'nullable|string',
        ]);

        if (!empty($data['project_id'])) {
            $projectQuery = Project::query()->where('id', $data['project_id']);
            if ($this->shouldScopeToActiveBranch($request)) {
                $projectQuery->where('branch_id', $this->activeBranchId($request));
            }
            $projectQuery->firstOrFail();
        }

        DB::transaction(function () use ($data, $request) {
            PurchaseRequest::create([
                'code'             => null,
                'title'            => $data['title'],
                'purpose'          => $data['purpose'] ?? null,
                'required_date'    => $data['required_date'] ?? null,
                'department_id'    => $data['department_id'] ?? null,
                'project_id'       => $data['project_id'] ?? null,
                'is_subcon_purchase_request' => (bool) ($data['is_subcon_purchase_request'] ?? false),
                'branch_id'        => $this->activeBranchId($request),
                'requested_by'     => auth()->id(),
                'status'           => 'draft',
                'requester_remark' => $data['requester_remark'] ?? null,
                'reviewer'         => null,
                'reviewer_remark'  => null,
                'approved_quotation_id' => null,
            ]);
        });

        // IMPORTANT: return back(), NOT redirect
        return back()->with('success', 'Purchase Request created');
    }

    public function initPurchaseRequestForm(Request $request)
    {
        $user = auth()->user();

        $departments = $user->departments()
            ->orderBy('name')
            ->get(['departments.id', 'departments.name']);

        // Fallback for users without pivot_user_departments rows.
        if ($departments->isEmpty()) {
            $departments = Department::query()
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $departmentIds = $departments->pluck('id');

        $prQuery = PurchaseRequest::query()
            ->where('status', 'draft')
            ->whereIn('department_id', $departmentIds);
        if ($this->shouldScopeToActiveBranch($request)) {
            $prQuery->where('branch_id', $this->activeBranchId($request));
        }

        if ($search = $request->input('search')) {
            $prQuery->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $purchaseRequests = $prQuery
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'code', 'title']);

        $projects = Project::whereIn('status', ['on_going', 'late', 'extended'])
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json([
            'departments' => $departments,
            'purchase_requests' => $purchaseRequests,
            'projects' => $projects,
        ]);
    }

    public function addQuotations(Request $request)
    {
        $data = $request->validate([
            'quotation_ids' => 'required|array',
            'quotation_ids.*' => 'exists:purchase_quotations,id',

            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'create_new' => 'boolean',

            'new_pr.title' => 'required_if:create_new,true|string',
            'new_pr.purpose' => 'nullable|string',
            'new_pr.department_id' => 'required_if:create_new,true|exists:departments,id',
            'new_pr.project_id' => 'nullable|exists:projects,id',
        ]);

        $userDepartmentIds = auth()->user()
            ->departments()
            ->pluck('departments.id')
            ->toArray();

        if ($request->boolean('create_new')) {

            if (! in_array($data['new_pr']['department_id'], $userDepartmentIds)) {
                abort(403, 'Invalid department');
            }

            $pr = PurchaseRequest::create([
                'code' => null,
                'title' => $data['new_pr']['title'],
                'purpose' => $data['new_pr']['purpose'] ?? null,

                'project_id' => $data['new_pr']['project_id'] ?? null,

                'department_id' => $data['new_pr']['department_id'],
                'branch_id' => $this->activeBranchId($request),

                'requested_by' => auth()->id(),
                'status' => 'draft',
            ]);

        } else {

            $pr = PurchaseRequest::whereIn('department_id', $userDepartmentIds)
                ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                    $q->where('branch_id', $this->activeBranchId($request))
                )
                ->findOrFail($data['purchase_request_id']);
        }

        $existing = $pr->quotations()
            ->whereIn('purchase_quotation_id', $data['quotation_ids'])
            ->pluck('purchase_quotation_id')
            ->toArray();

        $attach = array_diff($data['quotation_ids'], $existing);

        if (! empty($attach)) {
            $pr->quotations()->attach($attach);
        }

        return back()->with('success', 'Quotation(s) added');
    }

    public function edit(Request $request, string $uuid)
    {
        $pr = PurchaseRequest::with([
                'items',
                'quotations',
                'quotations.supplier',
                'quotations.attachment',
                'approvedQuotation',
                'requester',
                'siteContact:id,name',
            ])
            ->where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->firstOrFail();

        return Inertia::render('Transactions/PurchaseRequest/Edit', [
            'pr' => $pr,

            // dropdowns for basic info
            'departments' => Department::select('id', 'name')->get(),
            'projects' => Project::select('id', 'name')->get(),
            'contactUsers' => User::query()
                ->select('id', 'name')
                ->where('status', 1)
                ->where(function ($q) use ($request) {
                    $branchId = $this->activeBranchId($request);
                    $q->where('active_branch_id', $branchId)
                        ->orWhereHas('branches', fn ($qq) => $qq->where('branches.id', $branchId));
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function quotations(Request $request, string $prUuid, string $supplierUuid)
    {
        $pr = PurchaseRequest::where('uuid', $prUuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->firstOrFail();
        $supplier = Supplier::where('uuid', $supplierUuid)->firstOrFail();

        return $this->availableQuotationsForSupplier($pr, $supplier->id);
    }

    public function subConQuotations(Request $request, string $prUuid, string $subConUuid)
    {
        $pr = PurchaseRequest::where('uuid', $prUuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->firstOrFail();

        $subCon = SubCon::where('uuid', $subConUuid)->firstOrFail();
        $supplier = $this->resolveSupplierForSubCon($subCon, false);

        if (!$supplier) {
            return collect();
        }

        return $this->availableQuotationsForSupplier($pr, $supplier->id);
    }

    public function attachQuotation(Request $request, string $uuid)
    {
        $pr = PurchaseRequest::where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->firstOrFail();

        $data = $request->validate([
            'supplier_uuid' => ['nullable', 'exists:suppliers,uuid'],
            'sub_con_uuid' => ['nullable', 'exists:sub_cons,uuid'],

            // existing quotation
            'quotation_id' => ['nullable', 'exists:purchase_quotations,id'],

            // new quotation fields
            'quotation_no'  => ['nullable', 'string', 'max:100'],
            'amount'        => ['nullable', 'numeric', 'min:0'],
            'delivery_time' => ['nullable', 'integer', 'min:0'],
            'terms'         => ['nullable', 'string'],

            // file
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        if (empty($data['quotation_id']) && !$request->hasFile('file')) {
            return response()->json([
                'message' => 'Select existing quotation or upload new quotation',
            ], 422);
        }

        $supplier = $this->resolveAttachSupplier($pr, $data);

        DB::transaction(function () use ($data, $request, $pr, $supplier) {

            if (!empty($data['quotation_id'])) {
                $quotation = PurchaseQuotation::findOrFail($data['quotation_id']);
                if ((int) $quotation->supplier_id !== (int) $supplier->id) {
                    throw ValidationException::withMessages([
                        'quotation_id' => 'Selected quotation does not belong to selected vendor.',
                    ]);
                }
            } else {
                $quotation = PurchaseQuotation::create([
                    'supplier_id'   => $supplier->id,
                    'quotation_no'  => $data['quotation_no'],
                    'amount'        => $data['amount'],
                    'delivery_time' => $data['delivery_time'],
                    'terms'         => $data['terms'],
                ]);

                if ($request->hasFile('file')) {
                    $path = $request->file('file')->store('quotations', 'public');

                    $quotation->attachment()->create([
                        'original_name' => $request->file('file')->getClientOriginalName(),
                        'file_path'     => $path,
                        'disk'          => 'public',
                    ]);
                }
            }

            // attach safely (no duplicate)
            $pr->quotations()->syncWithoutDetaching([$quotation->id]);
        });

        return response()->json([
            'message' => 'Quotation attached successfully',
        ]);
    }

    private function resolveAttachSupplier(PurchaseRequest $pr, array $data): Supplier
    {
        if ((bool) $pr->is_subcon_purchase_request) {
            $subConUuid = trim((string) ($data['sub_con_uuid'] ?? ''));
            if ($subConUuid === '') {
                throw ValidationException::withMessages([
                    'sub_con_uuid' => 'Sub Con is required for Sub Con purchase request.',
                ]);
            }

            $subCon = SubCon::where('uuid', $subConUuid)->firstOrFail();

            return $this->resolveSupplierForSubCon($subCon, true);
        }

        $supplierUuid = trim((string) ($data['supplier_uuid'] ?? ''));
        if ($supplierUuid === '') {
            throw ValidationException::withMessages([
                'supplier_uuid' => 'Supplier is required.',
            ]);
        }

        return Supplier::where('uuid', $supplierUuid)->firstOrFail();
    }

    private function resolveSupplierForSubCon(SubCon $subCon, bool $createIfMissing): ?Supplier
    {
        $companyName = trim((string) ($subCon->company_name ?? ''));
        if ($companyName === '') {
            $companyName = trim((string) ($subCon->name ?? ''));
        }

        if ($companyName === '') {
            if ($createIfMissing) {
                throw ValidationException::withMessages([
                    'sub_con_uuid' => 'Selected Sub Con has no company/name to map quotation supplier.',
                ]);
            }

            return null;
        }

        $supplier = Supplier::query()
            ->whereRaw('LOWER(company_name) = ?', [strtolower($companyName)])
            ->first();

        if ($supplier || !$createIfMissing) {
            return $supplier;
        }

        return Supplier::create([
            'company_name' => $companyName,
            'contact_person' => $subCon->name ?: null,
            'contact_phone' => $subCon->phone ?: null,
            'email' => $subCon->email ?: null,
            'address' => $subCon->address ?: null,
            'status' => 'active',
        ]);
    }

    private function availableQuotationsForSupplier(PurchaseRequest $pr, int $supplierId)
    {
        return PurchaseQuotation::query()
            ->where('supplier_id', $supplierId)
            ->whereDoesntHave('purchaseRequests', function ($q) use ($pr) {
                $q->where('purchase_request_id', $pr->id);
            })
            ->orderByDesc('created_at')
            ->get([
                'id',
                'uuid',
                'quotation_no',
                'amount',
                'delivery_time',
            ]);
    }

    public function detachQuotation(Request $request, string $uuid, string $quotationUuid)
    {
        $pr = PurchaseRequest::where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->firstOrFail();

        $quotation = PurchaseQuotation::where('uuid', $quotationUuid)->firstOrFail();

        if (!$this->isEditableStatus($pr->status)) {
            return response()->json([
                'message' => 'Cannot modify quotation after submission',
            ], 422);
        }

        $pr->quotations()->detach($quotation->id);

        if ($pr->approved_quotation_id === $quotation->id) {
            $pr->updateQuietly([
                'approved_quotation_id' => null,
            ]);
        }

        return response()->json([
            'message' => 'Quotation removed from purchase request',
        ]);
    }

    public function update(Request $request, string $uuid)
    {
        /* =========================
        1️⃣ FETCH PR
        ========================== */
        $purchaseRequest = PurchaseRequest::where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->firstOrFail();

        if (!$this->isEditableStatus($purchaseRequest->status)) {
            abort(403, 'Only editable PR can be edited');
        }

        /* =========================
        2️⃣ VALIDATE PAYLOAD
        ========================== */
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'project_id' => 'nullable|exists:projects,id',
            'is_subcon_purchase_request' => 'nullable|boolean',
            'requester_remark' => 'nullable|string',
            'required_date' => 'nullable|date',
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|exists:purchase_request_items,id',
            'items.*.title' => 'nullable|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($purchaseRequest, $validated) {

            /* =========================
            UPDATE HEADER
            ========================== */
            $purchaseRequest->update(
                Arr::except($validated, ['items'])
            );

            $items = $validated['items'] ?? [];

            /* =========================
            3️⃣ BLOCK PARTIAL ROWS
            ========================== */
            foreach ($items as $index => $item) {
                $filled = collect([
                    trim((string) ($item['title'] ?? '')),
                    $item['quantity'] ?? null,
                    $item['unit_price'] ?? null,
                ])->filter(fn ($v) => $v !== null && $v !== '')->count();

                if ($filled > 0 && $filled < 3) {
                    throw ValidationException::withMessages([
                        "items.$index" =>
                            "Item row " . ($index + 1) . " is incomplete",
                    ]);
                }
            }

            /* =========================
            4️⃣ HANDLE REMOVALS
            ========================== */
            $submittedIds = collect($items)
                ->pluck('id')
                ->filter()
                ->values()
                ->all();

            $existingIds = $purchaseRequest->items()->pluck('id')->all();

            $idsToDelete = array_diff($existingIds, $submittedIds);

            if (!empty($idsToDelete)) {
                $purchaseRequest->items()
                    ->whereIn('id', $idsToDelete)
                    ->delete();
            }

            /* =========================
            5️⃣ UPSERT VALID ROWS
            ========================== */
            foreach ($items as $item) {

                // Completely empty row → ignore
                if (
                    trim((string) ($item['title'] ?? '')) === '' &&
                    ($item['quantity'] ?? null) === null &&
                    ($item['unit_price'] ?? null) === null
                ) {
                    continue;
                }

                $purchaseRequest->items()->updateOrCreate(
                    ['id' => $item['id'] ?? null],
                    [
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['quantity'] * $item['unit_price'],
                    ]
                );
            }
        });

        return back()->with('success', 'Draft saved');
    }

    public function submit(Request $request, string $uuid)
    {
        $purchaseRequest = PurchaseRequest::where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->firstOrFail();

        if (!in_array($purchaseRequest->status, ['draft', 'verified_own_department'], true)) {
            abort(403, 'PR is not in a submittable state');
        }

        if (! $purchaseRequest->quotations()->exists()) {
            throw ValidationException::withMessages([
                'quotations' => 'At least one quotation must be attached before submitting.',
            ]);
        }

        $header = $request->validate([
            'title' => 'required|string|max:255',
            'purpose' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'project_id' => 'nullable|exists:projects,id',
            'requester_remark' => 'nullable|string',
            'required_date' => 'required|date',
            'quotation_id' => ['required', 'exists:purchase_quotations,id'],
        ]);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:purchase_request_items,id',
            'items.*.title' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            if (
                trim((string) $item['title']) === '' ||
                !isset($item['quantity']) ||
                $item['quantity'] <= 0 ||
                !isset($item['unit_price']) ||
                $item['unit_price'] < 0
            ) {
                throw ValidationException::withMessages([
                    'items' =>
                        'One or more items are incomplete. Please complete or remove them.',
                ]);
            }
        }

        DB::transaction(function () use ($purchaseRequest, $header, $validated) {
            $fromStatus = (string) $purchaseRequest->status;
            $quotationId = (int) $header['quotation_id'];
            unset($header['quotation_id']);

            $hasQuotation = $purchaseRequest->quotations()
                ->where('purchase_quotation_id', $quotationId)
                ->exists();
            if (!$hasQuotation) {
                throw ValidationException::withMessages([
                    'quotation_id' => 'Selected quotation is not attached to this PR.',
                ]);
            }

            $purchaseRequest->update($header);

            $submittedIds = collect($validated['items'])
                ->pluck('id')
                ->filter()
                ->values()
                ->all();

            $existingIds = $purchaseRequest->items()->pluck('id')->all();
            $idsToDelete = array_diff($existingIds, $submittedIds);

            if (!empty($idsToDelete)) {
                $purchaseRequest->items()
                    ->whereIn('id', $idsToDelete)
                    ->delete();
            }

            foreach ($validated['items'] as $item) {
                $purchaseRequest->items()->updateOrCreate(
                    ['id' => $item['id'] ?? null],
                    [
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['quantity'] * $item['unit_price'],
                    ]
                );
            }

            $purchaseRequest->update([
                'code'   => $purchaseRequest->code ?: RunningNumberService::next(documentType: 'purchase_request'),
                'status' => 'submitted',
                'submitted_at' => now(),
                'requested_by' => auth()->id(),
                'approved_quotation_id' => $quotationId,
                'remark_log' => $this->appendRemarkLog(
                    existing: $purchaseRequest->remark_log,
                    fromStatus: $fromStatus,
                    toStatus: 'submitted',
                    remark: $purchaseRequest->requester_remark,
                    userId: auth()->id(),
                    userName: (string) (auth()->user()?->name ?? 'System'),
                ),
            ]);
        });

        return redirect()
            ->route('purchase-request.index', $purchaseRequest->uuid)
            ->with('success', 'Purchase Request submitted');
    }

    public function show(Request $httpRequest, string $uuid)
    {
        $request = PurchaseRequest::query()
            ->with([
                'department',
                'requester',
                'approver',
                'items',
                'quotations.attachment',
                'purchaseOrder.apInvoice',
                'siteContact:id,name',
            ])
            ->where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($httpRequest), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($httpRequest))
            )
            ->firstOrFail();

        $company = CompanyProfile::first();
        $logUserIds = collect($request->remark_log ?? [])
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();
        $remarkSigners = User::query()
            ->whereIn('id', $logUserIds)
            ->get(['id', 'name', 'signature_path'])
            ->mapWithKeys(function ($user) {
                return [
                    (string) $user->id => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'signature_url' => $user->signature_path
                            ? Storage::disk('public')->url($user->signature_path)
                            : null,
                    ],
                ];
            });

        return response()->json([
            'request' => $request,
            'company' => $company,
            'remark_signers' => $remarkSigners,
            'contact_users' => User::query()
                ->select('id', 'name')
                ->where('status', 1)
                ->where(function ($q) use ($httpRequest) {
                    $branchId = $this->activeBranchId($httpRequest);
                    $q->where('active_branch_id', $branchId)
                        ->orWhereHas('branches', fn ($qq) => $qq->where('branches.id', $branchId));
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function approval(Request $request, string $uuid, PurchaseRequestToPurchaseOrderService $prToPo)
    {
        $data = $request->validate([
            'status' => ['required', 'in:verify,approved,rejected,create_po,draft'],
            'remark' => ['nullable', 'string'],
            'quotation_id' => ['nullable', 'exists:purchase_quotations,id'],
            'delivery_period' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string'],
            'site_contact_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $pr = PurchaseRequest::with(['items', 'purchaseOrder'])
            ->where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->firstOrFail();

        $fromStatus = (string) $pr->status;
        if ($data['status'] === 'draft') {
            if (!in_array($pr->status, ['submitted', 'verified_own_department'], true)) {
                abort(422, 'Only submitted or own-department verified PR can return to draft.');
            }

            $pr->update([
                'status'          => 'draft',
                'reviewer_remark' => $fromStatus === 'verified_purchasing_department'
                    ? $data['remark']
                    : $pr->reviewer_remark,
                'reviewer'        => auth()->id(),
                'approved_at'     => now(),
                'remark_log' => $this->appendRemarkLog(
                    existing: $pr->remark_log,
                    fromStatus: $fromStatus,
                    toStatus: 'draft',
                    remark: $data['remark'],
                    userId: auth()->id(),
                    userName: (string) (auth()->user()?->name ?? 'System'),
                ),
            ]);

            return $this->approvalSuccessResponse(
                $request,
                'Purchase request returned to draft'
            );
        }

        if ($data['status'] === 'rejected') {
            if ($pr->status === 'po') {
                abort(422, 'PO-issued purchase request cannot be rejected.');
            }

            $pr->update([
                'status'          => 'rejected',
                'reviewer_remark' => $fromStatus === 'verified_purchasing_department'
                    ? $data['remark']
                    : $pr->reviewer_remark,
                'reviewer'        => auth()->id(),
                'approved_at'     => now(),
                'remark_log' => $this->appendRemarkLog(
                    existing: $pr->remark_log,
                    fromStatus: $fromStatus,
                    toStatus: 'rejected',
                    remark: $data['remark'],
                    userId: auth()->id(),
                    userName: (string) (auth()->user()?->name ?? 'System'),
                ),
            ]);

            return $this->approvalSuccessResponse(
                $request,
                'Purchase request rejected'
            );
        }

        if ($data['status'] === 'verify') {
            $nextStatus = match ($pr->status) {
                'submitted' => 'verified_own_department',
                'verified_own_department' => $pr->project_id
                    ? 'verified_project_department'
                    : 'verified_purchasing_department',
                'verified_project_department' => 'verified_purchasing_department',
                default => null,
            };

            if (!$nextStatus) {
                abort(422, 'Purchase request is not in a verifiable state.');
            }

            if ($nextStatus === 'verified_purchasing_department') {
                $isSubConRequest = (bool) $pr->is_subcon_purchase_request;
                $deliveryPeriod = trim((string) ($data['delivery_period'] ?? ''));
                $paymentTerms = trim((string) ($data['payment_terms'] ?? ''));
                $siteContactUserId = (int) ($data['site_contact_user_id'] ?? 0);

                if ((!$isSubConRequest && $deliveryPeriod === '') || $paymentTerms === '' || $siteContactUserId <= 0) {
                    throw ValidationException::withMessages([
                        'delivery_period' => $isSubConRequest
                            ? 'Payment terms and site contact person are required before purchasing verification.'
                            : 'Delivery period, payment terms, and site contact person are required before purchasing verification.',
                    ]);
                }
            }

            $pr->update([
                'status'          => $nextStatus,
                'reviewer_remark' => $fromStatus === 'verified_purchasing_department'
                    ? $data['remark']
                    : $pr->reviewer_remark,
                'reviewer'        => auth()->id(),
                'approved_at'     => now(),
                'delivery_period' => $nextStatus === 'verified_purchasing_department'
                    ? ($data['delivery_period'] ?? $pr->delivery_period)
                    : $pr->delivery_period,
                'payment_terms' => $nextStatus === 'verified_purchasing_department'
                    ? ($data['payment_terms'] ?? $pr->payment_terms)
                    : $pr->payment_terms,
                'site_contact_user_id' => $nextStatus === 'verified_purchasing_department'
                    ? ($data['site_contact_user_id'] ?? $pr->site_contact_user_id)
                    : $pr->site_contact_user_id,
                'remark_log' => $this->appendRemarkLog(
                    existing: $pr->remark_log,
                    fromStatus: $fromStatus,
                    toStatus: $nextStatus,
                    remark: $data['remark'],
                    userId: auth()->id(),
                    userName: (string) (auth()->user()?->name ?? 'System'),
                ),
            ]);

            return $this->approvalSuccessResponse(
                $request,
                'Purchase request verified'
            );
        }

        if ($data['status'] === 'approved') {
            if ($pr->status !== 'verified_purchasing_department') {
                abort(422, 'Only purchasing-verified PR can be CEO approved.');
            }

            if ($pr->purchaseOrder) {
                abort(422, 'Purchase order already created for this PR.');
            }

            $quotationId = (int) ($data['quotation_id'] ?? $pr->approved_quotation_id);
            if ($quotationId <= 0) {
                abort(422, 'No approved quotation selected for PO generation.');
            }

            $quotation = PurchaseQuotation::where('id', $quotationId)->firstOrFail();

            $isSubConRequest = (bool) $pr->is_subcon_purchase_request;
            $deliveryPeriod = trim((string) ($data['delivery_period'] ?? $pr->delivery_period ?? ''));
            $paymentTerms = trim((string) ($data['payment_terms'] ?? $pr->payment_terms ?? ''));
            $siteContactUserId = (int) ($data['site_contact_user_id'] ?? $pr->site_contact_user_id ?? 0);
            if ((!$isSubConRequest && $deliveryPeriod === '') || $paymentTerms === '' || $siteContactUserId <= 0) {
                throw ValidationException::withMessages([
                    'delivery_period' => $isSubConRequest
                        ? 'Missing purchasing verification terms. Please complete terms & condition and site contact person first.'
                        : 'Missing purchasing verification terms. Please complete verify-to-purchasing step first.',
                ]);
            }

            $items = $pr->items->map(fn ($item) => [
                'item_name' => $item->title,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_amount' => $item->quantity * $item->unit_price,
            ])->toArray();

            $po = $prToPo->createPO($pr, $quotation->supplier_id, $items, [
                'terms'  => $quotation->terms ?? null,
                'remark' => 'Auto-created from approved PR',
                'delivery_period' => $deliveryPeriod,
                'payment_terms' => $paymentTerms,
                'site_contact_user_id' => $siteContactUserId,
            ]);

            $pr->update([
                'status'                => 'po',
                'reviewer_remark'       => $fromStatus === 'verified_purchasing_department'
                    ? $data['remark']
                    : $pr->reviewer_remark,
                'reviewer'              => auth()->id(),
                'approved_at'           => now(),
                'approved_quotation_id' => $quotation->id,
                'remark_log' => $this->appendRemarkLog(
                    existing: $pr->remark_log,
                    fromStatus: $fromStatus,
                    toStatus: 'po',
                    remark: $data['remark'],
                    userId: auth()->id(),
                    userName: (string) (auth()->user()?->name ?? 'System'),
                ),
            ]);

            return $this->approvalSuccessResponse(
                $request,
                'Purchase request CEO approved and purchase order created',
                ['po_id' => $po->id]
            );
        }

        if ($pr->status !== 'verified_purchasing_department') {
            abort(422, 'Only purchasing-verified PR can be converted to PO.');
        }

        if ($pr->purchaseOrder) {
            abort(422, 'Purchase order already created for this PR.');
        }

        $quotationId = (int) ($data['quotation_id'] ?? $pr->approved_quotation_id);
        if ($quotationId <= 0) {
            abort(422, 'No approved quotation selected for PO generation.');
        }

        $quotation = PurchaseQuotation::where('id', $quotationId)->firstOrFail();

        $isSubConRequest = (bool) $pr->is_subcon_purchase_request;
        $deliveryPeriod = trim((string) ($data['delivery_period'] ?? $pr->delivery_period ?? ''));
        $paymentTerms = trim((string) ($data['payment_terms'] ?? $pr->payment_terms ?? ''));
        $siteContactUserId = (int) ($data['site_contact_user_id'] ?? $pr->site_contact_user_id ?? 0);
        if ((!$isSubConRequest && $deliveryPeriod === '') || $paymentTerms === '' || $siteContactUserId <= 0) {
            throw ValidationException::withMessages([
                'delivery_period' => $isSubConRequest
                    ? 'Missing purchasing verification terms. Please complete terms & condition and site contact person first.'
                    : 'Missing purchasing verification terms. Please complete verify-to-purchasing step first.',
            ]);
        }

        $items = $pr->items->map(fn ($item) => [
            'item_name' => $item->title,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_amount' => $item->quantity * $item->unit_price,
        ])->toArray();

        $po = $prToPo->createPO($pr, $quotation->supplier_id, $items, [
            'terms'  => $quotation->terms ?? null,
            'remark' => 'Auto-created from approved PR',
            'delivery_period' => $deliveryPeriod,
            'payment_terms' => $paymentTerms,
            'site_contact_user_id' => $siteContactUserId,
        ]);

        $pr->update([
            'status' => 'po',
            'reviewer_remark' => $fromStatus === 'verified_purchasing_department'
                ? $data['remark']
                : $pr->reviewer_remark,
            'approved_quotation_id' => $quotation->id,
            'remark_log' => $this->appendRemarkLog(
                existing: $pr->remark_log,
                fromStatus: $fromStatus,
                toStatus: 'po',
                remark: $data['remark'],
                userId: auth()->id(),
                userName: (string) (auth()->user()?->name ?? 'System'),
            ),
        ]);

        return $this->approvalSuccessResponse(
            $request,
            'Purchase order created',
            ['po_id' => $po->id]
        );
    }

    public function destroy(Request $request, string $uuid)
    {
        DB::transaction(function () use ($uuid, $request) {

            $pr = PurchaseRequest::where('uuid', $uuid)
                ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                    $q->where('branch_id', $this->activeBranchId($request))
                )
                ->firstOrFail();

            if ($pr->status !== 'draft') {
                abort(403, 'Only draft purchase requests can be deleted');
            }

            $pr->items()->delete();

            $pr->quotations()->detach();

            $pr->delete();
        });

        return back()->with('success', 'Purchase request deleted');
    }

    private function activeBranchId(Request $request): int
    {
        $branchId = (int) ($request->user()?->active_branch_id ?? 0);
        if ($branchId <= 0) {
            abort(422, 'Please select an active branch before proceeding.');
        }

        return $branchId;
    }

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
    }

    private function isEditableStatus(string $status): bool
    {
        return in_array($status, ['draft', 'verified_own_department', 'verified_project_department', 'verified_purchasing_department'], true);
    }

    private function appendRemarkLog(
        mixed $existing,
        string $fromStatus,
        string $toStatus,
        ?string $remark,
        ?int $userId,
        string $userName,
    ): array {
        $log = is_array($existing) ? $existing : [];

        $log[] = [
            'from' => $fromStatus,
            'to' => $toStatus,
            'remark' => $remark,
            'user_id' => $userId,
            'user_name' => $userName,
            'at' => now()->toDateTimeString(),
        ];

        return $log;
    }

    private function approvalSuccessResponse(Request $request, string $message, array $extra = [])
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(array_merge([
                'status' => 'success',
                'message' => $message,
            ], $extra));
        }

        return back()->with(array_merge([
            'success' => $message,
        ], $extra));
    }
}
