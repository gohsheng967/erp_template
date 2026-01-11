<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Warehouse;
use Inertia\Inertia;
class WarehouseController extends Controller
{
    public function index()
    {
        return Inertia::render('Warehouses/Index', [
            'warehouses' => Warehouse::orderBy('title')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'   => 'required|string|max:255',
            'address' => 'required|string',
        ]);

        Warehouse::create([
            'title'   => $data['title'],
            'address' => $data['address'],
            'status'  => 1,
        ]);

        return back()->with('success', 'Warehouse created');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'title'   => 'required|string|max:255',
            'address' => 'required|string',
        ]);

        $warehouse->update($data);

        return back()->with('success', 'Warehouse updated');
    }

    public function destroy(Warehouse $warehouse)
    {
        // Soft delete via status
        $warehouse->update(['status' => 0]);

        return back()->with('success', 'Warehouse deactivated');
    }
}

