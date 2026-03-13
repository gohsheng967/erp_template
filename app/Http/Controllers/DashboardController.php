<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Services\Dashboard\DashboardAttentionService;
use App\Models\ArInvoice;
use App\Models\ArInvoiceReceipt;
use App\Models\ApInvoice;
use App\Models\ApInvoicePayment;
use App\Models\Claim;
use App\Models\PettyCashWallet;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $attention = (new DashboardAttentionService())->summary();

        $totalReceivables = (float) ArInvoice::whereIn('status', ['issued', 'approved'])
            ->sum('total_amount');

        $receivedReceivables = (float) ArInvoiceReceipt::whereHas('invoice', function ($query) {
            $query->whereIn('status', ['issued', 'approved']);
        })->sum('amount');

        $totalPayables = (float) ApInvoice::whereIn('status', ['confirmed', 'partially_paid'])
            ->sum(DB::raw('COALESCE(balance_amount, invoice_amount - paid_amount)'));

        $cashOnHand = (float) PettyCashWallet::where('is_active', true)
            ->sum('current_balance');

        $activeProjects = Project::whereIn('status', ['on_going', 'late', 'extended'])->count();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $incoming = (float) ArInvoiceReceipt::whereBetween('received_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $outgoing = (float) ApInvoicePayment::whereNull('cancelled_at')
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('payment_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($query) use ($startOfMonth, $endOfMonth) {
                        $query->whereNull('payment_date')
                            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                    });
            })
            ->sum('amount');

        $incomingByDay = ArInvoiceReceipt::selectRaw('DATE(received_at) as day, SUM(amount) as total')
            ->whereBetween('received_at', [$startOfMonth, $endOfMonth])
            ->groupBy('day')
            ->pluck('total', 'day');

        $outgoingByDay = ApInvoicePayment::selectRaw('DATE(COALESCE(payment_date, created_at)) as day, SUM(amount) as total')
            ->whereNull('cancelled_at')
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('payment_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($query) use ($startOfMonth, $endOfMonth) {
                        $query->whereNull('payment_date')
                            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                    });
            })
            ->groupBy('day')
            ->pluck('total', 'day');

        $trendLabels = [];
        $trendIncoming = [];
        $trendOutgoing = [];

        foreach (CarbonPeriod::create($startOfMonth, $endOfMonth) as $date) {
            $key = $date->toDateString();
            $trendLabels[] = $date->format('d');
            $trendIncoming[] = (float) ($incomingByDay[$key] ?? 0);
            $trendOutgoing[] = (float) ($outgoingByDay[$key] ?? 0);
        }

        $activities = collect();

        PurchaseRequest::latest()
            ->limit(5)
            ->get(['uuid', 'code', 'title', 'created_at'])
            ->each(function ($pr) use ($activities) {
                $label = $pr->code ? "PR {$pr->code}" : 'PR';
                $activities->push([
                    'id' => "pr-{$pr->uuid}",
                    'message' => "{$label} - {$pr->title}",
                    'time' => $pr->created_at?->diffForHumans(),
                    'timestamp' => $pr->created_at?->timestamp ?? 0,
                    'route' => 'purchase-request.show',
                    'params' => ['uuid' => $pr->uuid],
                    'icon' => 'mdi-clipboard-text-outline',
                ]);
            });

        PurchaseOrder::latest()
            ->limit(5)
            ->get(['uuid', 'code', 'created_at'])
            ->each(function ($po) use ($activities) {
                $label = $po->code ? "PO {$po->code}" : 'PO';
                $activities->push([
                    'id' => "po-{$po->uuid}",
                    'message' => "{$label} created",
                    'time' => $po->created_at?->diffForHumans(),
                    'timestamp' => $po->created_at?->timestamp ?? 0,
                    'route' => 'purchase-orders.show',
                    'params' => ['uuid' => $po->uuid],
                    'icon' => 'mdi-clipboard-check-outline',
                ]);
            });

        ArInvoice::latest()
            ->limit(5)
            ->get(['uuid', 'invoice_no', 'title', 'created_at'])
            ->each(function ($invoice) use ($activities) {
                $label = $invoice->invoice_no ? "AR {$invoice->invoice_no}" : 'AR Invoice';
                $title = $invoice->title ? " - {$invoice->title}" : '';
                $activities->push([
                    'id' => "ar-{$invoice->uuid}",
                    'message' => "{$label}{$title}",
                    'time' => $invoice->created_at?->diffForHumans(),
                    'timestamp' => $invoice->created_at?->timestamp ?? 0,
                    'route' => 'ar-invoices.show',
                    'params' => ['invoice' => $invoice->uuid],
                    'icon' => 'mdi-file-document-outline',
                ]);
            });

        ApInvoicePayment::latest()
            ->limit(5)
            ->with('invoice:id,uuid,invoice_number')
            ->get()
            ->each(function ($payment) use ($activities) {
                $label = $payment->invoice?->invoice_number
                    ? "AP {$payment->invoice->invoice_number}"
                    : 'AP Invoice';
                $routeParams = $payment->invoice?->uuid
                    ? ['uuid' => $payment->invoice->uuid]
                    : null;
                $activities->push([
                    'id' => "ap-payment-{$payment->id}",
                    'message' => "Payment recorded for {$label}",
                    'time' => $payment->created_at?->diffForHumans(),
                    'timestamp' => $payment->created_at?->timestamp ?? 0,
                    'route' => $routeParams ? 'ap-invoices.show' : null,
                    'params' => $routeParams,
                    'icon' => 'mdi-cash-check',
                ]);
            });

        Claim::latest()
            ->limit(5)
            ->get(['id', 'title', 'created_at'])
            ->each(function ($claim) use ($activities) {
                $activities->push([
                    'id' => "claim-{$claim->id}",
                    'message' => "Claim submitted - {$claim->title}",
                    'time' => $claim->created_at?->diffForHumans(),
                    'timestamp' => $claim->created_at?->timestamp ?? 0,
                    'route' => 'claims.show',
                    'params' => ['claim' => $claim->id],
                    'icon' => 'mdi-receipt-text-outline',
                ]);
            });

        $recentActivity = $activities
            ->sortByDesc('timestamp')
            ->take(5)
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'attention' => $attention,
            'stats' => [
                'receivables' => max($totalReceivables - $receivedReceivables, 0),
                'payables' => max($totalPayables, 0),
                'cash_on_hand' => $cashOnHand,
                'active_projects' => $activeProjects,
            ],
            'cashflow' => [
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'net' => $incoming - $outgoing,
                'trend' => [
                    'labels' => $trendLabels,
                    'incoming' => $trendIncoming,
                    'outgoing' => $trendOutgoing,
                ],
            ],
            'recentActivity' => $recentActivity,
        ]);
    }
}
