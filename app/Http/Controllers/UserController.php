<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Inertia\Inertia;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | USER LISTING
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $filters = request()->all();

        $users = User::query()
            ->with(['departments', 'roles', 'branches', 'activeBranch'])
            ->when(request('search'), function ($q) {
                $search = request('search');
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('identity_no', 'like', "%$search%");
                });
            })
            ->when(request('status') !== null, function ($q) {
                $q->where('status', request('status'));
            })
            ->paginate(10)
            ->through(function ($u) {
                return [
                    'id'           => $u->id,
                    'identity_no'  => $u->identity_no,
                    'name'         => $u->name,
                    'email'        => $u->email,
                    'status'       => $u->status,
                    'is_superadmin' => (bool) $u->is_superadmin,
                    'is_general_manager' => (bool) $u->is_general_manager,

                    'is_protected' => $u->isSuperAdmin(),

                    'assignments'  => $u->is_superadmin
                        ? collect([[
                            'department_id' => null,
                            'department' => 'System',
                            'role_id' => null,
                            'role' => 'Superadmin',
                        ]])
                        : ($u->is_general_manager
                            ? collect([[
                                'department_id' => null,
                                'department' => 'Branch',
                                'role_id' => null,
                                'role' => 'General Manager',
                            ]])
                        : $u->departments->map(function ($d) use ($u) {
                            return [
                                'department_id' => $d->id,
                                'department'    => $d->name,
                                'role_id'       => $d->pivot->role_id,
                                'role'          => $u->roles->firstWhere('id', $d->pivot->role_id)?->name
                            ];
                        })),
                    'branches' => $u->branches->map(fn ($b) => [
                        'id' => $b->id,
                        'name' => $b->name,
                        'slug' => $b->slug,
                    ])->values(),
                    'active_branch_id' => $u->active_branch_id,
                ];
            });

        return Inertia::render('Users/Index', [
            'users'       => $users,
            'filters'     => $filters,
            'departments' => Department::query()
                ->whereIn('name', Department::fixedDepartmentNames())
                ->select('id','name')
                ->orderBy('name')
                ->get(),
            'branches'    => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'rolesByDept' => Department::query()
                ->whereIn('name', Department::fixedDepartmentNames())
                ->with('roles:id,name')
                ->get()
                ->mapWithKeys(fn($d) => [$d->id => $d->roles]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE USER
    |--------------------------------------------------------------------------
    */
    public function store(StoreUserRequest $request)
    {
        $temporaryPassword = Str::random(10);

        DB::transaction(function () use ($request, $temporaryPassword) {
            $activeBranchId = Branch::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');

            if (!$activeBranchId) {
                abort(422, 'Please create at least one active branch before creating users.');
            }

            $user = User::create([
                'identity_no' => $request->identity_no,
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => bcrypt($temporaryPassword),
                'status'      => $request->status,
                'is_superadmin' => (bool) $request->boolean('is_superadmin'),
                'is_general_manager' => (bool) $request->boolean('is_general_manager'),
                'must_change_password' => true,
                'active_branch_id' => $activeBranchId,
            ]);

            if (!$user->is_superadmin && !$user->is_general_manager) {
                foreach ($request->input('department_roles', []) as $dr) {
                    DB::table('pivot_user_departments')->insert([
                        'user_id'       => $user->id,
                        'department_id' => $dr['department_id'],
                        'role_id'       => $dr['role_id'],
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }

            DB::table('pivot_user_branches')->insert([
                'user_id' => $user->id,
                'branch_id' => $activeBranchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with(
            'success',
            "User created successfully. Temporary password: {$temporaryPassword}"
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE USER
    |--------------------------------------------------------------------------
    */
    public function update(UpdateUserRequest $request, User $user)
    {
        // 🔒 HARD PROTECTION
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin account cannot be modified.');
        }

        DB::transaction(function () use ($request, $user) {

            $user->update([
                'identity_no' => $request->identity_no,
                'name'        => $request->name,
                'email'       => $request->email,
                'status'      => $request->status,
                'is_general_manager' => (bool) $request->boolean('is_general_manager'),
            ]);

            // Reset assignments
            DB::table('pivot_user_departments')
                ->where('user_id', $user->id)
                ->delete();

            if (!$user->is_general_manager) {
                foreach ($request->department_roles as $dr) {
                    DB::table('pivot_user_departments')->insert([
                        'user_id'       => $user->id,
                        'department_id' => $dr['department_id'],
                        'role_id'       => $dr['role_id'],
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        });

        return back()->with('success', 'User updated successfully.');
    }

    public function updateBranches(\Illuminate\Http\Request $request, User $user)
    {
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin account cannot be modified.');
        }

        $data = $request->validate([
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'active_branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        if (!in_array((int) $data['active_branch_id'], array_map('intval', $data['branch_ids']), true)) {
            return back()->withErrors([
                'active_branch_id' => 'Active branch must be one of the selected branches.',
            ]);
        }

        DB::transaction(function () use ($user, $data) {
            $user->branches()->sync($data['branch_ids']);
            $user->active_branch_id = $data['active_branch_id'];
            $user->save();
        });

        return back()->with('success', 'User branches updated successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | RESET PASSWORD
    |--------------------------------------------------------------------------
    */
    public function resetPassword(User $user)
    {
        if ($user->isSuperAdmin()) {
            abort(403, "Superadmin password cannot be reset.");
        }

        $newPass = Str::random(10);

        $user->update([
            'password' => bcrypt($newPass),
            'must_change_password' => true,
        ]);

        return back()->with('success', "Password reset. Temporary password: {$newPass}");
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS (SUSPEND / ACTIVATE)
    |--------------------------------------------------------------------------
    */
    public function toggleStatus(User $user)
    {
        if ($user->isSuperAdmin()) {
            abort(403, "Superadmin status cannot be changed.");
        }

        $user->update(['status' => $user->status ? 0 : 1]);

        return back();
    }
}
