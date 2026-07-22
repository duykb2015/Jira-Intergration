<?php

namespace App\Providers;

use App\Contracts\ClockifyClient;
use App\Contracts\ClockifyWebhookParser;
use App\Contracts\TeamboardClient;
use App\Services\Clockify\FlexibleClockifyWebhookParser;
use App\Services\Clockify\HttpClockifyClient;
use App\Services\Teamboard\PendingTeamboardClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ClockifyClient::class, HttpClockifyClient::class);
        $this->app->bind(ClockifyWebhookParser::class, FlexibleClockifyWebhookParser::class);
        $this->app->bind(TeamboardClient::class, PendingTeamboardClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
