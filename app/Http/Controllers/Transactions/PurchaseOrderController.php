<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\CompanyProfile;
use App\Models\PurchaseDelivery;
use App\Models\PurchaseDeliveryItem;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Services\InventoryService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'from' => $request->get('from'),
            'to' => $request->get('to'),
        ];

        $query = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->when($filters['search'], function ($q) use ($filters) {
                $q->where(function ($qq) use ($filters) {
                    $qq->where('code', 'like', "%{$filters['search']}%")
                        ->orWhereHas('supplier', fn ($s) =>
                            $s->where('company_name', 'like', "%{$filters['search']}%")
                        );
                });
            })
            ->when($filters['from'], fn ($q) =>
                $q->whereDate('order_date', '>=', $filters['from'])
            )
            ->when($filters['to'], fn ($q) =>
                $q->whereDate('order_date', '<=', $filters['to'])
            );

        if ($this->shouldScopeToActiveBranch($request)) {
            $query->whereHas('purchaseRequest', function ($q) use ($request) {
                $q->where('branch_id', $this->activeBranchId($request));
            });
        }

        $purchaseOrders = $query
            ->latest('order_date')
            ->paginate(10)
            ->through(function ($po) {
                $totalOrdered = $po->items->sum('quantity');
                $totalDelivered = $po->items->sum('delivered_quantity');

                $deliveryPercent = $totalOrdered > 0
                    ? min(100, round(($totalDelivered / $totalOrdered) * 100))
                    : 0;

                return [
                    'id' => $po->id,
                    'uuid' => $po->uuid,
                    'code' => $po->code,
                    'status' => $po->status,
                    'created_at' => $po->created_at,
                    'confirmed_at' => $po->confirmed_at,
                    'order_date' => $po->order_date,
                    'supplier' => $po->supplier,
                    'delivered_qty' => $totalDelivered,
                    'delivery_percent' => $deliveryPercent,
                    'payment_status' => 'pending',
                    'items' => $po->items,
                ];
            });

        return Inertia::render('Transactions/PurchaseOrder/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, string $uuid)
    {
        $poQuery = PurchaseOrder::with([
            'supplier',
            'items',
            'purchaseRequest.approver',
            'confirmBy',
            'signedPo',
        ])->where('uuid', $uuid);

        if ($this->shouldScopeToActiveBranch($request)) {
            $poQuery->whereHas('purchaseRequest', function ($q) use ($request) {
                $q->where('branch_id', $this->activeBranchId($request));
            });
        }

        $po = $poQuery->firstOrFail();

        return response()->json([
            'po' => $po,
            'company' => CompanyProfile::first(),
        ]);
    }

    public function confirmOrder(Request $request, string $uuid)
    {
        $poQuery = PurchaseOrder::where('uuid', $uuid);

        if ($this->shouldScopeToActiveBranch($request)) {
            $poQuery->whereHas('purchaseRequest', function ($q) use ($request) {
                $q->where('branch_id', $this->activeBranchId($request));
            });
        }

        $po = $poQuery->firstOrFail();

        if ($po->status !== 'issued') {
            throw ValidationException::withMessages([
                'status' => 'Only issued purchase orders can be confirmed.',
            ]);
        }

        if ($po->confirmed_at) {
            throw ValidationException::withMessages([
                'status' => 'Purchase order already confirmed.',
            ]);
        }

        $data = $request->validate([
            'order_date' => ['required', 'date'],
            'signed_po' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        DB::transaction(function () use ($po, $data, $request) {
            $hasSignedPO = $po->attachments()->exists();

            if (!$hasSignedPO && !$request->hasFile('signed_po')) {
                throw ValidationException::withMessages([
                    'signed_po' => 'Signed PO is required before confirmation.',
                ]);
            }

            if ($request->hasFile('signed_po')) {
                if ($hasSignedPO) {
                    throw ValidationException::withMessages([
                        'signed_po' => 'Signed PO already uploaded.',
                    ]);
                }

                $file = $request->file('signed_po');
                $path = $file->store('purchase-orders/signed', 'public');

                $po->attachments()->create([
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'created_by' => auth()->id(),
                ]);
            }

            $po->update([
                'order_date' => $data['order_date'],
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'success' => true,
            'status' => 'confirmed',
        ]);
    }

    public function updateTerms(Request $request, string $uuid)
    {
        $poQuery = PurchaseOrder::where('uuid', $uuid);

        if ($this->shouldScopeToActiveBranch($request)) {
            $poQuery->whereHas('purchaseRequest', function ($q) use ($request) {
                $q->where('branch_id', $this->activeBranchId($request));
            });
        }

        $po = $poQuery->firstOrFail();

        $validated = $request->validate([
            'terms' => ['nullable', 'array'],
            'terms.*' => ['nullable', 'string'],
        ]);

        $terms = collect($validated['terms'] ?? [])
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values()
            ->toArray();

        $po->terms = $terms;
        $po->save();

        return response()->json([
            'success' => true,
            'terms' => $po->terms,
        ]);
    }

    public function deliveries(Request $request, string $uuid)
    {
        $purchaseOrderQuery = PurchaseOrder::with([
            'supplier',
            'items',
        ])->where('uuid', $uuid);

        if ($this->shouldScopeToActiveBranch($request)) {
            $purchaseOrderQuery->whereHas('purchaseRequest', function ($q) use ($request) {
                $q->where('branch_id', $this->activeBranchId($request));
            });
        }

        $purchaseOrder = $purchaseOrderQuery->firstOrFail();

        $deliveries = PurchaseDelivery::with([
            'items.orderItem',
            'creator',
            'attachments',
            'items.warehouse',
        ])
            ->where('purchase_order_id', $purchaseOrder->id)
            ->orderBy('delivery_date')
            ->get();

        $warehouses = Warehouse::select('id', 'title', 'address')
            ->orderBy('title')
            ->get();

        return inertia('Transactions/PurchaseOrder/Deliveries/Index', [
            'purchaseOrder' => $purchaseOrder,
            'deliveries' => $deliveries,
            'warehouses' => $warehouses,
        ]);
    }

    public function storeDelivery(Request $request, string $uuid)
    {
        $purchaseOrderQuery = PurchaseOrder::with('items')
            ->where('uuid', $uuid);

        if ($this->shouldScopeToActiveBranch($request)) {
            $purchaseOrderQuery->whereHas('purchaseRequest', function ($q) use ($request) {
                $q->where('branch_id', $this->activeBranchId($request));
            });
        }

        $purchaseOrder = $purchaseOrderQuery->firstOrFail();

        $validated = $request->validate([
            'delivery_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:transit,warehouse,returned'],
            'delivery_type' => ['required', 'in:partial,full'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'items' => ['array'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.destination' => ['nullable', 'string'],
            'items.*.remark' => ['nullable', 'string'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if ($validated['status'] === 'warehouse' && empty($validated['warehouse_id'])) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Warehouse is required when status is warehouse.',
            ]);
        }

        DB::transaction(function () use ($request, $purchaseOrder, $validated) {
            $delivery = PurchaseDelivery::create([
                'uuid' => Str::uuid(),
                'purchase_order_id' => $purchaseOrder->id,
                'delivery_date' => $validated['delivery_date'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'delivery_type' => $validated['delivery_type'],
                'created_by' => auth()->id(),
            ]);

            if ($validated['status'] === 'warehouse') {
                foreach ($validated['items'] as $itemData) {
                    if ((float) $itemData['quantity'] <= 0) {
                        continue;
                    }

                    $poItem = $purchaseOrder->items
                        ->firstWhere('id', $itemData['purchase_order_item_id']);

                    if (!$poItem) {
                        throw ValidationException::withMessages([
                            'items' => 'Invalid purchase order item.',
                        ]);
                    }

                    $remaining = $poItem->quantity - ($poItem->delivered_quantity ?? 0);

                    if ($itemData['quantity'] > $remaining) {
                        throw ValidationException::withMessages([
                            'quantity' => "Delivered quantity exceeds remaining for item {$poItem->item_name}.",
                        ]);
                    }

                    $deliveryItem = PurchaseDeliveryItem::create([
                        'purchase_delivery_id' => $delivery->id,
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => $itemData['quantity'],
                        'destination' => $validated['warehouse_id'] ?? null,
                        'remark' => $itemData['remark'] ?? null,
                    ]);

                    $inventory = app(InventoryService::class);
                    $inventory->stockIn([
                        'warehouse_id' => $validated['warehouse_id'],
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => $itemData['quantity'],
                        'reference_type' => PurchaseDeliveryItem::class,
                        'reference_id' => $deliveryItem->id,
                        'remark' => 'Purchase delivery',
                    ]);

                    $poItem->increment('delivered_quantity', $itemData['quantity']);
                }
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase-deliveries', 'public');

                    Attachment::create([
                        'attachable_type' => PurchaseDelivery::class,
                        'attachable_id' => $delivery->id,
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $totalOrdered = $purchaseOrder->items->sum('quantity');
            $totalDelivered = $purchaseOrder->items->sum('delivered_quantity');

            if ($totalDelivered >= $totalOrdered) {
                $delivery->update(['delivery_type' => 'full']);
            }
        });

        return back();
    }

    public function destroyDelivery(Request $request, string $uuid)
    {
        $deliveryQuery = PurchaseDelivery::with([
            'items.orderItem',
            'attachments',
        ])->where('uuid', $uuid);

        if ($this->shouldScopeToActiveBranch($request)) {
            $deliveryQuery->whereHas('purchaseOrder.purchaseRequest', function ($q) use ($request) {
                $q->where('branch_id', $this->activeBranchId($request));
            });
        }

        $delivery = $deliveryQuery->firstOrFail();

        DB::transaction(function () use ($delivery) {
            if ($delivery->status === 'warehouse') {
                foreach ($delivery->items as $item) {
                    $poItem = $item->orderItem;

                    if ($poItem) {
                        $poItem->decrement('delivered_quantity', $item->quantity);
                    }

                    $inventory = app(InventoryService::class);
                    $inventory->stockOut([
                        'warehouse_id' => $item->destination,
                        'purchase_order_item_id' => $item->purchase_order_item_id,
                        'quantity' => $item->quantity,
                        'reference_type' => PurchaseDeliveryItem::class,
                        'reference_id' => $item->id,
                        'remark' => 'Delivery cancelled',
                    ]);
                }
            }

            foreach ($delivery->attachments as $file) {
                Storage::disk('public')->delete($file->file_path);
                $file->delete();
            }

            $delivery->items()->delete();
            $delivery->delete();
        });

        return back();
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

