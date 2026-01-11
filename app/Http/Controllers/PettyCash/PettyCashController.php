<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashWallet;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\PettyCashTransaction;

class PettyCashController extends Controller
{
    public function index()
    {
        $wallets = PettyCashWallet::where('is_active', true)->get();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        $usageThisMonth = PettyCashTransaction::where('type', 'debit')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('debit_amount');

        $topupThisMonth = PettyCashTransaction::where('type', 'credit')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('credit_amount');

        return inertia('PettyCash/Index', [
            'wallets' => $wallets,
            'stats' => [
                'usage_this_month' => (float) $usageThisMonth,
                'topup_this_month' => (float) $topupThisMonth,
            ],
        ]);
    }
}
