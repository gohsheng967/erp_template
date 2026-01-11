<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\InventoryService;
use Illuminate\Validation\ValidationException;


class StockController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/Stocks/Index', [
            'warehouses' => Warehouse::where('status', 1)
                ->orderBy('title')
                ->get(),

            'stocks' => InventoryStock::with([
                    'warehouse:id,title',
                    'purchaseOrderItem:id,item_name,description',
                ])
                ->orderBy('warehouse_id')
                ->get(),
        ]);
    }

    public function movements(Request $request)
    {
        $warehouseId = $request->query('warehouse_id');
        $poItemId    = $request->query('po_item_id');

        $query = InventoryMovement::with([
            'warehouse:id,title',
            'purchaseOrderItem:id,item_name',
        ])->latest();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($poItemId) {
            $query->where('purchase_order_item_id', $poItemId);
        }

        return Inertia::render('Inventory/Stocks/Movements', [
            'warehouses' => Warehouse::where('status', 1)
                ->orderBy('title')
                ->get(),

            'filters' => [
                'warehouse_id' => $warehouseId,
                'po_item_id'   => $poItemId,
            ],

            'movements' => $query
                ->limit(500) // safety
                ->get(),
        ]);
    }

    public function issue(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'remark' => ['nullable', 'string'],
        ]);

        try {
            $inventory->stockOut([
                'warehouse_id' => $data['warehouse_id'],
                'purchase_order_item_id' => $data['purchase_order_item_id'],
                'quantity' => $data['quantity'],
                'remark' => $data['remark'] ?? 'Stock issue',
            ]);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'quantity' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Stock issued successfully');
    }

    public function transfer(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id'   => ['required', 'exists:warehouses,id'],
            'purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'remark'   => ['nullable', 'string'],
        ]);

        try {
            $inventory->transfer([
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'purchase_order_item_id' => $data['purchase_order_item_id'],
                'quantity' => $data['quantity'],
                'remark'   => $data['remark'] ?? 'Warehouse transfer',
            ]);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'quantity' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Stock transferred successfully');
    }

    public function adjust(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'remark' => ['required', 'string', 'max:255'],
        ]);

        try {
            $inventory->adjust([
                'warehouse_id' => $data['warehouse_id'],
                'purchase_order_item_id' => $data['purchase_order_item_id'],
                'quantity' => $data['quantity'], // final actual quantity
                'remark' => $data['remark'],
            ]);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'quantity' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Stock adjusted successfully');
    }

}
