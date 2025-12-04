<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use App\Models\Role;
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
            ->with(['departments', 'roles'])
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

                    // check if user belongs to Superadmin department
                    'is_protected' => $u->departments->contains('name', 'Superadmin'),

                    'assignments'  => $u->departments->map(function ($d) use ($u) {
                        return [
                            'department_id' => $d->id,
                            'department'    => $d->name,
                            'role_id'       => $d->pivot->role_id,
                            'role'          => $u->roles->firstWhere('id', $d->pivot->role_id)?->name
                        ];
                    })
                ];
            });

        return Inertia::render('Users/Index', [
            'users'       => $users,
            'filters'     => $filters,
            'departments' => Department::select('id','name')->get(),
            'rolesByDept' => Department::with('roles:id,name')
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
        DB::transaction(function () use ($request) {

            $user = User::create([
                'identity_no' => $request->identity_no,
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => bcrypt(Str::random(10)),
                'status'      => $request->status,
            ]);

            foreach ($request->department_roles as $dr) {
                DB::table('pivot_user_departments')->insert([
                    'user_id'       => $user->id,
                    'department_id' => $dr['department_id'],
                    'role_id'       => $dr['role_id'],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        });

        return back()->with('success', 'User created successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE USER
    |--------------------------------------------------------------------------
    */
    public function update(UpdateUserRequest $request, User $user)
    {
        // HARD PROTECTION
        if ($user->departments()->where('name', 'Superadmin')->exists()) {
            abort(403, "Superadmin account cannot be modified.");
        }

        DB::transaction(function () use ($request, $user) {

            $user->update([
                'identity_no' => $request->identity_no,
                'name'        => $request->name,
                'email'       => $request->email,
                'status'      => $request->status,
            ]);

            DB::table('pivot_user_departments')->where('user_id', $user->id)->delete();

            foreach ($request->department_roles as $dr) {
                DB::table('pivot_user_departments')->insert([
                    'user_id'       => $user->id,
                    'department_id' => $dr['department_id'],
                    'role_id'       => $dr['role_id'],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        });

        return back()->with('success', 'User updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | RESET PASSWORD
    |--------------------------------------------------------------------------
    */
    public function resetPassword(User $user)
    {
        if ($user->departments()->where('name', 'Superadmin')->exists()) {
            abort(403, "Superadmin password cannot be reset.");
        }

        $newPass = Str::random(10);

        $user->update(['password' => bcrypt($newPass)]);

        return back()->with('success', "Password reset. Temporary password: {$newPass}");
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS (SUSPEND / ACTIVATE)
    |--------------------------------------------------------------------------
    */
    public function toggleStatus(User $user)
    {
        if ($user->departments()->where('name', 'Superadmin')->exists()) {
            abort(403, "Superadmin status cannot be changed.");
        }

        $user->update(['status' => $user->status ? 0 : 1]);

        return back();
    }
}
