<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo',
        'signature_path',
        'active_branch_id',
        'contact_channels',
        'identity_no'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'contact_channels' => 'array',
        ];
    }


    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'pivot_user_departments')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'pivot_user_departments')
            ->withPivot('department_id')
            ->withTimestamps();
    }

    public function activeDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'active_department_id');
    }

    public function activeRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'active_role_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'pivot_user_branches')
            ->withTimestamps();
    }

    public function activeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'active_branch_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->departments()
            ->whereIn('departments.name', ['Superadmin', 'superadmin', 'Super Admin'])
            ->exists()
            || $this->roles()
                ->whereIn('roles.name', ['Superadmin', 'superadmin', 'Super Admin'])
                ->exists();
    }

    public function toResource()
    {
        return new UserResource($this);
    }

    public function inventoryAllocations()
    {
        return $this->morphMany(InventoryAllocation::class, 'allocatable');
    }

    public function bankAccounts()
    {
        return $this->hasMany(UserBankAccount::class);
    }
}
