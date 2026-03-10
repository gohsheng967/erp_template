<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashWallet;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\PettyCashTransaction;
use Illuminate\Http\Request;

class PettyCashController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $this->activeBranchId($request);

        $walletsQuery = PettyCashWallet::query()->where('is_active', true);
        if ($branchId !== null) {
            $walletsQuery->where(function ($q) use ($branchId) {
                $q->where('context_type', 'office')
                    ->orWhereHas('project', fn ($project) => $project->where('branch_id', $branchId));
            });
        }

        $wallets = $walletsQuery->get();
        $walletIds = $wallets->pluck('id');

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        $usageThisMonth = PettyCashTransaction::where('type', 'debit')
            ->whereIn('wallet_id', $walletIds)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('debit_amount');

        $topupThisMonth = PettyCashTransaction::where('type', 'credit')
            ->whereIn('wallet_id', $walletIds)
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

