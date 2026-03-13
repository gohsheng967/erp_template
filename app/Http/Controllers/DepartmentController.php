<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::query()
            ->whereIn('name', Department::fixedDepartmentNames())
            ->withCount('users')
            ->with('roles:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        abort(403, 'Departments are fixed by system policy.');
    }

    public function update(Request $request, Department $department)
    {
        abort(403, 'Departments are fixed by system policy.');
    }

    public function destroy(Department $department)
    {
        abort(403, 'Departments are fixed by system policy.');
    }
}
