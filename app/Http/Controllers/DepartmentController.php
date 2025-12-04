<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('users')
            ->with('roles:id,name')
            ->get();

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required','string','max:255','unique:departments,name']
        ]);

        Department::create(['name' => $request->name]);

        return back()->with('success','Department created.');
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => ['required','string','max:255','unique:departments,name,' . $department->id]
        ]);

        $department->update(['name'=>$request->name]);

        return back()->with('success','Department updated.');
    }

    public function destroy(Department $department)
    {
        if ($department->users()->count() > 0) {
            return back()->with('error','Cannot delete a department with assigned users.');
        }

        if ($department->roles()->count() > 0) {
            return back()->with('error','Remove roles before deleting department.');
        }

        $department->delete();

        return back()->with('success','Department deleted.');
    }
}
