<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        $branches = $this->isSuperAdmin()
            ? Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
            : $this->branches;

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
                ? Storage::disk('public')->url($this->profile_photo)
                : null,
            'signature' => $this->signature_path
                ? Storage::disk('public')->url($this->signature_path)
                : null,
            'branches' => $branches->map(function ($branch) {
                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'slug' => $branch->slug,
                ];
            }),
            'active_branch' => $this->activeBranch ? [
                'id' => $this->activeBranch->id,
                'name' => $this->activeBranch->name,
                'slug' => $this->activeBranch->slug,
            ] : null,
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
