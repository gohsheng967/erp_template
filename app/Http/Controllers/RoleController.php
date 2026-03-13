<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Department;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function store(Request $request, Department $department)
    {
        abort(403, 'Roles are fixed by system policy.');
    }

    public function update(Request $request, Role $role)
    {
        abort(403, 'Roles are fixed by system policy.');
    }

    public function destroy(Role $role)
    {
        abort(403, 'Roles are fixed by system policy.');
    }
}
