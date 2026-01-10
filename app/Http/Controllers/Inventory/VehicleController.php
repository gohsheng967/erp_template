<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryVehicle;
use App\Models\InventoryAllocation;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\Attachment;
use App\Services\QrCodeService;
use Str;

class VehicleController extends Controller
{
    /* =====================================================
     | INDEX
     ===================================================== */
    public function index(Request $request)
    {
        $filters = [
            'search'             => $request->get('search'),
            'status'             => $request->get('status'),
            'has_unpaid_saman'   => $request->boolean('has_unpaid_saman'),
            'insurance_expiring' => $request->boolean('insurance_expiring'),
            'roadtax_expiring'   => $request->boolean('roadtax_expiring'),
        ];

        $vehicles = InventoryItem::query()
            ->where('type', 'vehicle')

            ->when($filters['search'], function ($q) use ($filters) {
                $q->where(function ($qq) use ($filters) {
                    $qq->where('brand', 'like', "%{$filters['search']}%")
                        ->orWhere('model', 'like', "%{$filters['search']}%")
                        ->orWhereHas('vehicle', fn ($v) =>
                            $v->where('plate_number', 'like', "%{$filters['search']}%")
                        );
                });
            })

            ->when($filters['status'], fn ($q) =>
                $q->where('status', $filters['status'])
            )

            ->with([
                'vehicle',
                'activeAllocation.allocatable',
                'latestInsurance',
                'latestRoadtax',
                'attachment', // ✅ single image
            ])

            ->withSum([
                'samans as unpaid_saman_total' => fn ($q) =>
                    $q->where('status', 'unpaid')
            ], 'amount')

            ->when($filters['has_unpaid_saman'], fn ($q) =>
                $q->whereHas('samans', fn ($s) =>
                    $s->where('status', 'unpaid')
                )
            )

            ->when($filters['insurance_expiring'], fn ($q) =>
                $q->whereHas('latestInsurance', fn ($i) =>
                    $i->where('expiry_date', '<=', now()->addDays(30))
                )
            )

            ->when($filters['roadtax_expiring'], fn ($q) =>
                $q->whereHas('latestRoadtax', fn ($r) =>
                    $r->where('expiry_date', '<=', now()->addDays(30))
                )
            )

            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters'  => $filters,
        ]);
    }


    /* =====================================================
     | STORE
     ===================================================== */
    public function store(Request $request)
    {
        $data = $request->validate([
            'brand'          => 'required|string|max:100',
            'model'          => 'required|string|max:100',
            'engine_cc'      => 'nullable|integer|min:50',
            'plate_number'   => 'required|string|max:20|unique:inventory_vehicles,plate_number',

            'ownership_type' => ['required', Rule::in(['company', 'individual'])],
            'owner_name'     => 'nullable|string|max:255',

            'status'         => ['required', Rule::in(['active', 'inactive', 'disposed'])],
            'remark'         => 'nullable|string',

            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        DB::transaction(function () use ($request, $data) {

            $item = InventoryItem::create([
                'uuid'           => (string) Str::uuid(),
                'public_uuid'    => (string) Str::uuid(),
                'type'           => 'vehicle',
                'name'           => "{$data['brand']} {$data['model']}",
                'brand'          => $data['brand'],
                'model'          => $data['model'],
                'ownership_type' => $data['ownership_type'],
                'owner_name'     => $data['owner_name'],
                'status'         => $data['status'],
                'remark'         => $data['remark'],
            ]);

            /* =====================
            IMAGE (ONE ONLY)
            ===================== */
            if ($request->hasFile('image')) {

                $file = $request->file('image');
                $path = $file->store('attachments/inventory/vehicle', 'public');

                $item->attachments()->create([
                    'uuid'          => (string) Str::uuid(),
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getClientMimeType(),
                    'file_size'     => $file->getSize(),
                ]);
            }

            InventoryVehicle::create([
                'inventory_item_id' => $item->id,
                'engine_cc'         => $data['engine_cc'],
                'plate_number'      => strtoupper($data['plate_number']),
            ]);
        });

        return back();
    }


    /* =====================================================
    | UPDATE
    ===================================================== */
    public function update(Request $request, string $uuid)
    {
        $item = InventoryItem::where('uuid', $uuid)
            ->where('type', 'vehicle')
            ->firstOrFail();

        $data = $request->validate([
            'brand'          => 'required|string|max:100',
            'model'          => 'required|string|max:100',
            'engine_cc'      => 'nullable|integer|min:50',
            'plate_number'   => [
                'required',
                'string',
                'max:20',
                Rule::unique('inventory_vehicles', 'plate_number')
                    ->ignore($item->vehicle->id),
            ],

            'ownership_type' => ['required', Rule::in(['company', 'individual'])],
            'owner_name'     => 'nullable|string|max:255',

            'status'         => ['required', Rule::in(['active', 'inactive', 'disposed'])],
            'remark'         => 'nullable|string',

            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_image'   => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request, $item, $data) {

            $item->update([
                'name'           => "{$data['brand']} {$data['model']}",
                'brand'          => $data['brand'],
                'model'          => $data['model'],
                'ownership_type' => $data['ownership_type'],
                'owner_name'     => $data['owner_name'],
                'status'         => $data['status'],
                'remark'         => $data['remark'],
            ]);

            /* =====================
            REMOVE IMAGE
            ===================== */
            if (!empty($data['remove_image'])) {
                $item->attachments()->each(function ($att) {
                    Storage::disk('public')->delete($att->file_path);
                    $att->delete();
                });
            }

            /* =====================
            REPLACE IMAGE
            ===================== */
            if ($request->hasFile('image')) {

                $item->attachments()->each(function ($att) {
                    Storage::disk('public')->delete($att->file_path);
                    $att->delete();
                });

                $file = $request->file('image');
                $path = $file->store('attachments/inventory/vehicle', 'public');

                $item->attachments()->create([
                    'uuid'          => (string) Str::uuid(),
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getClientMimeType(),
                    'file_size'     => $file->getSize(),
                ]);
            }

            $item->vehicle->update([
                'engine_cc'    => $data['engine_cc'],
                'plate_number' => strtoupper($data['plate_number']),
            ]);
        });

        return back();
    }



    /* =====================================================
    | DELETE
    ===================================================== */
    public function destroy(string $uuid)
    {
        $item = InventoryItem::where('uuid', $uuid)
            ->where('type', 'vehicle')
            ->firstOrFail();

        DB::transaction(function () use ($item) {

            $item->attachments()->each(function ($att) {
                Storage::disk('public')->delete($att->file_path);
                $att->delete();
            });

            $item->delete();
        });

        return back();
    }


    public function show(string $uuid)
    {
        $vehicle = InventoryItem::query()
            ->where('uuid', $uuid)
            ->where('type', 'vehicle')

            ->with([
                'vehicle',
                'allocations' => fn ($q) =>
                    $q->latest('from_date')->with('allocatable'),
                'activeAllocation.allocatable',

                // insurance / roadtax
                'insurances' => fn ($q) => $q->latest('expiry_date'),
                'roadtaxes'  => fn ($q) => $q->latest('expiry_date'),

                // saman
                'samans'     => fn ($q) => $q->latest(),

                // ✅ SINGLE image attachment
                'attachment',
            ])

            ->withSum([
                'samans as unpaid_saman_total' => fn ($q) =>
                    $q->where('status', 'unpaid')
            ], 'amount')

            ->firstOrFail();

        return Inertia::render('Inventory/Vehicles/Show', [
            'vehicle' => $vehicle,
        ]);
    }

    public function qrCode(string $uuid, QrCodeService $qr)
    {
        $vehicle = InventoryItem::query()
            ->where('uuid', $uuid)
            ->where('type', 'vehicle')
            ->select('public_uuid')
            ->firstOrFail();

        $publicUrl = route('public.vehicles.show', $vehicle->public_uuid);

        return response(
            $qr->generatePng($publicUrl),
            200,
            ['Content-Type' => 'image/png']
        );
    }


    public function allocate(Request $request, string $uuid)
    {
        $item = InventoryItem::where('uuid', $uuid)
            ->where('type', 'vehicle')
            ->firstOrFail();

        $request->validate([
            'assign_type' => 'required|in:user,project,others,office',
            'allocatable_id' => 'nullable|integer',
            'allocatable_name' => 'nullable|string|max:255',
            'from_date' => 'required|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'location' => 'nullable|string|max:255',
            'remark' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $item) {

            InventoryAllocation::where('inventory_item_id', $item->id)
                ->whereNull('to_date')
                ->update(['to_date' => now()->toDateString()]);

            $allocatableType = null;
            $allocatableId   = null;
            $allocatableName = null;

            switch ($request->assign_type) {
                case 'user':
                    $allocatableType = User::class;
                    $allocatableId   = $request->allocatable_id;
                    $allocatableName = User::find($allocatableId)?->name;
                    break;

                case 'project':
                    $allocatableType = Project::class;
                    $allocatableId   = $request->allocatable_id;
                    $allocatableName = Project::find($allocatableId)?->name;
                    break;

                case 'others':
                    $allocatableName = $request->allocatable_name;
                    break;

                case 'office':
                    $allocatableName = 'Office';
                    break;
            }

            InventoryAllocation::create([
                'inventory_item_id' => $item->id,
                'allocatable_type'  => $allocatableType,
                'allocatable_id'    => $allocatableId,
                'allocatable_name'  => $allocatableName,
                'location'          => $request->location,
                'from_date'         => $request->from_date,
                'to_date'           => $request->to_date,
                'remark'            => $request->remark,
                'created_by'        => auth()->id(),
            ]);
        });

        return back()->with('success', 'Vehicle allocated successfully.');
    }

    public function loadUsers(Request $request)
    {
        return response()->json([
            'users' => User::query()
                ->when($request->exclude_id, fn ($q) =>
                    $q->where('id', '!=', $request->exclude_id)
                )
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function loadProjects(Request $request)
    {
        return response()->json([
            'projects' => Project::query()
                ->when($request->exclude_id, fn ($q) =>
                    $q->where('id', '!=', $request->exclude_id)
                )
                ->select('id', 'code', 'name')
                ->orderBy('code')
                ->get(),
        ]);
    }
}
