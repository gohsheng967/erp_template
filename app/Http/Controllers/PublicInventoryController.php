<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class PublicInventoryController extends Controller
{
    /**
     * Public vehicle asset card
     */
    public function vehicle(string $publicUuid)
    {
        $vehicle = InventoryItem::query()
            ->where('public_uuid', $publicUuid)
            ->where('type', 'vehicle')
            ->with([
                'vehicle',
                'activeAllocation.user',
                'latestInsurance',
                'latestRoadtax',
            ])
            ->withSum([
                'samans as unpaid_saman_total' => fn ($q) =>
                    $q->where('status', 'unpaid')
            ], 'amount')
            ->firstOrFail();

        return Inertia::render('Public/Vehicles/Show', [
            'vehicle' => $vehicle,
        ]);
    }

    public function vehicleLogbook(string $publicUuid)
    {
        $vehicle = InventoryItem::query()
            ->where('public_uuid', $publicUuid)
            ->where('type', InventoryItem::TYPE_VEHICLE)
            ->with(['vehicle', 'activeAllocation.allocatable'])
            ->firstOrFail();

        $projects = Project::query()
            ->select('id', 'code', 'name')
            ->orderBy('code')
            ->get();

        $configuredPin = (string) config('app.vehicle_logbook_pin');
        $pinRequired = preg_match('/^\d{6}$/', $configuredPin) === 1;

        $activeTrip = null;
        if (Schema::hasColumn('inventory_vehicle_logs', 'trip_status')) {
            $activeTrip = $vehicle->vehicleLogs()
                ->where('trip_status', 'in_use')
                ->latest('started_at')
                ->first();
        }

        return view('public.vehicle-logbook', [
            'vehicle' => $vehicle,
            'projects' => $projects,
            'pinRequired' => $pinRequired,
            'temporaryPin' => $pinRequired ? $configuredPin : null,
            'activeTrip' => $activeTrip,
        ]);
    }

    public function startVehicleLogbook(Request $request, string $publicUuid)
    {
        if (!$this->isTripCycleReady()) {
            return back()->withErrors([
                'trip' => 'Vehicle trip cycle is not ready yet. Please run latest migration.',
            ])->withInput();
        }

        $vehicle = InventoryItem::query()
            ->where('public_uuid', $publicUuid)
            ->where('type', InventoryItem::TYPE_VEHICLE)
            ->firstOrFail();

        $configuredPin = (string) config('app.vehicle_logbook_pin');
        $pinRequired = preg_match('/^\d{6}$/', $configuredPin) === 1;

        $rules = [
            'start_mileage' => ['required', 'numeric', 'min:0'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'bound_to_type' => ['required', 'in:office,project,others'],
            'bound_to_project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'bound_to_label' => ['nullable', 'string', 'max:255'],
            'driver_name' => ['required', 'string', 'max:255'],
        ];

        if ($pinRequired) {
            $rules['access_pin'] = ['required', 'digits:6'];
        }

        $data = $request->validate($rules);

        if ($pinRequired && !hash_equals($configuredPin, (string) ($data['access_pin'] ?? ''))) {
            return back()
                ->withErrors(['access_pin' => 'Invalid access PIN.'])
                ->withInput();
        }

        $hasOpenTrip = $vehicle->vehicleLogs()
            ->where('trip_status', 'in_use')
            ->exists();
        if ($hasOpenTrip) {
            return back()
                ->withErrors(['start_mileage' => 'There is already an active trip. Please end it first.'])
                ->withInput();
        }

        $boundLabel = $this->resolveBoundLabel($data, $request, $publicUuid);
        if ($boundLabel instanceof \Illuminate\Http\RedirectResponse) {
            return $boundLabel;
        }

        $driverName = trim((string) $data['driver_name']);
        $startAt = now();
        $vehicle->vehicleLogs()->create([
            'trip_date' => $startAt,
            'started_at' => $startAt,
            'ended_at' => null,
            'mileage' => null,
            'start_mileage' => $data['start_mileage'],
            'end_mileage' => null,
            'origin' => $data['origin'],
            'destination' => $data['destination'],
            'purpose' => $data['purpose'] ?? null,
            'trip_status' => 'in_use',
            'pin_bypassed' => false,
            'bound_to_type' => $data['bound_to_type'],
            'bound_to_project_id' => $data['bound_to_type'] === 'project' ? $data['bound_to_project_id'] : null,
            'bound_to_label' => $boundLabel,
            'driver_user_id' => null,
            'driver_name' => $driverName,
            'created_by' => null,
        ]);

        return redirect()
            ->route('public.vehicles.logbook', $publicUuid)
            ->with('success', 'Trip started. Please submit End Trip when usage is finished.');
    }

    public function endVehicleLogbook(Request $request, string $publicUuid)
    {
        if (!$this->isTripCycleReady()) {
            return back()->withErrors([
                'trip' => 'Vehicle trip cycle is not ready yet. Please run latest migration.',
            ])->withInput();
        }

        $vehicle = InventoryItem::query()
            ->where('public_uuid', $publicUuid)
            ->where('type', InventoryItem::TYPE_VEHICLE)
            ->firstOrFail();

        $configuredPin = (string) config('app.vehicle_logbook_pin');
        $pinRequired = preg_match('/^\d{6}$/', $configuredPin) === 1;

        $rules = [
            'log_id' => ['required', 'integer'],
            'driver_name' => ['required', 'string', 'max:255'],
            'end_mileage' => ['required', 'numeric', 'min:0'],
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
        ];

        if ($pinRequired) {
            $rules['access_pin'] = ['required', 'digits:6'];
        }

        $data = $request->validate($rules);

        if ($pinRequired && !hash_equals($configuredPin, (string) ($data['access_pin'] ?? ''))) {
            return back()
                ->withErrors(['access_pin' => 'Invalid access PIN.'])
                ->withInput();
        }

        $log = $vehicle->vehicleLogs()
            ->where('id', $data['log_id'])
            ->where('trip_status', 'in_use')
            ->first();
        if (!$log) {
            return back()
                ->withErrors(['log_id' => 'No active trip found to end.'])
                ->withInput();
        }

        $submittedDriver = trim((string) $data['driver_name']);
        $recordedDriver = trim((string) $log->driver_name);
        if (strcasecmp($submittedDriver, $recordedDriver) !== 0) {
            return back()
                ->withErrors(['driver_name' => 'Driver name must match the started trip.'])
                ->withInput();
        }

        $endMileage = (float) $data['end_mileage'];
        $startMileage = $log->start_mileage !== null ? (float) $log->start_mileage : null;
        if ($startMileage !== null && $endMileage < $startMileage) {
            return back()
                ->withErrors(['end_mileage' => 'End mileage cannot be lower than start mileage.'])
                ->withInput();
        }

        $log->update([
            'ended_at' => now(),
            'end_mileage' => $endMileage,
            'mileage' => $endMileage,
            'destination' => trim((string) $data['destination']),
            'purpose' => $data['purpose'] ?? $log->purpose,
            'trip_status' => 'completed',
        ]);

        return redirect()
            ->route('public.vehicles.logbook', $publicUuid)
            ->with('success', 'Trip ended and mileage recorded.');
    }

    public function storeVehicleLogbook(Request $request, string $publicUuid)
    {
        if (!$request->has('start_mileage') && $request->filled('mileage')) {
            $request->merge([
                'start_mileage' => $request->input('mileage'),
            ]);
        }

        return $this->startVehicleLogbook($request, $publicUuid);
    }

    private function resolveBoundLabel(array $data, Request $request, string $publicUuid)
    {
        if (($data['bound_to_type'] ?? null) === 'project' && empty($data['bound_to_project_id'])) {
            return redirect()
                ->route('public.vehicles.logbook', $publicUuid)
                ->withErrors(['bound_to_project_id' => 'Project is required when binding to project.'])
                ->withInput($request->all());
        }

        if (($data['bound_to_type'] ?? null) === 'others' && empty($data['bound_to_label'])) {
            return redirect()
                ->route('public.vehicles.logbook', $publicUuid)
                ->withErrors(['bound_to_label' => 'Please specify the binding label.'])
                ->withInput($request->all());
        }

        if (($data['bound_to_type'] ?? null) === 'office') {
            return 'Office';
        }

        if (($data['bound_to_type'] ?? null) === 'project') {
            return optional(Project::query()->find($data['bound_to_project_id']))->name;
        }

        return $data['bound_to_label'] ?? null;
    }

    private function isTripCycleReady(): bool
    {
        return Schema::hasColumn('inventory_vehicle_logs', 'trip_status')
            && Schema::hasColumn('inventory_vehicle_logs', 'start_mileage')
            && Schema::hasColumn('inventory_vehicle_logs', 'end_mileage');
    }
}
