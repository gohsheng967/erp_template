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
}
