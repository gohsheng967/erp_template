<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PettyCashWallet;
use App\Models\PettyCashTransaction;
use App\Models\PettyCashStatement;
use Carbon\Carbon;

class PettyCashWalletController extends Controller
{
    /**
     * =====================================================
     * WALLET INDEX
     * Entry page to Balance Statement module
     * =====================================================
     */
    public function index()
    {
        $wallets = PettyCashWallet::query()
            ->where('is_active', true)
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
                            'is_locked' => true,
                        ]
                        : null,
                ];
            });

        return inertia('PettyCash/Wallets/Index', [
            'wallets' => $wallets,
        ]);
    }
}
