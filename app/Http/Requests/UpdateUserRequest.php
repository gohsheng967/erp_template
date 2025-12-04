<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'identity_no' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users','identity_no')->ignore($userId)
            ],

            'name'  => 'required|string|max:255',

            'email' => [
                'required',
                'email',
                Rule::unique('users','email')->ignore($userId),
            ],

            'status' => 'required|in:0,1',

            'department_roles' => 'required|array|min:1',

            'department_roles.*.department_id' => 'required|exists:departments,id',

            'department_roles.*.role_id' => [
                'required',
                'exists:roles,id',
                function ($attr, $value, $fail) {
                    $index = explode('.', $attr)[1];
                    $dept  = $this->department_roles[$index]['department_id'];

                    $valid = DB::table('pivot_department_role')
                        ->where('department_id', $dept)
                        ->where('role_id', $value)
                        ->exists();

                    if (!$valid) {
                        $fail("Selected role does not belong to this department.");
                    }
                }
            ]
        ];
    }
}
