<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PettyCashWallet;
use App\Models\PettyCashTransaction;
use App\Models\PettyCashStatement;
use App\Models\ClaimType;
use Carbon\Carbon;

class PettyCashWalletController extends Controller
{
    /**
     * =====================================================
     * WALLET INDEX
     * Entry page to Balance Statement module
     * =====================================================
     */
    public function index(Request $request)
    {
        $branchId = $this->activeBranchId($request);
        $monthString = (string) $request->get('month', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $monthString), 404);

        $wallets = PettyCashWallet::query()
            ->where('is_active', true)
            ->when($branchId !== null, function ($q) use ($branchId) {
                $q->where(function ($wallet) use ($branchId) {
                    $wallet->where('context_type', 'office')
                        ->orWhereHas('project', fn ($project) => $project->where('branch_id', $branchId));
                });
            })
            ->orderBy('context_type')
            ->orderBy('id')
            ->get()
            ->map(function (PettyCashWallet $wallet) {

                $latestStatement = PettyCashStatement::where('wallet_id', $wallet->id)
                    ->orderByDesc('month') // YYYY-MM sorts correctly
                    ->first();

                return [
                    'id'              => $wallet->id,
                    'uuid'            => $wallet->uuid,

                    'name' => $wallet->isOffice()
                        ? 'Office Petty Cash'
                        : optional($wallet->project)->name ?? 'Project Wallet',

                    'context_type'    => $wallet->context_type,
                    'context_id'      => $wallet->context_id,

                    'current_balance' => (float) $wallet->current_balance,

                    // UI helper only
                    'latest_statement' => $latestStatement
                        ? [
                            'month'     => $latestStatement->month,
                            'is_locked' => (bool) $latestStatement->is_locked,
                        ]
                        : null,
                ];
            });

        $selectedWalletUuid = (string) $request->get('wallet_uuid', '');
        if ($selectedWalletUuid === '' && $wallets->isNotEmpty()) {
            $selectedWalletUuid = (string) $wallets->first()['uuid'];
        }

        $selectedWallet = null;
        if ($selectedWalletUuid !== '') {
            $selectedWallet = PettyCashWallet::query()
                ->where('uuid', $selectedWalletUuid)
                ->where('is_active', true)
                ->when($branchId !== null, function ($q) use ($branchId) {
                    $q->where(function ($wallet) use ($branchId) {
                        $wallet->where('context_type', 'office')
                            ->orWhereHas('project', fn ($project) => $project->where('branch_id', $branchId));
                    });
                })
                ->first();
        }

        $statementData = $selectedWallet
            ? $this->buildStatementData($selectedWallet, $monthString)
            : null;

        return inertia('PettyCash/Wallets/Index', [
            'wallets' => $wallets,
            'selectedWalletUuid' => $selectedWallet?->uuid,
            'statement' => $statementData,
        ]);
    }

    private function buildStatementData(PettyCashWallet $wallet, string $monthString): array
    {
        $statement = PettyCashStatement::where('wallet_id', $wallet->id)
            ->where('month', $monthString)
            ->first();

        $periodStart = Carbon::createFromFormat('Y-m', $monthString)->startOfMonth();
        $periodEnd = (clone $periodStart)->endOfMonth();

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
                'id' => $tx->id,
                'date' => $tx->transaction_date,
                'code' => $tx->code,
                'reference' => $tx->source_ref_no,
                'title' => $tx->sourceable?->title
                    ?? ($tx->source_type === \App\Models\PettyCashTopup::class ? 'Topup' : null),
                'description' => $tx->sourceable?->description
                    ?? ($tx->source_type === \App\Models\PettyCashTopup::class ? ($tx->sourceable?->reason ?? null) : null),
                'amount_in' => $tx->credit_amount > 0 ? (float) $tx->credit_amount : null,
                'amount_out' => $tx->debit_amount > 0 ? (float) $tx->debit_amount : null,
                'balance_after' => (float) $tx->balance_after,
                'type' => $tx->display_type,
            ]);

        $openingBalance =
            $statement?->opening_balance
            ?? PettyCashTransaction::where('wallet_id', $wallet->id)
                ->where('transaction_date', '<', $periodStart->toDateString())
                ->orderByDesc('transaction_date')
                ->value('balance_after')
            ?? $wallet->opening_balance;

        $totalIn = $transactions->sum('amount_in');
        $totalOut = $transactions->sum('amount_out');

        $closingBalance = $statement
            ? (float) $statement->closing_balance
            : ($openingBalance + $totalIn - $totalOut);

        $claimTypes = ClaimType::query()
            ->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();

        $isCurrentMonth = $monthString === now()->format('Y-m');

        return [
            'wallet' => [
                'id' => $wallet->id,
                'uuid' => $wallet->uuid,
                'name' => $wallet->isOffice()
                    ? 'Office Petty Cash'
                    : optional($wallet->project)->name ?? 'Project Wallet',
            ],
            'period' => [
                'month' => $monthString,
                'is_locked' => !$isCurrentMonth,
                'editable' => $isCurrentMonth,
            ],
            'summary' => [
                'opening' => round((float) $openingBalance, 2),
                'total_in' => round((float) $totalIn, 2),
                'total_out' => round((float) $totalOut, 2),
                'closing' => round((float) $closingBalance, 2),
            ],
            'transactions' => $transactions,
            'claimTypes' => $claimTypes,
        ];
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

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
    }
}

