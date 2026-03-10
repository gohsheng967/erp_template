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
                    $this->arInvoiceDueOrOverdue(),
                    $this->arInvoicePendingCollectionFromTerms(),
                    $this->apInvoiceDueOrOverdue(),
                    $this->projectPastExtensionDate(),
                ])),
                'warning' => array_values(array_filter([
                    $this->arInvoiceDueSoon(),
                    $this->apInvoiceDueSoon(),
                    $this->lowPettyCash(),
                    $this->pendingPurchaseOrders(),
                    $this->vehicleInsuranceExpiring(),
                    $this->projectNearEndOrWithinExtension(),
                ])),
            ]
        );
    }

    protected function cacheKey(): string
    {
        return 'dashboard.attention.user.v4.' . $this->user->id;
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
            'icon'    => 'mdi-clipboard-alert-outline',
            'message' => "{$count} claims pending approval (over 5 days)",
            'route'   => 'claims.index',
            'params'  => ['status' => 'submitted'],
        ];
    }

    /**
     * AR due today or overdue (approved only)
     */
    protected function arInvoiceDueOrOverdue(): ?array
    {
        if (!class_exists(\App\Models\ArInvoice::class)) {
            return null;
        }

        $count = \App\Models\ArInvoice::where('status', 'approved')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now())
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'critical',
            'icon'    => 'mdi-file-document-alert-outline',
            'message' => "{$count} receivable invoice(s) due or overdue",
            'route'   => 'ar-invoices.index',
            'params'  => ['tab' => 'approved'],
        ];
    }

    /**
     * AR pending collection based on payment terms (when due_date is missing)
     */
    protected function arInvoicePendingCollectionFromTerms(): ?array
    {
        if (!class_exists(\App\Models\ArInvoice::class)) {
            return null;
        }

        $count = \App\Models\ArInvoice::where('status', 'approved')
            ->whereNull('due_date')
            ->whereNotNull('payment_term_days')
            ->whereRaw(
                'DATE_ADD(COALESCE(issued_at, approved_at), INTERVAL payment_term_days DAY) <= ?',
                [now()->toDateTimeString()]
            )
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'critical',
            'icon'    => 'mdi-cash-check',
            'message' => "{$count} receivable invoice(s) pending collection (terms reached)",
            'route'   => 'ar-invoices.index',
            'params'  => ['tab' => 'approved'],
        ];
    }

    /**
     * AR due soon (next 5 days, approved only)
     */
    protected function arInvoiceDueSoon(): ?array
    {
        if (!class_exists(\App\Models\ArInvoice::class)) {
            return null;
        }

        $days = 5;

        $count = \App\Models\ArInvoice::where('status', 'approved')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->addDay(), now()->addDays($days)])
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'warning',
            'icon'    => 'mdi-calendar-clock-outline',
            'message' => "{$count} receivable invoice(s) due within {$days} days",
            'route'   => 'ar-invoices.index',
            'params'  => ['tab' => 'approved'],
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
            'icon'    => 'mdi-cash-remove',
            'message' => "{$count} payable invoice(s) due or overdue",
            'route'   => 'ap-invoices.index',
            'params'  => ['status' => 'issued', 'filter' => 'due'],
        ];
    }

    protected function projectPastExtensionDate(): ?array
    {
        if (!class_exists(\App\Models\Project::class)) {
            return null;
        }

        $today = now()->toDateString();

        $count = \App\Models\Project::query()
            ->where('is_finished', false)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('end_date')
            ->where(function ($query) use ($today) {
                $query->where(function ($subQuery) use ($today) {
                    $subQuery->whereNotNull('extension_date')
                        ->whereDate('extension_date', '<', $today);
                })->orWhere(function ($subQuery) use ($today) {
                    $subQuery->whereNull('extension_date')
                        ->whereDate('end_date', '<', $today);
                });
            })
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'critical',
            'icon'    => 'mdi-calendar-remove',
            'message' => "{$count} project(s) past end/extension date and not flagged as finished",
            'route'   => 'projects.index',
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
            'icon'    => 'mdi-cash-clock',
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
            'icon'    => 'mdi-wallet-outline',
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
            'icon'    => 'mdi-clipboard-text-clock-outline',
            'message' => "{$count} purchase order(s) awaiting confirmation",
            'route'   => 'purchase-orders.index',
        ];
    }

    /**
     * Vehicle insurance expiring within 30 days
     */
    protected function vehicleInsuranceExpiring(): ?array
    {
        if (!class_exists(\App\Models\InventoryItem::class)) {
            return null;
        }

        $days = 30;

        $count = \App\Models\InventoryItem::where('type', 'vehicle')
            ->whereHas('latestInsurance', function ($query) use ($days) {
                $query->whereNotNull('expiry_date')
                    ->where('status', 'active')
                    ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
            })
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'warning',
            'icon'    => 'mdi-car-outline',
            'message' => "{$count} vehicle insurance(s) expiring within {$days} days",
            'route'   => 'inventory.vehicles.index',
            'params'  => ['insurance_expiring' => true],
        ];
    }

    protected function projectNearEndOrWithinExtension(): ?array
    {
        if (!class_exists(\App\Models\Project::class)) {
            return null;
        }

        $nearDays = 7;
        $today = now()->toDateString();
        $nearDate = now()->addDays($nearDays)->toDateString();

        $count = \App\Models\Project::query()
            ->where('is_finished', false)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('end_date')
            ->where(function ($query) use ($today, $nearDate) {
                $query->whereBetween('end_date', [$today, $nearDate])
                    ->orWhere(function ($subQuery) use ($today) {
                        $subQuery->whereDate('end_date', '<', $today)
                            ->whereNotNull('extension_date')
                            ->whereDate('extension_date', '>=', $today);
                    });
            })
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level'   => 'warning',
            'icon'    => 'mdi-calendar-alert',
            'message' => "{$count} project(s) near end date or currently within extension date",
            'route'   => 'projects.index',
        ];
    }
}
