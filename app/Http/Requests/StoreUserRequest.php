<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or gate later
    }
    // public function rules(): array
    // {
    //     return [
    //         'identity_no' => 'required|string|max:50|unique:users,identity_no',
    //         'name'        => 'required|string|max:255',
    //         'email'       => 'required|email|unique:users,email',
    //         'status'      => 'required|in:0,1',

    //         'department_roles' => 'required|array|min:1',

    //         'department_roles.*.department_id' => 'required|exists:departments,id',

    //         'department_roles.*.role_id' => [
    //             'required',
    //             'exists:roles,id',
    //             function ($attr, $value, $fail) {
    //                 $index = explode('.', $attr)[1];
    //                 $dept  = $this->department_roles[$index]['department_id'];

    //                 $valid = DB::table('pivot_department_role')
    //                     ->where('department_id', $dept)
    //                     ->where('role_id', $value)
    //                     ->exists();

    //                 if (!$valid) {
    //                     $fail("Selected role does not belong to this department.");
    //                 }
    //             }
    //         ]
    //     ];
    // }

    public function rules(): array
    {
        return [
            'identity_no' => 'required|string|max:50|unique:users,identity_no',
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'status'      => 'required|in:0,1',
        ];
    }
}
