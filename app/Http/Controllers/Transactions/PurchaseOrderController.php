<?php

namespace App\Http\Controllers\Transactions;
use App\Http\Controllers\Controller;

use App\Models\PurchaseOrder;
use App\Models\CompanyProfile;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use DB;

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
            ->with(['supplier'])
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
            ->through(fn ($po) => [
                'id'    => $po->id,
                'uuid'  => $po->uuid,
                'code'  => $po->code,
                'created_at' => $po->created_at,
                'confirmed_at' => $po->confirmed_at,
                'order_date' => $po->order_date,
                'supplier'   => $po->supplier,

                // Phase 1 placeholders
                'delivered_qty'    => 0,
                'delivery_percent' => 0,
                'payment_status'   => 'pending',
            ]);

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

}
