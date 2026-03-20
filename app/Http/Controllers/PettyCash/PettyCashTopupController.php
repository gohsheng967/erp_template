<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashTopup;
use App\Models\PettyCashWallet;
use App\Models\Project;
use App\Services\PettyCashTransactionService;
use App\Services\RunningNumberService;
use App\Models\PaymentSlip;
use App\Services\AttachmentService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PettyCashTopupController extends Controller
{
    public function index(Request $request)
    {
        $allowedTabs = [
            'my_in_progress',
            'my_rejected',
            'my_completed',
            'all_non_draft',
            'checked',
            'verified',
            'approval',
            'payment',
            'rejected',
        ];
        $tab = in_array($request->tab, $allowedTabs, true) ? $request->tab : 'checked';
        $branchId = $this->activeBranchId($request);
        $verifiedStatuses = ['verified_own_department', 'verified_project_department'];
        $paymentState = in_array($request->payment_state, ['pending', 'paid'], true)
            ? $request->payment_state
            : 'all';

        $search = $request->search;
        $from = $request->from ?? Carbon::now()->subMonth()->toDateString();
        $to = $request->to ?? Carbon::now()->toDateString();
        $contextType = in_array($request->context_type, ['office', 'project'], true)
            ? $request->context_type
            : null;
        $projectId = $request->filled('project_id') ? (int) $request->project_id : null;
        $requesterId = $request->filled('requester_id') ? (int) $request->requester_id : null;
        $amountMin = $request->filled('amount_min') ? (float) $request->amount_min : null;
        $amountMax = $request->filled('amount_max') ? (float) $request->amount_max : null;

        $filterQuery = PettyCashTopup::query()
            ->when($branchId !== null, function ($q) use ($branchId) {
                $q->where(function ($query) use ($branchId) {
                    $query->whereHas('wallet', function ($wallet) use ($branchId) {
                        $wallet->where('context_type', 'office')
                            ->orWhereHas('project', fn ($project) => $project->where('branch_id', $branchId));
                    })
                        ->orWhereHas('companyBankAccount', fn ($bank) => $bank->where('branch_id', $branchId))
                        ->orWhereHas(
                            'paymentSlip.companyBankAccount',
                            fn ($bank) => $bank->where('branch_id', $branchId)
                        );
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('topup_no', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('payment_ref_no', 'like', "%{$search}%")
                        ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('wallet.project', fn ($project) => $project->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($contextType, fn ($q) => $q->whereHas('wallet', fn ($wallet) => $wallet->where('context_type', $contextType)))
            ->when($projectId, fn ($q) => $q->whereHas('wallet', fn ($wallet) => $wallet->where('context_type', 'project')->where('context_id', $projectId)))
            ->when($requesterId, fn ($q) => $q->where('requested_by', $requesterId))
            ->when($amountMin !== null, fn ($q) => $q->where('amount', '>=', $amountMin))
            ->when($amountMax !== null, fn ($q) => $q->where('amount', '<=', $amountMax));

        $listQuery = (clone $filterQuery)
            ->with([
                'wallet.project',
                'bankAccount',
                'companyBankAccount',
                'paymentSlip',
                'requester:id,name',
                'verifier:id,name',
                'approver:id,name',
                'rejector:id,name',
                'payer:id,name',
                'attachment',
            ])
            ->orderByDesc('created_at');

        $myBaseQuery = (clone $listQuery)->where('requested_by', (int) auth()->id());

        $checked = (clone $listQuery)->where('status', 'requested')->paginate(15)->withQueryString();
        $verified = (clone $listQuery)->whereIn('status', $verifiedStatuses)->paginate(15)->withQueryString();
        $approval = (clone $listQuery)->where('status', 'approved')->paginate(15)->withQueryString();
        $payment = (clone $listQuery)
            ->whereIn('status', ['approved', 'paid'])
            ->when($paymentState === 'pending', fn ($q) => $q->where('status', 'approved'))
            ->when($paymentState === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->paginate(15)
            ->withQueryString();
        $rejected = (clone $listQuery)->where('status', 'rejected')->paginate(15)->withQueryString();
        $allNonDraft = (clone $listQuery)->paginate(15)->withQueryString();

        $myInProgress = (clone $myBaseQuery)
            ->whereNotIn('status', ['rejected', 'paid'])
            ->paginate(15)
            ->withQueryString();
        $myRejected = (clone $myBaseQuery)->where('status', 'rejected')->paginate(15)->withQueryString();
        $myCompleted = (clone $myBaseQuery)->where('status', 'paid')->paginate(15)->withQueryString();

        $tabCounts = [
            'checked' => (clone $filterQuery)->where('status', 'requested')->count(),
            'verified' => (clone $filterQuery)->whereIn('status', $verifiedStatuses)->count(),
            'approval' => (clone $filterQuery)->where('status', 'approved')->count(),
            'payment' => (clone $filterQuery)->whereIn('status', ['approved', 'paid'])->count(),
            'my_in_progress' => (clone $filterQuery)->where('requested_by', (int) auth()->id())->whereNotIn('status', ['rejected', 'paid'])->count(),
            'my_rejected' => (clone $filterQuery)->where('requested_by', (int) auth()->id())->where('status', 'rejected')->count(),
            'my_completed' => (clone $filterQuery)->where('requested_by', (int) auth()->id())->where('status', 'paid')->count(),
            'rejected' => (clone $filterQuery)->where('status', 'rejected')->count(),
        ];

        $requesterIds = (clone $filterQuery)->distinct()->pluck('requested_by')->filter();
        $requesters = \App\Models\User::query()
            ->whereIn('id', $requesterIds)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return inertia('PettyCash/Topups/Index', [
            'topups' => [
                'my_in_progress' => $myInProgress,
                'my_rejected' => $myRejected,
                'my_completed' => $myCompleted,
                'all_non_draft' => $allNonDraft,
                'checked' => $checked,
                'verified' => $verified,
                'approval' => $approval,
                'payment' => $payment,
                'rejected' => $rejected,
            ],
            'tabCounts' => $tabCounts,
            'projects' => Project::select('id', 'name')
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'wallets' => PettyCashWallet::with('project')
                ->when($branchId !== null, function ($q) use ($branchId) {
                    $q->where(function ($wallet) use ($branchId) {
                        $wallet->where('context_type', 'office')
                            ->orWhereHas('project', fn ($project) => $project->where('branch_id', $branchId));
                    });
                })
                ->get(),
            'requesters' => $requesters,
            'activeTab' => $tab,
            'canBrowseAllTopups' => $this->canBrowseAllTopups($request),
            'filters' => [
                'search' => $search,
                'from' => $from,
                'to' => $to,
                'context_type' => $contextType,
                'project_id' => $projectId,
                'requester_id' => $requesterId,
                'amount_min' => $amountMin,
                'amount_max' => $amountMax,
                'payment_state' => $paymentState,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $branchId = $this->activeBranchId($request);

        $validated = $request->validate([
            'context_type' => ['required', 'in:office,project'],
            'project_id'   => ['nullable', 'exists:projects,id'],
            'bank_account_id' => [
                'required',
                Rule::exists('user_bank_accounts', 'id')->where(function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id)
                        ->where('status', 'active');
                }),
            ],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'reason'       => ['nullable', 'string', 'max:255'],
        ]);

        /* =========================
        RESOLVE OR CREATE WALLET
        ========================= */

        if ($validated['context_type'] === 'office') {

            $wallet = PettyCashWallet::firstOrCreate(
                ['context_type' => 'office'],
                [
                    'current_balance' => 0,
                    'created_by'      => $request->user()->id,
                ]
            );

        } else {

            if (!$validated['project_id']) {
                throw ValidationException::withMessages([
                    'project_id' => 'Project is required.',
                ]);
            }

            if (
                $branchId !== null
                && !Project::where('id', $validated['project_id'])->where('branch_id', $branchId)->exists()
            ) {
                throw ValidationException::withMessages([
                    'project_id' => 'Selected project does not belong to active branch.',
                ]);
            }

            $wallet = PettyCashWallet::firstOrCreate(
                [
                    'context_type' => 'project',
                    'context_id'   => $validated['project_id'],
                ],
                [
                    'current_balance' => 0,
                    'created_by'      => $request->user()->id,
                ]
            );
        }

        PettyCashTopup::create([
            'topup_no'     => RunningNumberService::next('petty_cash_topup'),
            'wallet_id'    => $wallet->id,
            'bank_account_id' => $validated['bank_account_id'],
            'amount'       => $validated['amount'],
            'reason'       => $validated['reason'],
            'status'       => 'requested',
            'requested_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Top-up request submitted.');
    }

    /* =========================
       APPROVE TOP-UP
    ========================== */
    public function approve(Request $request, PettyCashTopup $topup)
    {
        $this->ensureTopupBranchAccess($request, $topup);

        if (!in_array($topup->status, ['verified_own_department', 'verified_project_department'], true)) {
            abort(422, 'Invalid top-up state.');
        }

        $topup->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Top-up approved.');
    }

    public function verify(Request $request, PettyCashTopup $topup)
    {
        $this->ensureTopupBranchAccess($request, $topup);

        if ($topup->status !== 'requested') {
            abort(422, 'Invalid top-up state.');
        }

        $nextStatus = $topup->wallet?->context_type === 'project'
            ? 'verified_project_department'
            : 'verified_own_department';

        $topup->update([
            'status'      => $nextStatus,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return back()->with(
            'success',
            $nextStatus === 'verified_project_department'
                ? 'Top-up verified by project department.'
                : 'Top-up verified by own department.'
        );
    }

    public function reject(Request $request, PettyCashTopup $topup)
    {
        $this->ensureTopupBranchAccess($request, $topup);

        if (!in_array($topup->status, ['requested', 'verified_own_department', 'verified_project_department'], true)) {
            abort(422, 'Invalid top-up state.');
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $topup->update([
            'status'      => 'rejected',
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejected_reason' => $request->reason,
        ]);

        return back()->with('success', 'Top-up rejected.');
    }

    /* =========================
       PAY TOP-UP (FINANCE)
    ========================== */
    public function pay(Request $request, PettyCashTopup $topup, PettyCashTransactionService $service) 
    {
        $this->ensureTopupBranchAccess($request, $topup);

        $request->validate([
            'payment_ref_no' => ['required', 'string', 'max:255'],
            'attachments'   => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if ($topup->status !== 'approved') {
            abort(422, 'Only approved top-ups can be paid.');
        }

        if (!$topup->paymentSlip) {
            abort(422, 'Payment slip is required before payment.');
        }

        if (!$topup->paymentSlip->company_bank_account_id) {
            abort(422, 'Company bank account is required before payment.');
        }

        $slip = $topup->paymentSlip;

        foreach ($request->file('attachments', []) as $file) {
            AttachmentService::store($file, $slip);
        }

        $topup->update([
            'payment_ref_no' => $request->payment_ref_no,
        ]);

        $service->creditFromTopup(
            $topup->wallet,
            $topup,
            Carbon::now(),
            $request->file('attachments')
        );

        $slip->workflow_status = 'paid';
        $slip->save();

        return back()->with('success', 'Top-up paid and balance updated.');
    }

    public function uploadSlip(Request $request, PettyCashTopup $topup)
    {
        $this->ensureTopupBranchAccess($request, $topup);

        if (!$topup->payment_slip_no) {
            abort(422, 'Payment slip number is required before upload.');
        }

        $request->validate([
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('petty-cash/attachments', 'public');

            $topup->attachments()->create([
                'uuid' => (string) Str::uuid(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        return back()->with('success', 'Payment slip uploaded.');
    }

    public function paymentSlip(Request $request, PettyCashTopup $topup)
    {
        $this->ensureTopupBranchAccess($request, $topup);

        if ($topup->status !== 'approved') {
            abort(422, 'Only approved top-ups can generate a payment slip.');
        }

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

        $slip = $topup->paymentSlip ?? new PaymentSlip();
        if (!$slip->exists) {
            $slip->slip_no = RunningNumberService::next('payment_slip');
            $slip->source()->associate($topup);
        } elseif ($slip->cancelled_at) {
            $slip->slip_no = RunningNumberService::next('payment_slip');
            $slip->cancelled_at = null;
            $slip->cancelled_by = null;
            $slip->cancel_reason = null;
        }

        $slip->company_bank_account_id = $request->company_bank_account_id;
        $slip->amount = $topup->amount;
        $slip->payment_date = $topup->approved_at ?? now()->toDateString();
        $slip->less_retention = $request->input('less_retention');
        $slip->less_recoupment = $request->input('less_recoupment');
        $slip->less_material_ob = $request->input('less_material_ob');
        $slip->less_paid_previously = $request->input('less_paid_previously');
        $slip->workflow_status = 'processing';
        $slip->approved_at = null;
        $slip->approved_by = null;
        $slip->rejected_at = null;
        $slip->rejected_by = null;
        $slip->rejected_reason = null;
        $slip->payment_slip_remark = $request->input('payment_slip_remark');
        $slip->created_by = $request->user()->id;
        $slip->save();

        $topup->payment_slip_no = $slip->slip_no;
        $topup->company_bank_account_id = $request->company_bank_account_id;
        $topup->save();

        $slip->load([
            'companyBankAccount',
            'source.wallet.project',
            'source.bankAccount',
            'source.requester:id,name',
            'source.approver:id,name',
            'source.payer:id,name',
        ]);

        return response()->json([
            'slip' => $slip,
        ]);
    }

    public function destroy(Request $request, PettyCashTopup $topup)
    {
        $this->ensureTopupBranchAccess($request, $topup);

        if ($topup->status !== 'requested') {
            abort(403, 'Only requested top-ups can be deleted.');
        }

        if ($topup->requested_by !== auth()->id()) {
            abort(403, 'You are not allowed to delete this request.');
        }

        $topup->delete();

        return back()->with('success', 'Top-up request deleted.');
    }

    private function activeBranchId(Request $request): ?int
    {
        if (!$this->shouldScopeToActiveBranch($request)) {
            return null;
        }

        $branchId = (int) ($request->user()?->active_branch_id ?? 0);
        if ($branchId <= 0) {
            abort(422, 'Please select an active branch before proceeding.');
        }

        return $branchId;
    }

    private function ensureTopupBranchAccess(Request $request, PettyCashTopup $topup): void
    {
        $branchId = $this->activeBranchId($request);
        if ($branchId === null) {
            return;
        }

        $topup->loadMissing([
            'wallet.project:id,branch_id',
            'companyBankAccount:id,branch_id',
            'paymentSlip.companyBankAccount:id,branch_id',
        ]);

        $isAllowed = ($topup->wallet?->context_type === 'office')
            || (int) ($topup->wallet?->project?->branch_id ?? 0) === $branchId
            || (int) ($topup->companyBankAccount?->branch_id ?? 0) === $branchId
            || (int) ($topup->paymentSlip?->companyBankAccount?->branch_id ?? 0) === $branchId;

        if (!$isAllowed) {
            abort(404);
        }
    }

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
    }

    private function canBrowseAllTopups(Request $request): bool
    {
        return (bool) ($request->user()?->is_superadmin || $request->user()?->is_general_manager);
    }
}


