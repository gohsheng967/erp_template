<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Department;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function store(Request $request, Department $department)
    {
        $request->validate([
            'name' => ['required','string','max:255'],
        ]);

        $role = Role::create(['name' => $request->name]);

        // Attach role to department
        $department->roles()->syncWithoutDetaching($role->id);

        return back()->with('success','Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => ['required','string','max:255','unique:roles,name,' . $role->id],
        ]);

        $role->update(['name'=>$request->name]);

        return back()->with('success','Role updated.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->count() > 0) {
            return back()->with('error','Role is assigned to users and cannot be deleted.');
        }

        // detach from department_role
        $role->departments()->detach();

        $role->delete();

        return back()->with('success','Role deleted.');
    }
}
