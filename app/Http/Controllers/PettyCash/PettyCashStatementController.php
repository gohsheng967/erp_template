<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PettyCashWallet;
use App\Models\PettyCashTransaction;
use App\Models\PettyCashStatement;
use App\Models\Claim;
use App\Models\ClaimType;
use Inertia\Inertia;
use App\Services\RunningNumberService;

use Illuminate\Validation\ValidationException;

use App\Services\PettyCashTransactionService;
use App\Services\AttachmentService;

use Carbon\Carbon;
use Illuminate\Validation\Rule;

class PettyCashStatementController extends Controller
{
    public function show(Request $request, string $walletUuid)
    {
        $wallet = PettyCashWallet::where('uuid', $walletUuid)
            ->where('is_active', true)
            ->firstOrFail();

        /* =========================
        MONTH (DEFAULT CURRENT)
        ========================= */
        $monthString = $request->get('month', now()->format('Y-m'));

        abort_unless(
            preg_match('/^\d{4}-\d{2}$/', $monthString),
            404
        );

        /* =========================
        STATEMENT (OPTIONAL)
        ========================= */
        $statement = PettyCashStatement::where('wallet_id', $wallet->id)
            ->where('month', $monthString)
            ->first();


        /* =========================
        PERIOD RANGE
        ========================= */
        $periodStart = Carbon::createFromFormat('Y-m', $monthString)->startOfMonth();
        $periodEnd   = (clone $periodStart)->endOfMonth();

        /* =========================
        TRANSACTIONS (LEDGER)
        ========================= */
        $transactions = PettyCashTransaction::where('wallet_id', $wallet->id)
            ->whereBetween('transaction_date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->with('sourceable')
            ->get()
            ->map(fn (PettyCashTransaction $tx) => [
                'id'            => $tx->id,
                'date'          => $tx->transaction_date,
                'code'          => $tx->code,
                'reference'     => $tx->source_ref_no,
                'title'         => $tx->sourceable?->title
                    ?? ($tx->source_type === \App\Models\PettyCashTopup::class ? 'Topup' : null),
                'description'   => $tx->sourceable?->description
                    ?? ($tx->source_type === \App\Models\PettyCashTopup::class ? ($tx->sourceable?->reason ?? null) : null),
                'amount_in'     => $tx->credit_amount > 0 ? (float) $tx->credit_amount : null,
                'amount_out'    => $tx->debit_amount  > 0 ? (float) $tx->debit_amount  : null,
                'balance_after' => (float) $tx->balance_after,
                'type'          => $tx->display_type
            ]);

        /* =========================
        SUMMARY
        ========================= */
        $openingBalance =
            $statement?->opening_balance
            ?? PettyCashTransaction::where('wallet_id', $wallet->id)
                ->where('transaction_date', '<', $periodStart->toDateString())
                ->orderByDesc('transaction_date')
                ->value('balance_after')
            ?? $wallet->opening_balance;

        $totalIn  = $transactions->sum('amount_in');
        $totalOut = $transactions->sum('amount_out');

        $closingBalance = $statement
            ? (float) $statement->closing_balance
            : ($openingBalance + $totalIn - $totalOut);

        $claimTypes = ClaimType::query()
            ->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();

        $currentMonth = now()->format('Y-m');
        $isCurrentMonth = $monthString === $currentMonth;

        $canEdit = $isCurrentMonth;

        return inertia('PettyCash/Wallets/Show', [
            'wallet' => [
                'id'   => $wallet->id,
                'uuid' => $wallet->uuid,
                'name' => $wallet->isOffice()
                    ? 'Office Petty Cash'
                    : optional($wallet->project)->name ?? 'Project Wallet',
            ],

            'period' => [
                'month'    => $monthString,
                'is_locked'=> !$canEdit,
                'editable' => $canEdit,
            ],

            'summary' => [
                'opening' => round($openingBalance, 2),
                'total_in'=> round($totalIn, 2),
                'total_out'=> round($totalOut, 2),
                'closing' => round($closingBalance, 2),
            ],

            'transactions' => $transactions,
            'claimTypes' => $claimTypes,
        ]);
    }

    public function storeClaim(Request $request,PettyCashTransactionService $pettyCashTransactionService) 
    {
        $data = $request->validate([
            'wallet_uuid' => 'required|exists:petty_cash_wallets,uuid',
            'claim_type'  => [
                'required',
                Rule::exists('claim_types', 'code')->whereNull('deleted_at'),
            ],
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'receipt_no'  => 'required|string|max:100',
            'amount'      => 'required|numeric|min:0.01',

            'attachments'   => 'required|array|min:1|max:3',
            'attachments.*' => 'file|max:10120',
        ]);

        $wallet = PettyCashWallet::where('uuid', $data['wallet_uuid'])->firstOrFail();

        DB::transaction(function () use (
            $data,
            $wallet,
            $request,
            $pettyCashTransactionService
        ) {
            $claimTypes = ClaimType::query()
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray();

            $projectId = $wallet->context_type === 'project'
                ? $wallet->context_id
                : null;

            $claim = Claim::create([
                'user_id'      => $request->user()->id,
                'claim_no'     => RunningNumberService::next(documentType: 'claim'),
                'title'        => $data['title'],
                'description'  => $data['description'] ?? null,
                'status'       => 'paid',
                'project_id'   => $projectId,
                'total_amount' => $data['amount'],
                'remark'       => 'Created from Petty Cash',
                'submitted_at' => now(),
                'approved_by'  => null,
                'approved_at'  => now(),
                'paid_by'      => null,
                'paid_at'      => now(),
            ]);

            $claimItem = $claim->items()->create([
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'receipt_no'  => $data['receipt_no'] ?? null,
                'claim_type'  => $data['claim_type'],
                'amount'      => $data['amount'],
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    AttachmentService::store(
                        file: $file,
                        attachable: $claimItem,
                        disk: 'public'
                    );
                }
            }

            $display_type  = $claimTypes[$data['claim_type']] ?? $data['claim_type'];
            
            $transaction = $pettyCashTransactionService->debitFromClaim(
                wallet: $wallet,
                claim: $claim,
                amount: (float) $data['amount'],
                date: Carbon::today(),
                display_type: $display_type,
                source_ref_no: $claim->claim_no,
                attachments: []
            );

            $claim->update([
                'payment_ref_no' => $transaction->code,
            ]);
        });

        return redirect()
            ->back()
            ->with('success', 'Claim created successfully.');
    }

}
