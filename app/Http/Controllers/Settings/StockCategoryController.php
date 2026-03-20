<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\StockCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockCategoryController extends Controller
{
    public function index()
    {
        $categories = StockCategory::query()
            ->withCount('movements')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('StockCategories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:stock_categories,name'],
        ]);

        StockCategory::create([
            'name' => trim($validated['name']),
        ]);

        return back()->with('success', 'Stock category created successfully.');
    }

    public function update(Request $request, StockCategory $stockCategory)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:stock_categories,name,' . $stockCategory->id],
        ]);

        $newName = trim($validated['name']);
        $oldName = $stockCategory->name;

        if ($newName !== $oldName) {
            InventoryMovement::query()
                ->where('stock_category', $oldName)
                ->update(['stock_category' => $newName]);
        }

        $stockCategory->update([
            'name' => $newName,
        ]);

        return back()->with('success', 'Stock category updated successfully.');
    }

    public function destroy(StockCategory $stockCategory)
    {
        if ($stockCategory->movements()->exists()) {
            return back()->withErrors([
                'delete' => 'Category is in use and cannot be deleted.',
            ]);
        }

        $stockCategory->delete();

        return back()->with('success', 'Stock category deleted successfully.');
    }
}
