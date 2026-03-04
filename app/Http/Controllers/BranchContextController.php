<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchContextController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ]);

        $user = $request->user();

        $allowed = $user->isSuperAdmin()
            ? true
            : $user->branches()
                ->where('branches.id', $data['branch_id'])
                ->exists();

        if (!$allowed) {
            abort(403, 'Branch is not assigned to this user.');
        }

        $user->active_branch_id = $data['branch_id'];
        $user->save();

        return back()->with('status', 'branch-switched');
    }
}
