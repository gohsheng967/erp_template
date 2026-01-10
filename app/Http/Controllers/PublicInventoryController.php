<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
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
}
