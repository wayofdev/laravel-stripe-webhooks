<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\Bridge\Laravel\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WayOfDev\StripeWebhooks\Bridge\Laravel\Http\Controllers\StripeWebhooksController;

final class StripeWebhooksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../../../config/stripe-webhooks.php' => config_path('stripe-webhooks.php'),
            ], 'config');

            $this->registerConsoleCommands();
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../../config/stripe-webhooks.php', 'stripe-webhooks');

        Route::macro('stripeWebhooks', function ($url) {
            return Route::post($url, StripeWebhooksController::class);
        });
    }

    private function registerConsoleCommands(): void
    {
        $this->commands([]);
    }
}
