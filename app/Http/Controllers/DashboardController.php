<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Dashboard\DashboardAttentionService;

class DashboardController extends Controller
{
    public function index()
    {
        $attention = (new DashboardAttentionService())->summary();

        return Inertia::render('Dashboard', [
            'attention' => $attention,
        ]);
    }
}
