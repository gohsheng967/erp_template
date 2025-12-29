<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\PurchaseQuotation;
use App\Models\Attachment;
use App\Models\Supplier;

use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

        // SEARCH FILTER
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('contact_phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $suppliers = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return inertia('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only('search'),
        ]);
    }


    /* =========================
       CREATE
    ========================= */

    public function create()
    {
        return inertia('Suppliers/Create');
    }

    /* =========================
       STORE
    ========================= */

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name'     => 'required|string|max:255',
            'registration_no'  => 'required|string|max:100|unique:'.Supplier::class,
            'contact_person'   => 'nullable|string|max:255',
            'contact_phone'    => 'nullable|string|max:50',
            'email'            => 'nullable|email|max:255',
            'address'          => 'nullable|string',
            'status'           => 'nullable|string',
        ]);

        Supplier::create($data);

        return back()->with('success', 'Supplier created successfully.');
    }

    public function show(string $uuid)
    {
        $supplier = Supplier::where('uuid', $uuid)->firstOrFail();

        $quotations = $supplier->quotations()
            ->with([
                'attachment',
                'purchaseRequests:id,code'
            ])
            ->withCount('purchaseRequests as pr_count')
            ->latest()
            ->paginate(10);


        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'quotations' => $quotations,
            'stats' => [
                'quotations'  => $quotations->count(),
                'orders'      => $supplier->purchaseOrders()->count(),
                'invoices'    => 0,
                'total_spend' => 0,
                'currency'    => 'MYR',
            ],
        ]);
    }

    public function update(Request $request, string $uuid)
    {
        $supplier = Supplier::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'company_name'     => 'required|string|max:255',
            'registration_no'  => 'nullable|string|max:100',
            'contact_person'   => 'nullable|string|max:255',
            'contact_phone'    => 'nullable|string|max:50',
            'email'            => 'nullable|email|max:255',
            'address'          => 'nullable|string',
            'status'           => 'required|string',
        ]);

        $supplier->update($data);

        return back()->with('success', 'Supplier updated successfully.');
    }


    /* =========================
       SEARCH (Autocomplete)
       Used by PR quotation
    ========================= */

    public function search(Request $request)
    {
        $q = $request->get('q');

        if (!$q) {
            return collect();
        }

        return Supplier::where('status', 'active')
            ->where('company_name', 'like', "%{$q}%")
            ->orderBy('company_name')
            ->limit(10)
            ->get([
                'id',        // FK usage
                'uuid',      // routing
                'company_name',
                'contact_person',
                'contact_phone',
            ]);
    }

    /* =========================
       INLINE STORE
       Used inside PR quotation
    ========================= */

    public function inlineStore(Request $request)
    {
        $data = $request->validate([
            'company_name'   => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone'  => 'nullable|string|max:50',
        ]);

        $supplier = Supplier::create($data);

        return response()->json([
            'id'             => $supplier->id,    // FK
            'uuid'           => $supplier->uuid,  // route
            'company_name'   => $supplier->company_name,
            'contact_person' => $supplier->contact_person,
            'contact_phone'  => $supplier->contact_phone,
        ]);
    }

    public function destroy(string $uuid)
    {
        $supplier = Supplier::where('uuid', $uuid)->firstOrFail();

        if (
            $supplier->quotations()->exists() ||
            $supplier->purchaseOrders()->exists() ||
            $supplier->invoices()->exists()
        ) {
            return back()->withErrors([
                'delete' => 'Supplier cannot be deleted because it is already in use.',
            ]);
        }

        $supplier->delete();

        return back()->with('success', 'Supplier deleted successfully.');
    }

    public function updateNote(Request $request, $uuid)
    {
        $supplier = Supplier::where('uuid', $uuid)->firstOrFail();

        $supplier->update([
            'internal_note' => $request->string('internal_note'),
        ]);

        return back();
    }

    public function upload(Request $request, string $uuid)
    {
        $request->validate([
            'quotations' => ['required', 'array'],
            'quotations.*.file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'quotations.*.amount' => ['required', 'numeric', 'min:0'],
            'quotations.*.quotation_no' => ['required', 'string'],
            'quotations.*.delivery_time' => ['nullable', 'string', 'max:50'],
            'quotations.*.terms' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $uuid) {

            $supplier = Supplier::where('uuid', $uuid)->firstOrFail();

            foreach ($request->input('quotations') as $index => $data) {

                $file = $request->file("quotations.$index.file");

                $quotation = PurchaseQuotation::create([
                    'supplier_id'   => $supplier->id, 
                    'amount'        => $data['amount'],
                    'quotation_no'  => $data['quotation_no'],
                    'delivery_time' => $data['delivery_time'] ?? null,
                    'terms'         => $data['terms'] ?? null,
                ]);

                $path = $file->store('purchase-quotations', 'public');

                Attachment::create([
                    'category'        => 'purchase_quotation',
                    'attachable_type' => PurchaseQuotation::class,
                    'attachable_id'   => $quotation->id,
                    'file_path'       => $path,
                    'original_name'   => $file->getClientOriginalName(),
                ]);
            }

        });

        return back()->with('success', 'Quotation(s) uploaded successfully.');
    }

    public function destroyQuotation(string $uuid, PurchaseQuotation $quotation)
    {
        $supplier = Supplier::where('uuid', $uuid)->firstOrFail();

        if ($quotation->supplier_id !== $supplier->id) {
            abort(403, 'Quotation does not belong to this supplier.');
        }

        if ($quotation->purchaseRequests()->exists()) {
            return response()->json([
                'message' => 'Quotation is already linked to a Purchase Request.'
            ], 422);
        }

        DB::transaction(function () use ($quotation) {

            if ($quotation->attachment) {

                if (!empty($quotation->attachment->path)) {
                    Storage::disk('public')->delete($quotation->attachment->path);
                }

                $quotation->attachment->delete();
            }

            $quotation->delete();
        });

        return response()->json([
            'message' => 'Quotation deleted successfully.'
        ]);
    }

    public function list()
    {
        $list = Supplier::query()
            ->where('status', 'active')
            ->orderBy('company_name')
            ->get(['uuid', 'company_name']);

        return response()->json($list);
    }

}
