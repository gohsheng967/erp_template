<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Models\Claim;
use App\Models\Project;
use App\Models\Attachment;
use App\Models\ClaimItem;
use App\Models\ClaimType;
use App\Models\CompanyProfile;
use App\Models\PaymentSlip;

use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\RunningNumberService;
use App\Services\AttachmentService;

class ClaimsController extends Controller
{
    public function index(Request $request)
    {
        $allowedTabs = ['draft', 'submitted', 'checked', 'verified', 'approved', 'payment', 'rejected'];
        $tab = in_array($request->tab, $allowedTabs, true)
            ? $request->tab
            : 'submitted';

        $from = $request->from
            ?? Carbon::now()->subMonth()->toDateString();

        $to = $request->to
            ?? Carbon::now()->toDateString();

        $search = $request->search;

        /*
        |--------------------------------------------------------------------------
        | BASE FILTER (NO ORDER BY)
        |--------------------------------------------------------------------------
        */
        $filterQuery = Claim::query()
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('claims.branch_id', $this->activeBranchId($request))
            )
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('claim_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('issuer', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($from, fn ($q) =>
                $q->whereDate('claims.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('claims.created_at', '<=', $to)
            );

        /*
        |--------------------------------------------------------------------------
        | LIST QUERY (WITH ORDER BY)
        |--------------------------------------------------------------------------
        */
        $listQuery = (clone $filterQuery)
            ->with([
                'project:id,name',
                'issuer:id,name',
            ])
            ->withCount('items')
            ->withSum('items', 'amount')
            ->orderByDesc('claims.created_at');

        $draft       = (clone $listQuery)->where('status', 'draft')->paginate(15)->withQueryString();
        $submitted   = (clone $listQuery)->where('status', 'submitted')->paginate(15)->withQueryString();
        $checked     = (clone $listQuery)->where('status', 'checked')->paginate(15)->withQueryString();
        $verified    = (clone $listQuery)->where('status', 'verified')->paginate(15)->withQueryString();
        $approved    = (clone $listQuery)->where('status', 'approved')->paginate(15)->withQueryString();
        $paymentMade = (clone $listQuery)
            ->where(function ($q) {
                $q->whereIn('status', ['ceo_approved', 'paid']);
            })
            ->paginate(15)
            ->withQueryString();
        $rejected    = (clone $listQuery)->where('status', 'rejected')->paginate(15)->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | COUNTS (GROUP BY — NO ORDER BY)
        |--------------------------------------------------------------------------
        */
        $countsRaw = (clone $filterQuery)
            ->whereIn('status', ['submitted', 'checked', 'verified', 'approved', 'ceo_approved'])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'submitted' => (int) ($countsRaw['submitted'] ?? 0),
            'checked'   => (int) ($countsRaw['checked'] ?? 0),
            'verified'  => (int) ($countsRaw['verified'] ?? 0),
            'approved'  => (int) ($countsRaw['approved'] ?? 0),
            'payment'   => (int) ($countsRaw['ceo_approved'] ?? 0),
        ];

        /*
        |--------------------------------------------------------------------------
        | DONUT — BY PROJECT
        |--------------------------------------------------------------------------
        */
        $projectDonutRaw = (clone $filterQuery)
            ->leftJoin('projects', 'projects.id', '=', 'claims.project_id')
            ->when($tab === 'payment', function ($q) {
                $q->whereIn('claims.status', ['ceo_approved', 'paid']);
            }, fn ($q) => $q->where('claims.status', $tab))
            ->select(
                DB::raw('COALESCE(claims.project_id, 0) as project_key'),
                DB::raw('SUM(claims.total_amount) as total')
            )
            ->groupBy('project_key')
            ->get();

        $projectNames = Project::whereIn(
                'id',
                $projectDonutRaw->pluck('project_key')->filter()
            )
            ->pluck('name', 'id');

        $donutByProject = [];

        foreach ($projectDonutRaw as $row) {
            $donutByProject[] = [
                'label' => $row->project_key == 0
                    ? 'Others'
                    : ($projectNames[$row->project_key] ?? 'Unknown'),
                'amount' => (float) $row->total,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | DONUT — BY CATEGORY
        |--------------------------------------------------------------------------
        */
        $donutByCategory = DB::table('claim_items')
            ->join('claims', 'claims.id', '=', 'claim_items.claim_id')
            ->leftJoin('claim_types', 'claim_types.code', '=', 'claim_items.claim_type')
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('claims.branch_id', $this->activeBranchId($request))
            )
            ->when($from, fn ($q) =>
                $q->whereDate('claims.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('claims.created_at', '<=', $to)
            )
            ->when($tab === 'payment', function ($q) {
                $q->whereIn('claims.status', ['ceo_approved', 'paid']);
            }, fn ($q) => $q->where('claims.status', $tab))
            ->select(
                DB::raw('COALESCE(claim_types.name, claim_items.claim_type) as label'),
                DB::raw('SUM(claim_items.amount) as amount')
            )
            ->groupBy(DB::raw('COALESCE(claim_types.name, claim_items.claim_type)'))
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'label'  => $row->label,
                'amount' => (float) $row->amount,
            ]);

        /*
        |--------------------------------------------------------------------------
        | PROJECT LIST
        |--------------------------------------------------------------------------
        */
        $projects = Project::query()
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Transactions/Claims/Index', [
            'claims' => [
                'draft'     => $draft,
                'submitted' => $submitted,
                'checked'   => $checked,
                'verified'  => $verified,
                'approved'  => $approved,
                'payment'   => $paymentMade,
                'rejected'  => $rejected,
            ],

            'filters' => [
                'search' => $search,
                'from'   => $from,
                'to'     => $to,
            ],

            'counts' => $counts,

            'donut' => [
                'by_project'  => $donutByProject,
                'by_category' => $donutByCategory,
            ],

            'activeTab' => $tab,
            'projects'  => $projects,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id'   => ['nullable', 'exists:projects,id'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'title'=> ['required', 'max:255']
        ]);

        if (!empty($validated['project_id'])) {
            $projectQuery = Project::query()->where('id', $validated['project_id']);
            if ($this->shouldScopeToActiveBranch($request)) {
                $projectQuery->where('branch_id', $this->activeBranchId($request));
            }
            $projectQuery->firstOrFail();
        }

        $claim = Claim::create([
            'user_id'      => $request->user()->id,
            'title'        => $validated['title'],
            'project_id'   => $validated['project_id'] ?? null,
            'branch_id'    => $this->activeBranchId($request),
            'total_amount' => $validated['total_amount'],
            'status'       => 'draft',
        ]);

        return redirect()->route('claims.edit', $claim->uuid);
    }

    public function destroy(Request $request, string $uuid)
    {
        $claim = $this->claimByUuidOrFail($request, $uuid);

        if ($claim->status !== 'draft') {
            abort(403);
        }

        $claim->delete();

        return back();
    }

    public function edit(Request $request, string $uuid)
    {
        $claim = Claim::where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->with([
                'items.attachments', // ✅ REQUIRED
            ])
            ->firstOrFail();

        if ($claim->status !== 'draft') {
            abort(403, 'Claim is no longer editable');
        }

        $claimTypes = ClaimType::query()
            ->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();

        $existingCodes = $claim->items
            ->pluck('claim_type')
            ->filter()
            ->unique()
            ->values();

        if ($existingCodes->isNotEmpty()) {
            $existingTypes = ClaimType::withTrashed()
                ->whereIn('code', $existingCodes)
                ->get(['code', 'name', 'deleted_at']);

            foreach ($existingTypes as $type) {
                if (!isset($claimTypes[$type->code])) {
                    $label = $type->name;
                    if ($type->deleted_at) {
                        $label .= ' (Deleted)';
                    }
                    $claimTypes[$type->code] = $label;
                }
            }
        }

        return inertia('Transactions/Claims/Edit', [
            'claim' => $claim,
            'claimTypes' => $claimTypes,
        ]);
    }

    public function update(Request $request, string $uuid)
    {
        $claim = $this->claimByUuidOrFail($request, $uuid);

        /* ------------------------------------------------
        Lock enforcement
        ------------------------------------------------ */
        if ($claim->status !== 'draft') {
            abort(403, 'This claim is locked and cannot be edited.');
        }

        /* ------------------------------------------------
        Base validation (draft-safe)
        ------------------------------------------------ */
        $baseRules = [
            'status' => ['required', Rule::in(['draft', 'submitted'])],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.receipt_no' => ['nullable', 'string', 'max:100'],
            'items.*.receipt_date' => ['nullable', 'date', 'before_or_equal:today'],
            'items.*.claim_type' => ['nullable', 'string', 'max:255'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.attachment' => ['nullable', 'array'],
            'items.*.attachment.*' => ['nullable', 'file', 'max:10240'],

            // 🔴 MUST KEEP THESE
            'items.*._existing_attachments' => ['nullable', 'array'],
            'items.*._existing_attachments.*' => ['integer'],
        ];

        /* ------------------------------------------------
        Submit-only validation
        ------------------------------------------------ */
        $submitRules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.receipt_no' => ['nullable', 'string', 'max:100'],
            'items.*.receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'items.*.claim_type' => [
                'required',
                Rule::exists('claim_types', 'code')->whereNull('deleted_at'),
            ],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],

            // 🔴 MUST KEEP THESE (submit too)
            'items.*._existing_attachments' => ['nullable', 'array'],
            'items.*._existing_attachments.*' => ['integer'],

            'total_amount' => ['required', 'numeric', 'min:0.01'],
        ];

        $rules = $request->status === 'submitted'
            ? array_merge($baseRules, $submitRules)
            : $baseRules;

        $validated = $request->validate($rules);

        /* ------------------------------------------------
        Total vs item sum check (submit only)
        ------------------------------------------------ */
        if ($validated['status'] === 'submitted') {
            $itemsSum = collect($validated['items'])
                ->sum(fn ($item) => (float) ($item['amount'] ?? 0));

            if (bccomp((string) $itemsSum, (string) $validated['total_amount'], 2) !== 0) {
                throw ValidationException::withMessages([
                    'total_amount' =>
                        'Total amount must equal the sum of all item amounts.',
                ]);
            }
        }

        /* ------------------------------------------------
        Transaction
        ------------------------------------------------ */
        DB::transaction(function () use ($claim, $validated, $request) {

            /* -------- Generate claim number on submit -------- */
            if ($validated['status'] === 'submitted') {
                if (!$claim->claim_no) {
                    $claim->claim_no = RunningNumberService::next(documentType: 'claim');
                }

                // Re-submission should refresh submission time and clear downstream signatures.
                $claim->submitted_at = now();
                $claim->checked_by = null;
                $claim->checked_at = null;
                $claim->approved_by = null;
                $claim->approved_at = null;
            }

            /* -------- Update claim header -------- */
            $claim->update([
                'status' => $validated['status'],
                'total_amount' => $validated['total_amount'] ?? 0,
            ]);

            $keptItemIds = [];
            $itemsPayload = $validated['items'] ?? null;
            $hasItemArrayPayload = is_array($itemsPayload);

            foreach (($itemsPayload ?? []) as $index => $item) {

                /* -------- Update or create item -------- */
                if (!empty($item['id'])) {
                    $claimItem = $claim->items()
                        ->where('id', $item['id'])
                        ->firstOrFail();

                    $claimItem->update([
                        'title'        => $item['title'] ?? null,
                        'description'  => $item['description'] ?? null,
                        'receipt_no'   => $item['receipt_no'] ?? null,
                        'receipt_date' => $item['receipt_date'] ?? null,
                        'claim_type'   => $item['claim_type'] ?? null,
                        'amount'       => $item['amount'] ?? 0,
                    ]);
                } else {
                    $claimItem = $claim->items()->create([
                        'title'        => $item['title'] ?? null,
                        'description'  => $item['description'] ?? null,
                        'receipt_no'   => $item['receipt_no'] ?? null,
                        'receipt_date' => $item['receipt_date'] ?? null,
                        'claim_type'   => $item['claim_type'] ?? null,
                        'amount'       => $item['amount'] ?? 0,
                    ]);
                }

                $keptItemIds[] = $claimItem->id;

                /* -------- Attachment handling -------- */
                $keepAttachmentIds = $item['_existing_attachments'] ?? [];

                /* Delete removed attachments (DB + disk) */
                Attachment::where('attachable_type', ClaimItem::class)
                    ->where('attachable_id', $claimItem->id)
                    ->whereNotIn('id', $keepAttachmentIds)
                    ->each(function ($attachment) {
                        Storage::disk('public')->delete($attachment->file_path);
                        $attachment->delete();
                    });

                /* Re-attach kept attachments */
                if (!empty($keepAttachmentIds)) {
                    Attachment::whereIn('id', $keepAttachmentIds)->update([
                        'attachable_type' => ClaimItem::class,
                        'attachable_id'   => $claimItem->id,
                    ]);
                }

                /* -------- New uploads -------- */
                $newFiles = Arr::wrap($request->file("items.$index.attachment"));

                /* Submit-only attachment rules */
                if ($validated['status'] === 'submitted') {
                    $existingCount = count($keepAttachmentIds);
                    $newCount = count(array_filter($newFiles));

                    if ($existingCount + $newCount < 1) {
                        throw ValidationException::withMessages([
                            "items.$index.attachment" =>
                                'At least one attachment is required.',
                        ]);
                    }

                    if ($existingCount + $newCount > 3) {
                        throw ValidationException::withMessages([
                            "items.$index.attachment" =>
                                'Maximum 3 attachments allowed.',
                        ]);
                    }
                }

                foreach ($newFiles as $file) {
                    if (!$file) continue;

                    AttachmentService::store(
                        file: $file,
                        attachable: $claimItem,
                        disk: 'public'
                    );
                }
            }

            /* -------- Delete removed items (and their attachments) -------- */
            // Only sync-remove when client actually sends item array payload.
            // This prevents accidental full item wipe when draft save omits items.
            if ($hasItemArrayPayload) {
                $claim->items()
                    ->whereNotIn('id', $keptItemIds)
                    ->each(function ($item) {
                        $item->attachments()->each(function ($attachment) {
                            Storage::disk('public')->delete($attachment->file_path);
                            $attachment->delete();
                        });
                        $item->delete();
                    });
            }
        });

        return redirect()
            ->route('claims.index')
            ->with(
                'success',
                $validated['status'] === 'submitted'
                    ? 'Claim submitted successfully.'
                    : 'Draft saved successfully.'
            );
    }

    public function show(Request $request, string $uuid)
    {
        $claim = Claim::where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->with([
                'items.attachments',
                'issuer',
                'checker',
                'approver',
                'payer',
                'project',
                'items',
                'attachments',
                'paymentSlip.attachments',
                'paymentSlip.companyBankAccount',
            ])
            ->firstOrFail();

        $company = CompanyProfile::first();

        return response()->json([
            'claim'   => $claim,
            'company' => $company,
        ]);
    }

    public function approval(Request $request, string $uuid)
    {
        // =========================
        // VALIDATION
        // =========================
        $request->validate([
            'status' => 'required|in:draft,checked,verified,approved,ceo_approved,rejected',
            'remark' => 'nullable|string|max:1000',
        ]);

        // =========================
        // LOCK CLAIM (ANTI DOUBLE-SUBMIT)
        // =========================
        $claim = Claim::where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->lockForUpdate()
            ->firstOrFail();

        // =========================
        // SAFETY CHECK
        // =========================
        $allowedTransitions = [
            'submitted' => ['draft', 'checked'],
            'checked'   => ['draft', 'verified', 'rejected'],
            'verified'  => ['draft', 'approved', 'rejected'],
            'approved'  => ['draft', 'ceo_approved', 'rejected'],
        ];

        $current = (string) $claim->status;
        $next = (string) $request->status;

        if (!in_array($next, $allowedTransitions[$current] ?? [], true)) {
            abort(403, 'Invalid claim status transition.');
        }

        // =========================
        // TRANSACTION
        // =========================
        DB::transaction(function () use ($claim, $request) {

            $fromStatus = (string) $claim->status;
            $claim->status = $request->status;

            if ($request->status === 'draft') {
                $claim->checked_by = null;
                $claim->checked_at = null;
                $claim->approved_by = null;
                $claim->approved_at = null;
            }

            if ($request->status === 'checked') {
                $claim->checked_by = auth()->id();
                $claim->checked_at = now();
            }

            if ($request->status === 'approved') {
                $claim->approved_by = null;
                $claim->approved_at = null;
            }

            if ($request->status === 'ceo_approved') {
                $claim->approved_by = auth()->id();
                $claim->approved_at = now();
            }

            $claim->remark_log = $this->appendRemarkLog(
                existing: $claim->remark_log,
                fromStatus: $fromStatus,
                toStatus: (string) $request->status,
                remark: $request->remark,
                userId: auth()->id(),
                userName: (string) (auth()->user()?->name ?? 'System'),
            );

            $claim->save();
        });

        // =========================
        // RESPONSE
        // =========================
        return response()->json([
            'success' => true,
            'status'  => $claim->status,
        ]);
    }

    public function markPaid(Request $request, string $uuid)
    {
        $request->validate([
            'payment_ref' => 'required|string|max:255',
            'payment_slip_id' => 'required|exists:payment_slips,id',
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        return DB::transaction(function () use ($request, $uuid) {

            // Lock row to prevent double payment
            $claim = Claim::where('uuid', $uuid)
                ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                    $q->where('branch_id', $this->activeBranchId($request))
                )
                ->lockForUpdate()
                ->firstOrFail();

            /* =========================
            FLOW GUARD
            ========================== */

            if ($claim->status !== 'ceo_approved') {
                abort(422, 'Only CEO-approved claims can be marked as paid.');
            }

            $slip = PaymentSlip::where('id', $request->payment_slip_id)
                ->where('source_type', Claim::class)
                ->where('source_id', $claim->id)
                ->firstOrFail();

            if (!$slip->company_bank_account_id) {
                abort(422, 'Company bank account is required before payment.');
            }

            foreach ($request->file('attachments', []) as $file) {
                AttachmentService::store($file, $slip);
            }

            $claim->update([
                'status'       => 'paid',
                'payment_ref_no'  => $request->payment_ref,
                'payment_slip_no' => $claim->payment_slip_no ?? $slip->slip_no,
                'company_bank_account_id' => $claim->company_bank_account_id ?? $slip->company_bank_account_id,
                'paid_at'      => now(),
                'paid_by'      => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
            ]);
        });
    }

    public function paymentSlip(Request $request, string $uuid)
    {
        $request->validate([
            'company_bank_account_id' => [
                'required',
                Rule::exists('company_bank_accounts', 'id')->where(function ($query) use ($request) {
                    $query
                        ->where('status', 'active')
                        ->where('branch_id', (int) ($request->user()?->active_branch_id ?? 0));
                }),
            ],
            'less_retention' => ['nullable', 'numeric', 'min:0'],
            'less_recoupment' => ['nullable', 'numeric', 'min:0'],
            'less_material_ob' => ['nullable', 'numeric', 'min:0'],
            'less_paid_previously' => ['nullable', 'numeric', 'min:0'],
            'payment_slip_remark' => ['nullable', 'string', 'max:255'],
        ]);

        $claim = $this->claimByUuidOrFail($request, $uuid);

        if ($claim->status !== 'ceo_approved') {
            abort(422, 'Only CEO-approved claims can generate a payment slip.');
        }

        $slip = $claim->paymentSlip ?? new PaymentSlip();
        if (!$slip->exists) {
            $slip->slip_no = RunningNumberService::next('payment_slip');
            $slip->source()->associate($claim);
        } elseif ($slip->cancelled_at) {
            $slip->slip_no = RunningNumberService::next('payment_slip');
            $slip->cancelled_at = null;
            $slip->cancelled_by = null;
            $slip->cancel_reason = null;
        }

        $slip->company_bank_account_id = $request->company_bank_account_id;
        $slip->amount = $claim->total_amount;
        $slip->payment_date = $claim->approved_at ?? now()->toDateString();
        $slip->less_retention = $request->input('less_retention');
        $slip->less_recoupment = $request->input('less_recoupment');
        $slip->less_material_ob = $request->input('less_material_ob');
        $slip->less_paid_previously = $request->input('less_paid_previously');
        $slip->payment_slip_remark = $request->input('payment_slip_remark');
        $slip->created_by = $request->user()->id;
        $slip->save();

        $claim->payment_slip_no = $slip->slip_no;
        $claim->company_bank_account_id = $request->company_bank_account_id;
        $claim->save();

        $slip->load([
            'companyBankAccount',
            'source.project',
            'source.issuer:id,name',
            'source.approver:id,name',
            'source.payer:id,name',
        ]);

        return response()->json([
            'slip' => $slip,
        ]);
    }

    private function activeBranchId(Request $request): int
    {
        $branchId = (int) ($request->user()?->active_branch_id ?? 0);

        if ($branchId <= 0) {
            abort(422, 'Please select an active branch before proceeding.');
        }

        return $branchId;
    }

    private function claimByUuidOrFail(Request $request, string $uuid): Claim
    {
        return Claim::query()
            ->where('uuid', $uuid)
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->firstOrFail();
    }

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
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

}

