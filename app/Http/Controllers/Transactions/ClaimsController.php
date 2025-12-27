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

use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\RunningNumberService;
use App\Services\AttachmentService;

class ClaimsController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->tab ?? 'submitted';

        $from = $request->from
            ?? Carbon::now()->subMonth()->toDateString();

        $to = $request->to
            ?? Carbon::now()->toDateString();

        $search = $request->search;

        $baseQuery = Claim::query()
            ->with([
                'project:id,name',
                'user:id,name',
            ])
            ->withCount('items')
            ->withSum('items', 'amount')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('claim_no', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($u) use ($search) {
                          $u->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($from, fn ($q) =>
                $q->whereDate('claims.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('claims.created_at', '<=', $to)
            )
            ->orderByDesc('claims.created_at');

        $draft = (clone $baseQuery)->where('status', 'draft')->paginate(15)->withQueryString();
        $submitted = (clone $baseQuery)->where('status', 'submitted')->paginate(15)->withQueryString();
        $approved = (clone $baseQuery)->where('status', 'approved')->paginate(15)->withQueryString();
        $rejected = (clone $baseQuery)->where('status', 'rejected')->paginate(15)->withQueryString();
        $paymentMade = (clone $baseQuery)->where('status', 'payment_made')->paginate(15)->withQueryString();

        $countsRaw = (clone $baseQuery)
            ->whereIn('status', ['submitted', 'approved'])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'submitted' => (int) ($countsRaw['submitted'] ?? 0),
            'approved'  => (int) ($countsRaw['approved'] ?? 0),
        ];

        $projectDonutRaw = Claim::query()
            ->leftJoin('projects', 'projects.id', '=', 'claims.project_id')
            ->when($from, fn ($q) =>
                $q->whereDate('claims.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('claims.created_at', '<=', $to)
            )
            ->where('claims.status', $tab)
            ->select(
                DB::raw("COALESCE(claims.project_id, 0) as project_key"),
                DB::raw("SUM(claims.total_amount) as total")
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

        $donutByCategory = DB::table('claim_items')
            ->join('claims', 'claims.id', '=', 'claim_items.claim_id')
            ->when($from, fn ($q) =>
                $q->whereDate('claims.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('claims.created_at', '<=', $to)
            )
            ->where('claims.status', $tab)
            ->select(
                'claim_items.claim_type as label',
                DB::raw('SUM(claim_items.amount) as amount')
            )
            ->groupBy('claim_items.claim_type')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'label' => ucfirst(str_replace('_', ' ', $row->label)),
                'amount' => (float) $row->amount,
            ]);

        $projects = Project::select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Transactions/Claims/Index', [
            'claims' => [
                'draft'        => $draft,
                'submitted'    => $submitted,
                'approved'     => $approved,
                'rejected'     => $rejected,
                'payment_made' => $paymentMade,
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
            'projects' => $projects,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id'   => ['nullable', 'exists:projects,id'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'title'=> ['required', 'max:255']
        ]);

        $claim = Claim::create([
            'user_id'      => $request->user()->id,
            'title'        => $validated['title'],
            'project_id'   => $validated['project_id'] ?? null,
            'total_amount' => $validated['total_amount'],
            'status'       => 'draft',
        ]);

        return redirect()->route('claims.edit', $claim->uuid);
    }

    public function destroy(string $uuid)
    {
        $claim = Claim::where('uuid', $uuid)->firstOrFail();

        if ($claim->status !== 'draft') {
            abort(403);
        }

        $claim->delete();

        return back();
    }

    public function edit(string $uuid)
    {
        $claim = Claim::where('uuid', $uuid)
            ->with([
                'items.attachments', // ✅ REQUIRED
            ])
            ->firstOrFail();

        if ($claim->status !== 'draft') {
            abort(403, 'Claim is no longer editable');
        }

        return inertia('Transactions/Claims/Edit', [
            'claim' => $claim,
            'claimTypes' => config('claim.types'),
        ]);
    }

    public function update(Request $request, string $uuid)
    {
        $claim = Claim::where('uuid', $uuid)->firstOrFail();

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
                Rule::in(array_keys(config('claim.types', []))),
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
            if ($validated['status'] === 'submitted' && !$claim->claim_no) {
                $claim->claim_no = RunningNumberService::next(
                    documentType: 'claim',
                    prefix: 'CLM'
                );
                $claim->submitted_at = now();
            }

            /* -------- Update claim header -------- */
            $claim->update([
                'status' => $validated['status'],
                'total_amount' => $validated['total_amount'] ?? 0,
            ]);

            $keptItemIds = [];

            foreach ($validated['items'] ?? [] as $index => $item) {

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
            $claim->items()
                ->whereNotIn('id', $keptItemIds)
                ->each(function ($item) {
                    $item->attachments()->each(function ($attachment) {
                        Storage::disk('public')->delete($attachment->file_path);
                        $attachment->delete();
                    });
                    $item->delete();
                });
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

}
