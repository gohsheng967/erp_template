<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryVehicle;
use App\Models\InventoryAllocation;
use App\Models\InventoryInsurance;
use App\Models\InventoryService;
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

                // services
                'services' => fn ($q) => $q->latest('service_date'),
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

    public function storeService(Request $request, string $uuid)
    {
        $item = InventoryItem::where('uuid', $uuid)
            ->where('type', 'vehicle')
            ->firstOrFail();

        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'item_parts' => ['required', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $item->services()->create($data);

        return back()->with('success', 'Service record added.');
    }

    public function updateService(Request $request, string $uuid, int $serviceId)
    {
        $item = InventoryItem::where('uuid', $uuid)
            ->where('type', 'vehicle')
            ->firstOrFail();

        $service = $item->services()->where('id', $serviceId)->firstOrFail();

        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'item_parts' => ['required', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $service->update($data);

        return back()->with('success', 'Service record updated.');
    }

    public function destroyService(string $uuid, int $serviceId)
    {
        $item = InventoryItem::where('uuid', $uuid)
            ->where('type', 'vehicle')
            ->firstOrFail();

        $service = $item->services()->where('id', $serviceId)->firstOrFail();
        $service->delete();

        return back()->with('success', 'Service record deleted.');
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

    public function compliance(string $uuid)
    {
        $inventoryItem = InventoryItem::where('uuid', $uuid)->firstOrFail();

        $insurance = $inventoryItem->insurances()
            ->with('attachment')
            ->where('status', 'active')
            ->latest('expiry_date')
            ->first();

        $insuranceHistory = $inventoryItem->insurances()
            ->with('attachment')
            ->orderBy('status')
            ->latest('expiry_date')
            ->get();

        $roadtax = $inventoryItem->roadtaxes()
            ->with('attachment')
            ->latest('expiry_date')
            ->first();

        return response()->json([
            'insurance'         => $insurance,
            'insurance_history' => $insuranceHistory,
            'roadtax'           => $roadtax,
        ]);
    }

    public function storeInsurance(Request $request, string $uuid)
    {
        $inventoryItem = InventoryItem::where('uuid', $uuid)->firstOrFail();

        $isRenew = $request->input('_mode') === 'renew';

        $previousInsurance = null;
        if ($isRenew) {
            $previousInsurance = $inventoryItem->insurances()
                ->where('status', 'active')
                ->orderByDesc('expiry_date')
                ->first();
        }

        /* =========================
        BASE RULES (ARRAY FORM)
        ========================= */
        $rules = [
            'provider'        => ['nullable', 'string', 'max:255'],
            'policy_number'   => ['nullable', 'string', 'max:255'],
            'coverage_type'   => ['nullable', 'string', 'max:100'],
            'start_date'      => ['nullable', 'date'],
            'expiry_date'     => ['required', 'date', 'after:start_date'],
            'coverage_amount' => ['nullable', 'numeric', 'min:0'],
            'premium_amount'  => ['nullable', 'numeric', 'min:0'],
            'document'        => ['nullable', 'file', 'max:10240'],
        ];

        /* =========================
        RENEW-ONLY VALIDATION
        ========================= */
        if ($isRenew && $previousInsurance) {
            $rules['start_date'][] = function ($attr, $value, $fail) use ($previousInsurance) {
                if ($value && strtotime($value) < strtotime($previousInsurance->expiry_date)) {
                    $fail('Renewal start date cannot be earlier than previous expiry date.');
                }
            };

            $rules['expiry_date'][] = function ($attr, $value, $fail) use ($previousInsurance) {
                if (strtotime($value) <= strtotime($previousInsurance->expiry_date)) {
                    $fail('Renewal expiry date must be after previous expiry date.');
                }
            };
        }

        $data = $request->validate($rules, [
            'expiry_date.required' => 'Expiry date is required.',
            'expiry_date.after'    => 'Expiry date must be after the start date.',
            'document.file'        => 'Uploaded policy document is invalid.',
            'document.max'         => 'Policy document must not exceed 10MB.',
        ]);

        DB::beginTransaction();

        try {
            /* =========================
            EXPIRE CURRENT INSURANCE
            ========================= */
            $inventoryItem->insurances()
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            /* =========================
            CREATE NEW INSURANCE
            ========================= */
            $insurance = $inventoryItem->insurances()->create([
                ...$data,
                'status' => 'active',
            ]);

            /* =========================
            STORE DOCUMENT
            ========================= */
            if ($request->hasFile('document')) {
                $file = $request->file('document');

                $path = $file->store('insurance-documents', 'public');

                $attachment = Attachment::create([
                    'category'        => 'inventory_insurance',
                    'attachable_type' => InventoryInsurance::class,
                    'attachable_id'   => $insurance->id,
                    'file_path'       => $path,
                    'original_name'   => $file->getClientOriginalName(),
                    'mime_type'       => $file->getClientMimeType(),
                    'file_size'       => $file->getSize(),
                    'created_by'      => auth()->id(),
                ]);

                $insurance->update([
                    'document_id' => $attachment->id,
                ]);
            }

            DB::commit();

            return redirect()
                ->back()
                ->with(
                    'success',
                    $isRenew
                        ? 'Insurance renewed successfully.'
                        : 'Insurance saved successfully.'
                );

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function renewInsurance(Request $request, string $uuid)
    {
        $request->merge(['_mode' => 'renew']);

        return $this->storeInsurance($request, $uuid);
    }

    public function updateInsurance(Request $request, string $uuid, int $insuranceId) 
    {
        $inventoryItem = InventoryItem::where('uuid', $uuid)->firstOrFail();

        $insurance = $inventoryItem->insurances()
            ->where('id', $insuranceId)
            ->firstOrFail();

        $data = $request->validate([
            'provider'        => 'nullable|string|max:255',
            'policy_number'   => 'nullable|string|max:255',
            'coverage_type'   => 'nullable|string|max:100',
            'start_date'      => 'nullable|date',
            'expiry_date'     => 'required|date|after:start_date',
            'coverage_amount' => 'nullable|numeric|min:0',
            'premium_amount'  => 'nullable|numeric|min:0',
            'document'        => 'nullable|file|max:10240',
        ]);

        DB::beginTransaction();

        try {
            $insurance->update($data);

            /* =========================
            REPLACE DOCUMENT
            ========================= */
            if ($request->hasFile('document')) {

                // 🔥 delete old attachment first
                if ($insurance->document_id) {
                    $this->deleteAttachment(
                        Attachment::find($insurance->document_id)
                    );
                }

                $file = $request->file('document');
                $path = $file->store('insurance-documents', 'public');

                $attachment = Attachment::create([
                    'category'        => 'inventory_insurance',
                    'attachable_type' => InventoryInsurance::class,
                    'attachable_id'   => $insurance->id,
                    'file_path'       => $path,
                    'original_name'   => $file->getClientOriginalName(),
                    'mime_type'       => $file->getClientMimeType(),
                    'file_size'       => $file->getSize(),
                    'created_by'      => auth()->id(),
                ]);

                $insurance->update([
                    'document_id' => $attachment->id,
                ]);
            }

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'Insurance updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function renewRoadtax(Request $request, string $uuid)
    {
        $request->merge(['_mode' => 'renew']);

        return $this->storeRoadtax($request, $uuid);
    }


    public function updateRoadtax(Request $request, string $uuid, int $roadtaxId)
    {
        $inventoryItem = InventoryItem::where('uuid', $uuid)->firstOrFail();

        $roadtax = $inventoryItem->roadtaxes()->findOrFail($roadtaxId);

        $data = $request->validate([
            'start_date'  => ['nullable', 'date'],
            'expiry_date' => ['required', 'date', 'after:start_date'],
            'amount'      => ['nullable', 'numeric', 'min:0'],
            'document'    => ['nullable', 'file', 'max:10240'],
        ]);

        DB::beginTransaction();

        try {
            $roadtax->update($data);

            if ($request->hasFile('document')) {
                $file = $request->file('document');

                $path = $file->store('roadtax-documents', 'public');

                $attachment = Attachment::create([
                    'category'        => 'inventory_roadtax',
                    'attachable_type' => InventoryRoadtax::class,
                    'attachable_id'   => $roadtax->id,
                    'file_path'       => $path,
                    'original_name'   => $file->getClientOriginalName(),
                    'mime_type'       => $file->getClientMimeType(),
                    'file_size'       => $file->getSize(),
                    'created_by'      => auth()->id(),
                ]);

                $roadtax->update([
                    'document_id' => $attachment->id,
                ]);
            }

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'Roadtax updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function storeRoadtax(Request $request, string $uuid)
    {
        $inventoryItem = InventoryItem::where('uuid', $uuid)->firstOrFail();

        // latest roadtax (for renew validation)
        $previousRoadtax = $inventoryItem->roadtaxes()
            ->orderByDesc('expiry_date')
            ->first();

        $isRenew = $request->input('_mode') == 'renew';

        $rules = [
            'start_date'  => ['nullable', 'date'],
            'expiry_date' => ['required', 'date', 'after:start_date'],
            'amount'      => ['nullable', 'numeric', 'min:0'],
            'document'    => ['nullable', 'file', 'max:10240'],
        ];

        // 🔒 RENEW-ONLY VALIDATION
        if ($isRenew && $previousRoadtax) {
            $rules['start_date'][] = function ($attr, $value, $fail) use ($previousRoadtax) {
                if ($value && strtotime($value) < strtotime($previousRoadtax->expiry_date)) {
                    $fail('Renewal start date cannot be earlier than previous expiry date.');
                }
            };

            $rules['expiry_date'][] = function ($attr, $value, $fail) use ($previousRoadtax) {
                if (strtotime($value) <= strtotime($previousRoadtax->expiry_date)) {
                    $fail('Renewal expiry date must be after previous expiry date.');
                }
            };
        }

        $data = $request->validate($rules, [
            'expiry_date.required' => 'Expiry date is required.',
            'expiry_date.after'    => 'Expiry date must be after start date.',
        ]);

        DB::beginTransaction();

        try {
            /* =========================
            CREATE ROADTAX (ADD / RENEW)
            ========================= */
            $roadtax = $inventoryItem->roadtaxes()->create($data);

            /* =========================
            STORE DOCUMENT
            ========================= */
            if ($request->hasFile('document')) {
                $file = $request->file('document');

                $path = $file->store('roadtax-documents', 'public');

                $attachment = Attachment::create([
                    'category'        => 'inventory_roadtax',
                    'attachable_type' => InventoryRoadtax::class,
                    'attachable_id'   => $roadtax->id,
                    'file_path'       => $path,
                    'original_name'   => $file->getClientOriginalName(),
                    'mime_type'       => $file->getClientMimeType(),
                    'file_size'       => $file->getSize(),
                    'created_by'      => auth()->id(),
                ]);

                $roadtax->update([
                    'document_id' => $attachment->id,
                ]);
            }

            DB::commit();

            return redirect()
                ->back()
                ->with('success', $isRenew
                    ? 'Roadtax renewed successfully.'
                    : 'Roadtax added successfully.'
                );

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function deleteAttachment(?Attachment $attachment): void
    {
        if (! $attachment) {
            return;
        }

        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();
    }
}
