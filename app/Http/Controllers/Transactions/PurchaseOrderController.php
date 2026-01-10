<?php

namespace App\Http\Controllers\Transactions;
use App\Http\Controllers\Controller;

use App\Models\PurchaseOrder;
use App\Models\CompanyProfile;
use App\Models\PurchaseDelivery;
use App\Models\PurchaseDeliveryItem;
use App\Models\Attachment;
use App\Models\Warehouse;

use Illuminate\Support\Facades\Storage;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use DB;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'from'   => $request->get('from'),
            'to'     => $request->get('to'),
        ];

        $query = PurchaseOrder::query()
            ->with(['supplier', 'items']) // ✅ preload items
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
                    'id'              => $po->id,
                    'uuid'            => $po->uuid,
                    'code'            => $po->code,
                    'created_at'      => $po->created_at,
                    'confirmed_at'    => $po->confirmed_at,
                    'order_date'      => $po->order_date,
                    'supplier'        => $po->supplier,

                    // ✅ REAL DELIVERY DATA
                    'delivered_qty'    => $totalDelivered,
                    'delivery_percent' => $deliveryPercent,

                    // (still placeholder, future phase)
                    'payment_status'   => 'pending',
                ];
            });

        return Inertia::render('Transactions/PurchaseOrder/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters'        => $filters,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    | Used by POShowModal (AJAX)
    |--------------------------------------------------------------------------
    */
    public function show(string $uuid)
    {
        $po = PurchaseOrder::with([
            'supplier',
            'items',
            'purchaseRequest.approver',
            'confirmBy',
            'signedPo'
        ])
        ->where('uuid', $uuid)
        ->firstOrFail();

        return response()->json([
            'po'      => $po,
            'company' => CompanyProfile::first(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE DRAFT
    | Update order_date & expected_delivery_date only
    |--------------------------------------------------------------------------
    */
    public function confirmOrder(Request $request, string $uuid)
    {
        $po = PurchaseOrder::where('uuid', $uuid)->firstOrFail();

        // 1️⃣ Prevent double confirmation
        if ($po->confirmed_at) {
            abort(422, 'Purchase Order already confirmed.');
        }

        // 2️⃣ Validate order date
        $request->validate([
            'order_date' => ['required', 'date'],
            'signed_po'  => ['nullable', 'file', 'mimes:pdf,jpg,png'],
        ]);

        // 3️⃣ Check if signed PO already exists
        $hasSignedPO = $po->attachments()->exists();

        // 4️⃣ If no existing signed PO, require upload
        if (!$hasSignedPO && !$request->hasFile('signed_po')) {
            abort(422, 'Signed PO is required before confirmation.');
        }

        // 5️⃣ Upload signed PO (ONCE ONLY)
        if ($request->hasFile('signed_po')) {

            if ($hasSignedPO) {
                abort(422, 'Signed PO already uploaded.');
            }

            $file = $request->file('signed_po');
            $path = $file->store('purchase-orders/signed', 'public');

            $po->attachments()->create([
                'category'       => 'signed_po',
                'file_path'      => $path,
                'original_name'  => $file->getClientOriginalName(),
            ]);
        }

        $po->order_date   = $request->order_date;
        $po->confirmed_at = now();
        $po->confirmed_by = auth()->id();
        $po->save();

        return response()->json([
            'success' => true,
            'confirmed_at' => $po->confirmed_at,
        ]);
    }



    public function updateTerms(Request $request, string $uuid)
    {
        $po = PurchaseOrder::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'terms' => ['nullable', 'array'],
            'terms.*' => ['nullable', 'string'],
        ]);

        // Clean & normalize
        $terms = collect($validated['terms'] ?? [])
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values()
            ->toArray();

        $po->terms = $terms;
        $po->save();

        return response()->json([
            'success' => true,
            'terms'   => $po->terms,
        ]);
    }

    public function deliveries(string $uuid)
    {
        $purchaseOrder = PurchaseOrder::with([
                'supplier',
                'items',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

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
            'warehouses'    => $warehouses,
        ]);
    }

    public function storeDelivery(Request $request, string $uuid)
    {
        $purchaseOrder = PurchaseOrder::with('items')
            ->where('uuid', $uuid)
            ->firstOrFail();

        /* =========================
        VALIDATION
        ========================= */
        $validated = $request->validate([
            'delivery_date' => ['required', 'date'],
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'status'        => ['required', 'in:transit,warehouse,returned'],
            'delivery_type' => ['required', 'in:partial,full'],
            'warehouse_id'  => ['nullable', 'exists:warehouses,id'],

            'items'         => ['array'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.quantity'               => ['required', 'numeric', 'min:0'],
            'items.*.destination'            => ['nullable', 'string'],
            'items.*.remark'                 => ['nullable', 'string'],

            'attachments.*' => ['file', 'max:10240'], // 10MB
        ]);

        /* =========================
        BUSINESS RULES
        ========================= */
        if ($validated['status'] === 'warehouse' && empty($validated['warehouse_id'])) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Warehouse is required when status is warehouse.',
            ]);
        }

        DB::transaction(function () use ($request, $purchaseOrder, $validated) {

            /* =========================
            CREATE DELIVERY (PD)
            ========================= */
            $delivery = PurchaseDelivery::create([
                'uuid'              => Str::uuid(),
                'purchase_order_id' => $purchaseOrder->id,
                'delivery_date'     => $validated['delivery_date'],
                'title'             => $validated['title'],
                'description'       => $validated['description'] ?? null,
                'status'            => $validated['status'],
                'delivery_type'     => $validated['delivery_type'],
                'created_by'        => auth()->id(),
            ]);

            /* =========================
            ITEMS (WAREHOUSE ONLY)
            ========================= */
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

                    // create delivery item
                    PurchaseDeliveryItem::create([
                        'purchase_delivery_id' => $delivery->id,
                        'purchase_order_item_id' => $poItem->id,
                        'quantity'     => $itemData['quantity'],
                        'destination'  => $itemData['warehouse_id'] ?? null,
                        'remark'       => $itemData['remark'] ?? null,
                    ]);

                    // update delivered qty
                    $poItem->increment('delivered_quantity', $itemData['quantity']);
                }
            }

            /* =========================
            ATTACHMENTS (BIND TO PD)
            ========================= */
            if ($request->hasFile('attachments')) {

                foreach ($request->file('attachments') as $file) {

                    $path = $file->store('purchase-deliveries', 'public');

                    Attachment::create([
                        'category'        => 'purchase_delivery',
                        'attachable_type' => PurchaseDelivery::class, // ✅ CORRECT
                        'attachable_id'   => $delivery->id,           // ✅ CORRECT
                        'file_path'       => $path,
                        'original_name'   => $file->getClientOriginalName(),
                        'mime_type'       => $file->getClientMimeType(),
                        'file_size'       => $file->getSize(),
                        'created_by'      => auth()->id(),
                    ]);
                }
            }

            /* =========================
            AUTO FULL DELIVERY CHECK
            ========================= */
            $totalOrdered   = $purchaseOrder->items->sum('quantity');
            $totalDelivered = $purchaseOrder->items->sum('delivered_quantity');

            if ($totalDelivered >= $totalOrdered) {
                $delivery->update(['delivery_type' => 'full']);
            }
        });

        return back();
    }

    public function destroyDelivery(string $uuid)
    {
        $delivery = PurchaseDelivery::with([
            'items.orderItem',
            'attachments',
        ])
        ->where('uuid', $uuid)
        ->firstOrFail();

        DB::transaction(function () use ($delivery) {

            /* =========================
            REVERSE DELIVERED QTY
            ========================= */
            if ($delivery->status === 'warehouse') {

                foreach ($delivery->items as $item) {

                    $poItem = $item->orderItem;

                    if ($poItem) {
                        $poItem->decrement(
                            'delivered_quantity',
                            $item->quantity
                        );
                    }
                }
            }

            /* =========================
            DELETE ATTACHMENTS
            ========================= */
            foreach ($delivery->attachments as $file) {
                Storage::disk('public')->delete($file->file_path);
                $file->delete();
            }

            /* =========================
            DELETE ITEMS
            ========================= */
            $delivery->items()->delete();

            /* =========================
            DELETE DELIVERY
            ========================= */
            $delivery->delete();
        });

        return back();
    }
}
