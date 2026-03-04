<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    public function store(Request $request)
    {
        if (!$request->user()?->isSuperAdmin()) {
            abort(403, 'Only Superadmin can create branches.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('branches', 'slug')],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Branch::create([
            'name' => trim($data['name']),
            'slug' => strtolower(trim($data['slug'])),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('status', 'branch-added');
    }

    public function update(Request $request, Branch $branch)
    {
        if (!$request->user()?->isSuperAdmin()) {
            abort(403, 'Only Superadmin can update branches.');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('branches', 'slug')->ignore($branch->id),
            ],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        if (array_key_exists('slug', $data)) {
            $data['slug'] = strtolower(trim($data['slug']));
        }
        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }

        $branch->update($data);

        return back()->with('status', 'branch-updated');
    }

    public function destroy(Request $request, Branch $branch)
    {
        if (!$request->user()?->isSuperAdmin()) {
            abort(403, 'Only Superadmin can delete branches.');
        }

        $used = DB::table('pivot_user_branches')->where('branch_id', $branch->id)->exists()
            || DB::table('users')->where('active_branch_id', $branch->id)->exists()
            || DB::table('running_numbers')->where('branch_id', $branch->id)->exists()
            || DB::table('company_bank_accounts')->where('branch_id', $branch->id)->exists();

        if ($used) {
            return back()->withErrors([
                'branch' => 'Branch is in use and cannot be deleted.',
            ]);
        }

        $branch->delete();

        return back()->with('status', 'branch-deleted');
    }
}

