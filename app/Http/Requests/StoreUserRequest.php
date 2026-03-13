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
            'is_superadmin' => ['nullable', 'boolean'],
            'is_general_manager' => ['nullable', 'boolean'],
            'department_roles' => ['nullable', 'array'],
            'department_roles.*.department_id' => ['required_with:department_roles', 'exists:departments,id'],
            'department_roles.*.role_id' => [
                'required_with:department_roles',
                'exists:roles,id',
                function ($attr, $value, $fail) {
                    $parts = explode('.', $attr);
                    $index = $parts[1] ?? null;
                    $dept = $index !== null ? ($this->department_roles[$index]['department_id'] ?? null) : null;

                    if (!$dept) {
                        return;
                    }

                    $valid = DB::table('pivot_department_role')
                        ->where('department_id', $dept)
                        ->where('role_id', $value)
                        ->exists();

                    if (!$valid) {
                        $fail('Selected role does not belong to this department.');
                    }
                },
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $isSuperAdmin = filter_var($this->input('is_superadmin', false), FILTER_VALIDATE_BOOLEAN);
            $isGeneralManager = filter_var($this->input('is_general_manager', false), FILTER_VALIDATE_BOOLEAN);
            $rows = collect($this->input('department_roles', []))
                ->filter(fn ($row) => !empty($row['department_id']) || !empty($row['role_id']));

            if ($isSuperAdmin && $isGeneralManager) {
                $validator->errors()->add(
                    'is_general_manager',
                    'Superadmin cannot also be marked as General Manager.'
                );
            }

            if (!$isSuperAdmin && !$isGeneralManager && $rows->isEmpty()) {
                $validator->errors()->add(
                    'department_roles',
                    'At least one department & role is required for normal users.'
                );
            }
        });
    }
}
