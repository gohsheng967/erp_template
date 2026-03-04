<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            // Contact channels
            'contact_channels' => ['nullable', 'array'],
            'contact_channels.mobile.value' => ['nullable', 'string'],
            'contact_channels.telegram.value' => ['nullable', 'string'],
            'contact_channels.whatsapp.value' => ['nullable', 'string'],

            // NEW: Profile photo upload (max 5 MB)
            'profile_photo' => [
                'nullable',
                'image',       // jpeg, png, bmp, gif, svg, webp
                'max:5120',    // 5 MB in kilobytes
            ],
            'signature_file' => [
                'nullable',
                'file',
                'mimes:png',
                'max:2048',
            ],
            'signature_drawn' => [
                'nullable',
                'string',
            ],
        ];
    }

}
