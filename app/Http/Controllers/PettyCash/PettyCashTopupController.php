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
        $tab = $request->get('tab', 'requested');

        /* =========================
        BASE QUERY
        ========================= */
        $baseQuery = PettyCashTopup::query()
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

        /* =========================
        STATUS QUERIES
        ========================= */
        $requestedQuery = (clone $baseQuery)->where('status', 'requested');
        $approvedQuery  = (clone $baseQuery)->where('status', 'approved');
        $paidQuery      = (clone $baseQuery)->where('status', 'paid');
        $rejectedQuery  = (clone $baseQuery)->where('status', 'rejected');

        /* =========================
        PAGINATED DATA
        ========================= */
        $requested = $requestedQuery
            ->paginate(15)
            ->withQueryString();

        $approved = $approvedQuery
            ->paginate(15)
            ->withQueryString();

        $rejected = $rejectedQuery
            ->paginate(15)
            ->withQueryString();

        $paid = $paidQuery
            ->paginate(15)
            ->withQueryString();

        /* =========================
        TAB BADGE COUNTS
        ========================= */
        $tabCounts = [
            'requested' => (clone $requestedQuery)->count(),
            'approved'  => (clone $approvedQuery)->count(),
            'rejected'  => (clone $rejectedQuery)->count(),
            // paid intentionally excluded (history tab)
        ];

        /* =========================
        RESPONSE
        ========================= */
        return inertia('PettyCash/Topups/Index', [
            'topups' => [
                'requested' => $requested,
                'approved'  => $approved,
                'rejected'  => $rejected,
                'paid'      => $paid,
            ],

            // 🔔 Badge numbers for tabs
            'tabCounts' => $tabCounts,

            // Required for CreateTopupModal
            'projects' => Project::select('id', 'name')
                ->orderBy('name')
                ->get(),

            // Optional (future use)
            'wallets' => PettyCashWallet::with('project')->get(),

            'activeTab' => $tab,

            'filters' => [
                'search' => $request->search,
                'from'   => $request->from,
                'to'     => $request->to,
            ],
        ]);
    }

    public function store(Request $request)
    {
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
    public function approve(PettyCashTopup $topup)
    {
        if ($topup->status !== 'requested') {
            abort(422, 'Invalid top-up state.');
        }

        $topup->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Top-up approved.');
    }

    public function reject(PettyCashTopup $topup)
    {
        if ($topup->status !== 'requested') {
            abort(422, 'Invalid top-up state.');
        }

        request()->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $topup->update([
            'status'      => 'rejected',
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejected_reason' => request()->reason,
        ]);

        return back()->with('success', 'Top-up rejected.');
    }

    /* =========================
       PAY TOP-UP (FINANCE)
    ========================== */
    public function pay(Request $request, PettyCashTopup $topup, PettyCashTransactionService $service) 
    {
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

        return back()->with('success', 'Top-up paid and balance updated.');
    }

    public function uploadSlip(Request $request, PettyCashTopup $topup)
    {
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
        if ($topup->status !== 'approved') {
            abort(422, 'Only approved top-ups can generate a payment slip.');
        }

        $request->validate([
            'company_bank_account_id' => [
                'required',
                Rule::exists('company_bank_accounts', 'id')->where(function ($query) {
                    $query->where('status', 'active');
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
        }

        $slip->company_bank_account_id = $request->company_bank_account_id;
        $slip->amount = $topup->amount;
        $slip->payment_date = $topup->approved_at ?? now()->toDateString();
        $slip->less_retention = $request->input('less_retention');
        $slip->less_recoupment = $request->input('less_recoupment');
        $slip->less_material_ob = $request->input('less_material_ob');
        $slip->less_paid_previously = $request->input('less_paid_previously');
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

    public function destroy(PettyCashTopup $topup)
    {
        if ($topup->status !== 'requested') {
            abort(403, 'Only requested top-ups can be deleted.');
        }

        if ($topup->requested_by !== auth()->id()) {
            abort(403, 'You are not allowed to delete this request.');
        }

        $topup->delete();

        return back()->with('success', 'Top-up request deleted.');
    }

}
