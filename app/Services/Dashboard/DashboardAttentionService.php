<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class DashboardAttentionService
{
    protected $user;

    public function __construct($user = null)
    {
        $this->user = $user ?? Auth::user();
    }

    /**
     * Main entry for dashboard
     */
    public function summary(): array
    {
        return Cache::remember(
            $this->cacheKey(),
            now()->addMinutes(5),
            fn () => [
                'critical' => array_values(array_filter([
                    $this->claimsPendingApproval(),
                    $this->staleArInvoices(),
                    $this->apInvoiceDueOrOverdue(),
                ])),
                'warning' => array_values(array_filter([
                    $this->apInvoiceDueSoon(),
                    $this->lowPettyCash(),
                    $this->pendingPurchaseOrders(),
                    $this->vehicleInsuranceExpiring(),
                ])),
            ]
        );
    }

    protected function cacheKey(): string
    {
        return 'dashboard.attention.user.' . $this->user->id;
    }

    /* ==========================================================
       🔴 CRITICAL
    ========================================================== */

    protected function claimsPendingApproval(): ?array
    {
        if (!class_exists(\App\Models\Claim::class)) {
            return null;
        }

        $count = \App\Models\Claim::where('status', 'submitted')
            ->where('submitted_at', '<=', now()->subDays(5))
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'critical',
            'message' => "{$count} claims pending approval (over 5 days)",
            'route'   => 'claims.index',
            'params'  => ['status' => 'submitted'],
        ];
    }

    /**
     * AR has no due date → age-based risk
     */
    protected function staleArInvoices(): ?array
    {
        if (!class_exists(\App\Models\ArInvoice::class)) {
            return null;
        }

        $days = 14;

        $count = \App\Models\ArInvoice::where('status', 'issued')
            ->where('issued_at', '<=', now()->subDays($days))
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'critical',
            'message' => "{$count} AR invoice(s) unpaid for over {$days} days",
            'route'   => 'ar-invoices.index',
            'params'  => ['status' => 'issued'],
        ];
    }

    /**
     * AP due today or overdue
     */
    protected function apInvoiceDueOrOverdue(): ?array
    {
        if (!class_exists(\App\Models\ApInvoice::class)) {
            return null;
        }

        $count = \App\Models\ApInvoice::where('status', 'issued')
            ->whereDate('due_date', '<=', now())
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'critical',
            'message' => "{$count} payable invoice(s) due or overdue",
            'route'   => 'ap-invoices.index',
            'params'  => ['status' => 'issued', 'filter' => 'due'],
        ];
    }

    /* ==========================================================
       🟠 WARNING
    ========================================================== */

    /**
     * AP due soon (next 7 days)
     */
    protected function apInvoiceDueSoon(): ?array
    {
        if (!class_exists(\App\Models\ApInvoice::class)) {
            return null;
        }

        $days = 7;

        $count = \App\Models\ApInvoice::where('status', 'issued')
            ->whereBetween('due_date', [now()->addDay(), now()->addDays($days)])
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'warning',
            'message' => "{$count} payable invoice(s) due within {$days} days",
            'route'   => 'ap-invoices.index',
            'params'  => ['status' => 'issued', 'filter' => 'due_soon'],
        ];
    }

    protected function lowPettyCash(): ?array
    {
        if (!class_exists(\App\Models\PettyCashWallet::class)) {
            return null;
        }

        $minimum = 200;

        $wallets = \App\Models\PettyCashWallet::where('current_balance', '<', $minimum)
            ->count();

        if ($wallets === 0) {
            return null;
        }

        return [
            'level'   => 'warning',
            'message' => "{$wallets} petty cash wallet(s) below RM {$minimum}",
            'route'   => 'petty-cash.wallets.index',
        ];
    }

    protected function pendingPurchaseOrders(): ?array
    {
        if (!class_exists(\App\Models\PurchaseOrder::class)) {
            return null;
        }

        $count = \App\Models\PurchaseOrder::whereNull('confirmed_at')
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'warning',
            'message' => "{$count} purchase order(s) awaiting confirmation",
            'route'   => 'purchase-orders.index',
        ];
    }

    /**
     * Vehicle insurance expiring within 30 days
     */
    protected function vehicleInsuranceExpiring(): ?array
    {
        if (!class_exists(\App\Models\Vehicle::class)) {
            return null;
        }

        $days = 30;

        $count = \App\Models\Vehicle::whereNotNull('insurance_expiry_date')
            ->whereBetween('insurance_expiry_date', [now(), now()->addDays($days)])
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'warning',
            'message' => "{$count} vehicle insurance(s) expiring within {$days} days",
            'route'   => 'vehicles.index',
            'params'  => ['filter' => 'insurance_expiring'],
        ];
    }
}
