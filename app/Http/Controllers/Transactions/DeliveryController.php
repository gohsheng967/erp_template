<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->get('tab'), ['in_progress', 'delivered'], true)
            ? $request->get('tab')
            : 'in_progress';

        $filters = [
            'tab' => $tab,
            'search' => trim((string) $request->get('search', '')),
            'project_id' => $request->get('project_id'),
            'supplier_id' => $request->get('supplier_id'),
            'from' => $request->get('from'),
            'to' => $request->get('to'),
        ];

        $baseQuery = PurchaseOrder::query()
            ->with([
                'supplier:id,company_name',
                'purchaseRequest.project:id,name',
                'deliveries' => fn ($q) => $q
                    ->select('id', 'purchase_order_id', 'status', 'eod_date', 'delivery_date')
                    ->orderByDesc('delivery_date')
                    ->orderByDesc('id'),
            ])
            ->withSum('items as total_ordered_qty', 'quantity')
            ->withSum('items as total_delivered_qty', 'delivered_quantity')
            ->whereIn('status', ['issued', 'confirmed']);

        if ($this->shouldScopeToActiveBranch($request)) {
            $baseQuery->whereHas('purchaseRequest', function ($q) use ($request) {
                $q->where('branch_id', $this->activeBranchId($request));
            });
        }

        if ($filters['search'] !== '') {
            $like = '%' . $filters['search'] . '%';
            $baseQuery->where(function ($q) use ($like) {
                $q->where('code', 'like', $like)
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('company_name', 'like', $like))
                    ->orWhereHas('purchaseRequest.project', fn ($pq) => $pq->where('name', 'like', $like));
            });
        }

        if (!empty($filters['project_id'])) {
            $baseQuery->whereHas('purchaseRequest', fn ($q) => $q->where('project_id', $filters['project_id']));
        }

        if (!empty($filters['supplier_id'])) {
            $baseQuery->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['from'])) {
            $baseQuery->whereDate('order_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $baseQuery->whereDate('order_date', '<=', $filters['to']);
        }

        $orderedSubquery = '(select coalesce(sum(poi.quantity), 0) from purchase_order_items poi where poi.purchase_order_id = purchase_orders.id)';
        $deliveredSubquery = '(select coalesce(sum(poi.delivered_quantity), 0) from purchase_order_items poi where poi.purchase_order_id = purchase_orders.id)';

        $inProgressQuery = (clone $baseQuery)
            ->whereRaw("{$orderedSubquery} > {$deliveredSubquery}");

        $deliveredQuery = (clone $baseQuery)
            ->whereRaw("{$orderedSubquery} > 0")
            ->whereRaw("{$deliveredSubquery} >= {$orderedSubquery}");

        $rows = ($tab === 'delivered' ? $deliveredQuery : $inProgressQuery)
            ->latest('order_date')
            ->paginate(15)
            ->withQueryString()
            ->through(function (PurchaseOrder $po) {
                $ordered = (float) ($po->total_ordered_qty ?? 0);
                $delivered = (float) ($po->total_delivered_qty ?? 0);
                $percent = $ordered > 0 ? min(100, round(($delivered / $ordered) * 100)) : 0;
                $latestDelivery = $po->deliveries->first();
                $currentStatus = $latestDelivery?->status;
                $currentEodDate = $latestDelivery?->eod_date?->toDateString();
                $isDueAlert = $currentStatus === 'preparation'
                    && !empty($currentEodDate)
                    && now()->toDateString() >= $currentEodDate;

                return [
                    'id' => $po->id,
                    'uuid' => $po->uuid,
                    'code' => $po->code,
                    'order_date' => $po->order_date,
                    'status' => $po->status,
                    'supplier' => $po->supplier?->company_name ?? '-',
                    'project' => $po->purchaseRequest?->project?->name ?? 'Others',
                    'ordered_qty' => $ordered,
                    'delivered_qty' => $delivered,
                    'remaining_qty' => max($ordered - $delivered, 0),
                    'delivery_percent' => $percent,
                    'current_status' => $currentStatus,
                    'current_eod_date' => $currentEodDate,
                    'is_due_alert' => $isDueAlert,
                ];
            });

        return Inertia::render('Transactions/Deliveries/Index', [
            'deliveries' => $rows,
            'filters' => $filters,
            'counts' => [
                'in_progress' => (clone $inProgressQuery)->count(),
                'delivered' => (clone $deliveredQuery)->count(),
            ],
            'projects' => Project::query()
                ->when($this->shouldScopeToActiveBranch($request), fn ($q) => $q->where('branch_id', $this->activeBranchId($request)))
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'suppliers' => Supplier::query()
                ->select('id', 'company_name')
                ->orderBy('company_name')
                ->get(),
        ]);
    }

    private function activeBranchId(Request $request): int
    {
        $branchId = (int) ($request->user()?->active_branch_id ?? 0);

        if ($branchId <= 0) {
            abort(403, 'Active branch is required.');
        }

        return $branchId;
    }

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
    }
}
