<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashWallet;
use App\Models\Claim;
use App\Services\PettyCashTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PettyCashUsageController extends Controller
{
    public function store(
        Request $request,
        PettyCashTransactionService $service
    ) {
        $validated = $request->validate([
            'wallet_id'     => ['required', 'exists:petty_cash_wallets,id'],
            'title'         => ['required', 'string', 'max:255'],
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'attachments'   => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:10240'], // 10MB
        ]);

        return DB::transaction(function () use ($validated, $service, $request) {

            $wallet = PettyCashWallet::lockForUpdate()
                ->findOrFail($validated['wallet_id']);
            $this->ensureWalletBranchAccess($request, $wallet);

            /* =========================
               BALANCE GUARD
            ========================== */
            if ($wallet->current_balance < $validated['amount']) {
                return back()->withErrors([
                    'amount' => 'Insufficient petty cash balance.',
                ]);
            }

            /* =========================
               CREATE CLAIM (AUTO-APPROVED)
            ========================== */
            $claim = Claim::create([
                'user_id'      => $request->user()->id,
                'project_id'   => $wallet->project_id, // nullable (office wallet safe)
                'branch_id'    => $wallet->project?->branch_id ?? $this->activeBranchId($request),
                'type'         => 'petty_cash_usage',
                'title'        => $validated['title'],
                'status'       => 'approved',
                'approved_by'  => $request->user()->id,
                'approved_at'  => now(),
                'submitted_at' => now(),
                'total_amount' => $validated['amount'],
            ]);

            /* =========================
               DEBIT WALLET & STORE ATTACHMENTS
            ========================== */
            $service->debitFromClaim(
                wallet: $wallet,
                claim: $claim,
                amount: $validated['amount'],
                transactedAt: Carbon::now(),
                attachments: $request->file('attachments')
            );

            return back()->with(
                'success',
                'Petty cash usage recorded successfully.'
            );
        });
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

    private function ensureWalletBranchAccess(Request $request, PettyCashWallet $wallet): void
    {
        $branchId = $this->activeBranchId($request);
        if ($branchId === null) {
            return;
        }

        if ($wallet->context_type === 'project' && (int) ($wallet->project?->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
    }

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
    }
}

