<?php

namespace App\Http\Controllers;

use App\Models\ClaimType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClaimTypeController extends Controller
{
    public function index()
    {
        $types = ClaimType::query()
            ->withCount('claimItems')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('ClaimTypes/Index', [
            'types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
        ]);

        $code = Str::slug($validated['code'], '_');
        if ($code === '') {
            return back()->withErrors(['code' => 'Code is required.']);
        }

        $exists = ClaimType::withTrashed()
            ->where('code', $code)
            ->exists();

        if ($exists) {
            return back()->withErrors(['code' => 'This code already exists.']);
        }

        ClaimType::create([
            'name' => $validated['name'],
            'code' => $code,
        ]);

        return back()->with('success', 'Claim type created successfully.');
    }

    public function update(Request $request, ClaimType $claimType)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
        ]);

        $code = Str::slug($validated['code'], '_');
        if ($code === '') {
            return back()->withErrors(['code' => 'Code is required.']);
        }

        $exists = ClaimType::withTrashed()
            ->where('code', $code)
            ->where('id', '!=', $claimType->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['code' => 'This code already exists.']);
        }

        if ($code !== $claimType->code && $claimType->claimItems()->exists()) {
            return back()->withErrors([
                'code' => 'Code cannot be changed because it is already used in claims.',
            ]);
        }

        $claimType->update([
            'name' => $validated['name'],
            'code' => $code,
        ]);

        return back()->with('success', 'Claim type updated successfully.');
    }

    public function destroy(ClaimType $claimType)
    {
        $claimType->delete();

        return back()->with('success', 'Claim type deleted successfully.');
    }
}
