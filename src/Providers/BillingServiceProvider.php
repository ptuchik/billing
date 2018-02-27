<?php

namespace Ptuchik\Billing\Providers;

use App;
use Currency;
use Illuminate\Support\ServiceProvider;
use Ptuchik\Billing\CurrencyHelper;

/**
 * Class BillingServiceProvider
 * @package Ptuchik\Billing\Providers
 */
class BillingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish a config file
        $this->publishes([
            __DIR__.'/../../config/billing.php' => config_path('ptuchik-billing.php'),
        ], 'config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../migrations');

        $this->addDefaultCurrency();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/billing.php', 'ptuchik-billing');
    }

    /**
     * Add default currency if does not exist
     */
    protected function addDefaultCurrency()
    {
        if (!App::runningInConsole() && !Currency::getCurrency(config('currency.default'))) {
            (new CurrencyHelper())->add(config('currency.default'));
        }
    }
}
