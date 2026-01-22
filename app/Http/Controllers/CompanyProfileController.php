<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyProfileController extends Controller
{
    public function index()
    {
        $profile = CompanyProfile::first() ?? new CompanyProfile();

        return Inertia::render('CompanyProfile/Index', [
            'profile' => $profile,
            'bankOptions' => config('banks.malaysia', []),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_reg_no' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'office_number' => 'nullable|string|max:50',
            'logo' => 'nullable|image|max:2048',
        ]);

        $profile = CompanyProfile::first() ?? new CompanyProfile();

        $profile->fill($request->only([
            'company_name',
            'company_reg_no',
            'address',
            'office_number',
        ]));

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('company', 'public');
            $profile->logo = $path;
        }

        $profile->save();

        return back()->with('status', 'company-updated');
    }
}
