<?php

namespace App\Http\Controllers;

use App\Models\SubCon;
use App\Models\SubConTask;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubConController extends Controller
{
    public function index(Request $request)
    {
        $subCons = SubCon::query()
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('bank', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('SubCons/Index', [
            'subCons' => $subCons,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'bank' => 'nullable|string|max:255',
        ]);

        SubCon::create($validated);

        return redirect()->back()->with('success', 'Sub Con created successfully.');
    }

    public function show(SubCon $subCon)
    {
        $taskBase = SubConTask::query()
            ->where('sub_con_id', $subCon->id);

        $taskStats = [
            'total'     => (clone $taskBase)->count(),
            'draft'     => (clone $taskBase)->where('status', 'draft')->count(),
            'submitted' => (clone $taskBase)->where('status', 'submitted')->count(),
            'verified'  => (clone $taskBase)->where('status', 'verified')->count(),
            'justified' => (clone $taskBase)->where('status', 'justified')->count(),
            'certified' => (clone $taskBase)->where('status', 'certified')->count(),
            'paid'      => (clone $taskBase)->where('status', 'paid')->count(),
            'total_amount' => (float) (clone $taskBase)->sum('amount'),
        ];

        return Inertia::render('SubCons/Show', [
            'subCon' => $subCon,
            'taskStats' => $taskStats,
        ]);
    }

    public function update(Request $request, SubCon $subCon)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'bank' => 'nullable|string|max:255',
        ]);

        $subCon->update($validated);

        return redirect()->back()->with('success', 'Sub Con updated successfully.');
    }

    public function destroy(SubCon $subCon)
    {
        $subCon->delete();

        return redirect()->back()->with('success', 'Sub Con deleted successfully.');
    }
}
