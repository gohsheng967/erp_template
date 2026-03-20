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
use App\Models\User;

use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\RunningNumberService;
use App\Services\AttachmentService;

class ClaimsController extends Controller
{
    private const ALLOWED_TABS = [
        'my_in_progress',
        'my_rejected',
        'my_completed',
        'all_non_draft',
        Claim::STATUS_DRAFT,
        Claim::STATUS_SUBMITTED,
        Claim::STATUS_CHECKED,
        Claim::STATUS_VERIFIED,
        Claim::STATUS_APPROVED,
        'payment',
        Claim::STATUS_REJECTED,
    ];

    private const APPROVAL_TRANSITIONS = [
        Claim::STATUS_SUBMITTED => [Claim::STATUS_DRAFT, Claim::STATUS_CHECKED],
        Claim::STATUS_CHECKED   => [Claim::STATUS_DRAFT, Claim::STATUS_VERIFIED, Claim::STATUS_REJECTED],
        Claim::STATUS_VERIFIED  => [Claim::STATUS_DRAFT, Claim::STATUS_CEO_APPROVED, Claim::STATUS_REJECTED],
        // Keep legacy approved claims transition-safe.
        Claim::STATUS_APPROVED  => [Claim::STATUS_DRAFT, Claim::STATUS_CEO_APPROVED, Claim::STATUS_REJECTED],
    ];

    public function index(Request $request)
    {
        $tab = in_array($request->tab, self::ALLOWED_TABS, true)
            ? $request->tab
            : Claim::STATUS_SUBMITTED;

        $from = $request->from
            ?? Carbon::now()->subMonth()->toDateString();

        $to = $request->to
            ?? Carbon::now()->toDateString();

        $search = $request->search;
        $projectId = $request->filled('project_id') ? (int) $request->project_id : null;
        $issuerId = $request->filled('issuer_id') ? (int) $request->issuer_id : null;
        $amountMin = $request->filled('amount_min') ? (float) $request->amount_min : null;
        $amountMax = $request->filled('amount_max') ? (float) $request->amount_max : null;
        $paymentState = in_array($request->payment_state, ['pending', 'paid'], true)
            ? $request->payment_state
            : 'all';

        /*
        |--------------------------------------------------------------------------
        | BASE FILTER (NO ORDER BY)
        |--------------------------------------------------------------------------
        */
        $filterQuery = Claim::query()
            ->where(function ($q) {
                $q->whereNull('claims.remark')
                    ->orWhereRaw(
                        'LOWER(TRIM(claims.remark)) <> ?',
                        [strtolower(trim(Claim::REMARK_PETTY_CASH_ORIGIN))]
                    );
            })
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
            ->when($projectId, fn ($q) =>
                $q->where('claims.project_id', $projectId)
            )
            ->when($issuerId, fn ($q) =>
                $q->where('claims.user_id', $issuerId)
            )
            ->when($from, fn ($q) =>
                $q->whereDate('claims.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('claims.created_at', '<=', $to)
            )
            ->when($amountMin !== null, fn ($q) =>
                $q->where('claims.total_amount', '>=', $amountMin)
            )
            ->when($amountMax !== null, fn ($q) =>
                $q->where('claims.total_amount', '<=', $amountMax)
            );

        /*
        |--------------------------------------------------------------------------
        | LIST QUERY (WITH ORDER BY)
        |--------------------------------------------------------------------------
        */
        $listQuery = (clone $filterQuery)
            ->withTrashed()
            ->with([
                'project:id,name',
                'issuer:id,name',
            ])
            ->withCount('items')
            ->withSum('items', 'amount')
            ->orderByDesc('claims.created_at');

        $myBaseQuery = (clone $listQuery)
            ->where('claims.user_id', (int) auth()->id());

        $draft       = (clone $listQuery)
            ->whereNull('claims.deleted_at')
            ->where('status', Claim::STATUS_DRAFT)
            ->paginate(15)
            ->withQueryString();
        $allNonDraft = (clone $listQuery)
            ->where(function ($q) {
                $q->where('status', '!=', Claim::STATUS_DRAFT)
                    ->orWhereNotNull('claims.deleted_at');
            })
            ->paginate(15)
            ->withQueryString();
        $myInProgress = (clone $myBaseQuery)
            ->whereNull('claims.deleted_at')
            ->whereNotIn('status', [Claim::STATUS_REJECTED, Claim::STATUS_PAID])
            ->paginate(15)
            ->withQueryString();
        $myRejected = (clone $myBaseQuery)
            ->where(function ($q) {
                $q->where('status', Claim::STATUS_REJECTED)
                    ->orWhereNotNull('claims.deleted_at');
            })
            ->paginate(15)
            ->withQueryString();
        $myCompleted = (clone $myBaseQuery)
            ->whereNull('claims.deleted_at')
            ->where('status', Claim::STATUS_PAID)
            ->paginate(15)
            ->withQueryString();
        $submitted   = (clone $listQuery)->whereNull('claims.deleted_at')->where('status', Claim::STATUS_SUBMITTED)->paginate(15)->withQueryString();
        $checked     = (clone $listQuery)->whereNull('claims.deleted_at')->where('status', Claim::STATUS_CHECKED)->paginate(15)->withQueryString();
        $verified    = (clone $listQuery)->whereNull('claims.deleted_at')->where('status', Claim::STATUS_VERIFIED)->paginate(15)->withQueryString();
        $approved    = (clone $listQuery)->whereNull('claims.deleted_at')->where('status', Claim::STATUS_APPROVED)->paginate(15)->withQueryString();
        $paymentMade = (clone $listQuery)
            ->whereNull('claims.deleted_at')
            ->where(function ($q) {
                $q->whereIn('status', Claim::paymentStatuses());
            })
            ->when($paymentState === 'pending', fn ($q) =>
                $q->where('status', Claim::STATUS_CEO_APPROVED)
            )
            ->when($paymentState === 'paid', fn ($q) =>
                $q->where('status', Claim::STATUS_PAID)
            )
            ->paginate(15)
            ->withQueryString();
        $rejected    = (clone $listQuery)
            ->where(function ($q) {
                $q->where('status', Claim::STATUS_REJECTED)
                    ->orWhereNotNull('claims.deleted_at');
            })
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | COUNTS (GROUP BY — NO ORDER BY)
        |--------------------------------------------------------------------------
        */
        $countsRaw = (clone $filterQuery)
            ->whereIn('status', [
                Claim::STATUS_SUBMITTED,
                Claim::STATUS_CHECKED,
                Claim::STATUS_VERIFIED,
                Claim::STATUS_APPROVED,
                Claim::STATUS_CEO_APPROVED,
                Claim::STATUS_PAID,
            ])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'submitted' => (int) ($countsRaw[Claim::STATUS_SUBMITTED] ?? 0),
            'checked'   => (int) ($countsRaw[Claim::STATUS_CHECKED] ?? 0),
            'verified'  => (int) ($countsRaw[Claim::STATUS_VERIFIED] ?? 0),
            'approved'  => (int) ($countsRaw[Claim::STATUS_APPROVED] ?? 0),
            'payment'   => (int) ($countsRaw[Claim::STATUS_CEO_APPROVED] ?? 0)
                         + (int) ($countsRaw[Claim::STATUS_PAID] ?? 0),
        ];

        /*
        |--------------------------------------------------------------------------
        | DONUT — BY PROJECT
        |--------------------------------------------------------------------------
        */
        $projectDonutRaw = (clone $filterQuery)
            ->leftJoin('projects', 'projects.id', '=', 'claims.project_id')
            ->when($tab === 'payment', function ($q) {
                $q->whereIn('claims.status', Claim::paymentStatuses());
            }, function ($q) use ($tab) {
                if ($tab === 'my_in_progress') {
                    $q->where('claims.user_id', (int) auth()->id())
                        ->whereNotIn('claims.status', [Claim::STATUS_REJECTED, Claim::STATUS_PAID]);
                    return;
                }

                if ($tab === 'my_rejected') {
                    $q->where('claims.user_id', (int) auth()->id())
                        ->where('claims.status', Claim::STATUS_REJECTED);
                    return;
                }

                if ($tab === 'my_completed') {
                    $q->where('claims.user_id', (int) auth()->id())
                        ->where('claims.status', Claim::STATUS_PAID);
                    return;
                }

                if (!in_array($tab, ['my_in_progress', 'my_rejected', 'my_completed'], true)) {
                    $q->where('claims.status', $tab);
                }
            })
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
                $q->whereIn('claims.status', Claim::paymentStatuses());
            }, function ($q) use ($tab) {
                if ($tab === 'my_in_progress') {
                    $q->where('claims.user_id', (int) auth()->id())
                        ->whereNotIn('claims.status', [Claim::STATUS_REJECTED, Claim::STATUS_PAID]);
                    return;
                }

                if ($tab === 'my_rejected') {
                    $q->where('claims.user_id', (int) auth()->id())
                        ->where('claims.status', Claim::STATUS_REJECTED);
                    return;
                }

                if ($tab === 'my_completed') {
                    $q->where('claims.user_id', (int) auth()->id())
                        ->where('claims.status', Claim::STATUS_PAID);
                    return;
                }

                if (!in_array($tab, ['my_in_progress', 'my_rejected', 'my_completed'], true)) {
                    $q->where('claims.status', $tab);
                }
            })
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

        $issuerIds = (clone $filterQuery)
            ->whereNotNull('claims.user_id')
            ->distinct()
            ->pluck('claims.user_id');

        $issuers = User::query()
            ->whereIn('id', $issuerIds)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Transactions/Claims/Index', [
            'claims' => [
                'all_non_draft' => $allNonDraft,
                'my_in_progress' => $myInProgress,
                'my_rejected'    => $myRejected,
                'my_completed'   => $myCompleted,
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
                'project_id' => $projectId,
                'issuer_id' => $issuerId,
                'amount_min' => $amountMin,
                'amount_max' => $amountMax,
                'payment_state' => $paymentState,
            ],

            'counts' => $counts,

            'donut' => [
                'by_project'  => $donutByProject,
                'by_category' => $donutByCategory,
            ],

            'activeTab' => $tab,
            'projects'  => $projects,
            'issuers'   => $issuers,
            'canBrowseAllClaims' => $this->canBrowseAllClaims($request),
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
            'status'       => Claim::STATUS_DRAFT,
        ]);

        return redirect()->route('claims.edit', $claim->uuid);
    }

    public function destroy(Request $request, string $uuid)
    {
        $claim = $this->claimByUuidOrFail($request, $uuid);

        if ($claim->status !== Claim::STATUS_DRAFT) {
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

        if ($claim->status !== Claim::STATUS_DRAFT) {
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
        if ($claim->status !== Claim::STATUS_DRAFT) {
            abort(403, 'This claim is locked and cannot be edited.');
        }

        /* ------------------------------------------------
        Base validation (draft-safe)
        ------------------------------------------------ */
        $baseRules = [
            'status' => ['required', Rule::in([Claim::STATUS_DRAFT, Claim::STATUS_SUBMITTED])],
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

        $rules = $request->status === Claim::STATUS_SUBMITTED
            ? array_merge($baseRules, $submitRules)
            : $baseRules;

        $validated = $request->validate($rules);

        /* ------------------------------------------------
        Total vs item sum check (submit only)
        ------------------------------------------------ */
        if ($validated['status'] === Claim::STATUS_SUBMITTED) {
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
            if ($validated['status'] === Claim::STATUS_SUBMITTED) {
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
                if ($validated['status'] === Claim::STATUS_SUBMITTED) {
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
                $validated['status'] === Claim::STATUS_SUBMITTED
                    ? 'Claim submitted successfully.'
                    : 'Draft saved successfully.'
            );
    }

    public function show(Request $request, string $uuid)
    {
        $claim = Claim::withTrashed()
            ->where('uuid', $uuid)
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
        $claim->setAttribute('is_petty_cash_origin', $claim->isPettyCashOrigin());

        return response()->json([
            'claim'   => $claim,
            'company' => $company,
        ]);
    }

    public function approval(Request $request, string $uuid)
    {
        $request->validate([
            'status' => ['required', Rule::in($this->approvalTargetStatuses())],
            'remark' => 'nullable|string|max:1000',
        ]);

        $status = DB::transaction(function () use ($request, $uuid) {
            $claim = Claim::where('uuid', $uuid)
                ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                    $q->where('branch_id', $this->activeBranchId($request))
                )
                ->lockForUpdate()
                ->firstOrFail();

            $current = (string) $claim->status;
            $next = (string) $request->status;

            if (!in_array($next, self::APPROVAL_TRANSITIONS[$current] ?? [], true)) {
                abort(403, 'Invalid claim status transition.');
            }

            $actorId = (int) auth()->id();
            $fromStatus = (string) $claim->status;
            $requestedStatus = (string) $request->status;
            $finalStatus = $this->resolveApprovalStatus($claim, $requestedStatus);

            $claim->status = $finalStatus;
            $this->applyApprovalSideEffects(
                claim: $claim,
                requestedStatus: $requestedStatus,
                finalStatus: $finalStatus,
                actorId: $actorId,
            );

            $claim->remark_log = $this->appendRemarkLog(
                existing: $claim->remark_log,
                fromStatus: $fromStatus,
                toStatus: $finalStatus,
                remark: $request->remark,
                userId: $actorId,
                userName: (string) (auth()->user()?->name ?? 'System'),
            );

            $claim->save();

            return (string) $claim->status;
        });

        return response()->json([
            'success' => true,
            'status'  => $status,
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

            if ($claim->status !== Claim::STATUS_CEO_APPROVED) {
                abort(422, 'Only CEO-approved claims can be marked as paid.');
            }

            if ($claim->isPettyCashOrigin()) {
                abort(422, 'Petty cash claims are auto-paid after CEO / GM approval.');
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
                'status'       => Claim::STATUS_PAID,
                'payment_ref_no'  => $request->payment_ref,
                'payment_slip_no' => $claim->payment_slip_no ?? $slip->slip_no,
                'company_bank_account_id' => $claim->company_bank_account_id ?? $slip->company_bank_account_id,
                'paid_at'      => now(),
                'paid_by'      => auth()->id(),
            ]);

            $slip->workflow_status = 'paid';
            $slip->save();

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
            'less_retention_label' => ['nullable', 'string', 'max:255'],
            'less_recoupment' => ['nullable', 'numeric', 'min:0'],
            'less_recoupment_label' => ['nullable', 'string', 'max:255'],
            'less_material_ob' => ['nullable', 'numeric', 'min:0'],
            'less_material_ob_label' => ['nullable', 'string', 'max:255'],
            'less_paid_previously' => ['nullable', 'numeric', 'min:0'],
            'less_paid_previously_label' => ['nullable', 'string', 'max:255'],
            'payment_slip_remark' => ['nullable', 'string', 'max:255'],
            'remark_label' => ['nullable', 'string', 'max:255'],
        ]);

        $claim = $this->claimByUuidOrFail($request, $uuid);

        if ($claim->status !== Claim::STATUS_CEO_APPROVED) {
            abort(422, 'Only CEO-approved claims can generate a payment slip.');
        }

        if ($claim->isPettyCashOrigin()) {
            abort(422, 'Petty cash claims do not use payment slips. Please use top-up request payment flow.');
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
        $slip->less_retention_label = $request->input('less_retention_label');
        $slip->less_recoupment = $request->input('less_recoupment');
        $slip->less_recoupment_label = $request->input('less_recoupment_label');
        $slip->less_material_ob = $request->input('less_material_ob');
        $slip->less_material_ob_label = $request->input('less_material_ob_label');
        $slip->less_paid_previously = $request->input('less_paid_previously');
        $slip->less_paid_previously_label = $request->input('less_paid_previously_label');
        $slip->workflow_status = 'processing';
        $slip->approved_at = null;
        $slip->approved_by = null;
        $slip->rejected_at = null;
        $slip->rejected_by = null;
        $slip->rejected_reason = null;
        $slip->payment_slip_remark = $request->input('payment_slip_remark');
        $slip->remark_label = $request->input('remark_label');
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

    private function canBrowseAllClaims(Request $request): bool
    {
        return (bool) ($request->user()?->is_superadmin || $request->user()?->is_general_manager);
    }

    private function approvalTargetStatuses(): array
    {
        return array_values(
            array_unique(
                array_merge(...array_values(self::APPROVAL_TRANSITIONS))
            )
        );
    }

    private function resolveApprovalStatus(Claim $claim, string $requestedStatus): string
    {
        if (
            $requestedStatus === Claim::STATUS_CEO_APPROVED
            && $claim->isPettyCashOrigin()
        ) {
            return Claim::STATUS_PAID;
        }

        return $requestedStatus;
    }

    private function applyApprovalSideEffects(
        Claim $claim,
        string $requestedStatus,
        string $finalStatus,
        int $actorId,
    ): void {
        if ($requestedStatus === Claim::STATUS_DRAFT) {
            $claim->checked_by = null;
            $claim->checked_at = null;
            $claim->approved_by = null;
            $claim->approved_at = null;
            $claim->paid_by = null;
            $claim->paid_at = null;
            return;
        }

        if ($finalStatus === Claim::STATUS_CHECKED) {
            $claim->checked_by = $actorId;
            $claim->checked_at = now();
            return;
        }

        if ($finalStatus === Claim::STATUS_APPROVED) {
            $claim->approved_by = null;
            $claim->approved_at = null;
            return;
        }

        if ($finalStatus === Claim::STATUS_CEO_APPROVED) {
            $claim->approved_by = $actorId;
            $claim->approved_at = now();
            $claim->paid_by = null;
            $claim->paid_at = null;
            return;
        }

        if ($finalStatus === Claim::STATUS_PAID) {
            $claim->approved_by = $actorId;
            $claim->approved_at = now();
            $claim->paid_by = $actorId;
            $claim->paid_at = now();
        }
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

