<?php

namespace aryraditya\aCommerceLaravel;

use Illuminate\Support\ServiceProvider;

class aCommerceServiceProvider extends ServiceProvider
{

    private $configPath  = __DIR__ . '/../config/acommerce.php';

    public function boot()
    {
        $this->publishes([
            $this->configPath   => config_path('acommerce.php')
        ]);

    }

    public function register()
    {
        $this->mergeConfigFrom($this->configPath, 'acommerce');

    }
}