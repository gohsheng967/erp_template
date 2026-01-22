<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Http\Resources\UserResource;
use App\Models\CompanyProfile;
use App\Models\CompanyBankAccount;

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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? new UserResource($request->user())
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
            'companyBankAccounts' => function () {
                return CompanyBankAccount::query()
                    ->orderBy('id')
                    ->get(['id', 'bank_name', 'account_name', 'account_no', 'status']);
            },
        ];
    }
}
