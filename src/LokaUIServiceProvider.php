<?php

namespace LokaUI\Cli;

use Illuminate\Support\ServiceProvider;
use LokaUI\Cli\Commands\AddCommand;
use LokaUI\Cli\Commands\InitCommand;
use LokaUI\Cli\Commands\ListCommand;

class LokaUIServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap LokaUI CLI services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InitCommand::class,
                AddCommand::class,
                ListCommand::class,
            ]);
        }
    }

    /**
     * Register LokaUI CLI services.
     */
    public function register(): void
    {
        //
    }
}
