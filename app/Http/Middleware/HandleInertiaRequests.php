<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Http\Resources\UserResource;
use App\Models\CompanyProfile;
use App\Models\CompanyBankAccount;
use App\Models\Branch;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user && !$user->active_branch_id) {
            $fallbackBranchId = $user->isSuperAdmin()
                ? Branch::query()->where('is_active', true)->orderBy('id')->value('id')
                : $user->branches()->where('branches.is_active', true)->orderBy('branches.id')->value('branches.id');

            if ($fallbackBranchId) {
                $user->active_branch_id = $fallbackBranchId;
                $user->save();
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user
                    ? new UserResource(
                        $user->loadMissing([
                            'departments',
                            'branches',
                            'activeBranch',
                        ])
                    )
                    : null,
            ],
            'company' => function () {
                $company = CompanyProfile::first();

                return $company ? [
                    'company_name'   => $company->company_name,
                    'company_reg_no' => $company->company_reg_no,
                    'address'        => $company->address,
                    'office_number'  => $company->office_number,
                    'logo'           => $company->logo,
                ] : null;
            },
            'companyBankAccounts' => function () use ($request) {
                $activeBranchId = $request->user()?->active_branch_id;

                if (!$activeBranchId) {
                    return [];
                }

                return CompanyBankAccount::query()
                    ->where('branch_id', $activeBranchId)
                    ->with('branch:id,name,slug')
                    ->orderBy('id')
                    ->get(['id', 'branch_id', 'bank_name', 'account_name', 'account_no', 'status'])
                    ->map(function ($account) {
                        return [
                            'id' => $account->id,
                            'branch_id' => $account->branch_id,
                            'branch' => $account->branch ? [
                                'id' => $account->branch->id,
                                'name' => $account->branch->name,
                                'slug' => $account->branch->slug,
                            ] : null,
                            'bank_name' => $account->bank_name,
                            'account_name' => $account->account_name,
                            'account_no' => $account->account_no,
                            'status' => $account->status,
                        ];
                    })
                    ->values();
            },
        ];
    }
}
