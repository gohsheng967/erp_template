<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashTopup;
use App\Models\PettyCashWallet;
use App\Models\Project;
use App\Services\PettyCashTransactionService;
use App\Services\RunningNumberService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
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
                'requester:id,name',
                'approver:id,name',
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

        /* =========================
        PAGINATED DATA
        ========================= */
        $requested = $requestedQuery
            ->paginate(15)
            ->withQueryString();

        $approved = $approvedQuery
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
            // paid intentionally excluded (history tab)
        ];

        /* =========================
        RESPONSE
        ========================= */
        return inertia('PettyCash/Topups/Index', [
            'topups' => [
                'requested' => $requested,
                'approved'  => $approved,
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
