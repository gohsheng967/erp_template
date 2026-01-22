<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'identity_no' => $this->identity_no,

            // MFA
            'mfa_enabled' => (bool) $this->google2fa_secret,
            'mfa_backup_code' => $this->mfa_backup_code,
            'backup_plain' => $this->backup_plain,

            // Contact channels
            'contact_channels' => $this->contact_channels ?? [
                'mobile' => ['enabled' => false, 'value' => null],
                'telegram' => ['enabled' => false, 'value' => null],
                'whatsapp' => ['enabled' => false, 'value' => null],
            ],

            // Departments + roles (many-to-many structured)
            'departments' => $this->departments->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'role' => $dept->pivot->role_id
                        ? \App\Models\Role::find($dept->pivot->role_id)->name
                        : '—',
                ];
            }),
            'created_at' => $this->created_at,
            'profile_photo' => $this->profile_photo 
            ? asset('storage/' . $this->profile_photo)
            : null,
            'bank_accounts' => $this->bankAccounts()
                ->orderBy('id')
                ->get()
                ->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'bank_name' => $account->bank_name,
                        'account_name' => $account->account_name,
                        'account_no' => $account->account_no,
                        'status' => $account->status,
                    ];
                }),

        ];
    }
}
