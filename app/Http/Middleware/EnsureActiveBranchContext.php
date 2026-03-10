<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveBranchContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $activeBranchId = (int) ($user->active_branch_id ?? 0);
        if ($activeBranchId <= 0) {
            abort(422, 'Please select an active branch before proceeding.');
        }

        $isAllowedBranch = $user->branches()
            ->where('branches.id', $activeBranchId)
            ->where('branches.is_active', true)
            ->exists();

        if (!$isAllowedBranch) {
            $fallbackBranchId = $user->branches()
                ->where('branches.is_active', true)
                ->orderBy('branches.id')
                ->value('branches.id');

            if (!$fallbackBranchId) {
                abort(403, 'No active branch is assigned to your account.');
            }

            $user->active_branch_id = $fallbackBranchId;
            $user->save();
        }

        return $next($request);
    }
}
