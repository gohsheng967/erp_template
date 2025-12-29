<?php

namespace App\Http\Controllers\Transactions;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\PurchaseQuotation;
use App\Models\Project;
use App\Models\CompanyProfile;
use App\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Services\RunningNumberService;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use DB;

class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'draft');

        $filters = [
            'search' => $request->get('search'),
            'from'   => $request->get('from'),
            'to'     => $request->get('to'),
        ];

        $baseQuery = PurchaseRequest::query()
            ->with([
                'requester',
                'approver',
            ])
            ->withCount([
                'items',
                'quotations',
            ])
            ->withSum('items as total_amount', 'total_price')
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
            );

        $purchaseRequests = [
            'draft' => (clone $baseQuery)
                ->where('status', 'draft')
                ->latest()
                ->paginate(10),

            'submitted' => (clone $baseQuery)
                ->where('status', 'submitted')
                ->latest('updated_at')
                ->paginate(10),

            'approved' => (clone $baseQuery)
                ->where('status', 'approved')
                ->latest('updated_at')
                ->paginate(10),

            'rejected' => (clone $baseQuery)
                ->where('status', 'rejected')
                ->latest('updated_at')
                ->paginate(10),
        ];

        $counts = [
            'submitted' => (clone $baseQuery)
                ->where('status', 'submitted')
                ->count(),

            'approved' => (clone $baseQuery)
                ->where('status', 'approved')
                ->count(),
        ];

        return Inertia::render('Transactions/PurchaseRequest/Index', [
            'purchaseRequests' => $purchaseRequests,
            'counts'           => $counts,
            'filters'          => $filters,
            'activeTab'        => $tab,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'purpose'          => 'nullable|string',
            'department_id'    => 'nullable|exists:departments,id',
            'project_id'       => 'nullable|exists:projects,id',
            'required_date'    => 'required|date',
            'requester_remark' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data) {
            PurchaseRequest::create([
                'code'             => null,
                'title'            => $data['title'],
                'purpose'          => $data['purpose'] ?? null,
                'required_date'    => $data['required_date'] ?? null,
                'department_id'    => $data['department_id'] ?? null,
                'project_id'       => $data['project_id'] ?? null,
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

        $departmentIds = $departments->pluck('id');

        $prQuery = PurchaseRequest::query()
            ->where('status', 'draft')
            ->whereIn('department_id', $departmentIds);

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

        $projects = Project::where('status', 'active')
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

                'requested_by' => auth()->id(),
                'status' => 'draft',
            ]);

        } else {

            $pr = PurchaseRequest::whereIn('department_id', $userDepartmentIds)
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

    public function edit(string $uuid)
    {
        $pr = PurchaseRequest::with([
                'items',
                'quotations',
                'quotations.supplier',
                'quotations.attachment',
                'approvedQuotation',
                'requester',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Transactions/PurchaseRequest/Edit', [
            'pr' => $pr,

            // dropdowns for basic info
            'departments' => Department::select('id', 'name')->get(),
            'projects' => Project::select('id', 'name')->get(),
        ]);
    }

    public function quotations(string $prUuid, string $supplierUuid)
    {
        $pr = PurchaseRequest::where('uuid', $prUuid)->firstOrFail();
        $supplier = Supplier::where('uuid', $supplierUuid)->firstOrFail();

        return PurchaseQuotation::query()
            ->where('supplier_id', $supplier->id)
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

    public function attachQuotation(Request $request, string $uuid)
    {
        $pr = PurchaseRequest::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'supplier_uuid' => ['required', 'exists:suppliers,uuid'],

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

        $supplier = Supplier::where('uuid', $data['supplier_uuid'])->firstOrFail();

        DB::transaction(function () use ($data, $request, $pr, $supplier) {

            if (!empty($data['quotation_id'])) {
                $quotation = PurchaseQuotation::findOrFail($data['quotation_id']);
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

    public function detachQuotation(string $uuid, string $quotationUuid)
    {
        $pr = PurchaseRequest::where('uuid', $uuid)->firstOrFail();

        $quotation = PurchaseQuotation::where('uuid', $quotationUuid)->firstOrFail();

        if ($pr->status !== 'draft') {
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
        $purchaseRequest = PurchaseRequest::where('uuid', $uuid)->firstOrFail();

        if ($purchaseRequest->status !== 'draft') {
            abort(403, 'Only draft PR can be edited');
        }

        /* =========================
        2️⃣ VALIDATE PAYLOAD
        ========================== */
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'project_id' => 'nullable|exists:projects,id',
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
        $purchaseRequest = PurchaseRequest::where('uuid', $uuid)->firstOrFail();

        if ($purchaseRequest->status !== 'draft') {
            abort(403, 'PR already submitted');
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
        ]);

        $purchaseRequest->update($header);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:255',
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

        $purchaseRequest->update([
            'code'   => RunningNumberService::next(documentType: 'purchase_request'),
            'status' => 'submitted',
            'submitted_at' => now(),
            'requested_by' => auth()->id(),
        ]);

        return redirect()
            ->route('purchase-request.index', $purchaseRequest->uuid)
            ->with('success', 'Purchase Request submitted');
    }

    public function show(string $uuid)
    {
        $request = PurchaseRequest::query()
            ->with([
                'department',
                'requester',
                'approver',
                'items',
                'quotations.attachment'
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $company = CompanyProfile::first();

        return response()->json([
            'request' => $request,
            'company' => $company
        ]);
    }

    public function approval(Request $request, string $uuid)
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'remark' => ['nullable', 'string'],

            // 👇 required only when approving
            'quotation_id' => [
                'nullable',
                'required_if:status,approved',
                'exists:purchase_quotations,id',
            ],
        ]);

        $pr = PurchaseRequest::where('uuid', $uuid)->firstOrFail();

        if ($pr->status !== 'submitted') {
            abort(422, 'Purchase request not in submitted state');
        }

        if ($data['status'] === 'approved') {
            $quotationId = $data['quotation_id'];

            $belongs = $pr->quotations()
                ->where('purchase_quotations.id', $quotationId)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'quotation_id' => 'Invalid quotation selected for this purchase request.',
                ]);
            }
        }

        $pr->update([
            'status'                => $data['status'],
            'reviewer_remark'       => $data['remark'],
            'approver_id'           => auth()->id(),
            'approved_at'           => now(),

            'approved_quotation_id' =>
                $data['status'] === 'approved'
                    ? $data['quotation_id']
                    : null,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' =>
                $data['status'] === 'approved'
                    ? 'Purchase request approved'
                    : 'Purchase request rejected',
        ]);
    }

    public function destroy(string $uuid)
    {
        DB::transaction(function () use ($uuid) {

            $pr = PurchaseRequest::where('uuid', $uuid)->firstOrFail();

            if ($pr->status !== 'draft') {
                abort(403, 'Only draft purchase requests can be deleted');
            }

            $pr->items()->delete();

            $pr->quotations()->detach();

            $pr->delete();
        });

        return back()->with('success', 'Purchase request deleted');
    }

}
