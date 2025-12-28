<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\MilestonePhase;
use App\Models\MilestonePhaseTask;
use App\Observers\MilestonePhaseObserver;
use App\Observers\MilestonePhaseTaskObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MilestonePhase::observe(MilestonePhaseObserver::class);
        MilestonePhaseTask::observe(MilestonePhaseTaskObserver::class);

        Vite::prefetch(concurrency: 3);
    }
}
